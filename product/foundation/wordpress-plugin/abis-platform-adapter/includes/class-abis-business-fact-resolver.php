<?php
/**
 * Publication resolution: Manual > Native > NOT_ESTABLISHED.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Business_Fact_Resolver {
    /** @var array<string, string> */
    private array $provenance = array();

    /** @var list<string> */
    private array $not_established = array();

    /**
     * @param array<string, mixed> $native
     * @return array{businessIdentity: array<string, mixed>, publicPresence: array<string, mixed>, contact: array<string, mixed>, location: array<string, mixed>, operations: array<string, mixed>, factProvenance: array<string, string>, notEstablished: list<string>}
     */
    public function resolve_identity_blocks(array $native): array {
        $this->provenance = array();
        $this->not_established = array();

        $identity = $this->resolve_block(
            array(
                'businessIdentity.displayName'   => array('native' => $native['businessIdentity']['displayName'] ?? '', 'path' => 'businessIdentity.displayName', 'out_key' => 'displayName', 'native_prov' => ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE),
                'businessIdentity.description'   => array('native' => $native['businessIdentity']['description'], 'path' => 'businessIdentity.description', 'out_key' => 'description', 'native_prov' => ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE, 'allow_null' => true),
                'businessIdentity.businessType'  => array('native' => null, 'path' => 'businessIdentity.businessType', 'out_key' => 'businessType', 'native_prov' => ABIS_Constants::PROVENANCE_NOT_ESTABLISHED, 'manual_only' => true),
            )
        );
        $identity['canonicalUrl'] = $native['businessIdentity']['canonicalUrl'] ?? '';
        $this->provenance['businessIdentity.canonicalUrl'] = ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE;

        if ($identity['displayName'] === '') {
            $this->not_established[] = 'businessIdentity.displayName';
        }

        $presence = $this->resolve_block(
            array(
                'publicPresence.language'         => array('native' => $native['publicPresence']['language'], 'path' => 'publicPresence.language', 'out_key' => 'language', 'native_prov' => ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE, 'allow_null' => true),
                'publicPresence.locale'           => array('native' => $native['publicPresence']['locale'], 'path' => 'publicPresence.locale', 'out_key' => 'locale', 'native_prov' => ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE, 'allow_null' => true),
                'publicPresence.privacyPolicyUrl' => array('native' => $native['publicPresence']['privacyPolicyUrl'], 'path' => 'publicPresence.privacyPolicyUrl', 'out_key' => 'privacyPolicyUrl', 'native_prov' => ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE, 'allow_null' => true),
                'publicPresence.siteIconUrl'      => array('native' => $native['publicPresence']['siteIconUrl'], 'path' => 'publicPresence.siteIconUrl', 'out_key' => 'siteIconUrl', 'native_prov' => ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE, 'allow_null' => true),
                'publicPresence.logoUrl'          => array('native' => $native['publicPresence']['logoUrl'], 'path' => 'publicPresence.logoUrl', 'out_key' => 'logoUrl', 'native_prov' => ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE, 'allow_null' => true),
            )
        );
        $presence['homepageUrl'] = $native['publicPresence']['homepageUrl'] ?? '';
        $this->provenance['publicPresence.homepageUrl'] = ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE;

        if (($native['businessIdentity']['description'] ?? null) === null && !isset($this->provenance['businessIdentity.description'])) {
            $this->not_established[] = 'businessIdentity.description';
        }
        if (($native['publicPresence']['privacyPolicyUrl'] ?? null) === null && !isset($this->provenance['publicPresence.privacyPolicyUrl'])) {
            $this->not_established[] = 'publicPresence.privacyPolicyUrl';
        }

        $contact = $this->resolve_block($this->manual_only_map('contact', array('publicPhone', 'publicEmail', 'contactUrl')));
        $location = $this->resolve_block($this->manual_only_map('location', array('streetAddress', 'locality', 'region', 'postalCode', 'country', 'locationUrl')));
        $operations = $this->resolve_block($this->manual_only_map('operations', array('openingHours', 'specialHoursNote', 'serviceArea')));

        sort($this->not_established);
        $this->not_established = array_values(array_unique($this->not_established));

        return array(
            'businessIdentity' => $identity,
            'publicPresence'   => $presence,
            'contact'          => $contact,
            'location'         => $location,
            'operations'       => $operations,
            'factProvenance'   => $this->provenance,
            'notEstablished'   => $this->not_established,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $spec
     * @return array<string, mixed>
     */
    private function resolve_block(array $spec): array {
        $out = array();
        foreach ($spec as $path => $meta) {
            $key = $meta['out_key'];
            $resolved = $this->resolve_scalar($path, $meta);
            if ($resolved['include']) {
                $out[$key] = $resolved['value'];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{include: bool, value: mixed}
     */
    private function resolve_scalar(string $path, array $meta): array {
        $manual = ABIS_Business_Fact_Settings::get_field($path);
        $native = $meta['native'] ?? null;
        $manual_only = !empty($meta['manual_only']);

        if ($manual !== null && ($manual['mode'] ?? '') === ABIS_Constants::FIELD_MODE_MANUAL && !empty($manual['publish'])) {
            $val = $manual['value'] ?? '';
            if ($val === '' && empty($meta['allow_null'])) {
                $this->not_established[] = $path;
                return array('include' => false, 'value' => null);
            }
            $this->provenance[$path] = ABIS_Constants::PROVENANCE_BUSINESS_DECLARED;
            return array('include' => true, 'value' => $val === '' ? null : $val);
        }

        if ($manual !== null && ($manual['mode'] ?? '') === ABIS_Constants::FIELD_MODE_NOT_PUBLISHED) {
            return array('include' => false, 'value' => null);
        }

        if ($manual_only) {
            $this->not_established[] = $path;
            return array('include' => false, 'value' => null);
        }

        if ($native === null || $native === '') {
            if (!empty($meta['allow_null']) && $native === null) {
                return array('include' => false, 'value' => null);
            }
            $this->not_established[] = $path;
            return array('include' => false, 'value' => null);
        }

        $this->provenance[$path] = $meta['native_prov'] ?? ABIS_Constants::PROVENANCE_WORDPRESS_NATIVE;
        return array('include' => true, 'value' => $native);
    }

    /**
     * @param list<string> $keys
     * @return array<string, array<string, mixed>>
     */
    private function manual_only_map(string $prefix, array $keys): array {
        $map = array();
        foreach ($keys as $key) {
            $path = $prefix . '.' . $key;
            $map[$path] = array(
                'native'      => null,
                'path'        => $path,
                'out_key'     => $key,
                'native_prov' => ABIS_Constants::PROVENANCE_NOT_ESTABLISHED,
                'manual_only' => true,
                'allow_null'  => true,
            );
        }
        return $map;
    }
}