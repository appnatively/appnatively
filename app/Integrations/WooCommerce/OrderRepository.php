<?php

namespace Crafium\AppNatively\App\Integrations\WooCommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\OrderDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderItemDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\OrderPaginatorDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

class OrderRepository {
    /**
     * @var CartManager
     */
    private $cart_manager;

    /**
     * Constructor.
     *
     * @param CartManager $cart_manager
     */
    public function __construct( CartManager $cart_manager ) {
        $this->cart_manager = $cart_manager;
    }

    /**
     * Get orders list.
     */
    public function orders_get( ?OrderPaginatorDTO $order_paginator, Request $request ): OrderPaginatorDTO {
        $this->cart_manager->ensure_cart_loaded( $request );
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
                $product         = $item->get_product();
                $image_id        = $product ? $product->get_image_id() : null;
                $image_url       = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : null;
                $item_product_id = null;
                $item_variant_id = null;
                if ( $product ) {
                    if ( $product->is_type( 'variation' ) ) {
                        $item_product_id = $product->get_parent_id();
                        $item_variant_id = $product->get_id();
                    } else {
                        $item_product_id = $product->get_id();
                    }
                }

                $line_items[] = new OrderItemDTO(
                    [
                        'id'           => $product ? $product->get_id() : null,
                        'productId'    => $item_product_id,
                        'variantId'    => $item_variant_id,
                        'title'        => $item->get_name(),
                        'quantity'     => $item->get_quantity(),
                        'price'        => [
                            'amount'       => (string) $wc_order->get_item_total( $item, false, true ),
                            'currencyCode' => $wc_order->get_currency(),
                        ],
                        'variantTitle' => $product && $product->is_type( 'variation' ) ? $product->get_name() : null,
                        'image'        => $image_url ? [ 'url' => $image_url ] : null,
                    ] 
                );
            }

            $order_dtos[] = new OrderDTO(
                [
                    'id'                => (string) $wc_order->get_id(),
                    'name'              => '#' . $wc_order->get_order_number(),
                    'processedAt'       => $wc_order->get_date_created() ? $wc_order->get_date_created()->format( 'c' ) : '',
                    'financialStatus'   => $wc_order->get_status(),
                    'fulfillmentStatus' => $wc_order->get_status(), // @TODO: Map to more granular status
                    'totalPrice'        => [
                        'amount'       => (string) $wc_order->get_total(),
                        'currencyCode' => $wc_order->get_currency(),
                    ],
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
        $this->cart_manager->ensure_cart_loaded( $request );
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
            $image_id  = $product ? $product->get_image_id() : null;
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : null;

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

            $item_product_id = null;
            $item_variant_id = null;
            if ( $product ) {
                if ( $product->is_type( 'variation' ) ) {
                    $item_product_id = $product->get_parent_id();
                    $item_variant_id = $product->get_id();
                } else {
                    $item_product_id = $product->get_id();
                }
            }

            $line_items[] = new OrderItemDTO(
                [
                    'id'           => $product ? $product->get_id() : null,
                    'productId'    => $item_product_id,
                    'variantId'    => $item_variant_id,
                    'title'        => $title,
                    'quantity'     => $item->get_quantity(),
                    'price'        => [
                        'amount'       => (string) $wc_order->get_item_total( $item, false, true ),
                        'currencyCode' => $wc_order->get_currency(),
                    ],
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
                'processedAt'        => $wc_order->get_date_created() ? $wc_order->get_date_created()->format( 'c' ) : '',
                'financialStatus'    => $wc_order->get_status(),
                'fulfillmentStatus'  => $wc_order->get_status(),
                'totalPrice'         => [
                    'amount'       => (string) $wc_order->get_total(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
                'subtotalPrice'      => [
                    'amount'       => (string) $wc_order->get_subtotal(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
                'totalTax'           => [
                    'amount'       => (string) $wc_order->get_total_tax(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
                'totalShippingPrice' => [
                    'amount'       => (string) $wc_order->get_shipping_total(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
                'totalDiscount'      => [
                    'amount'       => (string) $wc_order->get_total_discount(),
                    'currencyCode' => $wc_order->get_currency(),
                ],
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
