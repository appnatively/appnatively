<?php

namespace AppNatively\App\DTO;

defined( "ABSPATH" ) || exit;

abstract class PaginatorDTO extends DTO {
    /**
     * The current page being viewed.
     *
     * @var int
     */
    protected $current_page;

    /**
     * The last page.
     *
     * @var int
     */
    protected $last_page;

    /**
     * The number of items to be shown per page.
     *
     * @var int
     */
    protected $per_page;

    /**
     * The total number of items before paginate.
     *
     * @var int
     */
    protected $total;

    public function __construct( int $current_page, int $per_page, int $total, int $last_page ) {
        $this->current_page = $current_page;
        $this->per_page     = $per_page;
        $this->total        = $total;
        $this->last_page    = $last_page;
    }

    /**
     * Get the value of current_page.
     *
     * @return int
     */
    public function get_current_page(): int {
        return $this->current_page;
    }

    /**
     * Set the value of current_page.
     *
     * @param int $current_page
     *
     * @return self
     */
    public function set_current_page( int $current_page ): self {
        $this->current_page = $current_page;
        return $this;
    }

    /**
     * Get the value of last_page.
     *
     * @return int
     */
    public function get_last_page(): int {
        return $this->last_page;
    }

    /**
     * Set the value of last_page.
     *
     * @param int $last_page
     *
     * @return self
     */
    public function set_last_page( int $last_page ): self {
        $this->last_page = $last_page;
        return $this;
    }

    /**
     * Get the value of per_page.
     *
     * @return int
     */
    public function get_per_page(): int {
        return $this->per_page;
    }

    /**
     * Set the value of per_page.
     *
     * @param int $per_page
     *
     * @return self
     */
    public function set_per_page( int $per_page ): self {
        $this->per_page = $per_page;
        return $this;
    }

    /**
     * Get the value of total.
     *
     * @return int
     */
    public function get_total(): int {
        return $this->total;
    }

    /**
     * Set the value of total.
     *
     * @param int $total
     *
     * @return self
     */
    public function set_total( int $total ): self {
        $this->total = $total;
        return $this;
    }
}