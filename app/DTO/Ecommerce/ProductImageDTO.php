<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class ProductImageDTO extends DTO {
    private int $id;

    private string $src;

    private string $alt = "";

    private string $title = "";

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

    /**
     * Get the value of src.
     *
     * @return string
     */
    public function get_src(): string {
        return $this->src;
    }

    /**
     * Set the value of src.
     *
     * @param string $src
     *
     * @return self
     */
    public function set_src( string $src ): self {
        $this->src = $src;
        return $this;
    }

    /**
     * Get the value of alt.
     *
     * @return string
     */
    public function get_alt(): string {
        return $this->alt;
    }

    /**
     * Set the value of alt.
     *
     * @param string $alt
     *
     * @return self
     */
    public function set_alt( string $alt ): self {
        $this->alt = $alt;
        return $this;
    }

    /**
     * Get the value of title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Set the value of title.
     *
     * @param string $title
     *
     * @return self
     */
    public function set_title( string $title ): self {
        $this->title = $title;
        return $this;
    }
}
