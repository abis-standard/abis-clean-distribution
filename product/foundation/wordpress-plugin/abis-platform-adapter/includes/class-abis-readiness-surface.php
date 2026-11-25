<?php
/**
 * Read-only readiness surfaces — REST canonical · well-known reference.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Readiness_Surface {
    public function register_hooks(): void {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('init', array(self::class, 'register_rewrite_rules'));
        add_action('template_redirect', array($this, 'maybe_serve_well_known'));
    }

    public static function register_rewrite_rules(): void {
        add_rewrite_rule(
            '^\\.well-known/abis/?$',
            'index.php?abis_well_known=1',
            'top'
        );
        add_rewrite_tag('%abis_well_known%', '1');
    }

    public function register_rest_routes(): void {
        register_rest_route(
            'abis/v1',
            '/declaration',
            array(
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => array($this, 'rest_declaration'),
            )
        );
    }

    /**
     * @return WP_REST_Response
     */
    public function rest_declaration() {
        $payload = (new ABIS_Declaration_Builder())->build_payload();
        $response = new WP_REST_Response($payload, 200);
        $response->header('X-ABIS-Declaration-Only', 'true');
        $response->header('X-ABIS-Discoverable-Semantic', 'DISCOVERY_SURFACE_DECLARED');
        $response->header('X-ABIS-Canonical-Surface', ABIS_Constants::CANONICAL_REST_ROUTE);
        return $response;
    }

    public function maybe_serve_well_known(): void {
        $flag = get_query_var('abis_well_known');
        if ((string) $flag !== '1') {
            return;
        }
        $payload = (new ABIS_Declaration_Builder())->build_payload();
        $payload['representation'] = 'well-known-reference';
        $payload['canonicalSurface'] = 'rest:' . ABIS_Constants::CANONICAL_REST_ROUTE;

        status_header(200);
        header('Content-Type: application/json; charset=utf-8');
        header('X-ABIS-Declaration-Only: true');
        header('X-ABIS-Canonical-Surface: ' . ABIS_Constants::CANONICAL_REST_ROUTE);
        echo wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}