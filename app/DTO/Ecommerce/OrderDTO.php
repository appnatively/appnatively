<?php
/**
 * OrderDTO class
 *
 * @package Crafium\AppNatively\App\DTO\Ecommerce
 */

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

/**
 * Class OrderDTO
 *
 * Data Transfer Object for orders.
 */
class OrderDTO extends DTO {
    /**
     * Order ID.
     *
     * @var string
     */
    public $id;

    /**
     * Order name (e.g. #1001).
     *
     * @var string
     */
    public $name;

    /**
     * Processed date.
     *
     * @var string
     */
    public $processed_at;

    /**
     * Financial status.
     *
     * @var string
     */
    public $financial_status;

    /**
     * Fulfillment status.
     *
     * @var string
     */
    public $fulfillment_status;

    /**
     * Total price details.
     *
     * @var array
     */
    public $total_price;

    /**
     * Line items.
     *
     * @var OrderItemDTO[]
     */

    /**
     * Line items.
     *
     * @var OrderItemDTO[]
     */
    public $line_items = [];

    /**
     * Subtotal price details.
     *
     * @var array
     */
    public $subtotal_price;

    /**
     * Total tax details.
     *
     * @var array
     */
    public $total_tax;

    /**
     * Total shipping price details.
     *
     * @var array
     */
    public $total_shipping_price;

    /**
     * Shipping address.
     *
     * @var array
     */
    public $shipping;

    /**
     * Total discount details.
     *
     * @var array
     */
    public $total_discount;

    /**
     * Payment method title.
     *
     * @var string
     */
    public $payment_method;

    /**
     * Discount code used.
     *
     * @var string
     */
    public $discount_code;

    /**
     * OrderDTO constructor.
     *
     * @param array $data Order data.
     */
    public function __construct( array $data ) {
        $this->id                   = (string) ( $data['id'] ?? '' );
        $this->name                 = $data['name'] ?? '';
        $this->processed_at         = $data['processedAt'] ?? '';
        $this->financial_status     = $data['financialStatus'] ?? '';
        $this->fulfillment_status   = $data['fulfillmentStatus'] ?? '';
        $this->total_price          = $data['totalPrice'] ?? [ 'amount' => '0', 'currencyCode' => 'USD' ];
        $this->subtotal_price       = $data['subtotalPrice'] ?? null;
        $this->total_tax            = $data['totalTax'] ?? null;
        $this->total_shipping_price = $data['totalShippingPrice'] ?? null;
        $this->shipping             = $data['shipping'] ?? null;
        $this->total_discount       = $data['totalDiscount'] ?? null;
        $this->payment_method       = $data['paymentMethod'] ?? '';
        $this->discount_code        = $data['discountCode'] ?? '';


        if ( isset( $data['lineItems'] ) && is_array( $data['lineItems'] ) ) {
            foreach ( $data['lineItems'] as $item ) {
                if ( $item instanceof OrderItemDTO ) {
                    $this->line_items[] = $item;
                } else {
                    $this->line_items[] = new OrderItemDTO( $item );
                }
            }
        }
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'processedAt'        => $this->processed_at,
            'financialStatus'    => $this->financial_status,
            'fulfillmentStatus'  => $this->fulfillment_status,
            'totalPrice'         => $this->total_price,
            'subtotalPrice'      => $this->subtotal_price,
            'totalTax'           => $this->total_tax,
            'totalShippingPrice' => $this->total_shipping_price,
            'shipping'           => $this->shipping,
            'totalDiscount'      => $this->total_discount,
            'paymentMethod'      => $this->payment_method,
            'discountCode'       => $this->discount_code,
            'lineItems'          => array_map(
                function( $item ) {
                    return $item->to_array();
                }, $this->line_items
            ),
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array {
        return $this->to_array();
    }
}
