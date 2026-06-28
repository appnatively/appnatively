<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class ProductVariantDTO extends DTO {
    private int $id;

    private string $sku;

    private string $name;

    private string $price;

    private string $compare_at_price;

    private float $weight = 0.0;

    private ProductDimensionDTO $dimensions;

    private bool $manage_stock = false;

    private int $stock_quantity = 0;

    private string $inventory_status;

    private array $attributes;

    private ?ProductImageDTO $image = null;

    /**
     * Get the value of id.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Set the value of id.
     */
    public function set_id( int $id ): self {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the value of sku.
     */
    public function get_sku(): string {
        return $this->sku;
    }

    /**
     * Set the value of sku.
     */
    public function set_sku( string $sku ): self {
        $this->sku = $sku;
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
     * Get the value of compare_at_price.
     */
    public function get_compare_at_price(): string {
        return $this->compare_at_price;
    }

    /**
     * Set the value of compare_at_price.
     */
    public function set_compare_at_price( string $compare_at_price ): self {
        $this->compare_at_price = $compare_at_price;
        return $this;
    }

    /**
     * Get the value of weight.
     */
    public function get_weight(): float {
        return $this->weight;
    }

    /**
     * Set the value of weight.
     */
    public function set_weight( float $weight ): self {
        $this->weight = $weight;
        return $this;
    }

    /**
     * Get the value of dimensions.
     */
    public function get_dimensions(): ProductDimensionDTO {
        return $this->dimensions;
    }

    /**
     * Set the value of dimensions.
     */
    public function set_dimensions( ProductDimensionDTO $dimensions ): self {
        $this->dimensions = $dimensions;
        return $this;
    }

    /**
     * Get the value of manage_stock.
     */
    public function is_manage_stock(): bool {
        return $this->manage_stock;
    }

    /**
     * Set the value of manage_stock.
     */
    public function set_manage_stock( bool $manage_stock ): self {
        $this->manage_stock = $manage_stock;
        return $this;
    }

    /**
     * Get the value of stock_quantity.
     */
    public function get_stock_quantity(): int {
        return $this->stock_quantity;
    }

    /**
     * Set the value of stock_quantity.
     */
    public function set_stock_quantity( int $stock_quantity ): self {
        $this->stock_quantity = $stock_quantity;
        return $this;
    }

    /**
     * Get the value of inventory_status.
     */
    public function get_inventory_status(): string {
        return $this->inventory_status;
    }

    /**
     * Set the value of inventory_status.
     */
    public function set_inventory_status( string $inventory_status ): self {
        $this->inventory_status = $inventory_status;
        return $this;
    }

    /**
     * Get the value of attributes.
     */
    public function get_attributes(): array {
        return $this->attributes;
    }

    /**
     * Set the value of attributes.
     */
    public function set_attributes( array $attributes ): self {
        $this->attributes = $attributes;
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
}
