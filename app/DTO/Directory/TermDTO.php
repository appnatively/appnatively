<?php

namespace Crafium\AppNatively\App\DTO\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class TermDTO extends DTO {
    private int $id;

    private string $name;

    private string $slug;

    private int $count = 0;

    private array $image = [];

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

    public function get_count(): int {
        return $this->count;
    }

    public function set_count( int $count ): self {
        $this->count = $count;
        return $this;
    }

    public function get_image(): array {
        return $this->image;
    }

    public function set_image( array $image ): self {
        $this->image = $image;
        return $this;
    }
}
