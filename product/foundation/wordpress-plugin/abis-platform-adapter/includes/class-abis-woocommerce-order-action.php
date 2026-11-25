<?php
/**
 * HD-160 — bounded WooCommerce test Order creation (operator-only).
 *
 * Order created !== Purchase completed !== Payment.
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_WooCommerce_Order_Action {

    private const META_TEST_MATERIAL = '_abis_test_material';

    /**
     * @return array<string, mixed>
     */
    public function create_test_order(
        int $product_id,
        int $quantity,
        string $correlation_id,
        string $execution_id,
        string $attempt_id
    ): array {
        if (!class_exists('WooCommerce') || !function_exists('wc_create_order')) {
            return array(
                'orderCreateAttempted' => false,
                'woocommerceActive'    => false,
                'testMaterial'         => ABIS_Installation_Profile::is_test_material(),
            );
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return array(
                'orderCreateAttempted' => true,
                'providerAccepted'     => false,
                'reason'               => 'invalid_product',
                'testMaterial'         => ABIS_Installation_Profile::is_test_material(),
            );
        }

        try {
            $order = wc_create_order(array('status' => 'pending'));
            if (!$order || !is_a($order, 'WC_Order')) {
                return array(
                    'orderCreateAttempted' => true,
                    'providerAccepted'     => false,
                    'reason'               => 'wc_create_order_failed',
                    'testMaterial'         => ABIS_Installation_Profile::is_test_material(),
                );
            }

            $marker = array(
                'testMaterial'  => ABIS_Installation_Profile::is_test_material(),
                'executionId'   => $execution_id,
                'attemptId'     => $attempt_id,
                'correlationId' => $correlation_id,
                'slice'         => 'HD-160',
            );
            $order->update_meta_data(self::META_TEST_MATERIAL, wp_json_encode($marker));
            $order->add_meta_data('_abis_execution_id', $execution_id, true);
            $order->add_meta_data('_abis_correlation_id', $correlation_id, true);

            $email = 'abis-hd160-' . substr(hash('sha256', $correlation_id), 0, 12) . '@test.invalid';
            $order->set_billing_email($email);
            $order->set_billing_first_name('ABIS');
            $order->set_billing_last_name('TestMaterial');

            $item_id = $order->add_product($product, max(1, $quantity));
            if (!$item_id) {
                $order->delete(true);
                return array(
                    'orderCreateAttempted' => true,
                    'providerAccepted'     => false,
                    'reason'               => 'add_product_failed',
                    'providerErrorNotices' => $this->collect_notices(),
                    'testMaterial'         => ABIS_Installation_Profile::is_test_material(),
                );
            }

            $order->calculate_totals();
            $order->save();

            return array_merge(
                $this->observe_order((int) $order->get_id()),
                array(
                    'orderCreateAttempted'  => true,
                    'providerAccepted'      => true,
                    'correlationId'         => $correlation_id,
                    'productId'             => $product_id,
                    'quantityRequested'     => $quantity,
                    'notPurchaseCompleted'  => true,
                    'purchaseCompletionClaim' => 'NOT_EVALUATED',
                    'paymentExecuted'       => false,
                    'notPaymentAuthorized'  => true,
                    'notPaymentCaptured'    => true,
                    'testMaterial'          => ABIS_Installation_Profile::is_test_material(),
                )
            );
        } catch (\Throwable $e) {
            return array(
                'orderCreateAttempted' => true,
                'providerAccepted'     => false,
                'reason'               => 'exception',
                'message'              => substr($e->getMessage(), 0, 200),
                'testMaterial'         => ABIS_Installation_Profile::is_test_material(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function observe_order(int $order_id): array {
        if ($order_id <= 0 || !function_exists('wc_get_order')) {
            return array('found' => false, 'testMaterial' => ABIS_Installation_Profile::is_test_material());
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return array('found' => false, 'testMaterial' => ABIS_Installation_Profile::is_test_material());
        }
        return array(
            'found'                => true,
            'nativeOrderId'        => (int) $order->get_id(),
            'opaqueNativeOrderRef' => 'wp:order:' . (int) $order->get_id(),
            'providerOrderStatus'  => (string) $order->get_status(),
            'orderTotal'           => (string) $order->get_total(),
            'currency'             => (string) $order->get_currency(),
            'hasAbisTestMarker'    => $order->meta_exists(self::META_TEST_MATERIAL),
            'notPurchaseCompleted' => true,
            'paymentExecuted'      => false,
            'testMaterial'         => ABIS_Installation_Profile::is_test_material(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cleanup_test_order(
        int $order_id,
        string $correlation_id,
        string $execution_id,
        string $attempt_id
    ): array {
        $order = $order_id > 0 ? wc_get_order($order_id) : null;
        if (!$order) {
            return array(
                'cleanupPerformed' => false,
                'reason'           => 'order_not_found',
                'testMaterial'     => ABIS_Installation_Profile::is_test_material(),
            );
        }
        if (!$this->verify_test_marker($order, $correlation_id, $execution_id, $attempt_id)) {
            return array(
                'cleanupPerformed' => false,
                'reason'           => 'cleanup_denied_identity_mismatch',
                'testMaterial'     => ABIS_Installation_Profile::is_test_material(),
            );
        }
        $order->update_status('cancelled', 'ABIS HD-160 test cleanup');
        $order->save();
        return array(
            'cleanupPerformed'     => true,
            'cleanupPolicy'        => 'cancel_and_retain',
            'providerOrderStatus'  => (string) $order->get_status(),
            'nativeOrderId'        => (int) $order->get_id(),
            'notPaymentExecuted'   => true,
            'testMaterial'         => ABIS_Installation_Profile::is_test_material(),
        );
    }

    /**
     * @param WC_Order $order
     */
    private function verify_test_marker($order, string $correlation_id, string $execution_id, string $attempt_id): bool {
        $raw = $order->get_meta(self::META_TEST_MATERIAL, true);
        if (!is_string($raw) || $raw === '') {
            return false;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return false;
        }
        return ($decoded['executionId'] ?? '') === $execution_id
            && ($decoded['attemptId'] ?? '') === $attempt_id
            && ($decoded['correlationId'] ?? '') === $correlation_id
            && !empty($decoded['testMaterial']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collect_notices(): array {
        $errors = wc_get_notices('error');
        wc_clear_notices();
        return is_array($errors) ? $errors : array();
    }
}