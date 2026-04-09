<?php

namespace AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use AppNatively\WpMVC\DTO\DTO;

class CategoryDTO extends DTO {
    private int $id;

    /**
     * Get the value of id.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Set the value of id.
     *
     * @param int $id
     *
     * @return self
     */
    public function set_id( int $id ): self {
        $this->id = $id;
        return $this;
    }
}