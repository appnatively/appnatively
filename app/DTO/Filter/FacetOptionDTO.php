<?php

namespace Crafium\AppNatively\App\DTO\Filter;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

/**
 * One option of a choice facet: what the client sends back when it is picked
 * (a term slug or a meta value), its label, and how many posts match.
 */
class FacetOptionDTO extends DTO {
    private string $value;

    private string $label;

    private int $count;

    public function get_value(): string {
        return $this->value;
    }

    public function set_value( string $value ): self {
        $this->value = $value;
        return $this;
    }

    public function get_label(): string {
        return $this->label;
    }

    public function set_label( string $label ): self {
        $this->label = $label;
        return $this;
    }

    public function get_count(): int {
        return $this->count;
    }

    public function set_count( int $count ): self {
        $this->count = $count;
        return $this;
    }
}
