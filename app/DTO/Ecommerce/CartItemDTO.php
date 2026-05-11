<?php

namespace AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\DTO;

class CartItemDTO extends DTO {
    private string $key;

    private int $product_id;

    private int $variation_id = 0;

    private int $quantity;

    private string $name;

    private string $price;

    private string $subtotal;

    private string $total;

    private ?ProductImageDTO $image = null;

    private array $variation = [];

    /**
     * Get the value of key.
     */
    public function get_key(): string {
        return $this->key;
    }

    /**
     * Set the value of key.
     */
    public function set_key( string $key ): self {
        $this->key = $key;
        return $this;
    }

    /**
     * Get the value of product_id.
     */
    public function get_product_id(): int {
        return $this->product_id;
    }

    /**
     * Set the value of product_id.
     */
    public function set_product_id( int $product_id ): self {
        $this->product_id = $product_id;
        return $this;
    }

    /**
     * Get the value of variation_id.
     */
    public function get_variation_id(): int {
        return $this->variation_id;
    }

    /**
     * Set the value of variation_id.
     */
    public function set_variation_id( int $variation_id ): self {
        $this->variation_id = $variation_id;
        return $this;
    }

    /**
     * Get the value of quantity.
     */
    public function get_quantity(): int {
        return $this->quantity;
    }

    /**
     * Set the value of quantity.
     */
    public function set_quantity( int $quantity ): self {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * Get the value of name.
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Set the value of name.
     */
    public function set_name( string $name ): self {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the value of price.
     */
    public function get_price(): string {
        return $this->price;
    }

    /**
     * Set the value of price.
     */
    public function set_price( string $price ): self {
        $this->price = $price;
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

    /**
     * Get the value of image.
     */
    public function get_image(): ?ProductImageDTO {
        return $this->image;
    }

    /**
     * Set the value of image.
     */
    public function set_image( ?ProductImageDTO $image ): self {
        $this->image = $image;
        return $this;
    }

    /**
     * Get the value of variation.
     */
    public function get_variation(): array {
        return $this->variation;
    }

    /**
     * Set the value of variation.
     */
    public function set_variation( array $variation ): self {
        $this->variation = $variation;
        return $this;
    }
}
