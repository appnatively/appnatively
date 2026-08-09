<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\FluentCart;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\OrderDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderItemDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderPaginatorDTO;
use Crafium\AppNatively\App\Integrations\Ecommerce\Concerns\EcommerceIntegrationHelpers;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use FluentCart\App\Models\Customer;
use FluentCart\App\Models\Order;

class OrderRepository {
    use EcommerceIntegrationHelpers;

    /**
     * Get orders list for the current logged-in user.
     */
    public function orders_get( ?OrderPaginatorDTO $order_paginator, Request $request ): OrderPaginatorDTO {
        $this->authenticate_from_bearer_token( $request );

        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 20;

        $customer = $this->get_current_customer();

        if ( ! $customer ) {
            return new OrderPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $paginator = Order::query()
            ->where( "customer_id", $customer->id )
            ->with( "order_items" )
            ->orderBy( "created_at", "desc" )
            ->paginate( $per_page, [ "*" ], "page", $page );

        $order_dtos = [];
        foreach ( $paginator->getCollection() as $order ) {
            $order_dtos[] = $this->map_to_order_dto( $order );
        }

        return new OrderPaginatorDTO(
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
            $paginator->lastPage(),
            $order_dtos
        );
    }

    /**
     * Get order details, scoped to the current logged-in user.
     */
    public function order_get( ?OrderDTO $order_dto, $id, Request $request ): ?OrderDTO {
        $this->authenticate_from_bearer_token( $request );

        $customer = $this->get_current_customer();

        if ( ! $customer ) {
            return null;
        }

        $order = Order::query()->with( "order_items" )->find( $id );

        if ( ! $order || (int) $order->customer_id !== (int) $customer->id ) {
            return null;
        }

        return $this->map_to_order_dto( $order );
    }

    /**
     * The FluentCart customer record linked to the current WP user, if any.
     *
     * @return Customer|null
     */
    private function get_current_customer(): ?Customer {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return null;
        }

        return Customer::query()->where( "user_id", $user_id )->first();
    }

    /**
     * Map FluentCart Order model to OrderDTO.
     *
     * @param Order $order The FluentCart order model.
     * @return OrderDTO
     */
    private function map_to_order_dto( Order $order ): OrderDTO {
        $line_items = [];

        foreach ( $order->order_items as $item ) {
            $product   = $item->product;
            $image_url = $product ? $product->thumbnail : null;

            $line_items[] = new OrderItemDTO(
                [
                    'id'           => (int) $item->object_id,
                    'productId'    => (int) $item->post_id,
                    'variantId'    => (int) $item->object_id,
                    'title'        => (string) ( $item->post_title ?: $item->title ),
                    'quantity'     => (int) $item->quantity,
                    'price'        => [
                        'amount'       => $this->format_amount( $item->unit_price ),
                        'currencyCode' => (string) $order->currency,
                    ],
                    'variantTitle' => (string) $item->title,
                    'image'        => $image_url ? [ 'url' => $image_url ] : null,
                ]
            );
        }

        return new OrderDTO(
            [
                'id'                 => (string) $order->id,
                'name'               => '#' . ( $order->invoice_no ?: $order->id ),
                'processedAt'        => $this->format_date( $order->created_at ),
                'financialStatus'    => (string) $order->payment_status,
                'fulfillmentStatus'  => (string) $order->status,
                'totalPrice'         => [
                    'amount'       => $this->format_amount( $order->total_amount ),
                    'currencyCode' => (string) $order->currency,
                ],
                'subtotalPrice'      => [
                    'amount'       => $this->format_amount( $order->subtotal ),
                    'currencyCode' => (string) $order->currency,
                ],
                'totalTax'           => [
                    'amount'       => $this->format_amount( $order->tax_total ),
                    'currencyCode' => (string) $order->currency,
                ],
                'totalShippingPrice' => [
                    'amount'       => $this->format_amount( $order->shipping_total ),
                    'currencyCode' => (string) $order->currency,
                ],
                'totalDiscount'      => [
                    'amount'       => $this->format_amount( (int) $order->coupon_discount_total + (int) $order->manual_discount_total ),
                    'currencyCode' => (string) $order->currency,
                ],
                'paymentMethod'      => (string) $order->payment_method_title,
                'discountCode'       => '',
                'lineItems'          => $line_items,
            ]
        );
    }
}
