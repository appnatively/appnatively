<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\FluentCart;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\CartDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CartItemDTO;
use Crafium\AppNatively\App\Integrations\Ecommerce\Concerns\EcommerceIntegrationHelpers;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use FluentCart\Api\Cookie\Cookie;
use FluentCart\Api\CurrencySettings;
use FluentCart\Api\Resource\FrontendResource\CartResource;
use FluentCart\Api\StoreSettings;
use FluentCart\App\Models\Cart;

class CartManager {
    use EcommerceIntegrationHelpers;

    /**
     * Bridge a guest cart id from the request into FluentCart's own cart cookie
     * (`fct_cart_hash`), since FluentCart resolves the active cart from that
     * cookie or the logged-in user — there is no header/param it reads directly.
     * No-ops once a user is authenticated (own cart is resolved via user id instead).
     *
     * @param Request $request The REST request instance.
     * @return void
     */
    private function bridge_guest_cart_id_cookie( Request $request ): void {
        if ( get_current_user_id() ) {
            return;
        }

        $cart_id = $this->resolve_cart_id_param( $request );
        if ( $cart_id ) {
            $_COOKIE[ Cookie::getCartHashKey() ] = $cart_id;
        }
    }

    /**
     * Establish request context: authenticate from Bearer token if present,
     * then bridge a guest cart id into FluentCart's cookie if still a guest.
     *
     * @param Request $request The REST request instance.
     * @return void
     */
    private function ensure_context( Request $request ): void {
        $this->authenticate_from_bearer_token( $request );
        $this->bridge_guest_cart_id_cookie( $request );
    }

    /**
     * Get (creating if necessary) the current FluentCart cart.
     *
     * @param Request $request The REST request instance.
     * @return Cart
     */
    private function get_cart( Request $request ): Cart {
        $this->ensure_context( $request );
        return CartResource::get( [ 'create' => true ] );
    }

    /**
     * Get cart DTO.
     */
    public function cart_get( ?CartDTO $cart_dto, Request $request ): CartDTO {
        return $this->map_to_cart_dto( $this->get_cart( $request ) );
    }

    /**
     * Add item(s) to cart.
     */
    public function cart_add( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->get_cart( $request );

        $items = (array) $request->get_param( "items" );
        foreach ( $items as $item ) {
            $variation_id = (int) ( $item['variantId'] ?? $item['productId'] ?? 0 );
            $quantity     = (int) ( $item['quantity'] ?? 1 );

            if ( ! $variation_id ) {
                continue;
            }

            $result = CartResource::create( [ 'id' => $variation_id, 'quantity' => $quantity ] );

            if ( is_wp_error( $result ) ) {
                throw new Exception( esc_html( $result->get_error_message() ), 400 );
            }
        }

        return $this->cart_get( null, $request );
    }

    /**
     * Update item quantities. FluentCart carts key items by variation id, so
     * `itemId` here is the same id used as `variantId`/`productId` when adding.
     */
    public function cart_update( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->get_cart( $request );

        $items = (array) $request->get_param( "items" );
        foreach ( $items as $item ) {
            $variation_id = (int) ( $item['itemId'] ?? 0 );
            $quantity     = (int) ( $item['quantity'] ?? 0 );

            if ( ! $variation_id ) {
                continue;
            }

            $result = CartResource::update(
                [
                    'item_id'  => $variation_id,
                    'quantity' => $quantity,
                    'by_input' => true,
                ]
            );

            if ( is_wp_error( $result ) ) {
                throw new Exception( esc_html( $result->get_error_message() ), 400 );
            }
        }

        return $this->cart_get( null, $request );
    }

    /**
     * Remove item(s) from cart.
     */
    public function cart_remove( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $cart = $this->get_cart( $request );

        $item_ids = (array) $request->get_param( "itemIds" );
        foreach ( $item_ids as $item_id ) {
            $cart->removeItem( (int) $item_id );
        }

        return $this->cart_get( null, $request );
    }

    /**
     * Clear cart.
     */
    public function cart_clear( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->get_cart( $request );
        CartResource::resetCartData();

        return $this->cart_get( null, $request );
    }

    /**
     * Map FluentCart Cart model to CartDTO.
     *
     * @param Cart $cart The FluentCart cart model.
     * @return CartDTO
     */
    private function map_to_cart_dto( Cart $cart ): CartDTO {
        $dto       = new CartDTO();
        $cart_data = (array) $cart->cart_data;

        $items = [];
        foreach ( $cart_data as $line ) {
            $item_dto = new CartItemDTO();
            $item_dto->set_key( (string) ( $line['object_id'] ?? '' ) )
                ->set_product_id( (int) ( $line['post_id'] ?? 0 ) )
                ->set_variation_id( (int) ( $line['object_id'] ?? 0 ) )
                ->set_quantity( (int) ( $line['quantity'] ?? 0 ) )
                ->set_name( (string) ( $line['post_title'] ?? $line['title'] ?? '' ) )
                ->set_price( $this->format_amount( $line['unit_price'] ?? $line['price'] ?? 0 ) )
                ->set_subtotal( $this->format_amount( $line['subtotal'] ?? $line['line_total'] ?? 0 ) )
                ->set_total( $this->format_amount( $line['total'] ?? $line['line_total'] ?? 0 ) );

            if ( ! empty( $line['featured_media'] ) ) {
                $img = new ProductImageDTO();
                $img->set_src( (string) $line['featured_media'] );
                $item_dto->set_image( $img );
            }

            $attributes = (array) ( $line['other_info']['item_attributes'] ?? [] );
            if ( $attributes ) {
                $item_dto->set_variation( $attributes );
            }

            $items[] = $item_dto;
        }

        $dto->set_id( (string) $cart->cart_hash )
            ->set_items( $items )
            ->set_subtotal( $this->format_amount( $cart->getItemsSubtotal() ) )
            ->set_total( $this->format_amount( $cart->getEstimatedTotal() ) )
            ->set_currency( (string) CurrencySettings::get( "currency" ) )
            ->set_item_count( (int) array_sum( array_column( $cart_data, 'quantity' ) ) )
            ->set_checkout_url( (string) ( new StoreSettings() )->getCheckoutPage() );

        return $dto;
    }
}
