<?php
/**
 * OrderItemDTO class
 *
 * @package AppNatively\App\DTO\Ecommerce
 */

namespace AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

/**
 * Class OrderItemDTO
 *
 * Data Transfer Object for order items.
 */
class OrderItemDTO {
    /**
     * Item title.
     *
     * @var string
     */
    public $title;

    /**
     * Item quantity.
     *
     * @var int
     */
    public $quantity;

    /**
     * Item price details.
     *
     * @var array
     */
    public $price;

    /**
     * Variant title.
     *
     * @var string|null
     */
    public $variantTitle;

    /**
     * Item image details.
     *
     * @var array|null
     */
    public $image;

    /**
     * OrderItemDTO constructor.
     *
     * @param array $data Item data.
     */
    public function __construct( array $data ) {
        $this->title        = $data['title'] ?? '';
        $this->quantity     = (int) ( $data['quantity'] ?? 1 );
        $this->price        = $data['price'] ?? [ 'amount' => '0', 'currencyCode' => 'USD' ];
        $this->variantTitle = $data['variantTitle'] ?? null;
        $this->image        = $data['image'] ?? null;
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'title'        => $this->title,
            'quantity'     => $this->quantity,
            'price'        => $this->price,
            'variantTitle' => $this->variantTitle,
            'image'        => $this->image,
        ];
    }
}
