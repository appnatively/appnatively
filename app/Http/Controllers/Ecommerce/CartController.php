<?php

namespace AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\Ecommerce\CartDTO;
use AppNatively\App\Http\Controllers\Controller;
use AppNatively\WpMVC\Exceptions\Exception;
use AppNatively\WpMVC\Routing\Response;
use AppNatively\WpMVC\RequestValidator\Request;

class CartController extends Controller {
    /**
     * Display the current cart.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function index( Request $request ): array {
        $request->validate(
            [
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "appnatively_ecommerce_{$integration}_cart_get", null, $request );

        if ( ! $cart instanceof CartDTO ) {
            throw new Exception( esc_html__( "Cart not found or integration missing", "appnatively" ) );
        }

        return Response::send( ["data" => $cart] );
    }

    /**
     * Add an item to the cart.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function add( Request $request ): array {
        $request->validate(
            [
                "items"       => "required|array",
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "appnatively_ecommerce_{$integration}_cart_add", null, $request );

        if ( ! $cart instanceof CartDTO ) {
            throw new Exception( esc_html__( "Failed to add item to cart", "appnatively" ) );
        }

        return Response::send( ["data" => $cart] );
    }

    /**
     * Update an item in the cart.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function update( Request $request ): array {
        $request->validate(
            [
                "items"       => "required|array",
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "appnatively_ecommerce_{$integration}_cart_update", null, $request );

        if ( ! $cart instanceof CartDTO ) {
            throw new Exception( esc_html__( "Failed to update cart item", "appnatively" ) );
        }

        return Response::send( ["data" => $cart] );
    }

    /**
     * Remove an item from the cart.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function remove( Request $request ): array {
        $request->validate(
            [
                "itemIds"     => "required|array",
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "appnatively_ecommerce_{$integration}_cart_remove", null, $request );

        if ( ! $cart instanceof CartDTO ) {
            throw new Exception( esc_html__( "Failed to remove cart item", "appnatively" ) );
        }

        return Response::send( ["data" => $cart] );
    }

    /**
     * Clear the cart.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function clear( Request $request ): array {
        $request->validate(
            [
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "appnatively_ecommerce_{$integration}_cart_clear", null, $request );

        if ( ! $cart instanceof CartDTO ) {
            throw new Exception( esc_html__( "Failed to clear cart", "appnatively" ) );
        }

        return Response::send( ["data" => $cart] );
    }
}
