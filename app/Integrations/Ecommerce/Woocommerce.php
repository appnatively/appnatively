<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\CartDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFiltersDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderPaginatorDTO;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\App\Integrations\Ecommerce\WooCommerce\CartManager;
use Crafium\AppNatively\App\Integrations\Ecommerce\WooCommerce\ProductRepository;
use Crafium\AppNatively\App\Integrations\Ecommerce\WooCommerce\OrderRepository;

class Woocommerce extends Provider {
    /**
     * @var CartManager
     */
    private $cart_manager;

    /**
     * @var ProductRepository
     */
    private $product_repository;

    /**
     * @var OrderRepository
     */
    private $order_repository;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->cart_manager       = new CartManager();
        $this->product_repository = new ProductRepository();
        $this->order_repository   = new OrderRepository();
    }

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
        add_filter( "craf_appna_ecommerce_woocommerce_wishlist", [$this, "wishlist"], 10, 3 );
        add_filter( "craf_appna_ecommerce_woocommerce_products_filters", [$this, "products_filters"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_product", [$this, "product"], 10, 3 );
        add_filter( "craf_appna_ecommerce_woocommerce_related_products", [$this, "related_products"], 10, 3 );
        add_filter( "craf_appna_ecommerce_woocommerce_product_reviews", [$this, "product_reviews"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_categories", [$this, "categories"], 10, 3 );
        add_filter( "craf_appna_ecommerce_woocommerce_category", [$this, "category"], 10, 3 );

        // Cart filters
        add_filter( "craf_appna_ecommerce_woocommerce_cart_get", [$this, "cart_get"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_add", [$this, "cart_add"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_update", [$this, "cart_update"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_remove", [$this, "cart_remove"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_clear", [$this, "cart_clear"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_discount_apply", [$this, "cart_discount_apply"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_cart_discount_remove", [$this, "cart_discount_remove"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_orders_get", [$this, "orders_get"], 10, 2 );
        add_filter( "craf_appna_ecommerce_woocommerce_order_get", [$this, "order_get"], 10, 3 );  
        add_filter( "craf_appna_shop_data", [$this, "shop_data"] );      
    }

    public function shop_data() {
        return [
            "currency" => get_woocommerce_currency()
        ];
    }

    /**
     * Get cart details.
     */
    public function cart_get( ?CartDTO $cart_dto, Request $request ): CartDTO {
        return $this->cart_manager->cart_get( $cart_dto, $request );
    }

    /**
     * Add item to cart.
     */
    public function cart_add( ?CartDTO $cart_dto, Request $request ): CartDTO {
        return $this->cart_manager->cart_add( $cart_dto, $request );
    }

    /**
     * Update item quantity.
     */
    public function cart_update( ?CartDTO $cart_dto, Request $request ): CartDTO {
        return $this->cart_manager->cart_update( $cart_dto, $request );
    }

    /**
     * Remove item.
     */
    public function cart_remove( ?CartDTO $cart_dto, Request $request ): CartDTO {
        return $this->cart_manager->cart_remove( $cart_dto, $request );
    }

    /**
     * Clear cart.
     */
    public function cart_clear( ?CartDTO $cart_dto, Request $request ): CartDTO {
        return $this->cart_manager->cart_clear( $cart_dto, $request );
    }

    public function cart_discount_apply( ?CartDTO $cart_dto, Request $request ): CartDTO {
        return $this->cart_manager->cart_discount_apply( $cart_dto, $request ); }

    public function cart_discount_remove( ?CartDTO $cart_dto, Request $request ): CartDTO {
        return $this->cart_manager->cart_discount_remove( $cart_dto, $request ); }

    public function product_reviews( ?array $reviews, Request $request ): array {
        $product_id = (int) craf_appna_route_param( $request, 'id' );
        $page       = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
        $per_page   = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 10 ) ) );
        $product    = wc_get_product( $product_id );
        if ( ! $product ) {
            return [ 'current_page' => 1, 'last_page' => 1, 'per_page' => $per_page, 'total' => 0, 'average_rating' => 0, 'review_count' => 0, 'rating_counts' => [], 'items' => [] ]; }
        $comments = get_comments( [ 'post_id' => $product_id, 'status' => 'approve', 'type' => 'review', 'number' => $per_page, 'paged' => $page ] );
        $total    = (int) get_comments( [ 'post_id' => $product_id, 'status' => 'approve', 'type' => 'review', 'count' => true ] );
        $items    = array_map( static function ( $comment ) { return [ 'id' => $comment->comment_ID, 'reviewer' => $comment->comment_author, 'review' => $comment->comment_content, 'rating' => (float) get_comment_meta( $comment->comment_ID, 'rating', true ), 'date_created' => $comment->comment_date_gmt ?: $comment->comment_date, 'avatar_url' => get_avatar_url( $comment->comment_author_email ) ]; }, $comments );
        $counts   = [ '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0 ];
        foreach ( $items as $item ) {
            $bucket = (string) round( (float) $item['rating'] ); if ( isset( $counts[ $bucket ] ) ) {
                $counts[ $bucket ]++; } }
        return [ 'current_page' => $page, 'last_page' => max( 1, (int) ceil( $total / $per_page ) ), 'per_page' => $per_page, 'total' => $total, 'average_rating' => (float) $product->get_average_rating(), 'review_count' => (int) $product->get_review_count(), 'rating_counts' => $counts, 'items' => $items ];
    }

    /**
     * Get products.
     */
    public function products( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        return $this->product_repository->products( $product_paginator, $request, $fields );
    }

    /**
     * Single product.
     */
    public function product( ?ProductDTO $product_dto, Request $request, array $fields = [] ): ?ProductDTO {
        return $this->product_repository->product( $product_dto, $request, $fields );
    }

    /**
     * Get products related to the requested product.
     */
    public function related_products( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        return $this->product_repository->related_products( $product_paginator, $request, $fields );
    }

    /**
     * Resolve wishlist product IDs into full product records.
     */
    public function wishlist( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        return $this->product_repository->wishlist( $product_paginator, $request, $fields );
    }

    /**
     * Available product filters for the current context.
     */
    public function products_filters( ?ProductFiltersDTO $product_filters, Request $request ): ProductFiltersDTO {
        return $this->product_repository->filters( $product_filters, $request );
    }

    /**
     * Get categories.
     */
    public function categories( ?CategoryPaginatorDTO $category_paginator, Request $request, array $fields = [] ): CategoryPaginatorDTO {
        return $this->product_repository->categories( $category_paginator, $request, $fields );
    }

    /**
     * Single category.
     */
    public function category( ?CategoryDTO $category_dto, Request $request, array $fields = [] ): ?CategoryDTO {
        return $this->product_repository->category( $category_dto, $request, $fields );
    }

    /**
     * Get orders.
     */
    public function orders_get( ?OrderPaginatorDTO $order_paginator, Request $request ): OrderPaginatorDTO {
        return $this->order_repository->orders_get( $order_paginator, $request );
    }

    /**
     * Get order details.
     */
    public function order_get( ?OrderDTO $order_dto, $id, Request $request ): ?OrderDTO {
        return $this->order_repository->order_get( $order_dto, $id, $request );
    }
}
