<?php

namespace AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\DTO;

class ProductOptionDTO extends DTO {
    private string $name;

    private array $values;

    /**
     * Get the value of name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Set the value of name.
     *
     * @param string $name
     *
     * @return self
     */
    public function set_name( string $name ): self {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the value of values.
     *
     * @return array
     */
    public function get_values(): array {
        return $this->values;
    }

    /**
     * Set the value of values.
     *
     * @param array $values
     *
     * @return self
     */
    public function set_values( array $values ): self {
        $this->values = $values;
        return $this;
    }
}
