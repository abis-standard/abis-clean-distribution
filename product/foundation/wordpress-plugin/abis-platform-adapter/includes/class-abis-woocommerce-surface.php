<?php
/**
 * HD-156 — WooCommerce read surfaces (catalog observation + admin seed).
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_WooCommerce_Surface {

    public function register_hooks(): void {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes(): void {
        register_rest_route(
            'abis/v1',
            '/wc-catalog-observation',
            array(
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => array($this, 'rest_catalog_observation'),
                'args'                => array(
                    'skus' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'mode' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            'abis/v1',
            '/wc-test-catalog/seed',
            array(
                'methods'             => 'POST',
                'permission_callback' => function () {
                    return current_user_can('manage_woocommerce');
                },
                'callback'            => array($this, 'rest_seed_test_catalog'),
            )
        );

        $operator_cart_perm = function () {
            return current_user_can('manage_woocommerce');
        };

        register_rest_route(
            'abis/v1',
            '/wc-cart-actionability/reset',
            array(
                'methods'             => 'POST',
                'permission_callback' => $operator_cart_perm,
                'callback'            => array($this, 'rest_cart_reset'),
            )
        );

        register_rest_route(
            'abis/v1',
            '/wc-cart-actionability/add-line',
            array(
                'methods'             => 'POST',
                'permission_callback' => $operator_cart_perm,
                'callback'            => array($this, 'rest_cart_add_line'),
            )
        );

        register_rest_route(
            'abis/v1',
            '/wc-cart-actionability/observe',
            array(
                'methods'             => 'GET',
                'permission_callback' => $operator_cart_perm,
                'callback'            => array($this, 'rest_cart_observe'),
            )
        );

        register_rest_route(
            'abis/v1',
            '/wc-cart-actionability/safety-probe',
            array(
                'methods'             => 'GET',
                'permission_callback' => $operator_cart_perm,
                'callback'            => array($this, 'rest_cart_safety_probe'),
            )
        );

        $operator_order_perm = function () {
            return current_user_can('manage_woocommerce');
        };

        register_rest_route(
            'abis/v1',
            '/wc-order-actionability/create-test-order',
            array(
                'methods'             => 'POST',
                'permission_callback' => $operator_order_perm,
                'callback'            => array($this, 'rest_order_create_test'),
            )
        );

        register_rest_route(
            'abis/v1',
            '/wc-order-actionability/observe',
            array(
                'methods'             => 'GET',
                'permission_callback' => $operator_order_perm,
                'callback'            => array($this, 'rest_order_observe'),
            )
        );

        register_rest_route(
            'abis/v1',
            '/wc-order-actionability/cleanup',
            array(
                'methods'             => 'POST',
                'permission_callback' => $operator_order_perm,
                'callback'            => array($this, 'rest_order_cleanup'),
            )
        );
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_catalog_observation(WP_REST_Request $request) {
        $profile = ABIS_Installation_Profile::resolve()['profile'];
        $mode = sanitize_text_field((string) $request->get_param('mode'));
        $skus_arg = (string) $request->get_param('skus');
        if ($profile !== 'production' && $mode === 'test_skus' && $skus_arg === '') {
            $skus_arg = 'ABIS-WC-TEST-A,ABIS-WC-TEST-B';
        }
        $skus = $skus_arg !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $skus_arg))))
            : array();
        $payload = (new ABIS_WooCommerce_Catalog_Observer())->observe_products_by_sku($skus);
        if ($profile === 'production' && count($skus) === 0) {
            $payload['observations'] = array();
            $payload['note'] = 'Provide skus or use GET /wp-json/abis/v1/business-fact for paginated catalog enumeration.';
        }
        $payload['executionId'] = 'wc_real_provider_catalog_observation_001';
        $status = !empty($payload['woocommerceActive']) ? 200 : 503;
        $response = new WP_REST_Response($payload, $status);
        $response->header('X-ABIS-Catalog-Read-Only', 'true');
        $response->header('X-ABIS-Commerce-Execution', 'NOT_GRANTED');
        return $response;
    }

    /**
     * @return WP_REST_Response
     */
    public function rest_seed_test_catalog() {
        $result = (new ABIS_WooCommerce_Test_Catalog_Seed())->ensure_products();
        $status = !empty($result['ok']) ? 200 : 503;
        return new WP_REST_Response($result, $status);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_cart_reset(WP_REST_Request $request) {
        $correlation = sanitize_text_field((string) $request->get_param('correlationId'));
        $payload     = (new ABIS_WooCommerce_Cart_Action())->reset_cart($correlation);
        $payload['executionId'] = 'wc_real_provider_cart_actionability_001';
        $response = new WP_REST_Response($payload, 200);
        $response->header('X-ABIS-Commerce-Execution', 'NOT_GRANTED');
        $response->header('X-ABIS-Cart-Operator-Only', 'true');
        return $response;
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_cart_add_line(WP_REST_Request $request) {
        $correlation = sanitize_text_field((string) $request->get_param('correlationId'));
        $product_id  = (int) $request->get_param('productId');
        $quantity    = (int) $request->get_param('quantity');
        if ($quantity < 1) {
            $quantity = 1;
        }
        $payload = (new ABIS_WooCommerce_Cart_Action())->add_line($product_id, $quantity, $correlation);
        $payload['executionId'] = 'wc_real_provider_cart_actionability_001';
        $response = new WP_REST_Response($payload, 200);
        $response->header('X-ABIS-Commerce-Execution', 'NOT_GRANTED');
        $response->header('X-ABIS-Cart-Operator-Only', 'true');
        return $response;
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_cart_observe(WP_REST_Request $request) {
        $correlation = sanitize_text_field((string) $request->get_param('correlationId'));
        $payload     = (new ABIS_WooCommerce_Cart_Action())->observe_cart_state($correlation);
        $payload['executionId'] = 'wc_real_provider_cart_actionability_001';
        $response = new WP_REST_Response($payload, 200);
        $response->header('X-ABIS-Commerce-Execution', 'NOT_GRANTED');
        $response->header('X-ABIS-Cart-Operator-Only', 'true');
        return $response;
    }

    /**
     * @return WP_REST_Response
     */
    public function rest_cart_safety_probe() {
        $payload = (new ABIS_WooCommerce_Cart_Action())->checkout_safety_probe();
        $payload['executionId'] = 'wc_real_provider_cart_actionability_001';
        return new WP_REST_Response($payload, 200);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_order_create_test(WP_REST_Request $request) {
        if (!$this->order_runtime_execution_granted()) {
            return new WP_REST_Response(
                array(
                    'orderCreateAttempted'      => false,
                    'runtimeExecutionGranted'   => false,
                    'providerNotCalled'         => true,
                    'reason'                    => 'ABIS_WC_ORDER_RUNTIME_EXECUTION not granted',
                    'testMaterial'              => ABIS_Installation_Profile::is_test_material(),
                    'purchaseCompletionClaim'   => 'NOT_EVALUATED',
                ),
                403
            );
        }
        $correlation = sanitize_text_field((string) $request->get_param('correlationId'));
        $execution   = sanitize_text_field((string) $request->get_param('executionId'));
        $attempt     = sanitize_text_field((string) $request->get_param('attemptId'));
        $product_id  = (int) $request->get_param('productId');
        $quantity    = max(1, (int) $request->get_param('quantity'));
        $payload     = (new ABIS_WooCommerce_Order_Action())->create_test_order(
            $product_id,
            $quantity,
            $correlation,
            $execution !== '' ? $execution : 'wc_real_provider_order_actionability_001',
            $attempt
        );
        $payload['executionId'] = 'wc_real_provider_order_actionability_001';
        $response = new WP_REST_Response($payload, 200);
        $response->header('X-ABIS-Commerce-Execution', 'NOT_GRANTED');
        $response->header('X-ABIS-Order-Operator-Only', 'true');
        return $response;
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_order_observe(WP_REST_Request $request) {
        $order_id = (int) $request->get_param('orderId');
        $payload  = (new ABIS_WooCommerce_Order_Action())->observe_order($order_id);
        $payload['executionId'] = 'wc_real_provider_order_actionability_001';
        $response = new WP_REST_Response($payload, 200);
        $response->header('X-ABIS-Order-Operator-Only', 'true');
        return $response;
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_order_cleanup(WP_REST_Request $request) {
        $order_id    = (int) $request->get_param('orderId');
        $correlation = sanitize_text_field((string) $request->get_param('correlationId'));
        $execution   = sanitize_text_field((string) $request->get_param('executionId'));
        $attempt     = sanitize_text_field((string) $request->get_param('attemptId'));
        $payload     = (new ABIS_WooCommerce_Order_Action())->cleanup_test_order(
            $order_id,
            $correlation,
            $execution !== '' ? $execution : 'wc_real_provider_order_actionability_001',
            $attempt
        );
        $payload['executionId'] = 'wc_real_provider_order_actionability_001';
        $response = new WP_REST_Response($payload, 200);
        $response->header('X-ABIS-Order-Operator-Only', 'true');
        return $response;
    }

    /**
     * Gate 2 — wp-config may define ABIS_WC_ORDER_RUNTIME_EXECUTION as true/1/'1'.
     */
    private function order_runtime_execution_granted(): bool {
        if (!defined('ABIS_WC_ORDER_RUNTIME_EXECUTION')) {
            return false;
        }
        $v = ABIS_WC_ORDER_RUNTIME_EXECUTION;
        return $v === true || $v === 1 || $v === '1';
    }
}