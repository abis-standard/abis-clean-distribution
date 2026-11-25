<?php
/**
 * HD-156 — deterministic test catalog (admin-initiated only).
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_WooCommerce_Test_Catalog_Seed {

    public const OPTION_KEY = 'abis_hd156_test_catalog_seeded';

    /**
     * @return array<string, mixed>
     */
    public static function specifications(): array {
        return array(
            array(
                'sku'          => 'ABIS-WC-TEST-A',
                'name'         => 'ABIS Catalog Probe A',
                'regularPrice' => '1980',
                'stockStatus'  => 'instock',
                'manageStock'  => true,
                'stockQty'     => 10,
            ),
            array(
                'sku'          => 'ABIS-WC-TEST-B',
                'name'         => 'ABIS Catalog Probe B',
                'regularPrice' => '2980',
                'stockStatus'  => 'outofstock',
                'manageStock'  => true,
                'stockQty'     => 0,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function ensure_products(): array {
        if (!class_exists('WooCommerce')) {
            return array('ok' => false, 'reason' => 'WOOCOMMERCE_NOT_ACTIVE');
        }
        $created = array();
        $skipped = array();
        foreach (self::specifications() as $spec) {
            $sku = $spec['sku'];
            $existing = wc_get_product_id_by_sku($sku);
            if ($existing) {
                $skipped[] = array('sku' => $sku, 'productId' => $existing);
                continue;
            }
            $product = new WC_Product_Simple();
            $product->set_name($spec['name']);
            $product->set_sku($sku);
            $product->set_regular_price($spec['regularPrice']);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_manage_stock((bool) $spec['manageStock']);
            $product->set_stock_quantity((int) $spec['stockQty']);
            $product->set_stock_status($spec['stockStatus']);
            $id = $product->save();
            $created[] = array('sku' => $sku, 'productId' => $id);
        }
        update_option(self::OPTION_KEY, gmdate('c'), false);
        return array(
            'ok'      => true,
            'created' => $created,
            'skipped' => $skipped,
            'testMaterial' => ABIS_Installation_Profile::is_test_material(),
            'noOrderData' => true,
        );
    }
}