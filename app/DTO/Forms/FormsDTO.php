<?php

namespace Crafium\AppNatively\App\DTO\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class FormsDTO extends DTO {
    protected array $items;

    public function get_items(): array {
        return $this->items;
    }
    /**
     * Summary of set_items
     * @param FormDTO[] $items
     * @return FormsDTO
     */
    public function set_items( array $items ): self {
        $this->items = $items;
        return $this;
    }
}
