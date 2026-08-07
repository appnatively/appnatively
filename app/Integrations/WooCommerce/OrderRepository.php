<?php

namespace Crafium\AppNatively\App\Integrations\WooCommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\OrderDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderItemDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderPaginatorDTO;
use Crafium\AppNatively\App\Integrations\Concerns\EcommerceIntegrationHelpers;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

class OrderRepository {
    use EcommerceIntegrationHelpers;

    /**
     * Resolve a line item's product/variant id split: variations report their
     * parent as the "product" and themselves as the "variant".
     *
     * @param \WC_Product|null $product The line item's resolved product.
     * @return array{0: ?int, 1: ?int} [product_id, variant_id]
     */
    private function resolve_item_product_and_variant_id( ?\WC_Product $product ): array {
        if ( ! $product ) {
            return [ null, null ];
        }
        if ( $product->is_type( 'variation' ) ) {
            return [ $product->get_parent_id(), $product->get_id() ];
        }
        return [ $product->get_id(), null ];
    }

    /**
     * Resolve a line item's thumbnail URL, if any.
     *
     * @param \WC_Product|null $product The line item's resolved product.
     * @return string|null
     */
    private function resolve_item_image_url( ?\WC_Product $product ): ?string {
        $image_id = $product ? $product->get_image_id() : null;
        return $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : null;
    }

    /**
     * Build the {amount, currencyCode} shape used throughout OrderDTO/OrderItemDTO.
     *
     * @param string $amount   The decimal amount.
     * @param string $currency The currency code.
     * @return array
     */
    private function money( string $amount, string $currency ): array {
        return [ 'amount' => $amount, 'currencyCode' => $currency ];
    }

    /**
     * Get orders list.
     */
    public function orders_get( ?OrderPaginatorDTO $order_paginator, Request $request ): OrderPaginatorDTO {
        $this->authenticate_from_bearer_token( $request );
        $user_id = get_current_user_id();

        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 20;

        if ( ! $user_id ) {
            return new OrderPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $status = wc_get_order_statuses();
        unset( $status['wc-checkout-draft'] );
        
        $paginator = wc_get_orders(
            [
                'customer' => $user_id,
                'limit'    => $per_page,
                'page'     => $page,
                'status'   => array_keys( $status ),
                'paginate' => true,
            ] 
        );

        $order_dtos = [];
        foreach ( $paginator->orders as $wc_order ) {
            $line_items = [];
            foreach ( $wc_order->get_items() as $item_id => $item ) {
                $product = $item->get_product();
                [ $item_product_id, $item_variant_id ] = $this->resolve_item_product_and_variant_id( $product );
                $image_url = $this->resolve_item_image_url( $product );

                $line_items[] = new OrderItemDTO(
                    [
                        'id'           => $product ? $product->get_id() : null,
                        'productId'    => $item_product_id,
                        'variantId'    => $item_variant_id,
                        'title'        => $item->get_name(),
                        'quantity'     => $item->get_quantity(),
                        'price'        => $this->money( (string) $wc_order->get_item_total( $item, false, true ), $wc_order->get_currency() ),
                        'variantTitle' => $product && $product->is_type( 'variation' ) ? $product->get_name() : null,
                        'image'        => $image_url ? [ 'url' => $image_url ] : null,
                    ]
                );
            }

            $order_dtos[] = new OrderDTO(
                [
                    'id'                => (string) $wc_order->get_id(),
                    'name'              => '#' . $wc_order->get_order_number(),
                    'processedAt'       => $this->format_date( $wc_order->get_date_created() ),
                    'financialStatus'   => $wc_order->get_status(),
                    'fulfillmentStatus' => $wc_order->get_status(), // @TODO: Map to more granular status
                    'totalPrice'        => $this->money( (string) $wc_order->get_total(), $wc_order->get_currency() ),
                    'lineItems'         => $line_items,
                ]
            );
        }

        return new OrderPaginatorDTO(
            $page,
            $per_page,
            $paginator->total,
            $paginator->max_num_pages,
            $order_dtos
        );
    }

    /**
     * Get order details.
     */
    public function order_get( ?OrderDTO $order_dto, $id, Request $request ): ?OrderDTO {
        $this->authenticate_from_bearer_token( $request );
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return null;
        }

        $wc_order = wc_get_order( $id );

        if ( ! $wc_order || $wc_order->get_customer_id() !== $user_id ) {
            return null;
        }

        $line_items = [];

        foreach ( $wc_order->get_items() as $item_id => $item ) {
            /**
             * @var \WC_Order_Item_Product $item
             */
            $product   = $item->get_product();
            $image_url = $this->resolve_item_image_url( $product );

            $variant_title = null;
            $title         = $item->get_name();

            if ( $product && $product->is_type( 'variation' ) ) {
                $variant_title = wc_get_formatted_variation( $product, true );
                $variant_title = trim( str_replace( [ '(', ')' ], '', $variant_title ) );

                // Fallback to item meta if standard variation formatter is empty
                if ( empty( $variant_title ) ) {
                    $formatted_meta = [];
                    foreach ( $item->get_formatted_meta_data( '_' ) as $meta ) {
                        $formatted_meta[] = $meta->display_key . ': ' . $meta->display_value;
                    }
                    $variant_title = implode( ', ', $formatted_meta );
                }

                $parent_id = $product->get_parent_id();
                if ( $parent_id ) {
                    $title = get_the_title( $parent_id );
                }
            }

            [ $item_product_id, $item_variant_id ] = $this->resolve_item_product_and_variant_id( $product );

            $line_items[] = new OrderItemDTO(
                [
                    'id'           => $product ? $product->get_id() : null,
                    'productId'    => $item_product_id,
                    'variantId'    => $item_variant_id,
                    'title'        => $title,
                    'quantity'     => $item->get_quantity(),
                    'price'        => $this->money( (string) $wc_order->get_item_total( $item, false, true ), $wc_order->get_currency() ),
                    'variantTitle' => $variant_title,
                    'image'        => $image_url ? [ 'url' => $image_url ] : null,
                ]
            );
        }

        $shipping = $wc_order->get_address( 'shipping' );

        return new OrderDTO(
            [
                'id'                 => (string) $wc_order->get_id(),
                'name'               => (string) '#' . $wc_order->get_order_number(),
                'processedAt'        => $this->format_date( $wc_order->get_date_created() ),
                'financialStatus'    => $wc_order->get_status(),
                'fulfillmentStatus'  => $wc_order->get_status(),
                'totalPrice'         => $this->money( (string) $wc_order->get_total(), $wc_order->get_currency() ),
                'subtotalPrice'      => $this->money( (string) $wc_order->get_subtotal(), $wc_order->get_currency() ),
                'totalTax'           => $this->money( (string) $wc_order->get_total_tax(), $wc_order->get_currency() ),
                'totalShippingPrice' => $this->money( (string) $wc_order->get_shipping_total(), $wc_order->get_currency() ),
                'totalDiscount'      => $this->money( (string) $wc_order->get_total_discount(), $wc_order->get_currency() ),
                'paymentMethod'      => $wc_order->get_payment_method_title(),
                'discountCode'       => implode( ', ', $wc_order->get_coupon_codes() ),
                'shipping'           => [
                    'firstName' => $shipping['first_name'],
                    'lastName'  => $shipping['last_name'],
                    'address1'  => $shipping['address_1'],
                    'address2'  => $shipping['address_2'],
                    'city'      => $shipping['city'],
                    'province'  => $shipping['state'],
                    'zip'       => $shipping['postcode'],
                    'country'   => $shipping['country'],
                ],
                'lineItems'          => $line_items,
            ] 
        );
    }
}
