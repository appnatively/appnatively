<?php

namespace Crafium\AppNatively\App\Filtering;

defined( "ABSPATH" ) || exit;

/**
 * A numeric range facet (price, rating): a post matches when its span
 * [min_sql, max_sql] overlaps the chosen range.
 */
final class RangeFacet {
    public string $label;

    /** A post's lowest value, in stored units. */
    public string $min_sql;

    /** A post's highest value, in stored units. */
    public string $max_sql;

    /** Stored units per shown unit (100 for amounts kept in cents). */
    public int $divisor;

    /** @var array{0: float, 1: float}|null Fixed bounds (0-5 stars) instead of the context's span. */
    public ?array $bounds;

    /** Condition some post in context must meet for the facet to show (e.g. has a rating). */
    public ?string $exists_sql;

    /**
     * @param array{0: float, 1: float}|null $bounds
     */
    public function __construct( string $label, string $min_sql, string $max_sql, int $divisor = 1, ?array $bounds = null, ?string $exists_sql = null ) {
        $this->label      = $label;
        $this->min_sql    = $min_sql;
        $this->max_sql    = $max_sql;
        $this->divisor    = $divisor;
        $this->bounds     = $bounds;
        $this->exists_sql = $exists_sql;
    }
}
