<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class CategoryDTO extends DTO {
    private int $id;

    private string $name;

    private string $slug;

    private string $description;

    private int $parent;

    private int $count;

    private ?ProductImageDTO $image = null;

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
     * Get the value of slug.
     *
     * @return string
     */
    public function get_slug(): string {
        return $this->slug;
    }

    /**
     * Set the value of slug.
     *
     * @param string $slug
     *
     * @return self
     */
    public function set_slug( string $slug ): self {
        $this->slug = $slug;
        return $this;
    }

    /**
     * Get the value of description.
     *
     * @return string
     */
    public function get_description(): string {
        return $this->description;
    }

    /**
     * Set the value of description.
     *
     * @param string $description
     *
     * @return self
     */
    public function set_description( string $description ): self {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the value of parent.
     *
     * @return int
     */
    public function get_parent(): int {
        return $this->parent;
    }

    /**
     * Set the value of parent.
     *
     * @param int $parent
     *
     * @return self
     */
    public function set_parent( int $parent ): self {
        $this->parent = $parent;
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

    /**
     * Get the value of image.
     *
     * @return ProductImageDTO|null
     */
    public function get_image(): ?ProductImageDTO {
        return $this->image;
    }

    /**
     * Set the value of image.
     *
     * @param ProductImageDTO|null $image
     *
     * @return self
     */
    public function set_image( ?ProductImageDTO $image ): self {
        $this->image = $image;
        return $this;
    }
}