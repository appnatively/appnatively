<?php

namespace Crafium\AppNatively\App\DTO\PostType;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\PaginatorDTO;

class PostTypeTermPaginatorDTO extends PaginatorDTO {
    /**
     * The items for the current page.
     *
     * @var PostTypeTermDTO[]
     */
    protected $items;

    public function __construct( int $current_page, int $per_page, int $total, int $last_page, array $items ) {
        parent::__construct( $current_page, $per_page, $total, $last_page );
        $this->items = $items;
    }

    /**
     * Get the value of items.
     *
     * @return PostTypeTermDTO[]
     */
    public function get_items(): array {
        return $this->items;
    }

    /**
     * Set the value of items.
     *
     * @param PostTypeTermDTO[] $items
     *
     * @return self
     */
    public function set_items( array $items ): self {
        $this->items = $items;
        return $this;
    }
}
