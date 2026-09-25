<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\SureCart;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\CategoryDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFilterSourceDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFiltersDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductImageDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductVariantDTO;
use Crafium\AppNatively\App\Integrations\Ecommerce\Catalog\ProductQuery;
use Crafium\AppNatively\App\Integrations\Ecommerce\Concerns\EcommerceIntegrationHelpers;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use SureCart\Models\Product;

class ProductRepository {
    use EcommerceIntegrationHelpers;

    /**
     * Relations to expand on the live single-product fetch.
     *
     * @var string[]
     */
    private const PRODUCT_EXPAND = [ 'prices', 'variants', 'variant_options', 'product_medias', 'product_media.media' ];

    /** Product lists and filter facets (see ProductQuery). */
    private ProductQuery $query;

    public function __construct() {
        $this->query = new ProductQuery( new SureCartCatalog() );
    }

    /**
     * Get products paginated: the page context, the shopper's filter selection and sort.
     *
     * Reads from the sc_product WP-mirrored posts (fast, gives real integer
     * ids, no per-item network calls) rather than the live SureCart API —
     * see plan for why single-product detail still refreshes from the API.
     */
    public function products( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        $page     = $this->query->paginate( $request );
        $products = [];
        foreach ( $page['ids'] as $id ) {
            $post    = get_post( $id );
            $product = $post ? sc_get_product( $post ) : null;
            if ( $product ) {
                $products[] = $this->map_to_product_dto( $product, $post->ID, $fields );
            }
        }

        return new ProductPaginatorDTO( $page['page'], $page['per_page'], $page['total'], $page['last_page'], $products );
    }

    /**
     * Get published products from the same collection as the requested product.
     */
    public function related_products( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        $product_id = (int) craf_appna_route_param( $request, 'id' );
        $page       = (int) $request->get_param( 'page' ) ?: 1;
        $per_page   = (int) $request->get_param( 'per_page' ) ?: 10;
        $term_ids   = $product_id ? wp_get_post_terms( $product_id, SureCartCatalog::COLLECTION_TAXONOMY, [ 'fields' => 'ids' ] ) : [];

        if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
            return new ProductPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $query = new \WP_Query(
            [
                'post_type'      => 'sc_product',
                'post_status'    => 'publish',
                'has_password'   => false,
                'paged'          => $page,
                'posts_per_page' => $per_page,
                //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- excludes the current product from its related results.
                'post__not_in'   => [ $product_id ],
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering products by collection is the point of this query.
                'tax_query'      => [
                    [
                        'taxonomy' => SureCartCatalog::COLLECTION_TAXONOMY,
                        'field'    => 'term_id',
                        'terms'    => array_map( 'intval', $term_ids ),
                    ],
                ],
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

        $products = [];
        foreach ( $query->posts as $post ) {
            $product = sc_get_product( $post );
            if ( $product ) {
                $products[] = $this->map_to_product_dto( $product, $post->ID, $fields );
            }
        }

        return new ProductPaginatorDTO( $page, $per_page, (int) $query->found_posts, max( 1, (int) $query->max_num_pages ), $products );
    }

    /**
     * Single product. Refreshes from SureCart's live API for freshest
     * pricing/variant/stock, falling back to the mirrored snapshot.
     */
    public function product( ?ProductDTO $product_dto, Request $request, array $fields = [] ): ?ProductDTO {
        $post_id = (int) craf_appna_route_param( $request, 'id' );
        $post    = $post_id ? get_post( $post_id ) : null;

        if ( ! $post || $post->post_type !== 'sc_product' || $post->post_status !== 'publish' || craf_appna_is_post_password_protected( $post ) ) {
            throw new Exception( esc_html__( 'Product not found.', 'appnatively' ), 404 );
        }

        $product = null;
        $sc_id   = get_post_meta( $post_id, 'sc_id', true );

        if ( $sc_id ) {
            $live = Product::with( self::PRODUCT_EXPAND )->find( $sc_id );
            if ( ! is_wp_error( $live ) && $live ) {
                $product = $live;
            }
        }

        if ( ! $product ) {
            $product = sc_get_product( $post );
        }

        if ( ! $product ) {
            throw new Exception( esc_html__( 'Product not found.', 'appnatively' ), 404 );
        }

        return $this->map_to_product_dto( $product, $post_id, $fields );
    }

    /**
     * Resolve device-local wishlist product IDs into full product records
     * (reads from the sc_product WP-mirrored posts, same as products()).
     */
    public function wishlist( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        $ids = (array) $request->get_param( 'ids' );
        $ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

        if ( empty( $ids ) ) {
            return new ProductPaginatorDTO( 1, 0, 0, 1, [] );
        }

        $query = new \WP_Query(
            [
                'post_type'      => 'sc_product',
                'post_status'    => 'publish',
                'has_password'   => false,
                'post__in'       => $ids,
                'posts_per_page' => count( $ids ),
                'orderby'        => 'post__in',
            ]
        );

        $products = [];
        foreach ( $query->posts as $post ) {
            $product = sc_get_product( $post );
            if ( $product ) {
                $products[] = $this->map_to_product_dto( $product, $post->ID, $fields );
            }
        }

        return new ProductPaginatorDTO( 1, count( $products ), count( $products ), 1, $products );
    }

    /**
     * The filters available for the current context, with per-option counts.
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
     * Get categories paginator (SureCart product collections, synced to the
     * sc_collection WP taxonomy).
     */
    public function categories( ?CategoryPaginatorDTO $category_paginator, Request $request, array $fields = [] ): CategoryPaginatorDTO {
        $page     = (int) $request->get_param( 'page' ) ?: 1;
        $per_page = (int) $request->get_param( 'per_page' ) ?: 10;
        $search   = sanitize_text_field( (string) $request->get_param( 'search' ) );

        $terms = get_terms(
            [
                'taxonomy'   => SureCartCatalog::COLLECTION_TAXONOMY,
                'hide_empty' => false,
                'number'     => $per_page,
                'offset'     => ( $page - 1 ) * $per_page,
                'search'     => $search,
            ]
        );

        $total = (int) wp_count_terms( SureCartCatalog::COLLECTION_TAXONOMY );

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return new CategoryPaginatorDTO( $page, $per_page, 0, 0, [] );
        }

        $categories = [];
        foreach ( $terms as $term ) {
            $categories[] = $this->map_to_category_dto( $term );
        }

        return new CategoryPaginatorDTO( $page, $per_page, $total, (int) ceil( $total / $per_page ), $categories );
    }

    /**
     * Single category.
     */
    public function category( ?CategoryDTO $category_dto, Request $request, array $fields = [] ): ?CategoryDTO {
        $id   = (int) craf_appna_route_param( $request, 'id' );
        $term = $id ? get_term( $id, SureCartCatalog::COLLECTION_TAXONOMY ) : null;

        if ( ! $term || is_wp_error( $term ) ) {
            throw new Exception( esc_html__( 'Category not found.', 'appnatively' ), 404 );
        }

        return $this->map_to_category_dto( $term );
    }

    /**
     * Map a SureCart Product (live or mirrored) to a ProductDTO.
     *
     * @param mixed $product The SureCart Product model instance.
     * @param int   $post_id The WP-mirrored sc_product post id, exposed as ProductDTO.id.
     * @param array $fields  The requested fields.
     * @return ProductDTO
     */
    private function map_to_product_dto( $product, int $post_id, array $fields ): ProductDTO {
        $dto = new ProductDTO();

        if ( in_array( 'id', $fields, true ) ) {
            $dto->set_id( $post_id );
        }

        if ( in_array( 'name', $fields, true ) ) {
            $dto->set_name( (string) ( $product->name ?? '' ) );
        }

        if ( in_array( 'slug', $fields, true ) ) {
            $dto->set_slug( (string) get_post_field( 'post_name', $post_id ) );
        }

        if ( in_array( 'description', $fields, true ) ) {
            $dto->set_description( (string) ( $product->description ?? '' ) );
        }

        if ( in_array( 'short_description', $fields, true ) ) {
            $dto->set_short_description( (string) get_post_field( 'post_excerpt', $post_id ) );
        }

        if ( in_array( 'status', $fields, true ) ) {
            $dto->set_status( (string) ( get_post_status( $post_id ) ?: 'publish' ) );
        }

        if ( in_array( 'url', $fields, true ) ) {
            $dto->set_url( (string) ( $product->permalink ?? get_permalink( $post_id ) ?: '' ) );
        }

        $currency = (string) ( \SureCart::account()->currency ?? 'USD' );
        $dto->set_currency( $currency );

        $prices = $this->to_list( $product->prices ?? null );
        $price  = $prices[0] ?? null;

        if ( $price ) {
            $amount         = (int) ( $price->amount ?? 0 );
            $scratch_amount = (int) ( $price->scratch_amount ?? 0 );

            if ( in_array( 'price', $fields, true ) ) {
                $dto->set_price( $this->format_amount( $amount ) );
            }
            if ( in_array( 'compare_at_price', $fields, true ) ) {
                $dto->set_compare_at_price( $this->format_amount( $scratch_amount ) );
            }
            if ( in_array( 'on_sale', $fields, true ) ) {
                $dto->set_on_sale( $scratch_amount > $amount );
            }
        }

        if ( in_array( 'stock_status', $fields, true ) || in_array( 'inventory_status', $fields, true ) ) {
            $in_stock = $product->in_stock ?? true;
            // The app's StockStatus uses WooCommerce's spelling.
            $dto->set_inventory_status( $in_stock ? 'instock' : 'outofstock' );
        }

        if ( in_array( 'images', $fields, true ) ) {
            $images = [];
            foreach ( $this->to_list( $product->product_medias ?? null ) as $media_item ) {
                $url = $media_item->media->url ?? $media_item->url ?? '';
                if ( ! $url ) {
                    continue;
                }
                $img = new ProductImageDTO();
                $img->set_src( (string) $url );
                $images[] = $img;
            }
            $dto->set_images( $images );
        }

        if ( in_array( 'categories', $fields, true ) ) {
            $categories = [];
            $terms      = wp_get_post_terms( $post_id, SureCartCatalog::COLLECTION_TAXONOMY );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $categories[] = $this->map_to_category_dto( $term );
                }
            }
            $dto->set_categories( $categories );
        }

        $variants = $this->to_list( $product->variants ?? null );

        if ( in_array( 'variants', $fields, true ) ) {
            $variant_dtos = [];
            foreach ( $variants as $index => $variant ) {
                $var_dto = new ProductVariantDTO();
                // .id is a position within this product's variants list, not a
                // real SureCart id — SureCart variants have no native WP-integer
                // id, so cart_add resolves the real variant by re-looking up this
                // index against the product (see CartManager::resolve_price_and_variant()).
                $var_dto->set_id( $index )
                    ->set_sku( (string) ( $variant->sku ?? '' ) )
                    ->set_name( (string) ( $variant->name ?? $variant->sku ?? sprintf( 'Option %d', $index + 1 ) ) )
                    ->set_price( $this->format_amount( (int) ( $variant->amount ?? ( $price->amount ?? 0 ) ) ) )
                    ->set_compare_at_price( $this->format_amount( (int) ( $variant->scratch_amount ?? 0 ) ) )
                    ->set_inventory_status( ( $variant->available ?? true ) ? 'instock' : 'outofstock' )
                    ->set_attributes( [] );
                $variant_dtos[] = $var_dto;
            }
            $dto->set_variants( $variant_dtos );
        }

        if ( in_array( 'type', $fields, true ) ) {
            $dto->set_type( ! empty( $variants ) ? 'variable' : 'simple' );
        }

        if ( in_array( 'date_created', $fields, true ) ) {
            $dto->set_date_created( $this->format_date( $product->created_at ?? null ) );
        }
        if ( in_array( 'date_updated', $fields, true ) ) {
            $dto->set_date_updated( $this->format_date( $product->updated_at ?? null ) );
        }

        return $dto;
    }

    /**
     * Map a WP_Term (sc_collection taxonomy) to a CategoryDTO.
     *
     * @param \WP_Term $term The term object.
     * @return CategoryDTO
     */
    private function map_to_category_dto( \WP_Term $term ): CategoryDTO {
        $dto = new CategoryDTO();
        $dto->set_id( (int) $term->term_id )
            ->set_name( (string) $term->name )
            ->set_slug( (string) $term->slug )
            ->set_description( (string) $term->description )
            ->set_parent( (int) $term->parent )
            ->set_count( (int) $term->count );

        $thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
        if ( $thumbnail_id ) {
            $image = new ProductImageDTO();
            $image->set_id( (int) $thumbnail_id )
                ->set_src( (string) wp_get_attachment_url( $thumbnail_id ) );
            $dto->set_image( $image );
        }

        return $dto;
    }
}
