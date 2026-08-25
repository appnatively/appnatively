<?php

namespace Crafium\AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\CartDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

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
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "craf_appna_ecommerce_{$integration}_cart_get", null, $request );

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
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "craf_appna_ecommerce_{$integration}_cart_add", null, $request );

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
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "craf_appna_ecommerce_{$integration}_cart_update", null, $request );

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
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "craf_appna_ecommerce_{$integration}_cart_remove", null, $request );

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
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $cart        = apply_filters( "craf_appna_ecommerce_{$integration}_cart_clear", null, $request );

        if ( ! $cart instanceof CartDTO ) {
            throw new Exception( esc_html__( "Failed to clear cart", "appnatively" ) );
        }

        return Response::send( ["data" => $cart] );
    }
}
