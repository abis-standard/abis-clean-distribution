<?php
/**
 * Plugin Name: ABIS Platform Adapter
 * Description: Business Fact + Agent Readiness for WordPress (read-only surfaces; manual Business declaration).
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Coa Retail
 * License: GPL-2.0-or-later
 * Text Domain: abis-platform-adapter
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ABIS_PLATFORM_ADAPTER_VERSION', '1.0.0');
define('ABIS_ADAPTER_ID', 'abis.adapter.wp.slice1.v1');
define('ABIS_ADAPTER_VERSION', '1.0.0');

require_once __DIR__ . '/includes/class-abis-constants.php';
require_once __DIR__ . '/includes/class-abis-installation-profile.php';
require_once __DIR__ . '/includes/class-abis-business-fact-settings.php';
require_once __DIR__ . '/includes/class-abis-business-fact-sanitizer.php';
require_once __DIR__ . '/includes/class-abis-wordpress-fact-collector.php';
require_once __DIR__ . '/includes/class-abis-woocommerce-fact-collector.php';
require_once __DIR__ . '/includes/class-abis-business-fact-resolver.php';
require_once __DIR__ . '/includes/class-abis-business-fact-builder.php';
require_once __DIR__ . '/includes/class-abis-business-fact-surface.php';
require_once __DIR__ . '/includes/class-abis-platform-facts.php';
require_once __DIR__ . '/includes/class-abis-business-fact.php';
require_once __DIR__ . '/includes/class-abis-declaration-builder.php';
require_once __DIR__ . '/includes/class-abis-readiness-surface.php';
require_once __DIR__ . '/includes/class-abis-admin-business-fact-page.php';
require_once __DIR__ . '/includes/class-abis-woocommerce-cart-action.php';
require_once __DIR__ . '/includes/class-abis-woocommerce-order-action.php';
require_once __DIR__ . '/includes/class-abis-woocommerce-catalog-observer.php';
require_once __DIR__ . '/includes/class-abis-woocommerce-test-catalog-seed.php';
require_once __DIR__ . '/includes/class-abis-woocommerce-surface.php';

final class ABIS_Platform_Adapter_Bootstrap {
    public static function activate(): void {
        if (!get_option('abis_slice1_config_version')) {
            add_option('abis_slice1_config_version', ABIS_PLATFORM_ADAPTER_VERSION, '', false);
        }
        if (get_option(ABIS_Constants::SETTINGS_OPTION, null) === null) {
            add_option(ABIS_Constants::SETTINGS_OPTION, ABIS_Business_Fact_Settings::defaults(), '', false);
        }
        ABIS_Readiness_Surface::register_rewrite_rules();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void {
        flush_rewrite_rules(false);
    }

    public static function init(): void {
        (new ABIS_Readiness_Surface())->register_hooks();
        (new ABIS_Business_Fact_Surface())->register_hooks();
        (new ABIS_WooCommerce_Surface())->register_hooks();
        if (is_admin()) {
            (new ABIS_Admin_Business_Fact_Page())->register_hooks();
        }
    }
}

register_activation_hook(__FILE__, array('ABIS_Platform_Adapter_Bootstrap', 'activate'));
register_deactivation_hook(__FILE__, array('ABIS_Platform_Adapter_Bootstrap', 'deactivate'));
add_action('plugins_loaded', array('ABIS_Platform_Adapter_Bootstrap', 'init'));