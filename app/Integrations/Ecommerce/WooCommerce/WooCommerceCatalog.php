<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\WooCommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Integrations\Ecommerce\Catalog\ProductCatalog;
use Crafium\AppNatively\WpMVC\Database\Query\Builder;

/**
 * WooCommerce's product data: prices, stock, sale and ratings come from
 * `wc_product_meta_lookup` (its indexed, variation-aware table).
 */
class WooCommerceCatalog extends ProductCatalog {
    /** @var array<string, string>|null */
    private ?array $attribute_taxonomies = null;

    /** @var array<string, string>|null */
    private ?array $extra_taxonomies = null;

    public function post_type(): string {
        return 'product';
    }

    public function category_taxonomy(): string {
        return 'product_cat';
    }

    public function tag_taxonomy(): ?string {
        return 'product_tag';
    }

    public function attribute_taxonomies(): array {
        if ( $this->attribute_taxonomies === null ) {
            $this->attribute_taxonomies = [];
            foreach ( wc_get_attribute_taxonomy_names() as $taxonomy ) {
                $this->attribute_taxonomies[ $taxonomy ] = wc_attribute_label( $taxonomy );
            }
        }
        return $this->attribute_taxonomies;
    }

    /**
     * Public product taxonomies (brands and the like), without categories, tags,
     * attributes and WooCommerce's internal taxonomies.
     */
    public function extra_taxonomies(): array {
        if ( $this->extra_taxonomies === null ) {
            $internal               = [ 'product_cat', 'product_tag', 'product_type', 'product_visibility', 'product_shipping_class' ];
            $this->extra_taxonomies = [];
            foreach ( get_object_taxonomies( 'product', 'objects' ) as $taxonomy ) {
                if ( in_array( $taxonomy->name, $internal, true ) || str_starts_with( $taxonomy->name, 'pa_' ) || ! $taxonomy->public ) {
                    continue;
                }
                $this->extra_taxonomies[ $taxonomy->name ] = (string) $taxonomy->labels->singular_name;
            }
        }
        return $this->extra_taxonomies;
    }

    public function internal_meta_keys(): array {
        return [ 'total_sales' ];
    }

    public function prepare( Builder $query ): void {
        $query->left_join( 'wc_product_meta_lookup', 'posts.ID', '=', 'wc_product_meta_lookup.product_id' );
    }

    /**
     * Leaves out products hidden from the catalog (or from search results when
     * searching), and out-of-stock products when the store hides them.
     */
    public function where_visible( Builder $query, bool $searching ): void {
        global $wpdb;
        $term_ids = wc_get_product_visibility_term_ids();
        $hidden   = [ $term_ids[ $searching ? 'exclude-from-search' : 'exclude-from-catalog' ] ?? 0 ];
        if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
            $hidden[] = $term_ids['outofstock'] ?? 0;
        }
        $hidden = array_filter( array_map( 'intval', $hidden ) );
        if ( empty( $hidden ) ) {
            return;
        }

        $query->where_raw(
            "NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} visibility WHERE visibility.object_id = posts.ID AND visibility.term_taxonomy_id IN (" . implode( ',', $hidden ) . '))'
        );
    }

    public function price_min_sql(): string {
        return 'wc_product_meta_lookup.min_price';
    }

    public function price_max_sql(): string {
        return 'wc_product_meta_lookup.max_price';
    }

    public function in_stock_sql(): string {
        return "wc_product_meta_lookup.stock_status = 'instock'";
    }

    public function on_sale_sql(): string {
        return 'wc_product_meta_lookup.onsale = 1';
    }

    public function rating_sql(): ?string {
        return 'wc_product_meta_lookup.average_rating';
    }

    public function rated_sql(): ?string {
        return 'wc_product_meta_lookup.rating_count > 0';
    }

    public function popularity_sql(): ?string {
        return 'wc_product_meta_lookup.total_sales';
    }
}
