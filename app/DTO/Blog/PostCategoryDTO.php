<?php

namespace Crafium\AppNatively\App\DTO\Blog;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class PostCategoryDTO extends DTO {
    private int $id;

    private string $name;

    private string $slug;

    private string $description;

    private int $parent;

    private int $count;

    public function get_id(): int {
        return $this->id;
    }

    public function set_id( int $id ): self {
        $this->id = $id;
        return $this;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function set_name( string $name ): self {
        $this->name = $name;
        return $this;
    }

    public function get_slug(): string {
        return $this->slug;
    }

    public function set_slug( string $slug ): self {
        $this->slug = $slug;
        return $this;
    }

    public function get_description(): string {
        return $this->description;
    }

    public function set_description( string $description ): self {
        $this->description = $description;
        return $this;
    }

    public function get_parent(): int {
        return $this->parent;
    }

    public function set_parent( int $parent ): self {
        $this->parent = $parent;
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
