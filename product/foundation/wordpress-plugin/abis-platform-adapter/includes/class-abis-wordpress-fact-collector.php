<?php
/**
 * Native public WordPress facts (no inference).
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_WordPress_Fact_Collector {
    /**
     * @return array<string, mixed>
     */
    public function collect_native(): array {
        $display = function_exists('get_bloginfo') ? trim((string) get_bloginfo('name')) : '';
        $description = function_exists('get_bloginfo') ? trim((string) get_bloginfo('description')) : '';
        $canonical = function_exists('home_url') ? trailingslashit((string) home_url('/')) : '';
        $language = function_exists('get_bloginfo') ? trim((string) get_bloginfo('language')) : '';
        $locale = function_exists('get_locale') ? (string) get_locale() : '';
        $privacy = function_exists('get_privacy_policy_url') ? (string) get_privacy_policy_url() : '';
        $privacy = $privacy !== '' ? $privacy : null;
        $site_icon = function_exists('get_site_icon_url') ? get_site_icon_url() : false;
        $site_icon = is_string($site_icon) && $site_icon !== '' ? $site_icon : null;
        $logo = $this->custom_logo_url();
        $wp_version = function_exists('get_bloginfo') ? (string) get_bloginfo('version') : '';

        return array(
            'businessIdentity' => array(
                'displayName'   => $display,
                'canonicalUrl'  => $canonical,
                'description'   => $description !== '' ? $description : null,
            ),
            'publicPresence' => array(
                'homepageUrl'       => $canonical,
                'privacyPolicyUrl'  => $privacy,
                'language'          => $language !== '' ? $language : null,
                'locale'            => $locale !== '' ? $locale : null,
                'siteIconUrl'       => $site_icon,
                'logoUrl'           => $logo,
            ),
            'platform' => array(
                'kind'               => 'wordpress',
                'wordpressVersion'   => $wp_version,
                'woocommerceActive'  => class_exists('WooCommerce'),
                'woocommerceVersion' => class_exists('WooCommerce') && defined('WC_VERSION') ? (string) WC_VERSION : null,
                'adapterId'          => ABIS_ADAPTER_ID,
                'adapterVersion'     => ABIS_ADAPTER_VERSION,
                'pluginVersion'      => ABIS_PLATFORM_ADAPTER_VERSION,
            ),
        );
    }

    private function custom_logo_url(): ?string {
        if (!function_exists('get_theme_mod') || !function_exists('wp_get_attachment_image_url')) {
            return null;
        }
        $logo_id = (int) get_theme_mod('custom_logo');
        if ($logo_id < 1) {
            return null;
        }
        $url = wp_get_attachment_image_url($logo_id, 'full');
        return is_string($url) && $url !== '' ? $url : null;
    }
}