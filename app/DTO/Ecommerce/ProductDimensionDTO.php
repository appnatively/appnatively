<?php

namespace AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\DTO;

class ProductDimensionDTO extends DTO {
    private float $length;

    private float $width;

    private float $height;

    private string $unit;

    /**
     * Get the value of length.
     *
     * @return float
     */
    public function get_length(): float {
        return $this->length;
    }

    /**
     * Set the value of length.
     *
     * @param float $length
     *
     * @return self
     */
    public function set_length( float $length ): self {
        $this->length = $length;
        return $this;
    }

    /**
     * Get the value of width.
     *
     * @return float
     */
    public function get_width(): float {
        return $this->width;
    }

    /**
     * Set the value of width.
     *
     * @param float $width
     *
     * @return self
     */
    public function set_width( float $width ): self {
        $this->width = $width;
        return $this;
    }

    /**
     * Get the value of height.
     *
     * @return float
     */
    public function get_height(): float {
        return $this->height;
    }

    /**
     * Set the value of height.
     *
     * @param float $height
     *
     * @return self
     */
    public function set_height( float $height ): self {
        $this->height = $height;
        return $this;
    }

    /**
     * Get the value of unit.
     *
     * @return string
     */
    public function get_unit(): string {
        return $this->unit;
    }

    /**
     * Set the value of unit.
     *
     * @param string $unit
     *
     * @return self
     */
    public function set_unit( string $unit ): self {
        $this->unit = $unit;
        return $this;
    }
}
