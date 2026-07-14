<?php

namespace Crafium\AppNatively\App\DTO\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class FormDTO extends DTO {
    private int $id;

    private string $title;

    private array $fields = [];

    public function get_id(): int {
        return $this->id;
    }

    public function set_id( int $id ): self {
        $this->id = $id;
        return $this;
    }

    public function get_title(): string {
        return $this->title;
    }

    public function set_title( string $title ): self {
        $this->title = $title;
        return $this;
    }

    public function get_fields(): array {
        return $this->fields;
    }

    public function set_fields( array $fields ): self {
        $this->fields = $fields;
        return $this;
    }
}
