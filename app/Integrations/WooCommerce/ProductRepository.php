<?php

namespace Crafium\AppNatively\App\Integrations\WooCommerce;

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
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

class ProductRepository {
    /**
     * Get products paginated.
     *
     * @param ProductPaginatorDTO|null $product_paginator The product paginator DTO.
     * @param Request $request The REST request instance.
     * @param array $fields The requested fields.
     * @return ProductPaginatorDTO
     */
    public function products( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        $page        = (int) $request->get_param( "page" ) ?: 1;
        $per_page    = (int) $request->get_param( "per_page" ) ?: 10;
        $search      = $request->get_param( "search" );
        $sort        = $request->get_param( "sort" );
        $category_id = $request->get_param( "categoryId" );

        $order_by = "date";
        $order    = "DESC";

        if ( ! empty( $sort ) ) {
            if ( str_starts_with( $sort, "-" ) ) {
                $order_by = ltrim( $sort, "-" );
                $order    = "DESC";
            } else {
                $order_by = $sort;
                $order    = "ASC";
            }
        }

        $query = Post::where( "post_type", "product" )
            ->where( "post_status", "publish" );

        if ( ! empty( $category_id ) ) {
            $query->where_has(
                'terms', function( $q ) use ( $category_id ) {
                    $q->where( 'taxonomy', 'product_cat' )
                    ->where( 'term_id', (int) $category_id );
                } 
            );
        }

        // SQL select optimization
        $columns = $this->get_columns_from_fields( $fields );
        $query->select( $columns );

        if ( ! empty( $search ) ) {
            global $wpdb;
            $search = $wpdb->esc_like( $search );
            $query->where( "post_title", "like", "%$search%" );
        }

        // Sorting mapping
        $sort_map = [
            "date"  => "post_date",
            "title" => "post_title",
            "name"  => "post_name",
            "id"    => "ID",
        ];
        $sort_col = $sort_map[$order_by] ?? "post_date";
        $query->order_by( $sort_col, $order );

        $paginator = $query->paginate( $page, $per_page );

        $items = [];
        foreach ( $paginator->items() as $post ) {
            $items[] = $this->map_post_to_product_dto( $post, $fields );
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
     * Single product.
     *
     * @param ProductDTO|null $product_dto The product DTO.
     * @param Request $request The REST request instance.
     * @param array $fields The requested fields.
     * @return ProductDTO|null
     */
    public function product( ?ProductDTO $product_dto, Request $request, array $fields = [] ): ?ProductDTO {
        $id = (int) $request->get_param( "id" );

        if ( empty( $id ) ) {
            return $product_dto;
        }

        $columns = $this->get_columns_from_fields( $fields );
        $post    = Post::select( $columns )
            ->where( "post_type", "product" )
            ->where( "post_status", "publish" )
            ->find( $id );

        if ( ! $post ) {
            throw new Exception( esc_html__( "Product not found.", "appnatively" ), 404 );
        }

        return $this->map_post_to_product_dto( $post, $fields );
    }

    /**
     * Get SQL columns from fields.
     *
     * @param array $fields
     * @return array
     */
    private function get_columns_from_fields( array $fields ): array {
        $map = [
            "id"                => "ID",
            "name"              => "post_title",
            "slug"              => "post_name",
            "description"       => "post_content",
            "short_description" => "post_excerpt",
            "status"            => "post_status",
        ];

        $columns = ["ID"]; // Always include ID
        foreach ( $fields as $field ) {
            if ( isset( $map[$field] ) ) {
                $columns[] = $map[$field];
            }
        }

        return array_unique( $columns );
    }

    /**
     * Map Post model to ProductDTO.
     *
     * @param Post $post The post model instance.
     * @param array $fields The requested fields.
     * @return ProductDTO
     */
    private function map_post_to_product_dto( Post $post, array $fields ): ProductDTO {
        $dto     = new ProductDTO();
        $product = wc_get_product( $post->ID );

        if ( ! $product ) {
            return $dto; // Should not happen for valid products
        }

        if ( in_array( "id", $fields ) ) {
            $dto->set_id( $post->ID );
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
        if ( in_array( "permalink", $fields ) ) {
            $dto->set_permalink( get_permalink( $post->ID ) );
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

        // Logistics
        if ( in_array( "sku", $fields ) ) {
            $dto->set_sku( (string) $product->get_sku() );
        }
        if ( in_array( "inventory_status", $fields ) ) {
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
            $dto->set_date_created( $product->get_date_created() ? $product->get_date_created()->format( "c" ) : "" );
        }

        if ( in_array( "date_updated", $fields ) ) {
            $dto->set_date_updated( $product->get_date_modified() ? $product->get_date_modified()->format( "c" ) : "" );
        }

        // Images
        if ( in_array( "images", $fields ) ) {
            $image_ids = array_merge( [$product->get_image_id()], $product->get_gallery_image_ids() );
            $images    = [];
            foreach ( array_filter( $image_ids ) as $id ) {
                $img_dto = new ProductImageDTO();
                $img_dto->set_id( (int) $id )
                    ->set_src( (string) wp_get_attachment_url( $id ) )
                    ->set_alt( (string) get_post_meta( $id, "_wp_attachment_image_alt", true ) )
                    ->set_title( (string) get_the_title( $id ) );
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

        $paginator = $query->paginate( $page, $per_page );

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
        $id = (int) $request->get_param( "id" );

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
        $map = [
            "id"          => "terms.term_id",
            "name"        => "terms.name",
            "slug"        => "terms.slug",
            "description" => "term_taxonomy.description",
            "parent"      => "term_taxonomy.parent",
            "count"       => "term_taxonomy.count",
        ];

        $columns = ["terms.term_id"]; // Always include ID
        foreach ( $fields as $field ) {
            if ( isset( $map[$field] ) ) {
                $columns[] = $map[$field];
            }
        }

        return array_unique( $columns );
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
