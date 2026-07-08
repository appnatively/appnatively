<?php

namespace Crafium\AppNatively\App\DTO\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class TermDTO extends DTO {
    private int $id;

    private string $name;

    private string $slug;

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
}
