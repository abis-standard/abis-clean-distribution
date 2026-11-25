<?php
/**
 * Read-only WooCommerce catalog observation (legacy SKU route).
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_WooCommerce_Catalog_Observer {

    /**
     * @return array<string, mixed>
     */
    public function observe_products_by_sku(array $skus): array {
        if (!class_exists('WooCommerce')) {
            return array(
                'woocommerceActive' => false,
                'observations'      => array(),
                'testMaterial'      => ABIS_Installation_Profile::is_test_material(),
                'notCommerceExecution' => true,
            );
        }

        $collector = new ABIS_WooCommerce_Fact_Collector();
        $out = array();
        foreach ($skus as $sku) {
            $sku = (string) $sku;
            if ($sku === '') {
                continue;
            }
            $product_id = wc_get_product_id_by_sku($sku);
            if (!$product_id) {
                $out[] = array(
                    'sku'    => $sku,
                    'found'  => false,
                    'native' => null,
                    'normalized' => null,
                );
                continue;
            }
            $product = wc_get_product($product_id);
            if (!$product) {
                $out[] = array(
                    'sku'   => $sku,
                    'found' => false,
                );
                continue;
            }
            $mapped = $collector->map_product($product);
            $out[] = array(
                'sku'        => $sku,
                'found'      => true,
                'native'     => $mapped,
                'normalized' => $mapped,
            );
        }

        return array(
            'woocommerceActive'                    => true,
            'observations'                         => $out,
            'providerIdentityRef'                  => 'abis.provider/woocommerce/runtime',
            'catalogReadOnly'                      => true,
            'notCommerceExecution'                 => true,
            'notPurchaseAuthorized'                => true,
            'catalogEnumerationPreferredSurface'   => ABIS_Business_Fact_Surface::canonical_rest_ref(),
            'testMaterial'                         => ABIS_Installation_Profile::is_test_material(),
        );
    }
}