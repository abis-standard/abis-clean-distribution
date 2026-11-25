<?php
/**
 * @deprecated Use ABIS_WordPress_Fact_Collector.
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Platform_Facts {
    /**
     * @return array<string, string>
     */
    public function collect_public_metadata(): array {
        $native = (new ABIS_WordPress_Fact_Collector())->collect_native();
        return array(
            'wordpressVersion' => (string) ($native['platform']['wordpressVersion'] ?? ''),
            'siteUrl'          => (string) ($native['businessIdentity']['canonicalUrl'] ?? ''),
            'siteTitle'        => (string) ($native['businessIdentity']['displayName'] ?? ''),
            'adapterVersion'   => (string) ($native['platform']['pluginVersion'] ?? ''),
        );
    }

    /**
     * @param array<string, string> $public_metadata
     * @return array<string, string>
     */
    public function collect_opaque_refs(array $public_metadata): array {
        $digest = hash('sha256', wp_json_encode($public_metadata, JSON_UNESCAPED_SLASHES));

        return array(
            'nativeSiteRef'       => 'abis.native.ref/host-site/' . substr($digest, 0, 8),
            'wordpressVersionRef' => 'abis.native.ref/host-core/' . preg_replace('/[^0-9a-zA-Z\\.\\-]/', '', $public_metadata['wordpressVersion']),
            'siteUrlRef'          => 'abis.native.ref/url/' . substr(hash('sha256', $public_metadata['siteUrl']), 0, 12),
            'siteTitleRef'        => 'abis.native.ref/title/' . substr(hash('sha256', $public_metadata['siteTitle']), 0, 12),
            'adapterVersionRef'   => 'abis.native.ref/adapter/' . $public_metadata['adapterVersion'],
        );
    }
}