<?php

namespace Crafium\AppNatively\App\Integrations;

defined( "ABSPATH" ) || exit;

use WP_REST_Request;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\App\DTO\Ecommerce\CartDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderPaginatorDTO;
use Crafium\AppNatively\App\Integrations\FluentCart\CartManager;
use Crafium\AppNatively\App\Integrations\FluentCart\OrderRepository;
use Crafium\AppNatively\App\Integrations\FluentCart\ProductRepository;
use FluentCart\Api\CurrencySettings;

class FluentCart extends Provider {
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
     * Boot the provider and register hooks.
     *
     * @return void
     */
    public function boot(): void {
        add_filter( "craf_appna_ecommerce_fluent-cart_products", [ $this, "products" ], 10, 3 );
        add_filter( "craf_appna_ecommerce_fluent-cart_product", [ $this, "product" ], 10, 3 );
        add_filter( "craf_appna_ecommerce_fluent-cart_categories", [ $this, "categories" ], 10, 3 );
        add_filter( "craf_appna_ecommerce_fluent-cart_category", [ $this, "category" ], 10, 3 );

        // Cart filters
        add_filter( "craf_appna_ecommerce_fluent-cart_cart_get", [ $this, "cart_get" ], 10, 2 );
        add_filter( "craf_appna_ecommerce_fluent-cart_cart_add", [ $this, "cart_add" ], 10, 2 );
        add_filter( "craf_appna_ecommerce_fluent-cart_cart_update", [ $this, "cart_update" ], 10, 2 );
        add_filter( "craf_appna_ecommerce_fluent-cart_cart_remove", [ $this, "cart_remove" ], 10, 2 );
        add_filter( "craf_appna_ecommerce_fluent-cart_cart_clear", [ $this, "cart_clear" ], 10, 2 );
        add_filter( "craf_appna_ecommerce_fluent-cart_orders_get", [ $this, "orders_get" ], 10, 2 );
        add_filter( "craf_appna_ecommerce_fluent-cart_order_get", [ $this, "order_get" ], 10, 3 );
        add_filter( "craf_appna_shop_data", [ $this, "shop_data" ] );
    }

    /**
     * Shop-wide data (currency).
     *
     * @return array
     */
    public function shop_data(): array {
        return [
            "currency" => (string) CurrencySettings::get( "currency" ),
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
        return $this->product_repository->products( $data, $request, $fields );
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
        return $this->product_repository->product( $data, $request, $fields );
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
        return $this->product_repository->categories( $data, $request, $fields );
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
        return $this->product_repository->category( $data, $request, $fields );
    }
}
