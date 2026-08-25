<?php
/**
 * OrderController class
 *
 * @package Crafium\AppNatively\App\Http\Controllers\Ecommerce
 */

namespace Crafium\AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderPaginatorDTO;

/**
 * Class OrderController
 *
 * Handles order-related REST API requests.
 */
class OrderController extends Controller {
    /**
     * Get customer orders.
     *
     * @param Request $request REST request instance.
     * @return array
     * @throws Exception
     */
    public function index( Request $request ): array {
        $request->validate(
            [
                "page"        => "nullable|integer|min:1",
                "per_page"    => "nullable|integer|min:1|max:100",
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );

        // Apply filter to get orders from specific integration (e.g. WooCommerce)
        $order_paginator = apply_filters( "craf_appna_ecommerce_{$integration}_orders_get", null, $request );

        if ( ! $order_paginator instanceof OrderPaginatorDTO ) {
            throw new Exception( esc_html__( "Failed to retrieve orders", "appnatively" ) );
        }

        return Response::send( [ "data" => $order_paginator ] );
    }

    /**
     * Get single order details.
     *
     * @param Request $request REST request instance.
     * @return array
     * @throws Exception
     */
    public function show( Request $request ): array {
        $request->validate(
            [
                "id"          => "required",
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $id          = craf_appna_route_param( $request, "id" );

        // Apply filter to get order from specific integration (e.g. WooCommerce)
        $order = apply_filters( "craf_appna_ecommerce_{$integration}_order_get", null, $id, $request );

        if ( ! $order ) {
            throw new Exception( esc_html__( "Order not found", "appnatively" ) );
        }

        return Response::send( [ "data" => $order ] );
    }
}
