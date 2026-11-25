<?php
/**
 * Dedicated Business Fact REST surface.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_Business_Fact_Surface {
    public function register_hooks(): void {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes(): void {
        register_rest_route(
            'abis/v1',
            '/business-fact',
            array(
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => array($this, 'rest_business_fact'),
                'args'                => array(
                    'page' => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'per_page' => array(
                        'default'           => ABIS_Constants::DEFAULT_PER_PAGE,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function rest_business_fact(WP_REST_Request $request) {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = (int) $request->get_param('per_page');
        if ($per_page < 1) {
            $per_page = ABIS_Constants::DEFAULT_PER_PAGE;
        }
        if ($per_page > ABIS_Constants::MAX_PER_PAGE) {
            return new WP_Error(
                'abis_invalid_per_page',
                'per_page exceeds maximum',
                array('status' => 400)
            );
        }

        $payload = (new ABIS_Business_Fact_Builder())->build($page, $per_page);
        $response = new WP_REST_Response($payload, 200);
        $profile = ABIS_Installation_Profile::resolve()['profile'];
        $response->header('X-ABIS-Business-Fact-Only', 'true');
        $response->header('X-ABIS-Commerce-Execution', 'NOT_GRANTED');
        $response->header('X-ABIS-Discoverable-Semantic', 'BUSINESS_FACT_SURFACE');
        $response->header('X-ABIS-Installation-Profile', $profile);
        $response->header('Cache-Control', 'no-store');
        return $response;
    }

    public static function canonical_rest_ref(): string {
        return 'rest:' . ABIS_Constants::BUSINESS_FACT_REST_ROUTE;
    }

    public static function canonical_url(): string {
        if (function_exists('rest_url')) {
            return (string) rest_url(ABIS_Constants::BUSINESS_FACT_REST_ROUTE);
        }
        return '';
    }
}