<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\WooCommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\Models\Term;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductDimensionDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductImageDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductOptionDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductVariantDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFilterSourceDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFiltersDTO;
use Crafium\AppNatively\App\Integrations\Ecommerce\Catalog\ProductQuery;
use Crafium\AppNatively\App\Integrations\Ecommerce\Concerns\EcommerceIntegrationHelpers;
use Crafium\AppNatively\WpMVC\Database\Query\Builder;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

class ProductRepository {
    use EcommerceIntegrationHelpers;

    /** Product lists and filter facets (see ProductQuery). */
    private ProductQuery $query;

    public function __construct() {
        $this->query = new ProductQuery( new WooCommerceCatalog() );
    }

    /**
     * Get products paginated: the page context, the shopper's filter selection and sort.
     *
     * @param ProductPaginatorDTO|null $product_paginator The product paginator DTO.
     * @param Request $request The REST request instance.
     * @param array $fields The requested fields.
     * @return ProductPaginatorDTO
     */
    public function products( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        $page  = $this->query->paginate( $request );
        $items = array_map( fn( int $id ) => $this->map_product_to_dto( $id, $fields ), $page['ids'] );

        return new ProductPaginatorDTO( $page['page'], $page['per_page'], $page['total'], $page['last_page'], $items );
    }


    /**
     * Get published products sharing a category or tag with the requested product.
     *
     * @param ProductPaginatorDTO|null $product_paginator The product paginator DTO.
     * @param Request $request The REST request instance.
     * @param array $fields The requested fields.
     * @return ProductPaginatorDTO
     */
    public function related_products( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        $product_id = (int) craf_appna_route_param( $request, 'id' );
        $page       = (int) $request->get_param( 'page' ) ?: 1;
        $per_page   = (int) $request->get_param( 'per_page' ) ?: 10;
        $product    = $product_id ? wc_get_product( $product_id ) : null;

        if ( ! $product ) {
            return new ProductPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $term_ids = [];
        foreach ( [ 'product_cat', 'product_tag' ] as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $ids = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'ids' ] );
            if ( ! is_wp_error( $ids ) ) {
                $term_ids = array_merge( $term_ids, array_map( 'intval', $ids ) );
            }
        }

        if ( empty( $term_ids ) ) {
            return new ProductPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        // Listed like any product list: hidden-from-catalog products stay out.
        $query = $this->query->base()
            ->where( 'posts.ID', '!=', $product_id )
            ->where_has(
                'terms', function( $q ) use ( $term_ids ) {
                    $q->where_in( 'term_id', array_unique( $term_ids ) );
                }
            )
            ->select( [ 'posts.ID' ] )
            ->order_by( 'posts.post_date', 'desc' )
            ->order_by( 'posts.ID', 'desc' );

        $paginator = $query->paginate( $page, $per_page, 1 );
        $items     = [];

        foreach ( $paginator->items() as $post ) {
            $items[] = $this->map_product_to_dto( (int) $post->ID, $fields );
        }

        return new ProductPaginatorDTO(
            $page,
            $per_page,
            $paginator->total(),
            $paginator->last_page(),
            $items
        );
    }

    /**
     * Resolve device-local wishlist product IDs into full product records.
     * No pagination, filtering, or sorting — just the exact saved set.
     *
     * @param ProductPaginatorDTO|null $product_paginator The product paginator DTO.
     * @param Request $request The REST request instance.
     * @param array $fields The requested fields.
     * @return ProductPaginatorDTO
     */
    public function wishlist( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        $ids = (array) $request->get_param( "ids" );
        $ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

        if ( empty( $ids ) ) {
            return new ProductPaginatorDTO( 1, 0, 0, 1, [] );
        }

        $columns = $this->get_columns_from_fields( $fields );
        $posts   = $this->base_product_query()
            ->where_in( 'posts.ID', $ids )
            ->select( $columns )
            ->get();

        $items = [];
        foreach ( $posts as $post ) {
            $items[] = $this->map_product_to_dto( (int) $post->ID, $fields );
        }

        return new ProductPaginatorDTO( 1, count( $items ), count( $items ), 1, $items );
    }

    /**
     * The filters available for the current context, with per-option counts.
     *
     * @param ProductFiltersDTO|null $product_filters
     * @param Request $request The REST request instance.
     * @return ProductFiltersDTO
     */
    public function filters( ?ProductFiltersDTO $product_filters, Request $request ): ProductFiltersDTO {
        return $this->query->filters( $request );
    }

    /**
     * What the app builder can offer as filter rows.
     *
     * @return ProductFilterSourceDTO[]
     */
    public function filter_sources(): array {
        return $this->query->filter_sources();
    }

    /**
     * Published, unprotected products, without catalog visibility: single fetches and
     * saved wishlists reach a product hidden from the catalog (lists use ProductQuery).
     *
     * @return Builder
     */
    private function base_product_query(): Builder {
        // post_password is excluded here rather than at each call site because
        // this is the one gate every product read passes through, single
        // fetches included. A protected product keeps the `publish` status, and
        // its description is served straight from post_content.
        return Post::where( "post_type", "product" )
            ->where( "post_status", "publish" )
            ->where( "post_password", "" );
    }

    /**
     * Single product.
     *
     * @param ProductDTO|null $product_dto The product DTO.
     * @param Request $request The REST request instance.
     * @param array $fields The requested fields.
     * @return ProductDTO|null
     */
    public function product( ?ProductDTO $product_dto, Request $request, array $fields = [] ): ?ProductDTO {
        $id = (int) craf_appna_route_param( $request, "id" );

        if ( empty( $id ) ) {
            return $product_dto;
        }

        $columns = $this->get_columns_from_fields( $fields );
        $post    = $this->base_product_query()
            ->select( $columns )
            ->find( $id );

        if ( ! $post ) {
            throw new Exception( esc_html__( "Product not found.", "appnatively" ), 404 );
        }

        return $this->map_product_to_dto( (int) $post->ID, $fields );
    }

    /**
     * Resolve a field-map into a list of SQL columns to select, always including
     * a base identifier column. Shared shape for both the product and category
     * field->column maps.
     *
     * @param array  $fields         The requested fields.
     * @param array  $map            Field name => SQL column map.
     * @param string $always_include The column to always include (the id column).
     * @return array
     */
    private function columns_from_fields( array $fields, array $map, string $always_include ): array {
        $columns = [ $always_include ];
        foreach ( $fields as $field ) {
            if ( isset( $map[$field] ) ) {
                $columns[] = $map[$field];
            }
        }

        return array_unique( $columns );
    }

    /**
     * Get SQL columns from product fields.
     *
     * @param array $fields
     * @return array
     */
    private function get_columns_from_fields( array $fields ): array {
        return $this->columns_from_fields(
            $fields,
            [
                "id"                => "ID",
                "name"              => "post_title",
                "slug"              => "post_name",
                "description"       => "post_content",
                "short_description" => "post_excerpt",
                "status"            => "post_status",
            ],
            "ID"
        );
    }

    /**
     * Map a product to ProductDTO.
     *
     * @param int $id The product id.
     * @param array $fields The requested fields.
     * @return ProductDTO
     */
    private function map_product_to_dto( int $id, array $fields ): ProductDTO {
        $dto     = new ProductDTO();
        $product = wc_get_product( $id );

        if ( ! $product ) {
            return $dto; // Should not happen for valid products
        }

        if ( in_array( "id", $fields ) ) {
            $dto->set_id( $id );
        }
        if ( in_array( "name", $fields ) ) {
            $dto->set_name( $product->get_name() );
        }
        if ( in_array( "slug", $fields ) ) {
            $dto->set_slug( $product->get_slug() );
        }
        if ( in_array( "type", $fields ) ) {
            $dto->set_type( $product->get_type() );
        }
        if ( in_array( "status", $fields ) ) {
            $dto->set_status( $product->get_status() );
        }
        if ( in_array( "description", $fields ) ) {
            $dto->set_description( $this->parse_content( (string) $product->get_description() ) );
        }
        if ( in_array( "short_description", $fields ) ) {
            $dto->set_short_description( $this->parse_content( (string) $product->get_short_description() ) );
        }
        if ( in_array( "url", $fields ) ) {
            $dto->set_url( get_permalink( $id ) );
        }

        // Financials
        $dto->set_currency( get_woocommerce_currency() );
        if ( in_array( "price", $fields ) ) {
            $dto->set_price( (string) $product->get_price() );
        }
        if ( in_array( "compare_at_price", $fields ) ) {
            $dto->set_compare_at_price( (string) $product->get_regular_price() );
        }
        if ( in_array( "on_sale", $fields ) ) {
            $dto->set_on_sale( $product->is_on_sale() );
        }
        if ( in_array( "average_rating", $fields ) ) {
            $dto->set_average_rating( (float) $product->get_average_rating() );
        }
        if ( in_array( "rating_count", $fields ) ) {
            $dto->set_rating_count( (int) $product->get_rating_count() );
        }

        // Logistics
        if ( in_array( "sku", $fields ) ) {
            $dto->set_sku( (string) $product->get_sku() );
        }
        if ( in_array( "stock_status", $fields ) || in_array( "inventory_status", $fields ) ) {
            $dto->set_inventory_status( $product->get_stock_status() );
        }
        if ( in_array( "manage_stock", $fields ) ) {
            $dto->set_manage_stock( $product->get_manage_stock() );
        }
        if ( in_array( "stock_quantity", $fields ) ) {
            $dto->set_stock_quantity( (int) $product->get_stock_quantity() );
        }
        if ( in_array( "weight", $fields ) ) {
            $dto->set_weight( (float) $product->get_weight() );
        }

        // Dimensions
        $dim_dto = new ProductDimensionDTO();
        $dim_dto->set_length( (float) $product->get_length() )
            ->set_width( (float) $product->get_width() )
            ->set_height( (float) $product->get_height() )
            ->set_unit( get_option( "woocommerce_dimension_unit" ) );
        $dto->set_dimensions( $dim_dto );

        // Audit
        if ( in_array( "date_created", $fields ) ) {
            $dto->set_date_created( $this->format_date( $product->get_date_created() ) );
        }

        if ( in_array( "date_updated", $fields ) ) {
            $dto->set_date_updated( $this->format_date( $product->get_date_modified() ) );
        }

        // Images
        if ( in_array( "images", $fields ) ) {
            $image_ids = array_merge( [$product->get_image_id()], $product->get_gallery_image_ids() );
            $images    = [];
            foreach ( array_filter( $image_ids ) as $image_id ) {
                $img_dto = new ProductImageDTO();
                $img_dto->set_id( (int) $image_id )
                    ->set_src( (string) wp_get_attachment_url( $image_id ) )
                    ->set_alt( (string) get_post_meta( $image_id, "_wp_attachment_image_alt", true ) )
                    ->set_title( (string) get_the_title( $image_id ) );
                $images[] = $img_dto;
            }
            $dto->set_images( $images );
        }

        // Categories
        if ( in_array( "categories", $fields ) ) {
            $categories = [];
            $term_ids   = $product->get_category_ids();
            foreach ( $term_ids as $term_id ) {
                $term = get_term( $term_id );
                if ( $term ) {
                    $cat_dto = new CategoryDTO();
                    $cat_dto->set_id( $term->term_id )
                        ->set_name( $term->name )
                        ->set_slug( $term->slug );

                    $image_id = get_term_meta( $term->term_id, "thumbnail_id", true );
                    if ( $image_id ) {
                        $cat_img = new ProductImageDTO();
                        $cat_img->set_id( (int) $image_id )
                            ->set_src( (string) wp_get_attachment_url( $image_id ) );
                        $cat_dto->set_image( $cat_img );
                    }
                    $categories[] = $cat_dto;
                }
            }
            $dto->set_categories( $categories );
        }

        // Hierarchy (Variations)
        if ( $product->is_type( "variable" ) && in_array( "variants", $fields ) ) {
            $variants = [];
            /** @var \WC_Product_Variable $product */
            foreach ( $product->get_children() as $child_id ) {
                $variation = wc_get_product( $child_id );
                if ( $variation ) {
                    $var_dto = new ProductVariantDTO();
                    $var_dto->set_id( $variation->get_id() )
                        ->set_sku( $variation->get_sku() )
                        ->set_name( $variation->get_name() )
                        ->set_price( (string) $variation->get_price() )
                        ->set_compare_at_price( (string) $variation->get_regular_price() )
                        ->set_inventory_status( $variation->get_stock_status() )
                        ->set_manage_stock( $variation->get_manage_stock() )
                        ->set_stock_quantity( (int) $variation->get_stock_quantity() )
                        ->set_weight( (float) $variation->get_weight() )
                        ->set_attributes( $this->resolve_attributes_labels( $variation->get_attributes(), $product ) );
                    
                    $var_img_id = $variation->get_image_id();
                    if ( $var_img_id ) {
                        $var_img = new ProductImageDTO();
                        $var_img->set_id( (int) $var_img_id )
                            ->set_src( (string) wp_get_attachment_url( $var_img_id ) );
                        $var_dto->set_image( $var_img );
                    }

                    $variants[] = $var_dto;
                }
            }
            $dto->set_variants( $variants );
        }

        // Options (variation attributes: name + possible values)
        if ( $product->is_type( "variable" ) && in_array( "options", $fields ) ) {
            /** @var \WC_Product_Variable $product */
            $options = [];
            foreach ( $product->get_variation_attributes() as $attribute_key => $values ) {
                $attribute_name = preg_replace( '/^attribute_/', '', $attribute_key );
                $option_dto     = new ProductOptionDTO();
                $option_dto->set_name( $this->resolve_attribute_label( $attribute_name, $product ) )
                    ->set_values(
                        array_map(
                            fn( $value ) => $this->resolve_attribute_value_label( $attribute_name, $value ),
                            array_values( $values )
                        )
                    );
                $options[] = $option_dto;
            }
            $dto->set_options( $options );
        }

        return $dto;
    }

    /**
     * Human-readable label for a product attribute.
     */
    private function resolve_attribute_label( string $attribute_name, \WC_Product $product ): string {
        return wc_attribute_label( $attribute_name, $product );
    }

    /**
     * Human-readable label for a product attribute's value.
     */
    private function resolve_attribute_value_label( string $attribute_name, string $value ): string {
        if ( $value !== '' && taxonomy_exists( $attribute_name ) ) {
            $term = get_term_by( 'slug', $value, $attribute_name );
            if ( $term ) {
                return $term->name;
            }
        }
        return $value;
    }

    /**
     * Maps variant attributes.
     */
    private function resolve_attributes_labels( array $raw_attributes, \WC_Product $product ): array {
        $labeled_attributes = [];
        foreach ( $raw_attributes as $attribute_name => $value ) {
            $labeled_attributes[ $this->resolve_attribute_label( $attribute_name, $product ) ] =
                $this->resolve_attribute_value_label( $attribute_name, $value );
        }
        return $labeled_attributes;
    }

    /**
     * Parse content safely.
     */
    private function parse_content( string $content ): string {
        if ( empty( $content ) ) {
            return "";
        }

        // Render Gutenberg blocks
        if ( function_exists( "do_blocks" ) ) {
            $content = do_blocks( $content );
        }

        // Add paragraphs
        $content = wpautop( $content );

        // Standard texturizing
        $content = wptexturize( $content );
        $content = convert_chars( $content );

        // Shortcodes cleanup and execution
        $content = shortcode_unautop( $content );
        $content = do_shortcode( $content );

        return $content;
    }

    /**
     * Get categories.
     */
    public function categories( ?CategoryPaginatorDTO $category_paginator, Request $request, array $fields = [] ): CategoryPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $search   = $request->get_param( "search" );

        $query = Term::join( "term_taxonomy", "terms.term_id", "=", "term_taxonomy.term_id" )
            ->where( "term_taxonomy.taxonomy", "product_cat" );

        // SQL select optimization
        $columns = $this->get_category_columns_from_fields( $fields );
        $query->select( $columns );

        if ( ! empty( $search ) ) {
            global $wpdb;
            $search = $wpdb->esc_like( $search );
            $query->where( "terms.name", "like", "%$search%" );
        }

        $paginator = $query->paginate( $page, $per_page, 1 );

        $items = [];
        foreach ( $paginator->items() as $term ) {
            $items[] = $this->map_term_to_category_dto( $term, $fields );
        }

        return new CategoryPaginatorDTO(
            $page,
            $per_page,
            $paginator->total(),
            $paginator->last_page(),
            $items
        );
    }

    /**
     * Single category.
     */
    public function category( ?CategoryDTO $category_dto, Request $request, array $fields = [] ): ?CategoryDTO {
        $id = (int) craf_appna_route_param( $request, "id" );

        if ( empty( $id ) ) {
            return $category_dto;
        }

        $columns = $this->get_category_columns_from_fields( $fields );
        $term    = Term::join( "term_taxonomy", "terms.term_id", "=", "term_taxonomy.term_id" )
            ->where( "term_taxonomy.taxonomy", "product_cat" )
            ->where( "terms.term_id", $id )
            ->select( $columns )
            ->first();

        if ( ! $term ) {
            throw new Exception( esc_html__( "Category not found.", "appnatively" ), 404 );
        }

        return $this->map_term_to_category_dto( $term, $fields );
    }

    /**
     * SQL columns map.
     */
    private function get_category_columns_from_fields( array $fields ): array {
        return $this->columns_from_fields(
            $fields,
            [
                "id"          => "terms.term_id",
                "name"        => "terms.name",
                "slug"        => "terms.slug",
                "description" => "term_taxonomy.description",
                "parent"      => "term_taxonomy.parent",
                "count"       => "term_taxonomy.count",
            ],
            "terms.term_id"
        );
    }

    /**
     * Map term to category DTO.
     */
    private function map_term_to_category_dto( $term, array $fields ): CategoryDTO {
        $dto = new CategoryDTO();

        if ( in_array( "id", $fields ) ) {
            $dto->set_id( (int) $term->term_id );
        }
        if ( in_array( "name", $fields ) ) {
            $dto->set_name( $term->name );
        }
        if ( in_array( "slug", $fields ) ) {
            $dto->set_slug( $term->slug );
        }
        if ( in_array( "description", $fields ) ) {
            $dto->set_description( $term->description );
        }
        if ( in_array( "parent", $fields ) ) {
            $dto->set_parent( (int) $term->parent );
        }
        if ( in_array( "count", $fields ) ) {
            $dto->set_count( (int) $term->count );
        }

        // Image
        if ( in_array( "image", $fields ) ) {
            $image_id = get_term_meta( $term->term_id, "thumbnail_id", true );
            if ( ! empty( $image_id ) ) {
                $img_dto = new ProductImageDTO();
                $img_dto->set_id( (int) $image_id )
                    ->set_src( (string) wp_get_attachment_url( $image_id ) );
                $dto->set_image( $img_dto );
            }
        }

        return $dto;
    }
}
