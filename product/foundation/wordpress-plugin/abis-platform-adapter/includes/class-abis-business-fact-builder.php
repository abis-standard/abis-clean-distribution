<?php
/**
 * Builds Agent-facing Business Fact payload.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Business_Fact_Builder {
    /**
     * @return array<string, mixed>
     */
    public function build(int $page = 1, int $per_page = ABIS_Constants::DEFAULT_PER_PAGE): array {
        $native = (new ABIS_WordPress_Fact_Collector())->collect_native();
        $resolved = (new ABIS_Business_Fact_Resolver())->resolve_identity_blocks($native);

        $wc = new ABIS_WooCommerce_Fact_Collector();
        $wc_active = !empty($native['platform']['woocommerceActive']);
        $offerings_status = 'NOT_ESTABLISHED';
        $items = array();
        $pagination = null;
        $catalog_enum = 'NOT_APPLICABLE';
        $provenance = $resolved['factProvenance'];

        if ($wc_active) {
            $enum = $wc->enumerate_offerings($page, $per_page);
            $items = $enum['items'];
            $pagination = $enum['pagination'];
            $provenance = array_merge($provenance, $enum['provenance']);
            $offerings_status = count($items) > 0 || (int) $pagination['totalItems'] > 0 ? 'ESTABLISHED' : 'ESTABLISHED';
            $catalog_enum = 'ACTIVE';
        } else {
            $manual = $this->manual_offerings();
            if (count($manual) > 0) {
                $items = $manual;
                $offerings_status = 'ESTABLISHED';
                foreach ($items as $i => $_item) {
                    $provenance['offerings.items[' . $i . ']'] = ABIS_Constants::PROVENANCE_BUSINESS_DECLARED;
                }
            } else {
                $resolved['notEstablished'][] = 'offerings';
            }
        }

        $resolved['notEstablished'] = array_values(array_unique($resolved['notEstablished']));
        sort($resolved['notEstablished']);

        $payload = array(
            'businessFactSchema'        => ABIS_Constants::SCHEMA_BUSINESS_FACT,
            'businessFactSchemaRevision'=> ABIS_Constants::BUSINESS_FACT_SCHEMA_REVISION,
            'generatedAt'               => gmdate('c'),
            'installationProfile'         => ABIS_Installation_Profile::resolve()['profile'],
            'profileResolution'           => ABIS_Installation_Profile::resolve()['profileResolution'],
            'testMaterial'              => ABIS_Installation_Profile::is_test_material(),
            'sensitivityClass'          => 'PUBLIC',
            'businessIdentity'          => $resolved['businessIdentity'],
            'publicPresence'            => $resolved['publicPresence'],
            'contact'                   => $resolved['contact'],
            'location'                  => $resolved['location'],
            'operations'                => $resolved['operations'],
            'offerings'                 => array(
                'status' => $offerings_status,
                'items'  => $items,
            ),
            'platform'                  => $native['platform'],
            'availability'              => array(
                'commerceExecution'     => 'NOT_GRANTED',
                'catalogEnumeration'    => $catalog_enum,
                'authorizationImplied'  => false,
            ),
            'factProvenance'            => $provenance,
            'notEstablished'            => $resolved['notEstablished'],
        );

        if ($pagination !== null) {
            $payload['offerings']['pagination'] = $pagination;
        }

        $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payload['businessFactId'] = 'abis.bf/' . substr(hash('sha256', (string) $canonical), 0, 16);
        $payload['opaqueRefs'] = array(
            'normalizationDigestRef' => 'abis.ref.digest/sha256-' . substr(hash('sha256', (string) $canonical), 0, 16),
        );

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manual_offerings(): array {
        $rows = ABIS_Business_Fact_Settings::published_manual_offerings();
        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'offeringRef'           => 'abis.offering/manual/' . sanitize_key((string) $row['id']),
                'name'                  => (string) $row['name'],
                'shortDescription'      => (string) ($row['shortDescription'] ?? ''),
                'publicUrl'             => (string) ($row['publicUrl'] ?? ''),
                'categoryOrType'        => (string) ($row['categoryOrType'] ?? ''),
                'offeringKind'          => 'service',
                'capabilityLanes'       => array(ABIS_Constants::CAPABILITY_SEARCH),
                'notPurchaseAuthorized' => true,
            );
        }
        return $out;
    }
}