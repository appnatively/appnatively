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
use Crafium\AppNatively\App\Integrations\Ecommerce\SureCart\CartManager;
use Crafium\AppNatively\App\Integrations\Ecommerce\SureCart\ProductRepository;
use Crafium\AppNatively\App\Integrations\Ecommerce\SureCart\OrderRepository;

class SureCart extends Provider {
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
        if ( ! $this->is_loaded() ) {
            return;
        }

        add_filter( "craf_appna_ecommerce_surecart_products", [$this, "products"], 10, 3 );
        add_filter( "craf_appna_ecommerce_surecart_wishlist", [$this, "wishlist"], 10, 3 );
        add_filter( "craf_appna_ecommerce_surecart_products_filters", [$this, "products_filters"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_product", [$this, "product"], 10, 3 );
        add_filter( "craf_appna_ecommerce_surecart_related_products", [$this, "related_products"], 10, 3 );
        add_filter( "craf_appna_ecommerce_surecart_product_reviews", [$this, "product_reviews"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_categories", [$this, "categories"], 10, 3 );
        add_filter( "craf_appna_ecommerce_surecart_category", [$this, "category"], 10, 3 );

        // Cart filters
        add_filter( "craf_appna_ecommerce_surecart_cart_get", [$this, "cart_get"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_cart_add", [$this, "cart_add"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_cart_update", [$this, "cart_update"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_cart_remove", [$this, "cart_remove"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_cart_clear", [$this, "cart_clear"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_cart_discount_apply", [$this, "cart_discount_apply"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_cart_discount_remove", [$this, "cart_discount_remove"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_orders_get", [$this, "orders_get"], 10, 2 );
        add_filter( "craf_appna_ecommerce_surecart_order_get", [$this, "order_get"], 10, 3 );
        add_filter( "craf_appna_shop_data", [$this, "shop_data"] );
    }

    private function is_loaded(): bool {
        return defined( "SURECART_PLUGIN_FILE" ) || class_exists( "SureCart" );
    }

    public function shop_data() {
        return [
            "currency" => (string) ( \SureCart::account()->currency ?? "USD" )
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
        $per_page = (int) ( $request->get_param( 'per_page' ) ?: 10 ); return [ 'current_page' => 1, 'last_page' => 1, 'per_page' => $per_page, 'total' => 0, 'average_rating' => 0, 'review_count' => 0, 'rating_counts' => [], 'items' => [] ]; }

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
     * Available product filters for the current context.
     */
    public function products_filters( ?ProductFiltersDTO $product_filters, Request $request ): ProductFiltersDTO {
        return $this->product_repository->filters( $product_filters, $request );
    }

    /**
     * Resolve wishlist product IDs into full product records.
     */
    public function wishlist( ?ProductPaginatorDTO $product_paginator, Request $request, array $fields = [] ): ProductPaginatorDTO {
        return $this->product_repository->wishlist( $product_paginator, $request, $fields );
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
