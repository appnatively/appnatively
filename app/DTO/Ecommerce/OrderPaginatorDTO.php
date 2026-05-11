<?php

namespace AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\PaginatorDTO;

/**
 * Class OrderPaginatorDTO
 * 
 * Data transfer object for paginated orders.
 */
class OrderPaginatorDTO extends PaginatorDTO {
    /**
     * The items for the current page.
     *
     * @var OrderDTO[]
     */
    protected $items;

    /**
     * OrderPaginatorDTO constructor.
     *
     * @param int $current_page
     * @param int $per_page
     * @param int $total
     * @param int $last_page
     * @param OrderDTO[] $items
     */
    public function __construct( int $current_page, int $per_page, int $total, int $last_page, array $items ) {
        parent::__construct( $current_page, $per_page, $total, $last_page );
        $this->items = $items;
    }

    /**
     * Get the value of items.
     *
     * @return OrderDTO[]
     */
    public function get_items(): array {
        return $this->items;
    }

    /**
     * Set the value of items.
     *
     * @param OrderDTO[] $items
     *
     * @return self
     */
    public function set_items( array $items ): self {
        $this->items = $items;
        return $this;
    }
}
