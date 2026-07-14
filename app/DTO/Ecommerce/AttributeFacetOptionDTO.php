<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class AttributeFacetOptionDTO extends DTO {
    private string $value;

    private string $label;

    private int $count;

    /**
     * Get the value of value.
     *
     * @return string
     */
    public function get_value(): string {
        return $this->value;
    }

    /**
     * Set the value of value.
     *
     * @param string $value
     *
     * @return self
     */
    public function set_value( string $value ): self {
        $this->value = $value;
        return $this;
    }

    /**
     * Get the value of label.
     *
     * @return string
     */
    public function get_label(): string {
        return $this->label;
    }

    /**
     * Set the value of label.
     *
     * @param string $label
     *
     * @return self
     */
    public function set_label( string $label ): self {
        $this->label = $label;
        return $this;
    }

    /**
     * Get the value of count.
     *
     * @return int
     */
    public function get_count(): int {
        return $this->count;
    }

    /**
     * Set the value of count.
     *
     * @param int $count
     *
     * @return self
     */
    public function set_count( int $count ): self {
        $this->count = $count;
        return $this;
    }
}
