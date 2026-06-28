<?php

namespace Crafium\AppNatively\App\Integrations;

defined( "ABSPATH" ) || exit;

use WP_REST_Request;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductDimensionDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductImageDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductVariantDTO;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use FluentCart\App\Helpers\Helper;

// FluentCart Classes
use FluentCart\App\Models\Product;
use FluentCart\App\Services\Filter\ProductFilter;
use FluentCart\Framework\Http\Request\Request;

class FluentCart extends Provider {
    /**
     * Boot the provider and register hooks.
     *
     * @return void
     */
    public function boot(): void {
        add_filter( "craf_appna_ecommerce_fluent-cart_products", [ $this, "products" ], 10, 3 );
        add_filter( "craf_appna_ecommerce_fluent-cart_product", [ $this, "product" ], 10, 3 );
        add_filter( "craf_appna_ecommerce_fluent-cart_categories", [ $this, "categories" ], 10, 3 );
        add_filter( "craf_appna_ecommerce_fluent-cart_category", [ $this, "category" ], 10, 3 );
    }

    /**
     * Bridge WP_REST_Request to FluentCart Request.
     *
     * @param WP_REST_Request $request
     * @return Request
     */
    private function bridge_request( WP_REST_Request $request ): Request {
        $params = $request->get_params();
        $sort   = $request->get_param( 'sort' );

        if ( $sort ) {
            if ( str_starts_with( $sort, '-' ) ) {
                $params['sort_by']   = ltrim( $sort, '-' );
                $params['sort_type'] = 'desc';
            } else {
                $params['sort_by']   = $sort;
                $params['sort_type'] = 'asc';
            }
        }

        return new Request( 
            \FluentCart\App\App::getInstance(), 
            $params, 
            $request->get_body_params() 
        );
    }

    /**
     * Get products paginator.
     *
     * @param mixed           $data    The current data.
     * @param WP_REST_Request $request The request object.
     * @param array           $fields  The verified fields.
     *
     * @return ProductPaginatorDTO|null
     */
    public function products( $data, WP_REST_Request $request, array $fields ): ?ProductPaginatorDTO {
        $fc_request = $this->bridge_request( $request );
        
        // Use native filter to handle searching and pagination
        $paginator = ProductFilter::fromRequest( $fc_request )->paginate( 
            $request->get_param( 'per_page' ) ?: 10 
        );

        $products = [];
        foreach ( $paginator->getCollection() as $product ) {
            $products[] = $this->map_to_product_dto( $product, $fields );
        }

        return new ProductPaginatorDTO(
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
            $paginator->lastPage(),
            $products
        );
    }

    /**
     * Get single product.
     *
     * @param mixed           $data    The current data.
     * @param WP_REST_Request $request The request object.
     * @param array           $fields  The verified fields.
     *
     * @return ProductDTO|null
     */
    public function product( $data, WP_REST_Request $request, array $fields ): ?ProductDTO {
        $id = (int) $request->get_param( "id" );

        if ( ! $id ) {
            return null;
        }

        // Use native model with eager loading
        $product = Product::with( [ 'detail', 'variants' ] )->find( $id );

        if ( ! $product || $product->post_status !== 'publish' ) {
            throw new Exception( esc_html__( "Product not found.", "appnatively" ), 404 );
        }

        return $this->map_to_product_dto( $product, $fields );
    }

    /**
     * Get categories paginator.
     *
     * @param mixed           $data    The current data.
     * @param WP_REST_Request $request The request object.
     * @param array           $fields  The verified fields.
     *
     * @return CategoryPaginatorDTO|null
     */
    public function categories( $data, WP_REST_Request $request, array $fields ): ?CategoryPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $search   = sanitize_text_field( $request->get_param( "search" ) );

        $args = [
            'taxonomy'   => 'product-categories',
            'hide_empty' => false,
            'number'     => $per_page,
            'offset'     => ( $page - 1 ) * $per_page,
            'search'     => $search,
        ];

        $terms            = get_terms( $args );
        $total_categories = wp_count_terms( 'product-categories' ); //TODO: need to add search

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return new CategoryPaginatorDTO( $page, $per_page, 0, 0, [] );
        }

        $categories = [];
        foreach ( $terms as $term ) {
            $categories[] = $this->map_to_category_dto( $term, $fields );
        }

        return new CategoryPaginatorDTO(
            $page,
            $per_page,
            (int) $total_categories,
            ceil( (int) $total_categories / $per_page ),
            $categories
        );
    }

    /**
     * Get single category.
     *
     * @param mixed           $data    The current data.
     * @param WP_REST_Request $request The request object.
     * @param array           $fields  The verified fields.
     *
     * @return CategoryDTO|null
     */
    public function category( $data, WP_REST_Request $request, array $fields ): ?CategoryDTO {
        $id = (int) $request->get_param( "id" );

        if ( ! $id ) {
            return null;
        }

        $term = get_term( $id, 'product-categories' );

        if ( ! $term || is_wp_error( $term ) ) {
            throw new Exception( esc_html__( "Category not found.", "appnatively" ), 404 );
        }

        return $this->map_to_category_dto( $term, $fields );
    }

    /**
     * Map FluentCart Product model to ProductDTO.
     *
     * @param Product $product The FluentCart product model.
     * @param array $fields The requested fields.
     * @return ProductDTO
     */
    private function map_to_product_dto( Product $product, array $fields ): ProductDTO {
        $dto    = new ProductDTO();
        $detail = $product->detail;

        if ( in_array( "id", $fields ) ) {
            $dto->set_id( $product->ID );
        }

        if ( in_array( "name", $fields ) ) {
            $dto->set_name( $product->post_title );
        }

        if ( in_array( "slug", $fields ) ) {
            $dto->set_slug( $product->post_name );
        }

        if ( in_array( "description", $fields ) ) {
            $dto->set_description( $product->post_content );
        }

        if ( in_array( "short_description", $fields ) ) {
            $dto->set_short_description( $product->post_excerpt );
        }

        if ( in_array( "status", $fields ) ) {
            $dto->set_status( $product->post_status );
        }

        if ( in_array( "permalink", $fields ) ) {
            $dto->set_permalink( (string) $product->view_url );
        }

        // Financials
        $dto->set_currency( (string) Helper::shopConfig( "currency" ) );

        if ( $detail ) {
            if ( in_array( "type", $fields ) ) {
                $dto->set_type( $detail->variation_type );
            }

            if ( in_array( "price", $fields ) ) {
                $dto->set_price( (string) $detail->min_price );
            }

            if ( in_array( "compare_at_price", $fields ) ) {
                $dto->set_compare_at_price( (string) $detail->max_price );
            }

            if ( in_array( "on_sale", $fields ) ) {
                $dto->set_on_sale( $detail->min_price < $detail->max_price );
            }

            if ( in_array( "inventory_status", $fields ) ) {
                $dto->set_inventory_status( $detail->stock_availability ? "in_stock" : "out_of_stock" );
            }

            if ( in_array( "manage_stock", $fields ) ) {
                $dto->set_manage_stock( (bool) $detail->manage_stock );
            }

            if ( in_array( "stock_quantity", $fields ) ) {
                $dto->set_stock_quantity( (int) $detail->stock_availability );
            }

            // Logistics Mapping
            $other_info = (array) $detail->other_info;
            if ( in_array( "weight", $fields ) ) {
                $dto->set_weight( (float) ( $other_info["weight"] ?? 0.0 ) );
            }

            if ( in_array( "dimensions", $fields ) ) {
                $dim_dto = new ProductDimensionDTO();
                $dim_dto->set_length( (float) ( $other_info["length"] ?? 0.0 ) )
                    ->set_width( (float) ( $other_info["width"] ?? 0.0 ) )
                    ->set_height( (float) ( $other_info["height"] ?? 0.0 ) )
                    ->set_unit( (string) ( $other_info["dimension_unit"] ?? "cm" ) );
                $dto->set_dimensions( $dim_dto );
            }
        }

        // Audit
        if ( in_array( "date_created", $fields ) ) {
            $dto->set_date_created( (string) $product->post_date_gmt );
        }
        if ( in_array( "date_updated", $fields ) ) {
            $dto->set_date_updated( (string) $product->post_modified_gmt );
        }

        // Handle relations using native methods
        if ( in_array( "images", $fields ) ) {
            $images = [];
            foreach ( $product->images() as $image ) {
                $img_dto = new ProductImageDTO();
                $img_dto->set_id( (int) ( $image["attachment_id"] ?? 0 ) )
                    ->set_src( (string) ( $image["url"] ?? "" ) )
                    ->set_alt( (string) ( $image["alt"] ?? "" ) )
                    ->set_title( (string) ( $image["product_title"] ?? "" ) );
                $images[] = $img_dto;
            }
            $dto->set_images( $images );
        }

        if ( in_array( "categories", $fields ) ) {
            $categories = [];
            foreach ( $product->getCategories() as $category ) {
                $cat_dto = new CategoryDTO();
                $cat_dto->set_id( (int) $category->term_id )
                    ->set_name( (string) $category->name )
                    ->set_slug( (string) $category->slug );
                
                // Handle category image if available
                $image_id = get_term_meta( $category->term_id, "thumbnail_id", true );
                if ( $image_id ) {
                    $cat_img = new ProductImageDTO();
                    $cat_img->set_id( (int) $image_id )
                        ->set_src( (string) wp_get_attachment_url( $image_id ) );
                    $cat_dto->set_image( $cat_img );
                }
                
                $categories[] = $cat_dto;
            }
            $dto->set_categories( $categories );
        }

        // Variants mapping
        if ( in_array( "variants", $fields ) && $product->variations ) {
            $variants = [];
            foreach ( $product->variations as $variation ) {
                $var_dto = new ProductVariantDTO();
                $var_dto->set_id( (int) $variation->id )
                    ->set_sku( (string) $variation->sku )
                    ->set_name( (string) $variation->variation_title )
                    ->set_price( (string) $variation->item_price )
                    ->set_compare_at_price( (string) $variation->compare_price )
                    ->set_inventory_status( (string) $variation->stock_status )
                    ->set_manage_stock( (bool) $variation->manage_stock )
                    ->set_stock_quantity( (int) $variation->available );
                
                // Attributes mapping
                $attributes = [];
                if ( ! empty( $variation->variation_identifier ) ) {
                    $attributes["selection"] = $variation->variation_identifier;
                }
                $var_dto->set_attributes( $attributes );

                // Variation Logistics
                $v_other_info = (array) $variation->other_info;
                $var_dto->set_weight( (float) ( $v_other_info["weight"] ?? 0.0 ) );

                $v_dim_dto = new ProductDimensionDTO();
                $v_dim_dto->set_length( (float) ( $v_other_info["length"] ?? 0.0 ) )
                    ->set_width( (float) ( $v_other_info["width"] ?? 0.0 ) )
                    ->set_height( (float) ( $v_other_info["height"] ?? 0.0 ) )
                    ->set_unit( (string) ( $v_other_info["dimension_unit"] ?? "cm" ) );
                $var_dto->set_dimensions( $v_dim_dto );

                $variants[] = $var_dto;
            }
            $dto->set_variants( $variants );
        }

        return $dto;
    }

    /**
     * Map WP_Term to CategoryDTO.
     *
     * @param \WP_Term $term   The term object.
     * @param array    $fields The verified fields.
     *
     * @return CategoryDTO
     */
    private function map_to_category_dto( \WP_Term $term, array $fields ): CategoryDTO {
        $dto = new CategoryDTO();

        $mapping = [
            "id"          => (int) $term->term_id,
            "name"        => (string) $term->name,
            "slug"        => (string) $term->slug,
            "description" => (string) $term->description,
            "parent"      => (int) $term->parent,
            "count"       => (int) $term->count,
        ];

        foreach ( $fields as $field ) {
            if ( isset( $mapping[ $field ] ) ) {
                $method = "set_$field";
                $dto->$method( $mapping[ $field ] );
            }
        }

        return $dto;
    }
}
