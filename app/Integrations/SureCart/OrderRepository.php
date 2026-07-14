<?php

namespace Crafium\AppNatively\App\Integrations\SureCart;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\OrderDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderItemDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderPaginatorDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use SureCart\Models\Order;
use SureCart\Models\User as SureCartUser;

class OrderRepository {
    /**
     * Relations to expand when fetching orders. Order itself carries no
     * monetary fields or customer reference — both live on its Checkout.
     *
     * @var string[]
     */
    private const ORDER_EXPAND = [ 'checkout', 'checkout.line_items', 'checkout.line_items.price', 'checkout.line_items.product', 'checkout.line_items.variant' ];

    /**
     * Normalize a relation value that may come back as a plain array, a
     * SureCart\Models\Collection (->data), a single object, or empty.
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
     * Format a raw minor-unit (cents) amount as a plain decimal string.
     *
     * @param mixed $cents Raw amount in the currency's minor unit.
     * @return string
     */
    private function format_amount( $cents ): string {
        return number_format( ( (int) $cents ) / 100, 2, '.', '' );
    }

    /**
     * Format a model date attribute (Carbon-like object or plain string) as ISO 8601.
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
     * product id, so OrderItemDTO.product_id is a usable local reference.
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
     * Get orders list for the current logged-in user.
     */
    public function orders_get( ?OrderPaginatorDTO $order_paginator, Request $request ): OrderPaginatorDTO {
        $page     = (int) $request->get_param( 'page' ) ?: 1;
        $per_page = (int) $request->get_param( 'per_page' ) ?: 20;

        $customer_id = $this->current_customer_id();
        if ( ! $customer_id ) {
            return new OrderPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $paginator = Order::where( [ 'customer_ids' => [ $customer_id ] ] )
            ->with( self::ORDER_EXPAND )
            ->paginate( [ 'page' => $page, 'per_page' => $per_page ] );

        if ( is_wp_error( $paginator ) ) {
            return new OrderPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $orders = [];
        foreach ( $this->to_list( $paginator->data ?? null ) as $order ) {
            $orders[] = $this->map_to_order_dto( $order );
        }

        return new OrderPaginatorDTO(
            $page,
            $per_page,
            (int) ( $paginator->pagination->count ?? count( $orders ) ),
            (int) ( method_exists( $paginator, 'totalPages' ) ? $paginator->totalPages() : 1 ),
            $orders
        );
    }

    /**
     * Get order details, scoped to the current logged-in user.
     */
    public function order_get( ?OrderDTO $order_dto, $id, Request $request ): ?OrderDTO {
        $customer_id = $this->current_customer_id();
        if ( ! $customer_id ) {
            return null;
        }

        $order = Order::with( self::ORDER_EXPAND )->find( $id );

        if ( is_wp_error( $order ) || ! $order || empty( $order->checkout ) || (string) $order->checkout->customer !== (string) $customer_id ) {
            return null;
        }

        return $this->map_to_order_dto( $order );
    }

    /**
     * Map a SureCart Order (with its expanded Checkout) to an OrderDTO.
     *
     * @param Order $order The SureCart order model.
     * @return OrderDTO
     */
    private function map_to_order_dto( Order $order ): OrderDTO {
        $checkout = $order->checkout ?? null;
        $currency = (string) ( $checkout->currency ?? ( \SureCart::account()->currency ?? 'USD' ) );

        $line_items = [];
        foreach ( $this->to_list( $checkout->line_items ?? null ) as $item ) {
            $product   = $item->product ?? null;
            $variant   = $item->variant ?? null;
            $image_url = $product->image_url ?? null;

            $line_items[] = new OrderItemDTO(
                [
                    'productId'    => $product ? $this->resolve_post_id_for_sc_id( (string) $product->id ) : null,
                    'title'        => (string) ( $product->name ?? '' ),
                    'quantity'     => (int) ( $item->quantity ?? 1 ),
                    'price'        => [
                        'amount'       => $this->format_amount( $item->price->amount ?? 0 ),
                        'currencyCode' => $currency,
                    ],
                    'variantTitle' => $variant->name ?? null,
                    'image'        => $image_url ? [ 'url' => $image_url ] : null,
                ]
            );
        }

        return new OrderDTO(
            [
                'id'                 => (string) $order->id,
                'name'               => '#' . ( $order->number ?? $order->id ),
                'processedAt'        => $this->format_date( $order->created_at ?? null ),
                'financialStatus'    => (string) ( $order->status ?? '' ),
                'fulfillmentStatus'  => (string) ( $order->fulfillment_status ?? '' ),
                'totalPrice'         => [ 'amount' => $this->format_amount( $checkout->total_amount ?? 0 ), 'currencyCode' => $currency ],
                'subtotalPrice'      => [ 'amount' => $this->format_amount( $checkout->subtotal_amount ?? 0 ), 'currencyCode' => $currency ],
                'totalTax'           => [ 'amount' => $this->format_amount( $checkout->tax_amount ?? 0 ), 'currencyCode' => $currency ],
                'totalShippingPrice' => [ 'amount' => $this->format_amount( $checkout->shipping_amount ?? 0 ), 'currencyCode' => $currency ],
                'totalDiscount'      => [ 'amount' => $this->format_amount( $checkout->discount_amount ?? 0 ), 'currencyCode' => $currency ],
                'paymentMethod'      => '',
                'discountCode'       => '',
                'lineItems'          => $line_items,
            ]
        );
    }
}
