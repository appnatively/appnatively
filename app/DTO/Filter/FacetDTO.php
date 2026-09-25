<?php

namespace Crafium\AppNatively\App\DTO\Filter;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

/**
 * One filterable dimension of the current list context. `id` names what it
 * filters and is what the client keys its selection by (see CatalogQuery):
 *
 * - `category`, `tag`, `location`, `taxonomy:<name>`, `attribute:<name>`: term choices
 * - `price`, `rating`, `distance`: ranges (`min` / `max`)
 * - `availability` (products), `status` (listings): flag choices
 * - `meta:<key>` (ACF), `field:<key>` (the plugin's own fields): custom fields
 *
 * `options`, `min` and `max` are only serialized when set.
 */
class FacetDTO extends DTO {
    public const KIND_CHOICE = "choice";
    public const KIND_RANGE  = "range";
    public const KIND_TOGGLE = "toggle";

    private string $id;

    private string $label;

    private string $kind;

    /**
     * @var FacetOptionDTO[]
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
     * @return FacetOptionDTO[]
     */
    public function get_options(): array {
        return $this->options;
    }

    /**
     * @param FacetOptionDTO[] $options
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
