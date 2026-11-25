<?php
/**
 * HD-157 — bounded WooCommerce cart mutation (operator-only; not public declaration).
 *
 * Cart mutation !== purchase completion.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_WooCommerce_Cart_Action {

    /**
     * @return array<string, mixed>
     */
    public function observe_cart_state(string $correlation_id = ''): array {
        if (!class_exists('WooCommerce')) {
            return array(
                'woocommerceActive' => false,
                'testMaterial'      => ABIS_Installation_Profile::is_test_material(),
            );
        }
        $this->ensure_cart_loaded();
        $lines = array();
        foreach (WC()->cart->get_cart() as $key => $item) {
            $lines[] = array(
                'cartItemKey' => (string) $key,
                'productId'   => (int) $item['product_id'],
                'quantity'    => (int) $item['quantity'],
            );
        }
        $encoded = wp_json_encode($lines);
        $hash    = is_string($encoded) ? hash('sha256', $encoded) : '';

        return array(
            'woocommerceActive'    => true,
            'correlationId'        => $correlation_id,
            'itemCount'            => (int) WC()->cart->get_cart_contents_count(),
            'lineCount'            => count($lines),
            'lines'                => $lines,
            'cartStateOpaqueRef'   => 'wc-cart-sha256:' . substr($hash, 0, 16),
            'notPurchaseCompleted' => true,
            'notOrderCreated'      => true,
            'notPaymentData'       => true,
            'testMaterial'         => ABIS_Installation_Profile::is_test_material(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function reset_cart(string $correlation_id = ''): array {
        if (!class_exists('WooCommerce')) {
            return array('ok' => false, 'woocommerceActive' => false);
        }
        $this->ensure_cart_loaded();
        WC()->cart->empty_cart();
        wc_clear_notices();
        $state = $this->observe_cart_state($correlation_id);
        $state['resetPerformed'] = true;
        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function add_line(int $product_id, int $quantity, string $correlation_id = ''): array {
        if (!class_exists('WooCommerce')) {
            return array(
                'mutationAttempted' => false,
                'woocommerceActive' => false,
            );
        }
        $this->ensure_cart_loaded();
        $product = wc_get_product($product_id);
        $pre     = $this->observe_cart_state($correlation_id . ':pre');
        $key     = false;
        if ($product) {
            $key = WC()->cart->add_to_cart($product_id, max(1, $quantity));
        }
        $errors = wc_get_notices('error');
        wc_clear_notices();
        $post = $this->observe_cart_state($correlation_id . ':post');

        return array(
            'mutationAttempted'    => true,
            'woocommerceActive'    => true,
            'correlationId'        => $correlation_id,
            'productId'            => $product_id,
            'quantityRequested'    => $quantity,
            'cartItemKey'          => $key ? (string) $key : null,
            'providerAccepted'     => (bool) $key,
            'providerErrorNotices' => $errors,
            'preCartState'         => $pre,
            'postCartState'        => $post,
            'notPurchaseCompleted' => true,
            'notOrderCreated'      => true,
            'testMaterial'         => ABIS_Installation_Profile::is_test_material(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function checkout_safety_probe(): array {
        global $wpdb;
        $recent = 0;
        if (isset($wpdb) && function_exists('wc_get_orders')) {
            $ids = wc_get_orders(
                array(
                    'limit'   => 5,
                    'orderby' => 'date',
                    'order'   => 'DESC',
                    'return'  => 'ids',
                )
            );
            $recent = is_array($ids) ? count($ids) : 0;
        }
        return array(
            'recentOrdersSampleCount' => $recent,
            'notCheckoutExecuted'     => true,
            'notPaymentCaptured'      => true,
            'testMaterial'            => ABIS_Installation_Profile::is_test_material(),
        );
    }

    private function ensure_cart_loaded(): void {
        if (null === WC()->cart) {
            wc_load_cart();
        }
    }
}