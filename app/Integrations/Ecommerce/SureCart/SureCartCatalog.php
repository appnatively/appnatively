<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\SureCart;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Integrations\Ecommerce\Catalog\ProductCatalog;

/**
 * SureCart's product data, from the `sc_product` posts it mirrors into
 * WordPress: prices (in cents), stock, sale and review figures are post meta.
 * SureCart groups products in collections and has no tags or attributes.
 */
class SureCartCatalog extends ProductCatalog {
    /** The taxonomy SureCart syncs its product collections to. */
    public const COLLECTION_TAXONOMY = 'sc_collection';

    public function post_type(): string {
        return 'sc_product';
    }

    public function category_taxonomy(): string {
        return self::COLLECTION_TAXONOMY;
    }

    /** The mirror's own bookkeeping meta, never a shopper filter. */
    public function internal_meta_keys(): array {
        return [
            'sc_id', 'product', 'min_price_amount', 'max_price_amount', 'available_stock', 'stock_enabled',
            'allow_out_of_stock_purchases', 'featured', 'recurring', 'shipping_enabled', 'purchase_limit', 'sku',
            'weight', 'weight_unit', 'display_amount', 'scratch_display_amount', 'range_display_amount',
            'is_on_sale', 'total_reviews', 'average_stars', 'reviews_enabled',
        ];
    }

    public function price_min_sql(): string {
        return $this->meta_number( 'min_price_amount' );
    }

    public function price_max_sql(): string {
        return $this->meta_number( 'max_price_amount' );
    }

    public function price_divisor(): int {
        return 100;
    }

    /** Stock isn't tracked, or some is left, or it sells when out of stock. */
    public function in_stock_sql(): string {
        return '(NOT ' . $this->meta_is_true( 'stock_enabled' ) . ' OR ' . $this->meta_number( 'available_stock' ) . ' > 0 OR ' . $this->meta_is_true( 'allow_out_of_stock_purchases' ) . ')';
    }

    public function on_sale_sql(): string {
        return $this->meta_is_true( 'is_on_sale' );
    }

    public function rating_sql(): ?string {
        return $this->meta_number( 'average_stars' );
    }

    public function rated_sql(): ?string {
        return $this->meta_number( 'total_reviews' ) . ' > 0';
    }

    /**
     * A numeric meta value of the product (NULL when missing). `+ 0` instead of CAST:
     * the query builder reads " AS " in an ORDER BY expression as a column alias.
     */
    private function meta_number( string $key ): string {
        global $wpdb;
        return "(SELECT sc_meta.meta_value + 0 FROM {$wpdb->postmeta} sc_meta WHERE sc_meta.post_id = posts.ID AND sc_meta.meta_key = '{$key}' LIMIT 1)";
    }

    private function meta_is_true( string $key ): string {
        global $wpdb;
        return "EXISTS (SELECT 1 FROM {$wpdb->postmeta} sc_flag WHERE sc_flag.post_id = posts.ID AND sc_flag.meta_key = '{$key}' AND sc_flag.meta_value IN ('1', 'true'))";
    }
}
