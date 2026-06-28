<?php

namespace Crafium\AppNatively\App\DTO\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\PaginatorDTO;

class ListingPaginatorDTO extends PaginatorDTO {
    /**
     * @var ListingDTO[]
     */
    protected $items;

    public function __construct( int $current_page, int $per_page, int $total, int $last_page, array $items ) {
        parent::__construct( $current_page, $per_page, $total, $last_page );
        $this->items = $items;
    }

    /**
     * @return ListingDTO[]
     */
    public function get_items(): array {
        return $this->items;
    }

    /**
     * @param ListingDTO[] $items
     */
    public function set_items( array $items ): self {
        $this->items = $items;
        return $this;
    }
}
