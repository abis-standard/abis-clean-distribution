<?php
/**
 * Sanitize Manual Business Fact settings on write.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Business_Fact_Sanitizer {
    /** @var list<string> */
    private const TEXT_FIELDS = array(
        'businessIdentity.displayName',
        'businessIdentity.description',
        'businessIdentity.businessType',
        'publicPresence.language',
        'publicPresence.locale',
        'publicPresence.logoUrl',
        'publicPresence.privacyPolicyUrl',
        'contact.publicPhone',
        'contact.publicEmail',
        'contact.contactUrl',
        'location.streetAddress',
        'location.locality',
        'location.region',
        'location.postalCode',
        'location.country',
        'location.locationUrl',
        'operations.openingHours',
        'operations.specialHoursNote',
        'operations.serviceArea',
    );

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize_settings(array $input): array {
        $out = ABIS_Business_Fact_Settings::defaults();
        $fields = array();
        $input_fields = isset($input['fields']) && is_array($input['fields']) ? $input['fields'] : array();

        foreach (self::TEXT_FIELDS as $path) {
            if (!isset($input_fields[$path]) || !is_array($input_fields[$path])) {
                continue;
            }
            $row = $input_fields[$path];
            $mode = isset($row['mode']) ? (string) $row['mode'] : ABIS_Constants::FIELD_MODE_AUTO;
            if (!in_array($mode, array(ABIS_Constants::FIELD_MODE_AUTO, ABIS_Constants::FIELD_MODE_MANUAL, ABIS_Constants::FIELD_MODE_NOT_PUBLISHED), true)) {
                $mode = ABIS_Constants::FIELD_MODE_AUTO;
            }
            $value = isset($row['value']) ? (string) $row['value'] : '';
            if (strpos($path, 'Url') !== false || strpos($path, 'contactUrl') !== false || strpos($path, 'locationUrl') !== false) {
                $value = $value !== '' ? esc_url_raw($value) : '';
            } elseif ($path === 'businessIdentity.description' || $path === 'operations.specialHoursNote') {
                $value = wp_kses_post($value);
            } elseif ($path === 'contact.publicEmail') {
                $value = sanitize_email($value);
            } else {
                $value = sanitize_text_field($value);
            }
            $fields[$path] = array(
                'value'   => $value,
                'mode'    => $mode,
                'publish' => !empty($row['publish']),
            );
        }

        $out['fields'] = $fields;
        $out['manualOfferings'] = self::sanitize_offerings($input);

        return $out;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<int, array<string, mixed>>
     */
    private static function sanitize_offerings(array $input): array {
        $rows = isset($input['manualOfferings']) && is_array($input['manualOfferings']) ? $input['manualOfferings'] : array();
        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) && (string) $row['id'] !== ''
                ? sanitize_key((string) $row['id'])
                : 'mo_' . substr(hash('sha256', $name . '|' . wp_rand()), 0, 12);
            $name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
            if ($name === '') {
                continue;
            }
            $out[] = array(
                'id'               => $id,
                'name'             => $name,
                'shortDescription' => isset($row['shortDescription']) ? wp_kses_post((string) $row['shortDescription']) : '',
                'publicUrl'        => isset($row['publicUrl']) ? esc_url_raw((string) $row['publicUrl']) : '',
                'categoryOrType'   => isset($row['categoryOrType']) ? sanitize_text_field((string) $row['categoryOrType']) : '',
                'publish'          => !empty($row['publish']),
            );
        }
        return $out;
    }
}