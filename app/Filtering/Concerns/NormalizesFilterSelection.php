<?php

namespace Crafium\AppNatively\App\Filtering\Concerns;

defined( "ABSPATH" ) || exit;

use WP_REST_Request;
use Crafium\AppNatively\App\DTO\Filter\FiltersDTO;

trait NormalizesFilterSelection
{
    /**
     * Sort tokens this catalog supports, in FiltersDTO::SORT_TOKENS order.
     *
     * @return string[]
     */
    private function sort_options(): array {
        $tokens = array_keys( $this->catalog->sorts() );
        if ( $this->catalog->coordinates() ) {
            $tokens[] = 'distance';
        }
        return array_values( array_intersect( FiltersDTO::SORT_TOKENS, $tokens ) );
    }

    /**
     * The request's sort as [expression, direction] pairs; `distance` needs `near`
     * and falls back to relevance without it.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function sort_orders( WP_REST_Request $request ): array {
        $token = $this->scalar( $request->get_param( 'sort' ) );
        if ( $token === 'distance' ) {
            $distance = $this->distance_sql( $request );
            if ( $distance ) {
                // Posts without a location (NULL) go last, not first.
                return [
                    [
                        "({$distance} IS NULL)",
                        'asc'
                    ],
                    [
                        $distance,
                        'asc'
                    ]
                ];
            }
        }
        $sorts  = $this->catalog->sorts();
        $sort   = $sorts[$token] ?? $sorts['relevance'];
        $orders = [];
        foreach ( is_array( $sort[0] ) ? $sort : [$sort] as [$expression, $direction] ) {
            // MySQL sorts NULL first ascending: posts without the value (no price) go last.
            if ( $direction === 'asc' && ! str_starts_with( $expression, 'posts.' ) ) {
                $orders[] = [
                    "({$expression} IS NULL)",
                    'asc'
                ];
            }
            $orders[] = [
                $expression,
                $direction
            ];
        }
        return $orders;
    }

    // -------------------------------------------------------------------------
    // Request reading. Every nested param is read as scalars only: a malformed
    // request narrows nothing instead of reaching a string function with an array.
    // -------------------------------------------------------------------------

    /**
     * @param mixed $value
     */
    private function scalar( $value ): string {
        return is_scalar( $value ) ? trim( (string) $value ) : '';
    }

    /**
     * Non-empty scalar strings from a request array param, capped.
     *
     * @param mixed $value
     * @return string[]
     */
    private function scalar_list( $value ): array {
        $list = [];
        foreach ( is_array( $value ) ? $value : [$value] as $item ) {
            $item = $this->scalar( $item );
            if ( $item !== '' && strlen( $item ) <= self::MAX_VALUE_LENGTH ) {
                $list[] = $item;
            }
        }
        return array_slice( array_values( array_unique( $list ) ), 0, self::MAX_SELECTED_VALUES );
    }

    /**
     * Positive integer ids from a request array param.
     *
     * @param mixed $value
     * @return int[]
     */
    private function positive_ids( $value ): array {
        return array_values( array_filter( array_map( 'intval', $this->scalar_list( $value ) ), fn( $id ) => $id > 0 ) );
    }

    /**
     * The selection's choice values: facet id => values.
     *
     * @return array<string, string[]>
     */
    private function selected_values( WP_REST_Request $request ): array {
        $values = $request->get_param( 'values' );
        $result = [];
        foreach ( is_array( $values ) ? $values : [] as $facet_id => $list ) {
            $list = $this->scalar_list( $list );
            if ( is_string( $facet_id ) && ! empty( $list ) ) {
                $result[$facet_id] = $list;
            }
        }
        return $result;
    }

    /**
     * The selection's ranges: facet id => {min?, max?} (numeric bounds only).
     *
     * @return array<string, array{min?: float, max?: float}>
     */
    private function selected_ranges( WP_REST_Request $request ): array {
        $ranges = $request->get_param( 'ranges' );
        $result = [];
        foreach ( is_array( $ranges ) ? $ranges : [] as $facet_id => $range ) {
            if ( ! is_string( $facet_id ) || ! is_array( $range ) ) {
                continue;
            }
            $bounds = [];
            foreach ( [
                'min',
                'max'
            ] as $bound ) {
                if ( isset( $range[$bound] ) && is_numeric( $range[$bound] ) ) {
                    $bounds[$bound] = (float) $range[$bound];
                }
            }
            if ( ! empty( $bounds ) ) {
                $result[$facet_id] = $bounds;
            }
        }
        return $result;
    }

    /**
     * The shopper's location (`near[lat]`, `near[lng]`), when valid.
     *
     * @return array{0: float, 1: float}|null
     */
    private function near( WP_REST_Request $request ): ?array {
        $near = $request->get_param( 'near' );
        if ( ! is_array( $near ) || ! isset( $near['lat'], $near['lng'] ) || ! is_numeric( $near['lat'] ) || ! is_numeric( $near['lng'] ) ) {
            return null;
        }
        $lat = (float) $near['lat'];
        $lng = (float) $near['lng'];
        return abs( $lat ) <= 90 && abs( $lng ) <= 180 ? [
            $lat,
            $lng
        ] : null;
    }

    /**
     * Normalize a boolean-ish request flag (`"false"` and `"0"` must not count as set).
     *
     * @param mixed $value
     */
    private function flag( $value ): bool {
        return is_scalar( $value ) && filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * The facet ids a filters request asks for (deduped, capped). None asks only for the sort
     * orders (a filter with just a Sort row).
     *
     * @return string[]
     */
    private function requested_facet_ids( WP_REST_Request $request ): array {
        $requested = $request->get_param( 'facets' );
        return array_slice( is_array( $requested ) ? $this->scalar_list( $requested ) : [], 0, self::MAX_FACETS );
    }

    /**
     * The request params that narrow the context, normalized for a cache key. The location
     * is rounded to about a kilometre.
     *
     * @return array{search: string, terms: array, flags: string[], values: array, ranges: array, near: array|null}
     */
    private function context_cache_params( WP_REST_Request $request ): array {
        $terms = [];
        foreach ( array_keys( self::CONTEXT_TERM_PARAMS ) as $param ) {
            $terms[$param] = $this->positive_ids( $request->get_param( $param ) );
        }
        $flags = array_values( array_filter( array_keys( $this->catalog->context_flags() ), fn( $flag ) => $this->flag( $request->get_param( $flag ) ) ) );
        $near  = $this->near( $request );
        return [
            'search' => $this->scalar( $request->get_param( 'search' ) ),
            'terms'  => $terms,
            'flags'  => $flags,
            'values' => $this->selected_values( $request ),
            'ranges' => $this->selected_ranges( $request ),
            'near'   => $near ? [
                round( $near[0], 2 ),
                round( $near[1], 2 )
            ] : null
        ];
    }
}
