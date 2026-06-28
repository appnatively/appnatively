<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\PaginatorDTO;

class CategoryPaginatorDTO extends PaginatorDTO {
    /**
     * The items for the current page.
     *
     * @var CategoryDTO[]
     */
    protected $items;

    public function __construct( int $current_page, int $per_page, int $total, int $last_page, array $items ) {
        parent::__construct( $current_page, $per_page, $total, $last_page );
        $this->items = $items;
    }

    /**
     * Get the value of items.
     *
     * @return CategoryDTO[]
     */
    public function get_items(): array {
        return $this->items;
    }

    /**
     * Set the value of items.
     *
     * @param CategoryDTO[] $items
     *
     * @return self
     */
    public function set_items( array $items ): self {
        $this->items = $items;
        return $this;
    }
}