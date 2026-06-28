<?php

namespace Crafium\AppNatively\App\Integrations;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\CartDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CartItemDTO;
use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductDimensionDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductImageDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductVariantDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderPaginatorDTO;
use Crafium\AppNatively\App\Models\Term;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

class Woocommerce extends Provider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {}

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        add_filter( "craf_appna_ecommerce_woocommerce_products", [$this, "products"], 10, 3 );
        add_filter( "craf_appna_ecommerce_woocommerce_product", [$this, "product"], 10, 3 );
        add_filter( "craf_appna_ecommerce_woocommerce_categories", [$this, "categories"], 10, 3 );
        add_filter( "craf_appna_ecommerce_woocommerce_category", [$this, "category"], 10, 3 );

        // Cart filters
        add_filter( "craf_appna_ecommerce_woocommerce_cart_get", [$this, "cart_get"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_add", [$this, "cart_add"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_update", [$this, "cart_update"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_remove", [$this, "cart_remove"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_clear", [$this, "cart_clear"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_orders_get", [$this, "orders_get"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_order_get", [$this, "order_get"], 10, 3 );


        // Autologin handler for web checkout
        add_action( 'init', [$this, 'handle_autologin'] );
    }

    /**
     * Handle autologin from mobile app token.
     *
     * @return void
     */
    public function handle_autologin(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! empty( $_GET['craf_appna_token'] ) && ! is_user_logged_in() ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $token         = sanitize_text_field( wp_unslash( $_GET['craf_appna_token'] ) );
            $hashed_token  = hash( 'sha256', $token );
            $transient_key = 'craf_appna_autologin_' . $hashed_token;

            // One-time-use: read and immediately delete the transient
            $user_id = get_transient( $transient_key );

            if ( $user_id ) {
                delete_transient( $transient_key ); // Invalidate immediately — cannot be replayed
                wp_set_auth_cookie( (int) $user_id );
                wp_safe_redirect( remove_query_arg( 'craf_appna_token' ) );
                exit;
            }
        }
    }

    /**
     * Ensure WooCommerce cart and session are loaded.
     * Also authenticates the user from the Bearer token if present.
     *
     * @param Request|null $request The REST request instance.
     * @return void
     */
    private function ensure_cart_loaded( ?Request $request = null ): void {
        if ( $request && ! get_current_user_id() ) {
            $auth_header = $request->get_header( 'Authorization' );
            if ( $auth_header && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
                $token        = $matches[1];
                $hashed_token = hash( 'sha256', $token );
                $users        = get_users(
                    [
                        //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                        'meta_key'    => 'craf_appna_auth_token',
                        //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                        'meta_value'  => $hashed_token,
                        'number'      => 1,
                        'count_total' => false,
                    ] 
                );

                if ( ! empty( $users ) ) {
                    wp_set_current_user( $users[0]->ID );
                }
            }
        }

        if ( is_null( WC()->cart ) ) {
            wc_load_cart();
        }
        if ( is_null( WC()->session ) ) {
            WC()->session = new \WC_Session_Handler();
            WC()->session->init();
        }
    }

    /**
     * Get cart DTO.
     *
     * @param CartDTO|null $cart_dto The cart DTO.
     * @param Request $request The REST request instance.
     * @return CartDTO
     */
    public function cart_get( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        WC()->cart->calculate_totals();

        $dto          = new CartDTO();
        $checkout_url = wc_get_checkout_url();
        $auth_header  = $request->get_header( 'Authorization' );
        if ( $auth_header && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
            $checkout_url = add_query_arg( 'craf_appna_token', $matches[1], $checkout_url );
        }

        $dto->set_subtotal( (string) WC()->cart->get_subtotal() )
            ->set_total( (string) WC()->cart->get_total( 'edit' ) )
            ->set_currency( get_woocommerce_currency() )
            ->set_item_count( WC()->cart->get_cart_contents_count() )
            ->set_checkout_url( $checkout_url );

        $items = [];
        foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
            $item_dto = new CartItemDTO();
            $product  = $cart_item['data'];
            
            $item_dto->set_key( $key )
                ->set_product_id( $cart_item['product_id'] )
                ->set_variation_id( $cart_item['variation_id'] )
                ->set_quantity( $cart_item['quantity'] )
                ->set_name( $product->get_name() )
                ->set_price( (string) $product->get_price() )
                ->set_subtotal( (string) $cart_item['line_subtotal'] )
                ->set_total( (string) $cart_item['line_total'] );

            $image_id = $product->get_image_id();
            
            // Fallback to parent image if variation has no image
            if ( ! $image_id && $product->is_type( 'variation' ) ) {
                $image_id = get_post_thumbnail_id( $product->get_parent_id() );
            }

            if ( $image_id ) {
                $img_url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
                if ( $img_url ) {
                    $img = new ProductImageDTO();
                    $img->set_id( (int) $image_id )
                        ->set_src( $img_url )
                        ->set_alt( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
                    $item_dto->set_image( $img );
                }
            }

            if ( ! empty( $cart_item['variation'] ) ) {
                $item_dto->set_variation( $cart_item['variation'] );
            }

            $items[] = $item_dto;
        }

        $dto->set_items( $items );

        return $dto;
    }

    /**
     * Add item to cart.
     */
    public function cart_add( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        
        $items = $request->get_param( "items" );
        foreach ( (array) $items as $item ) {
            $product_id   = (int) ( $item['productId'] ?? 0 );
            $variation_id = (int) ( $item['variantId'] ?? 0 );
            $quantity     = (int) ( $item['quantity'] ?? 1 );
            $variations   = (array) ( $item['options'] ?? [] );

            if ( $product_id ) {
                WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
            }
        }

        return $this->cart_get( null, $request );
    }

    /**
     * Update cart item quantity.
     */
    public function cart_update( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        
        $items = $request->get_param( "items" );
        foreach ( (array) $items as $item ) {
            $key      = sanitize_text_field( $item['itemId'] ?? '' );
            $quantity = (int) ( $item['quantity'] ?? 0 );

            if ( $key ) {
                WC()->cart->set_quantity( $key, $quantity );
            }
        }

        return $this->cart_get( null, $request );
    }

    /**
     * Remove cart item.
     */
    public function cart_remove( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        
        $item_ids = $request->get_param( "itemIds" );
        foreach ( (array) $item_ids as $key ) {
            WC()->cart->remove_cart_item( sanitize_text_field( $key ) );
        }

        return $this->cart_get( null, $request );
    }

    /**
     * Clear cart.
     */
    public function cart_clear( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        WC()->cart->empty_cart();

        return $this->cart_get( null, $request );
    }

    /**
     * Get customer orders.
     *
     * @param array $orders Current orders array.
     * @param Request $request REST request instance.
     * @return array
     */

    /**
     * Orders get.
     *
     * @param OrderPaginatorDTO|null $order_paginator The order paginator.
     * @param Request $request The REST request instance.
     * @return OrderPaginatorDTO
     */
    public function orders_get( ?OrderPaginatorDTO $order_paginator, Request $request ): OrderPaginatorDTO {
        $this->ensure_cart_loaded( $request );
        $user_id = get_current_user_id();

        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 20;

        if ( ! $user_id ) {
            return new OrderPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $status = wc_get_order_statuses();
        unset( $status['wc-checkout-draft'] );
        
        $paginator = wc_get_orders(
            [
                'customer' => $user_id,
                'limit'    => $per_page,
                'page'     => $page,
                'status'   => array_keys( $status ),
                'paginate' => true,
            ] 
        );

        $order_dtos = [];
        foreach ( $paginator->orders as $wc_order ) {
            $line_items = [];
            foreach ( $wc_order->get_items() as $item_id => $item ) {
                $product   = $item->get_product();
                $image_id  = $product ? $product->get_image_id() : null;
                $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : null;

                $line_items[] = new \Crafium\AppNatively\App\DTO\Ecommerce\OrderItemDTO(
                    [
                        'title'        => $item->get_name(),
                        'quantity'     => $item->get_quantity(),
                        'price'        => [
                            'amount'       => (string) $wc_order->get_item_total( $item, false, true ),
                            'currencyCode' => $wc_order->get_currency(),
                        ],
                        'variantTitle' => $product && $product->is_type( 'variation' ) ? $product->get_name() : null,
                        'image'        => $image_url ? [ 'url' => $image_url ] : null,
                    ] 
                );
            }

            $order_dtos[] = new \Crafium\AppNatively\App\DTO\Ecommerce\OrderDTO(
                [
                    'id'                => (string) $wc_order->get_id(),
                    'name'              => '#' . $wc_order->get_order_number(),
                    'processedAt'       => $wc_order->get_date_created() ? $wc_order->get_date_created()->format( 'c' ) : '',
                    'financialStatus'   => $wc_order->get_status(),
                    'fulfillmentStatus' => $wc_order->get_status(), // @TODO: Map to more granular status
                    'totalPrice'        => [
                        'amount'       => (string) $wc_order->get_total(),
                        'currencyCode' => $wc_order->get_currency(),
                    ],
                    'lineItems'         => $line_items,
                ] 
            );
        }

        return new OrderPaginatorDTO(
            $page,
            $per_page,
            $paginator->total,
            $paginator->max_num_pages,
            $order_dtos
        );
    }

    /**
     * Get single order details.
     *
     * @param OrderDTO|null $order_dto The order DTO.
     * @param int|string $id The order ID.
     * @param Request $request The REST request instance.
     * @return OrderDTO|null
     */
    public function order_get( ?OrderDTO $order_dto, $id, Request $request ): ?OrderDTO {
        $this->ensure_cart_loaded( $request );
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return null;
        }

        $wc_order = wc_get_order( $id );

        if ( ! $wc_order || $wc_order->get_customer_id() !== $user_id ) {
            return null;
        }

        $line_items = [];

        foreach ( $wc_order->get_items() as $item_id => $item ) {
            /**
             * @var \WC_Order_Item_Product $item
             */
            $product   = $item->get_product();
            $image_id  = $product ? $product->get_image_id() : null;
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : null;

            $variant_title = null;
            $title         = $item->get_name();

            if ( $product && $product->is_type( 'variation' ) ) {
                $variant_title = wc_get_formatted_variation( $product, true );
                $variant_title = trim( str_replace( [ '(', ')' ], '', $variant_title ) );
                
                // Fallback to item meta if standard variation formatter is empty
                if ( empty( $variant_title ) ) {
                    $formatted_meta = [];
                    foreach ( $item->get_formatted_meta_data( '_' ) as $meta ) {
                        $formatted_meta[] = $meta->display_key . ': ' . $meta->display_value;
                    }
                    $variant_title = implode( ', ', $formatted_meta );
                }

                $parent_id = $product->get_parent_id();
                if ( $parent_id ) {
                    $title = get_the_title( $parent_id );
                }
            }

            $line_items[] = new \Crafium\AppNatively\App\DTO\Ecommerce\OrderItemDTO(
                [
                    'title'        => $title,
                    'quantity'     => $item->get_quantity(),
                    'price'        => [
                        'amount'       => (string) $wc_order->get_item_total( $item, false, true ),
                        'currencyCode' => $wc_order->get_currency(),
                    ],
                    'variantTitle' => $variant_title,
                    'image'        => $image_url ? [ 'url' => $image_url ] : null,
                ] 
            );

        }

        $shipping = $wc_order->get_address( 'shipping' );

        return new \Crafium\AppNatively\App\DTO\Ecommerce\OrderDTO(
            [
                'id'                 => (string) $wc_order->get_id(),
                'name'               => (string) '#' . $wc_order->get_order_number(),

                'processedAt'        => $wc_order->get_date_created() ? $wc_order->get_date_created()->format( 'c' ) : '',
                'financialStatus'    => $wc_order->get_status(),
                'fulfillmentStatus'  => $wc_order->get_status(),
                'totalPrice'         => [
                    'amount'       => (string) $wc_order->get_total(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
                'subtotalPrice'      => [
                    'amount'       => (string) $wc_order->get_subtotal(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
                'totalTax'           => [
                    'amount'       => (string) $wc_order->get_total_tax(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
                'totalShippingPrice' => [
                    'amount'       => (string) $wc_order->get_shipping_total(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
                'totalDiscount'      => [
                    'amount'       => (string) $wc_order->get_total_discount(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
                'paymentMethod'      => $wc_order->get_payment_method_title(),
                'discountCode'       => implode( ', ', $wc_order->get_coupon_codes() ),
                'shipping'           => [


                    'firstName' => $shipping['first_name'],
                    'lastName'  => $shipping['last_name'],
                    'address1'  => $shipping['address_1'],
                    'address2'  => $shipping['address_2'],
                    'city'      => $shipping['city'],
                    'province'  => $shipping['state'],
                    'zip'       => $shipping['postcode'],
                    'country'   => $shipping['country'],
                ],
                'lineItems'          => $line_items,

            ] 
        );
    }

    /**
     * Product paginator.
     *
     * @param ProductPaginatorDTO|null $product_paginator The product paginator.
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
            $dto->set_description( $product->get_description() );
        }
        if ( in_array( "short_description", $fields ) ) {
            $dto->set_short_description( $product->get_short_description() );
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
                        ->set_attributes( $variation->get_attributes() );
                    
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

        return $dto;
    }

    /**
     * Category paginator.
     *
     * @param CategoryPaginatorDTO|null $category_paginator The category paginator.
     * @param Request $request The REST request instance.
     * @param array $fields The requested fields.
     * @return CategoryPaginatorDTO
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
     *
     * @param CategoryDTO|null $category_dto The category DTO.
     * @param Request $request The REST request instance.
     * @param array $fields The requested fields.
     * @return CategoryDTO|null
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
     * Get SQL columns from fields for categories.
     *
     * @param array $fields
     * @return array
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
     * Map Term model result to CategoryDTO.
     *
     * @param mixed $term The term result (stdClass or Model).
     * @param array $fields The requested fields.
     * @return CategoryDTO
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
