<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class CartDTO extends DTO {
    private ?string $id = null;

    /**
     * @var CartItemDTO[]
     */
    private array $items = [];

    private string $subtotal = "0";

    private string $total = "0";

    private string $discount_total = "0";

    private string $shipping_total = "0";

    private string $tax_total = "0";

    /** @var CouponDTO[] */
    private array $coupons = [];

    private string $currency;

    private int $item_count = 0;

    private string $checkout_url;

    /**
     * Get the value of items.
     *
     * @return CartItemDTO[]
     */
    public function get_items(): array {
        return $this->items;
    }

    /**
     * Set the value of items.
     *
     * @param CartItemDTO[] $items
     */
    public function set_items( array $items ): self {
        $this->items = $items;
        return $this;
    }

    /**
     * Get the value of subtotal.
     */
    public function get_subtotal(): string {
        return $this->subtotal;
    }

    /**
     * Set the value of subtotal.
     */
    public function set_subtotal( string $subtotal ): self {
        $this->subtotal = $subtotal;
        return $this;
    }

    /**
     * Get the value of total.
     */
    public function get_total(): string {
        return $this->total;
    }

    /**
     * Set the value of total.
     */
    public function set_total( string $total ): self {
        $this->total = $total;
        return $this;
    }

    public function get_discount_total(): string {
        return $this->discount_total;
    }

    public function set_discount_total( string $value ): self {
        $this->discount_total = $value;
        return $this;
    }

    public function get_shipping_total(): string {
        return $this->shipping_total;
    }

    public function set_shipping_total( string $value ): self {
        $this->shipping_total = $value;
        return $this;
    }

    public function get_tax_total(): string {
        return $this->tax_total;
    }

    public function set_tax_total( string $value ): self {
        $this->tax_total = $value;
        return $this;
    }

    /** @return CouponDTO[] */
    public function get_coupons(): array {
        return $this->coupons;
    }

    /** @param CouponDTO[] $coupons */
    public function set_coupons( array $coupons ): self {
        $this->coupons = $coupons; return $this;
    }

    /**
     * Get the value of currency.
     */
    public function get_currency(): string {
        return $this->currency;
    }

    /**
     * Set the value of currency.
     */
    public function set_currency( string $currency ): self {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Get the value of item_count.
     */
    public function get_item_count(): int {
        return $this->item_count;
    }

    /**
     * Set the value of item_count.
     */
    public function set_item_count( int $item_count ): self {
        $this->item_count = $item_count;
        return $this;
    }

    /**
     * Get the value of checkout_url.
     */
    public function get_checkout_url(): string {
        return $this->checkout_url;
    }

    /**
     * Set the value of checkout_url.
     */
    public function set_checkout_url( string $checkout_url ): self {
        $this->checkout_url = $checkout_url;
        return $this;
    }

    /**
     * Get the value of id.
     */
    public function get_id(): ?string {
        return $this->id;
    }

    /**
     * Set the value of id.
     */
    public function set_id( ?string $id ): self {
        $this->id = $id;
        return $this;
    }
}
