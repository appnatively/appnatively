<?php
/**
 * OrderDTO class
 *
 * @package AppNatively\App\DTO\Ecommerce
 */

namespace AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

/**
 * Class OrderDTO
 *
 * Data Transfer Object for orders.
 */
class OrderDTO {
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
    public $processedAt;

    /**
     * Financial status.
     *
     * @var string
     */
    public $financialStatus;

    /**
     * Fulfillment status.
     *
     * @var string
     */
    public $fulfillmentStatus;

    /**
     * Total price details.
     *
     * @var array
     */
    public $totalPrice;

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
    public $lineItems = [];

    /**
     * Subtotal price details.
     *
     * @var array
     */
    public $subtotalPrice;

    /**
     * Total tax details.
     *
     * @var array
     */
    public $totalTax;

    /**
     * Total shipping price details.
     *
     * @var array
     */
    public $totalShippingPrice;

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
    public $totalDiscount;

    /**
     * Payment method title.
     *
     * @var string
     */
    public $paymentMethod;

    /**
     * Discount code used.
     *
     * @var string
     */
    public $discountCode;



    /**
     * OrderDTO constructor.
     *
     * @param array $data Order data.
     */
    public function __construct( array $data ) {
        $this->id                = (string) ( $data['id'] ?? '' );
        $this->name              = $data['name'] ?? '';
        $this->processedAt       = $data['processedAt'] ?? '';
        $this->financialStatus   = $data['financialStatus'] ?? '';
        $this->fulfillmentStatus = $data['fulfillmentStatus'] ?? '';
        $this->totalPrice        = $data['totalPrice'] ?? [ 'amount' => '0', 'currencyCode' => 'USD' ];
        $this->subtotalPrice     = $data['subtotalPrice'] ?? null;
        $this->totalTax          = $data['totalTax'] ?? null;
        $this->totalShippingPrice = $data['totalShippingPrice'] ?? null;
        $this->shipping          = $data['shipping'] ?? null;
        $this->totalDiscount     = $data['totalDiscount'] ?? null;
        $this->paymentMethod     = $data['paymentMethod'] ?? '';
        $this->discountCode      = $data['discountCode'] ?? '';


        if ( isset( $data['lineItems'] ) && is_array( $data['lineItems'] ) ) {
            foreach ( $data['lineItems'] as $item ) {
                if ( $item instanceof OrderItemDTO ) {
                    $this->lineItems[] = $item;
                } else {
                    $this->lineItems[] = new OrderItemDTO( $item );
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
            'processedAt'        => $this->processedAt,
            'financialStatus'    => $this->financialStatus,
            'fulfillmentStatus'  => $this->fulfillmentStatus,
            'totalPrice'         => $this->totalPrice,
            'subtotalPrice'      => $this->subtotalPrice,
            'totalTax'           => $this->totalTax,
            'totalShippingPrice' => $this->totalShippingPrice,
            'shipping'           => $this->shipping,
            'totalDiscount'      => $this->totalDiscount,
            'paymentMethod'      => $this->paymentMethod,
            'discountCode'       => $this->discountCode,


            'lineItems'          => array_map(
                function( $item ) {
                    return $item->to_array();
                }, $this->lineItems 
            ),
        ];
    }

}
