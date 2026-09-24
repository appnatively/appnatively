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
use Crafium\AppNatively\App\DTO\Ecommerce\AttributeFacetDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\AttributeFacetOptionDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFiltersDTO;
use Crafium\AppNatively\App\Integrations\Ecommerce\Concerns\EcommerceIntegrationHelpers;
use Crafium\AppNatively\WpMVC\Database\Query\Builder;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

class ProductRepository {
    use EcommerceIntegrationHelpers;

    /**
     * Memoized result of `wc_get_attribute_taxonomy_names()` — a single filters
     * request can otherwise trigger this call a dozen+ times (once per
     * `apply_context_filters()` call, once per taxonomy in `build_attribute_facets()`).
     *
     * @var string[]|null
     */
    private ?array $attribute_taxonomies = null;

    /**
     * Get products paginated.
     *
     * @param ProductPaginatorDTO|null $product_paginator The product paginator DTO.
     * @param Request $request The REST request instance.
     * @param array $fields The requested fields.
     * @return ProductPaginatorDTO
     */
    public function products( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $sort     = (string) ( $request->get_param( "sort" ) ?: "relevance" );

        $query = $this->base_product_query();

        $needs_lookup = $this->apply_context_filters( $query, $request );

        [$sort_column, $sort_direction, $sort_needs_lookup] = $this->resolve_sort( $sort );
        if ( $sort_needs_lookup ) {
            $this->join_meta_lookup( $query );
        }

        // SQL select optimization
        $columns = $this->get_columns_from_fields( $fields );
        $query->select( $columns );

        $query->order_by( $sort_column, $sort_direction );

        $paginator = $query->paginate( $page, $per_page, 1 );

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

        $query = $this->base_product_query()
            ->where( 'posts.ID', '!=', $product_id )
            ->where_has(
                'terms', function( $q ) use ( $term_ids ) {
                    $q->where_in( 'term_id', array_unique( $term_ids ) );
                }
            )
            ->select( $this->get_columns_from_fields( $fields ) )
            ->order_by( 'post_date', 'desc' );

        $paginator = $query->paginate( $page, $per_page, 1 );
        $items     = [];

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
            $items[] = $this->map_post_to_product_dto( $post, $fields );
        }

        return new ProductPaginatorDTO( 1, count( $items ), count( $items ), 1, $items );
    }

    /**
     * Describe the filters available for the current context (category / search /
     * already-applied filters) with per-option counts, so the client can render the
     * drawer purely from what the backend says is available.
     *
     * @param ProductFiltersDTO|null $product_filters
     * @param Request $request The REST request instance.
     * @return ProductFiltersDTO
     */
    public function filters( ?ProductFiltersDTO $product_filters, Request $request ): ProductFiltersDTO {
        $dto = new ProductFiltersDTO();
        $dto->set_sort_options( ProductFiltersDTO::SORT_TOKENS );

        // Price bounds across every filter currently in effect (including attributes).
        $price_query = $this->base_product_query();
        $this->apply_context_filters( $price_query, $request );
        $this->join_meta_lookup( $price_query );

        $min_price = $price_query->min( "wc_product_meta_lookup.min_price" );
        $max_price = $price_query->max( "wc_product_meta_lookup.max_price" );
        $dto->set_price( $min_price !== null && $max_price !== null ? ["min" => (string) $min_price, "max" => (string) $max_price] : null );

        // Rating: the drawer only needs to know whether a star-picker is worth showing.
        $rating_query = $this->base_product_query();
        $this->apply_context_filters( $rating_query, $request );
        $this->join_meta_lookup( $rating_query );
        $rating_query->where( "wc_product_meta_lookup.rating_count", ">", 0 );
        $dto->set_rating( $rating_query->exists() ? ["max" => 5] : null );

        // Availability counts.
        $in_stock_query = $this->base_product_query();
        $this->apply_context_filters( $in_stock_query, $request );
        $this->join_meta_lookup( $in_stock_query );
        $in_stock_count = $in_stock_query->where( "wc_product_meta_lookup.stock_status", "instock" )->count();

        $on_sale_query = $this->base_product_query();
        $this->apply_context_filters( $on_sale_query, $request );
        $this->join_meta_lookup( $on_sale_query );
        $on_sale_count = $on_sale_query->where( "wc_product_meta_lookup.onsale", 1 )->count();

        $dto->set_availability( ["inStockCount" => (int) $in_stock_count, "onSaleCount" => (int) $on_sale_count] );

        // Attribute facets: each taxonomy's own option counts are computed with that
        // taxonomy's own selection excluded from the context, so users can see what
        // adding another option within the same facet would do (OR within a facet).
        $dto->set_attributes( $this->build_attribute_facets( $request ) );

        return $dto;
    }

    /**
     * A fresh, unfiltered query scoped to published products — the common starting
     * point every product/filter query builds on.
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
     * The taxonomies WooCommerce has registered as product attributes, memoized
     * for the lifetime of this repository instance (a single filters request can
     * otherwise call `wc_get_attribute_taxonomy_names()` a dozen+ times).
     *
     * @return string[]
     */
    private function get_attribute_taxonomies(): array {
        if ( $this->attribute_taxonomies === null ) {
            $this->attribute_taxonomies = wc_get_attribute_taxonomy_names();
        }
        return $this->attribute_taxonomies;
    }

    /**
     * Apply the shared set of context filters (category, search, price, availability,
     * rating, attributes) to a product query. Returns whether the query now needs the
     * `wc_product_meta_lookup` join (already applied here when true).
     *
     * @param mixed $query A Post query builder instance.
     * @param Request $request The REST request instance.
     * @param string[] $exclude_attribute_taxonomies Attribute taxonomies to skip (used to compute that facet's own option counts without self-filtering).
     * @return bool
     */
    private function apply_context_filters( $query, Request $request, array $exclude_attribute_taxonomies = [] ): bool {
        $category_id = $request->get_param( "categoryId" );
        $search      = $request->get_param( "search" );
        $price_min   = $request->get_param( "price_min" );
        $price_max   = $request->get_param( "price_max" );
        $on_sale     = $request->get_param( "on_sale" );
        $in_stock    = $request->get_param( "in_stock" );
        $rating_min  = $request->get_param( "rating_min" );
        $attributes  = $request->get_param( "attributes" );

        if ( ! empty( $category_id ) ) {
            $query->where_has(
                'terms', function( $q ) use ( $category_id ) {
                    $q->where( 'taxonomy', 'product_cat' )
                        ->where( 'term_id', (int) $category_id );
                }
            );
        }

        if ( ! empty( $search ) ) {
            global $wpdb;
            $like = $wpdb->esc_like( $search );
            $query->where( "post_title", "like", "%$like%" );
        }

        if ( is_array( $attributes ) ) {
            $attribute_taxonomies = $this->get_attribute_taxonomies();

            foreach ( $attributes as $taxonomy => $values ) {
                $taxonomy = sanitize_key( (string) $taxonomy );

                if ( in_array( $taxonomy, $exclude_attribute_taxonomies, true ) ) {
                    continue;
                }

                // Only ever trust taxonomies WooCommerce itself registered as product attributes.
                if ( ! in_array( $taxonomy, $attribute_taxonomies, true ) ) {
                    continue;
                }

                $slugs = array_values( array_filter( array_map( 'sanitize_title', (array) $values ) ) );
                if ( empty( $slugs ) ) {
                    continue;
                }

                $term_ids = get_terms(
                    [
                        'taxonomy'   => $taxonomy,
                        'slug'       => $slugs,
                        'fields'     => 'ids',
                        'hide_empty' => false,
                    ]
                );

                if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
                    // The requested option(s) don't exist — this can never match anything.
                    $query->where( 'posts.ID', '=', 0 );
                    continue;
                }

                $query->where_has(
                    'terms', function( $q ) use ( $taxonomy, $term_ids ) {
                        $q->where( 'taxonomy', $taxonomy )
                            ->where_in( 'term_id', $term_ids );
                    }
                );
            }
        }

        $needs_lookup = $price_min !== null || $price_max !== null || ! empty( $on_sale ) || ! empty( $in_stock ) || $rating_min !== null;

        if ( $needs_lookup ) {
            $this->join_meta_lookup( $query );

            if ( $price_min !== null && $price_min !== '' ) {
                $query->where( 'wc_product_meta_lookup.max_price', '>=', (float) $price_min );
            }
            if ( $price_max !== null && $price_max !== '' ) {
                $query->where( 'wc_product_meta_lookup.min_price', '<=', (float) $price_max );
            }
            if ( ! empty( $on_sale ) ) {
                $query->where( 'wc_product_meta_lookup.onsale', '=', 1 );
            }
            if ( ! empty( $in_stock ) ) {
                $query->where( 'wc_product_meta_lookup.stock_status', '=', 'instock' );
            }
            if ( $rating_min !== null && $rating_min !== '' ) {
                $query->where( 'wc_product_meta_lookup.average_rating', '>=', (float) $rating_min );
            }
        }

        return $needs_lookup;
    }

    /**
     * Left-join `wc_product_meta_lookup` (WooCommerce's indexed, variation-aware price
     * /stock/rating table) onto the product query, if it isn't already joined.
     *
     * @param mixed $query A Post query builder instance.
     * @return void
     */
    private function join_meta_lookup( $query ): void {
        foreach ( $query->joins as $join ) {
            if ( $join->as === 'wc_product_meta_lookup' ) {
                return;
            }
        }

        $query->left_join( 'wc_product_meta_lookup', 'posts.ID', '=', 'wc_product_meta_lookup.product_id' );
    }

    /**
     * Resolve a client-facing sort token into [column, direction, requiresLookupJoin].
     *
     * @param string $sort
     * @return array{0: string, 1: string, 2: bool}
     */
    private function resolve_sort( string $sort ): array {
        switch ( $sort ) {
            case 'newest':
                return ['post_date', 'desc', false];
            case 'oldest':
                return ['post_date', 'asc', false];
            case 'price_low':
                return ['wc_product_meta_lookup.min_price', 'asc', true];
            case 'price_high':
                return ['wc_product_meta_lookup.min_price', 'desc', true];
            case 'rating':
                return ['wc_product_meta_lookup.average_rating', 'desc', true];
            case 'popularity':
                return ['wc_product_meta_lookup.total_sales', 'desc', true];
            case 'name_az':
                return ['post_title', 'asc', false];
            case 'name_za':
                return ['post_title', 'desc', false];
            case 'relevance':
            default:
                return ['post_date', 'desc', false];
        }
    }

    /**
     * Build the attribute facet list: one entry per registered WooCommerce product
     * attribute taxonomy that has at least one option in the current context.
     *
     * @param Request $request The REST request instance.
     * @return AttributeFacetDTO[]
     */
    private function build_attribute_facets( Request $request ): array {
        $facets = [];

        foreach ( $this->get_attribute_taxonomies() as $taxonomy ) {
            $query = $this->base_product_query();
            // Exclude this taxonomy's own selection so its counts reflect "if I also picked this".
            $this->apply_context_filters( $query, $request, [$taxonomy] );

            $query->join( 'term_relationships', 'posts.ID', '=', 'term_relationships.object_id' )
                ->join( 'term_taxonomy', 'term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id' )
                ->join( 'terms', 'term_taxonomy.term_id', '=', 'terms.term_id' )
                ->where( 'term_taxonomy.taxonomy', $taxonomy )
                ->select( ['terms.slug as value', 'terms.name as label', 'COUNT(DISTINCT posts.ID) as product_count'] )
                ->group_by( ['terms.term_id', 'terms.slug', 'terms.name'] );

            $rows = $query->get();
            if ( empty( $rows ) ) {
                continue;
            }

            $options = [];
            foreach ( $rows as $row ) {
                $option = new AttributeFacetOptionDTO();
                $option->set_value( (string) $row->value )
                    ->set_label( (string) $row->label )
                    ->set_count( (int) $row->product_count );
                $options[] = $option;
            }

            $facet = new AttributeFacetDTO();
            $facet->set_taxonomy( $taxonomy )
                ->set_label( wc_attribute_label( $taxonomy ) )
                ->set_options( $options );
            $facets[] = $facet;
        }

        return $facets;
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

        return $this->map_post_to_product_dto( $post, $fields );
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
        if ( in_array( "url", $fields ) ) {
            $dto->set_url( get_permalink( $post->ID ) );
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
            $dto->set_date_created( $this->format_date( $product->get_date_created() ) );
        }

        if ( in_array( "date_updated", $fields ) ) {
            $dto->set_date_updated( $this->format_date( $product->get_date_modified() ) );
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
