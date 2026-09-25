<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

/**
 * One filterable dimension of the current product context. `id` names what it
 * filters and is what the client keys its selection by:
 *
 * - `price`, `rating`: ranges (`min` / `max`)
 * - `availability`: choice of `in_stock` / `on_sale`
 * - `terms:<taxonomy>`: product_cat, product_tag or any product taxonomy
 * - `attribute:<pa_*>`: a WooCommerce attribute taxonomy
 * - `meta:<key>`: an allow-listed custom field (choice, or range for numbers)
 *
 * `options`, `min` and `max` are only serialized when set.
 */
class ProductFacetDTO extends DTO {
    public const KIND_CHOICE = "choice";
    public const KIND_RANGE  = "range";
    public const KIND_TOGGLE = "toggle";

    private string $id;

    private string $label;

    private string $kind;

    /**
     * @var ProductFacetOptionDTO[]
     */
    private array $options;

    private float $min;

    private float $max;

    public function get_id(): string {
        return $this->id;
    }

    public function set_id( string $id ): self {
        $this->id = $id;
        return $this;
    }

    public function get_label(): string {
        return $this->label;
    }

    public function set_label( string $label ): self {
        $this->label = $label;
        return $this;
    }

    public function get_kind(): string {
        return $this->kind;
    }

    public function set_kind( string $kind ): self {
        $this->kind = $kind;
        return $this;
    }

    /**
     * @return ProductFacetOptionDTO[]
     */
    public function get_options(): array {
        return $this->options;
    }

    /**
     * @param ProductFacetOptionDTO[] $options
     */
    public function set_options( array $options ): self {
        $this->options = $options;
        return $this;
    }

    public function get_min(): float {
        return $this->min;
    }

    public function set_min( float $min ): self {
        $this->min = $min;
        return $this;
    }

    public function get_max(): float {
        return $this->max;
    }

    public function set_max( float $max ): self {
        $this->max = $max;
        return $this;
    }
}
