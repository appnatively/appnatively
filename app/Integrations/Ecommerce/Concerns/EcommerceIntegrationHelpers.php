<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\Concerns;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

trait EcommerceIntegrationHelpers {
    /**
     * Resolve the WP user id a request's Bearer token belongs to, without
     * changing the current user.
     *
     * Delegates to the Auth service so there is exactly one place that knows
     * how a token is formatted and where its hash is stored.
     *
     * @param Request $request The REST request instance.
     * @return int|null
     */
    private function resolve_user_id_from_bearer_token( Request $request ): ?int {
        return Auth::resolve( $request );
    }

    /**
     * Authenticate the mobile-app user from a Bearer token, if no WP login
     * cookie is already present. No-ops if a current user is already set.
     *
     * @param Request $request The REST request instance.
     * @return void
     */
    private function authenticate_from_bearer_token( Request $request ): void {
        Auth::authenticate( $request );
    }

    /**
     * Resolve a client-supplied cart/checkout id from the X-Cart-Id header
     * or the cartId param, treating the literal string "cart" as unset.
     *
     * @param Request $request The REST request instance.
     * @return string|null
     */
    private function resolve_cart_id_param( Request $request ): ?string {
        $cart_id = $request->get_header( 'X-Cart-Id' ) ?: $request->get_param( 'cartId' );
        return ( $cart_id && is_string( $cart_id ) && $cart_id !== 'cart' ) ? $cart_id : null;
    }

    /**
     * Format a raw minor-unit (cents) amount as a plain decimal string.
     *
     * @param mixed $cents Raw amount in the currency's minor unit.
     * @return string
     */
    private function format_amount( $cents ): string {
        return number_format( ( (int) $cents ) / 100, 2, '.', '' );
    }

    /**
     * Normalize a relation value that may come back as a plain array, a
     * ->data collection wrapper, a single object, or empty.
     *
     * @param mixed $value The relation value.
     * @return array
     */
    private function to_list( $value ): array {
        if ( empty( $value ) ) {
            return [];
        }
        if ( is_array( $value ) ) {
            return $value;
        }
        if ( isset( $value->data ) && is_array( $value->data ) ) {
            return $value->data;
        }
        return [ $value ];
    }

    /**
     * Format a date-like value (Carbon-like object or plain string) as ISO 8601.
     *
     * @param mixed $date
     * @return string
     */
    private function format_date( $date ): string {
        if ( empty( $date ) ) {
            return '';
        }
        if ( is_object( $date ) && method_exists( $date, 'format' ) ) {
            return $date->format( 'c' );
        }
        return (string) $date;
    }

    /**
     * Reverse-lookup the WP-mirrored sc_product post id for a real SureCart
     * product id, so an OrderItemDTO/CartItemDTO's product_id is a usable
     * local reference.
     *
     * @param string $sc_id The SureCart product id.
     * @return int
     */
    private function resolve_post_id_for_sc_id( string $sc_id ): int {
        if ( ! $sc_id ) {
            return 0;
        }

        $posts = get_posts(
            [
                'post_type'      => 'sc_product',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_key'       => 'sc_id',
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                'meta_value'     => $sc_id,
            ]
        );

        return (int) ( $posts[0] ?? 0 );
    }
}
