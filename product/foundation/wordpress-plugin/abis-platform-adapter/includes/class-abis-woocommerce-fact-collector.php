<?php
/**
 * WooCommerce public catalog facts (read-only).
 *
 * @package ABISPlatformAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class ABIS_WooCommerce_Fact_Collector {
    private const VISIBLE = array('visible', 'catalog');

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|bool>, provenance: array<string, string>}
     */
    public function enumerate_offerings(int $page, int $per_page): array {
        if (!function_exists('wc_get_products')) {
            return array(
                'items'      => array(),
                'pagination' => array('page' => $page, 'perPage' => $per_page, 'totalItems' => 0, 'totalPages' => 0, 'hasMore' => false),
                'provenance' => array(),
            );
        }

        $query = wc_get_products(
            array(
                'status'   => 'publish',
                'limit'    => $per_page,
                'page'     => $page,
                'orderby'  => 'menu_order',
                'order'    => 'ASC',
                'return'   => 'objects',
                'paginate' => true,
            )
        );

        $products = is_object($query) && isset($query->products) ? $query->products : array();
        $total = is_object($query) && isset($query->total) ? (int) $query->total : 0;
        $pages = is_object($query) && isset($query->max_num_pages) ? (int) $query->max_num_pages : 0;

        $items = array();
        $provenance = array();
        $index = 0;
        foreach ($products as $product) {
            if (!$product instanceof WC_Product) {
                continue;
            }
            $vis = (string) $product->get_catalog_visibility();
            if (!in_array($vis, self::VISIBLE, true)) {
                continue;
            }
            if ($product->get_parent_id() > 0) {
                continue;
            }
            $mapped = $this->map_product($product);
            $items[] = $mapped;
            $provenance['offerings.items[' . $index . ']'] = ABIS_Constants::PROVENANCE_WOOCOMMERCE_NATIVE;
            ++$index;
        }

        return array(
            'items'      => $items,
            'pagination' => array(
                'page'        => $page,
                'perPage'     => $per_page,
                'totalItems'  => $total,
                'totalPages'  => $pages,
                'hasMore'     => $page < $pages,
            ),
            'provenance' => $provenance,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function map_product(WC_Product $product): array {
        $pid = (int) $product->get_id();
        $sku = (string) $product->get_sku();
        $categories = array();
        if (function_exists('wp_get_post_terms')) {
            $terms = wp_get_post_terms($pid, 'product_cat', array('fields' => 'names'));
            if (is_array($terms) && !is_wp_error($terms)) {
                $categories = array_values(array_map('strval', $terms));
            }
        }
        $image_id = (int) $product->get_image_id();
        $image_url = $image_id > 0 && function_exists('wp_get_attachment_image_url')
            ? wp_get_attachment_image_url($image_id, 'full')
            : null;
        $image_url = is_string($image_url) && $image_url !== '' ? $image_url : null;

        $regular = (string) $product->get_regular_price();
        $sale = (string) $product->get_sale_price();
        $current = (string) $product->get_price();

        return array(
            'offeringRef'            => 'abis.offering/wc/' . ($sku !== '' ? $sku : 'id-' . $pid),
            'productId'              => $pid,
            'sku'                    => $sku !== '' ? $sku : null,
            'name'                   => (string) $product->get_name(),
            'productType'            => (string) $product->get_type(),
            'permalink'              => (string) $product->get_permalink(),
            'shortDescription'       => (string) $product->get_short_description(),
            'description'            => (string) $product->get_description(),
            'regularPrice'           => $regular !== '' ? $regular : null,
            'salePrice'              => $sale !== '' ? $sale : null,
            'currentDisplayedPrice'  => $current !== '' ? $current : null,
            'currency'               => function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '',
            'stockStatus'            => (string) $product->get_stock_status(),
            'catalogVisibility'      => (string) $product->get_catalog_visibility(),
            'purchasable'            => (bool) $product->is_purchasable(),
            'categories'             => $categories,
            'primaryImageUrl'        => $image_url,
            'capabilityLanes'        => array(ABIS_Constants::CAPABILITY_SEARCH, ABIS_Constants::CAPABILITY_PRICE_DISCOVERY),
            'notPurchaseAuthorized'  => true,
        );
    }
}