<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\FluentCart;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Integrations\Ecommerce\Catalog\ProductCatalog;
use Crafium\AppNatively\WpMVC\Database\Query\Builder;

/**
 * FluentCart's product data: prices and sale prices on `fct_product_variations`
 * (amounts in cents), stock on `fct_product_details`. FluentCart has no
 * product tags, attributes or ratings.
 */
class FluentCartCatalog extends ProductCatalog {
    public const CATEGORY_TAXONOMY = 'product-categories';
    public const BRAND_TAXONOMY    = 'product-brands';

    public function post_type(): string {
        return 'fluent-products';
    }

    public function category_taxonomy(): string {
        return self::CATEGORY_TAXONOMY;
    }

    public function extra_taxonomies(): array {
        $brands = get_taxonomy( self::BRAND_TAXONOMY );
        return $brands ? [ self::BRAND_TAXONOMY => (string) $brands->labels->singular_name ] : [];
    }

    public function prepare( Builder $query ): void {
        $query->left_join( 'fct_product_details', 'posts.ID', '=', 'fct_product_details.post_id' );
    }

    /**
     * From the variations, like FluentCart's own ProductDetail::getMinPriceAttribute()
     * (what the app shows): the stored `min_price` column can be stale.
     */
    public function price_min_sql(): string {
        return $this->variation_price( 'MIN' );
    }

    public function price_max_sql(): string {
        return $this->variation_price( 'MAX' );
    }

    public function price_divisor(): int {
        return 100;
    }

    public function in_stock_sql(): string {
        return "fct_product_details.stock_availability = 'in-stock'";
    }

    public function on_sale_sql(): string {
        global $wpdb;
        return "EXISTS (SELECT 1 FROM {$wpdb->prefix}fct_product_variations sale_variation WHERE sale_variation.post_id = posts.ID AND sale_variation.compare_price > sale_variation.item_price)";
    }

    private function variation_price( string $aggregate ): string {
        global $wpdb;
        return "(SELECT {$aggregate}(price_variation.item_price) FROM {$wpdb->prefix}fct_product_variations price_variation WHERE price_variation.post_id = posts.ID)";
    }
}
