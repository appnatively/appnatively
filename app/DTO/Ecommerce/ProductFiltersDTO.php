<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

/**
 * Describes which filters are available for the current product context
 * (category / search / already-applied filters), with per-option counts, as a
 * flat list of facets (see ProductFacetDTO). Clients never hardcode filter
 * shapes; they render whatever facets come back.
 */
class ProductFiltersDTO extends DTO {
    /**
     * Sort tokens the backend accepts. Single source of truth shared by the
     * controller's request validation and this DTO's own `sort_options`.
     * Mirrored by hand in the SDK (`WORDPRESS_SORT_OPTIONS`).
     *
     * @var string[]
     */
    public const SORT_TOKENS = [
        "relevance", "newest", "oldest", "price_low", "price_high",
        "rating", "popularity", "name_az", "name_za",
    ];

    /**
     * @var ProductFacetDTO[]
     */
    private array $facets = [];

    /**
     * Sort tokens the backend accepts, in the order they should be presented.
     *
     * @var string[]
     */
    private array $sort_options = [];

    /**
     * @return ProductFacetDTO[]
     */
    public function get_facets(): array {
        return $this->facets;
    }

    /**
     * @param ProductFacetDTO[] $facets
     */
    public function set_facets( array $facets ): self {
        $this->facets = $facets;
        return $this;
    }

    /**
     * @return string[]
     */
    public function get_sort_options(): array {
        return $this->sort_options;
    }

    /**
     * @param string[] $sort_options
     */
    public function set_sort_options( array $sort_options ): self {
        $this->sort_options = $sort_options;
        return $this;
    }
}
