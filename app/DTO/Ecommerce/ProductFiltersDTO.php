<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

/**
 * Describes which filters are available for the current product context
 * (category / search / already-applied filters), with per-option counts.
 *
 * This is the "facet descriptor" the filter drawer renders itself from —
 * clients never hardcode filter shapes, they render whatever the backend says
 * is available.
 */
class ProductFiltersDTO extends DTO {
    /**
     * Sort tokens the backend accepts. Single source of truth shared by the
     * controller's request validation and this DTO's own `sort_options` (see
     * ProductRepository::filters()) — the "in:" validation rule and the values
     * reported to the client are the same list by construction, not by convention.
     *
     * @var string[]
     */
    public const SORT_TOKENS = [
        "relevance", "newest", "oldest", "price_low", "price_high",
        "rating", "popularity", "name_az", "name_za",
    ];

    /**
     * Price bounds across the current context, e.g. ["min" => "9.99", "max" => "249.00"].
     * Null when the store has no priced products in context.
     *
     * @var array{min: string, max: string}|null
     */
    private ?array $price = null;

    /**
     * Star-rating ceiling, e.g. ["max" => 5]. Null when no product in context has reviews.
     *
     * @var array{max: int}|null
     */
    private ?array $rating = null;

    /**
     * @var array{inStockCount: int, onSaleCount: int}
     */
    private array $availability;

    /**
     * @var AttributeFacetDTO[]
     */
    private array $attributes = [];

    /**
     * Sort tokens the backend accepts, in the order they should be presented.
     *
     * @var string[]
     */
    private array $sort_options = [];

    /**
     * Get the value of price.
     *
     * @return array{min: string, max: string}|null
     */
    public function get_price(): ?array {
        return $this->price;
    }

    /**
     * Set the value of price.
     *
     * @param array{min: string, max: string}|null $price
     *
     * @return self
     */
    public function set_price( ?array $price ): self {
        $this->price = $price;
        return $this;
    }

    /**
     * Get the value of rating.
     *
     * @return array{max: int}|null
     */
    public function get_rating(): ?array {
        return $this->rating;
    }

    /**
     * Set the value of rating.
     *
     * @param array{max: int}|null $rating
     *
     * @return self
     */
    public function set_rating( ?array $rating ): self {
        $this->rating = $rating;
        return $this;
    }

    /**
     * Get the value of availability.
     *
     * @return array{inStockCount: int, onSaleCount: int}
     */
    public function get_availability(): array {
        return $this->availability;
    }

    /**
     * Set the value of availability.
     *
     * @param array{inStockCount: int, onSaleCount: int} $availability
     *
     * @return self
     */
    public function set_availability( array $availability ): self {
        $this->availability = $availability;
        return $this;
    }

    /**
     * Get the value of attributes.
     *
     * @return AttributeFacetDTO[]
     */
    public function get_attributes(): array {
        return $this->attributes;
    }

    /**
     * Set the value of attributes.
     *
     * @param AttributeFacetDTO[] $attributes
     *
     * @return self
     */
    public function set_attributes( array $attributes ): self {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Get the value of sortOptions.
     *
     * @return string[]
     */
    public function get_sort_options(): array {
        return $this->sort_options;
    }

    /**
     * Set the value of sortOptions.
     *
     * @param string[] $sort_options
     *
     * @return self
     */
    public function set_sort_options( array $sort_options ): self {
        $this->sort_options = $sort_options;
        return $this;
    }
}
