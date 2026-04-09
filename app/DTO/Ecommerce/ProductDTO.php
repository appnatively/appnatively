<?php

namespace AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\DTO;

class ProductDTO extends DTO {
    private int $id;

    private string $name;

    private string $slug;

    private string $type;

    private string $status;

    private string $description;

    private string $short_description;

    private string $sku;

    private string $price;

    private string $compare_at_price;

    private string $currency;

    private bool $on_sale;

    private string $inventory_status;

    private bool $manage_stock = false;

    private int $stock_quantity = 0;

    private float $weight = 0.0;

    private ?ProductDimensionDTO $dimensions = null;

    private string $permalink;

    private string $brand = "";

    private array $tags = [];

    /**
     * @var ProductImageDTO[]
     */
    private array $images = [];

    /**
     * @var CategoryDTO[]
     */
    private array $categories = [];

    /**
     * @var ProductVariantDTO[]
     */
    private array $variants = [];

    /**
     * @var ProductOptionDTO[]
     */
    private array $options = [];

    private string $date_created;

    private string $date_updated;

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
     * Get the value of slug.
     */
    public function get_slug(): string {
        return $this->slug;
    }

    /**
     * Set the value of slug.
     */
    public function set_slug( string $slug ): self {
        $this->slug = $slug;
        return $this;
    }

    /**
     * Get the value of type.
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Set the value of type.
     */
    public function set_type( string $type ): self {
        $this->type = $type;
        return $this;
    }

    /**
     * Get the value of status.
     */
    public function get_status(): string {
        return $this->status;
    }

    /**
     * Set the value of status.
     */
    public function set_status( string $status ): self {
        $this->status = $status;
        return $this;
    }

    /**
     * Get the value of description.
     */
    public function get_description(): string {
        return $this->description;
    }

    /**
     * Set the value of description.
     */
    public function set_description( string $description ): self {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the value of short_description.
     */
    public function get_short_description(): string {
        return $this->short_description;
    }

    /**
     * Set the value of short_description.
     */
    public function set_short_description( string $short_description ): self {
        $this->short_description = $short_description;
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
     * Get the value of on_sale.
     */
    public function is_on_sale(): bool {
        return $this->on_sale;
    }

    /**
     * Set the value of on_sale.
     */
    public function set_on_sale( bool $on_sale ): self {
        $this->on_sale = $on_sale;
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
    public function get_dimensions(): ?ProductDimensionDTO {
        return $this->dimensions;
    }

    /**
     * Set the value of dimensions.
     */
    public function set_dimensions( ?ProductDimensionDTO $dimensions ): self {
        $this->dimensions = $dimensions;
        return $this;
    }

    /**
     * Get the value of permalink.
     */
    public function get_permalink(): string {
        return $this->permalink;
    }

    /**
     * Set the value of permalink.
     */
    public function set_permalink( string $permalink ): self {
        $this->permalink = $permalink;
        return $this;
    }

    /**
     * Get the value of brand.
     */
    public function get_brand(): string {
        return $this->brand;
    }

    /**
     * Set the value of brand.
     */
    public function set_brand( string $brand ): self {
        $this->brand = $brand;
        return $this;
    }

    /**
     * Get the value of tags.
     */
    public function get_tags(): array {
        return $this->tags;
    }

    /**
     * Set the value of tags.
     */
    public function set_tags( array $tags ): self {
        $this->tags = $tags;
        return $this;
    }

    /**
     * Get the value of images.
     *
     * @return ProductImageDTO[]
     */
    public function get_images(): array {
        return $this->images;
    }

    /**
     * Set the value of images.
     *
     * @param ProductImageDTO[] $images
     */
    public function set_images( array $images ): self {
        $this->images = $images;
        return $this;
    }

    /**
     * Get the value of categories.
     *
     * @return CategoryDTO[]
     */
    public function get_categories(): array {
        return $this->categories;
    }

    /**
     * Set the value of categories.
     *
     * @param CategoryDTO[] $categories
     */
    public function set_categories( array $categories ): self {
        $this->categories = $categories;
        return $this;
    }

    /**
     * Get the value of variants.
     *
     * @return ProductVariantDTO[]
     */
    public function get_variants(): array {
        return $this->variants;
    }

    /**
     * Set the value of variants.
     *
     * @param ProductVariantDTO[] $variants
     */
    public function set_variants( array $variants ): self {
        $this->variants = $variants;
        return $this;
    }

    /**
     * Get the value of options.
     *
     * @return ProductOptionDTO[]
     */
    public function get_options(): array {
        return $this->options;
    }

    /**
     * Set the value of options.
     *
     * @param ProductOptionDTO[] $options
     */
    public function set_options( array $options ): self {
        $this->options = $options;
        return $this;
    }

    /**
     * Get the value of date_created.
     */
    public function get_date_created(): string {
        return $this->date_created;
    }

    /**
     * Set the value of date_created.
     */
    public function set_date_created( string $date_created ): self {
        $this->date_created = $date_created;
        return $this;
    }

    /**
     * Get the value of date_updated.
     */
    public function get_date_updated(): string {
        return $this->date_updated;
    }

    /**
     * Set the value of date_updated.
     */
    public function set_date_updated( string $date_updated ): self {
        $this->date_updated = $date_updated;
        return $this;
    }
}