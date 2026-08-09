<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\SureCart;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\CartDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CartItemDTO;
use Crafium\AppNatively\App\Integrations\Ecommerce\Concerns\EcommerceIntegrationHelpers;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use SureCart\Models\Checkout;
use SureCart\Models\LineItem;
use SureCart\Models\User as SureCartUser;

class CartManager {
    use EcommerceIntegrationHelpers;

    /**
     * Line items to expand when fetching a checkout, so the mapped CartDTO
     * has product name/image and variant info without extra requests.
     *
     * @var string[]
     */
    private const CHECKOUT_EXPAND = [ 'line_items', 'line_items.price', 'line_items.product', 'line_items.variant' ];

    /**
     * The current user's SureCart customer id, if any.
     *
     * @return string|null
     */
    private function current_customer_id(): ?string {
        if ( ! get_current_user_id() ) {
            return null;
        }

        return SureCartUser::current()->customerId( 'live' ) ?: null;
    }

    /**
     * Whether the given checkout is unclaimed (guest) or belongs to the
     * requesting user. Guards against a leaked/guessed checkout id being used
     * to read or mutate a different customer's cart.
     *
     * @param Checkout|null $checkout
     * @return bool
     */
    private function owns_checkout( ?Checkout $checkout ): bool {
        if ( ! $checkout || empty( $checkout->customer ) ) {
            return true;
        }

        return (string) $checkout->customer === (string) $this->current_customer_id();
    }

    /**
     * Resolve the requesting user's owned checkout (by client-supplied id) along
     * with the set of line-item ids that actually belong to it, throwing a 404
     * if the checkout is missing or not owned. Shared by cart_update()/cart_remove(),
     * which both need this "load + validate ownership + whitelist item ids" sequence.
     *
     * @param Request $request The REST request instance.
     * @return array{0: Checkout, 1: array} [checkout, valid_item_ids]
     */
    private function load_owned_checkout_with_valid_item_ids( Request $request ): array {
        $checkout_id = $this->resolve_cart_id_param( $request );
        if ( ! $checkout_id ) {
            throw new Exception( esc_html__( 'Cart not found.', 'appnatively' ), 404 );
        }

        $checkout = Checkout::with( [ 'line_items' ] )->find( $checkout_id );
        if ( is_wp_error( $checkout ) || ! $checkout || ! $this->owns_checkout( $checkout ) ) {
            throw new Exception( esc_html__( 'Cart not found.', 'appnatively' ), 404 );
        }

        $valid_item_ids = wp_list_pluck( $this->to_list( $checkout->line_items ?? null ), 'id' );

        return [ $checkout, $valid_item_ids ];
    }

    /**
     * Resolve a purchasable price id (and optional variant id) for a product.
     *
     * SureCart separates Price (purchase plan) from Variant (attribute
     * differentiation) — a line item needs both. We default to the product's
     * first active price, matching SureCart's own BuyPageController behavior.
     * $variant_index is a position within the product's variants list (see
     * ProductRepository::map_to_product_dto()) since SureCart variants have no
     * native WP-integer id to round-trip through ProductVariantDTO.id.
     *
     * @param int        $product_post_id The WP-mirrored sc_product post id.
     * @param mixed      $variant_index   Positional index into the product's variants, or null.
     * @return array{0: ?string, 1: ?string} [price_id, variant_id]
     */
    private function resolve_price_and_variant( int $product_post_id, $variant_index ): array {
        $product = sc_get_product( $product_post_id );

        if ( ! $product ) {
            throw new Exception( esc_html__( 'Product not found.', 'appnatively' ), 404 );
        }

        $prices   = $this->to_list( $product->prices ?? null );
        $price_id = $prices[0]->id ?? null;

        $variant_id = null;
        if ( $variant_index !== null && $variant_index !== '' ) {
            $variants   = $this->to_list( $product->variants ?? null );
            $variant_id = $variants[ (int) $variant_index ]->id ?? null;
        }

        return [ $price_id, $variant_id ];
    }

    /**
     * Fetch and map a checkout by id, falling back to an empty cart if it's gone.
     *
     * @param string $checkout_id The SureCart checkout id.
     * @return CartDTO
     */
    private function cart_get_by_id( string $checkout_id ): CartDTO {
        $checkout = Checkout::with( self::CHECKOUT_EXPAND )->find( $checkout_id );

        if ( is_wp_error( $checkout ) || ! $checkout || empty( $checkout->id ) || ! $this->owns_checkout( $checkout ) ) {
            return $this->map_to_cart_dto( null );
        }

        return $this->map_to_cart_dto( $checkout );
    }

    /**
     * Map a SureCart Checkout (or null) to a CartDTO.
     *
     * @param Checkout|null $checkout The checkout, or null for an empty cart.
     * @return CartDTO
     */
    private function map_to_cart_dto( ?Checkout $checkout ): CartDTO {
        $dto      = new CartDTO();
        $currency = (string) ( \SureCart::account()->currency ?? 'USD' );

        if ( ! $checkout || empty( $checkout->id ) ) {
            return $dto->set_id( null )
                ->set_items( [] )
                ->set_subtotal( '0' )
                ->set_total( '0' )
                ->set_currency( $currency )
                ->set_item_count( 0 )
                ->set_checkout_url( esc_url( (string) \SureCart::pages()->url( 'checkout' ) ) );
        }

        $items      = [];
        $item_count = 0;

        foreach ( $this->to_list( $checkout->line_items ?? null ) as $line_item ) {
            $product = $line_item->product ?? null;
            $variant = $line_item->variant ?? null;
            $qty     = (int) ( $line_item->quantity ?? 1 );

            $item_dto = new CartItemDTO();
            $item_dto->set_key( (string) $line_item->id )
                ->set_product_id( $product ? $this->resolve_post_id_for_sc_id( (string) $product->id ) : 0 )
                ->set_variation_id( 0 )
                ->set_quantity( $qty )
                ->set_name( (string) ( $product->name ?? '' ) )
                ->set_price( $this->format_amount( $line_item->price->amount ?? 0 ) )
                ->set_subtotal( $this->format_amount( $line_item->subtotal_amount ?? 0 ) )
                ->set_total( $this->format_amount( $line_item->total_amount ?? 0 ) );

            if ( $variant && ! empty( $variant->name ) ) {
                $item_dto->set_variation( [ 'name' => $variant->name ] );
            }

            $items[]     = $item_dto;
            $item_count += $qty;
        }

        return $dto->set_id( (string) $checkout->id )
            ->set_items( $items )
            ->set_subtotal( $this->format_amount( $checkout->subtotal_amount ?? 0 ) )
            ->set_total( $this->format_amount( $checkout->total_amount ?? 0 ) )
            ->set_currency( (string) ( $checkout->currency ?? $currency ) )
            ->set_item_count( $item_count )
            ->set_checkout_url( esc_url( add_query_arg( 'checkout_id', $checkout->id, (string) \SureCart::pages()->url( 'checkout' ) ) ) );
    }

    /**
     * Get cart details.
     */
    public function cart_get( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->authenticate_from_bearer_token( $request );
        $checkout_id = $this->resolve_cart_id_param( $request );

        return $checkout_id ? $this->cart_get_by_id( $checkout_id ) : $this->map_to_cart_dto( null );
    }

    /**
     * Add item(s) to cart, creating the checkout on the first item if needed.
     */
    public function cart_add( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->authenticate_from_bearer_token( $request );

        $checkout_id = $this->resolve_cart_id_param( $request );
        $checkout    = $checkout_id ? Checkout::find( $checkout_id ) : null;
        if ( is_wp_error( $checkout ) || ( $checkout && ! $this->owns_checkout( $checkout ) ) ) {
            $checkout = null;
        }

        $items = (array) $request->get_param( 'items' );

        foreach ( $items as $item ) {
            $product_post_id = (int) ( $item['productId'] ?? 0 );
            $variant_index   = $item['variantId'] ?? null;
            $quantity        = max( 1, (int) ( $item['quantity'] ?? 1 ) );

            if ( ! $product_post_id ) {
                continue;
            }

            [ $price_id, $variant_id ] = $this->resolve_price_and_variant( $product_post_id, $variant_index );

            if ( ! $price_id ) {
                throw new Exception( esc_html__( 'Product is not purchasable.', 'appnatively' ), 400 );
            }

            $line_item_data = array_filter(
                [
                    'price'    => $price_id,
                    'variant'  => $variant_id,
                    'quantity' => $quantity,
                ]
            );

            if ( ! $checkout ) {
                $checkout = Checkout::create(
                    array_filter(
                        [
                            'line_items' => [ $line_item_data ],
                            'customer'   => $this->current_customer_id(),
                        ]
                    )
                );

                if ( is_wp_error( $checkout ) ) {
                    throw new Exception( $checkout->get_error_message(), 400 );
                }
            } else {
                $line_item_data['checkout'] = $checkout->id;
                $result                     = LineItem::create( $line_item_data );

                if ( is_wp_error( $result ) ) {
                    throw new Exception( $result->get_error_message(), 400 );
                }
            }
        }

        if ( ! $checkout || empty( $checkout->id ) ) {
            return $this->map_to_cart_dto( null );
        }

        return $this->cart_get_by_id( $checkout->id );
    }

    /**
     * Update item quantities.
     */
    public function cart_update( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->authenticate_from_bearer_token( $request );

        [ $checkout, $valid_item_ids ] = $this->load_owned_checkout_with_valid_item_ids( $request );

        $items = (array) $request->get_param( 'items' );
        foreach ( $items as $item ) {
            $item_id  = sanitize_text_field( $item['itemId'] ?? '' );
            $quantity = (int) ( $item['quantity'] ?? 0 );

            if ( ! $item_id || $quantity < 1 || ! in_array( $item_id, $valid_item_ids, true ) ) {
                continue;
            }

            $result = ( new LineItem( [ 'id' => $item_id ] ) )->update( [ 'quantity' => $quantity ] );

            if ( is_wp_error( $result ) ) {
                throw new Exception( $result->get_error_message(), 400 );
            }
        }

        return $this->cart_get_by_id( (string) $checkout->id );
    }

    /**
     * Remove item(s) from cart.
     */
    public function cart_remove( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->authenticate_from_bearer_token( $request );

        [ $checkout, $valid_item_ids ] = $this->load_owned_checkout_with_valid_item_ids( $request );

        $item_ids = (array) $request->get_param( 'itemIds' );
        foreach ( $item_ids as $item_id ) {
            $item_id = sanitize_text_field( $item_id );
            if ( $item_id && in_array( $item_id, $valid_item_ids, true ) ) {
                LineItem::delete( $item_id );
            }
        }

        return $this->cart_get_by_id( (string) $checkout->id );
    }

    /**
     * Clear cart. SureCart has no bulk "empty checkout" call, so remove each
     * line item individually.
     */
    public function cart_clear( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->authenticate_from_bearer_token( $request );

        $checkout_id = $this->resolve_cart_id_param( $request );
        if ( ! $checkout_id ) {
            return $this->map_to_cart_dto( null );
        }

        $checkout = Checkout::with( [ 'line_items' ] )->find( $checkout_id );
        if ( ! is_wp_error( $checkout ) && $checkout && $this->owns_checkout( $checkout ) ) {
            foreach ( $this->to_list( $checkout->line_items ?? null ) as $line_item ) {
                if ( ! empty( $line_item->id ) ) {
                    LineItem::delete( $line_item->id );
                }
            }
        }

        return $this->cart_get_by_id( $checkout_id );
    }
}
