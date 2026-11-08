<?php

namespace Crafium\AppNatively\App\Filtering\Concerns;

defined( "ABSPATH" ) || exit;

use Closure;
use WP_REST_Request;
use Crafium\AppNatively\App\DTO\Filter\FacetDTO;
use Crafium\AppNatively\App\DTO\Filter\FacetOptionDTO;
use Crafium\AppNatively\WpMVC\Database\Query\Builder;
use Crafium\AppNatively\App\Filtering\Field;
use Crafium\AppNatively\App\Filtering\RangeFacet;
use Crafium\AppNatively\App\Filtering\TermSource;

trait BuildsCatalogFacets
{
    // -------------------------------------------------------------------------
    // Facets
    // -------------------------------------------------------------------------

    /**
     * Build one facet by id, or null when the id is unknown, not allowed, or has
     * nothing to offer in the current context.
     */
    private function build_facet( string $facet_id, WP_REST_Request $request ): ?FacetDTO {
        $range = $this->catalog->ranges()[$facet_id] ?? null;
        if ( $range ) {
            return $this->build_range_facet( $facet_id, $range, $request );
        }
        $flag = $this->catalog->flags()[$facet_id] ?? null;
        if ( $flag ) {
            return $this->build_flag_facet( $facet_id, $flag, $request );
        }
        $source = $this->term_source( $facet_id );
        if ( $source ) {
            return $this->build_term_facet( $facet_id, $source, $request );
        }
        $field = $this->facet_field( $facet_id );
        return $field ? $this->build_field_facet( $facet_id, $field, $request ) : null;
    }

    private function build_range_facet( string $facet_id, RangeFacet $range, WP_REST_Request $request ): ?FacetDTO {
        $query = $this->context_query( $request, $facet_id );
        $facet = ( new FacetDTO() )->set_id( $facet_id )->set_label( $range->label )->set_kind( FacetDTO::KIND_RANGE );
        if ( $range->bounds ) {
            if ( $range->exists_sql && ! $query->where_raw( $range->exists_sql )->exists() ) {
                return null;
            }
            return $facet->set_min( $range->bounds[0] )->set_max( $range->bounds[1] );
        }
        $min = ( clone $query )->min( $range->min_sql );
        $max = $query->max( $range->max_sql );
        if ( $min === null || $max === null ) {
            return null;
        }
        return $facet->set_min( (float) $min / $range->divisor )->set_max( (float) $max / $range->divisor );
    }

    /**
     * @param array{label: string, options: array<string, array{0: string, 1: string|Closure}>} $flag
     */
    private function build_flag_facet( string $facet_id, array $flag, WP_REST_Request $request ): FacetDTO {
        $options = [];
        foreach ( $flag['options'] as $value => [$label, $condition] ) {
            $count     = (int) $this->context_query( $request, $facet_id )->where_raw( $this->condition( $condition ) )->count();
            $options[] = $this->facet_option( (string) $value, $label, $count );
        }
        return ( new FacetDTO() )->set_id( $facet_id )->set_label( $flag['label'] )->set_kind( FacetDTO::KIND_CHOICE )->set_options( $options );
    }

    /**
     * Options of one term source with post counts. Its own selection is left out of the
     * context, so counts show what adding another option would give (OR within a facet).
     */
    private function build_term_facet( string $facet_id, TermSource $source, WP_REST_Request $request ): ?FacetDTO {
        // A factory, not one query: a source may need several, and each must be ungrouped.
        $terms   = $source->options( fn(): Builder => $this->context_query( $request, $facet_id ), self::MAX_FACET_OPTIONS );
        $options = [];
        foreach ( $terms as [$value, $option_label, $count] ) {
            if ( $count > 0 ) {
                $options[] = $this->facet_option( $value, $option_label, $count );
            }
        }
        if ( empty( $options ) ) {
            return null;
        }
        return ( new FacetDTO() )->set_id( $facet_id )->set_label( $source->label() )->set_kind( FacetDTO::KIND_CHOICE )->set_options( array_slice( $options, 0, self::MAX_FACET_OPTIONS ) );
    }

    /**
     * A custom field's facet: a range for numbers, a toggle for true/false, or its
     * distinct values (fixed choices with their labels) with counts.
     */
    private function build_field_facet( string $facet_id, Field $field, WP_REST_Request $request ): ?FacetDTO {
        $facet = ( new FacetDTO() )->set_id( $facet_id )->set_label( $field->label );
        // How many posts in context (this facet's own selection left out) match one value.
        $count_matching = function ( string $value ) use ( $request, $facet_id, $field ): int {
            $query = $this->context_query( $request, $facet_id );
            $this->where_field( $query, $field, ['values' => [$value]] );
            return (int) $query->count();
        };
        $query          = $this->context_query( $request, $facet_id );
        if ( $field->meta_key !== null ) {
            $subject = 'facet_meta.meta_value';
            $query->join( 'postmeta as facet_meta', 'posts.ID', '=', 'facet_meta.post_id' )->where( 'facet_meta.meta_key', $field->meta_key )->where( 'facet_meta.meta_value', '!=', '' );
        } else {
            $subject = $field->column;
            $query->where_raw( "{$subject} IS NOT NULL AND {$subject} <> ''" );
        }
        if ( $field->value_type === Field::TYPE_NUMBER ) {
            $cast = "CAST({$subject} AS DECIMAL(20,4))";
            $min  = ( clone $query )->min( $cast );
            $max  = $query->max( $cast );
            if ( $min === null || $max === null ) {
                return null;
            }
            return $facet->set_kind( FacetDTO::KIND_RANGE )->set_min( (float) $min )->set_max( (float) $max );
        }
        if ( $field->value_type === Field::TYPE_BOOLEAN ) {
            return $facet->set_kind( FacetDTO::KIND_TOGGLE )->set_options( [$this->facet_option( '1', $field->label, $count_matching( '1' ) )] );
        }
        $options = [];
        if ( $field->multiple ) {
            // Several choices stored in one value can't be grouped: count each choice.
            foreach ( array_slice( $field->choices, 0, self::MAX_FACET_OPTIONS, true ) as $value => $choice_label ) {
                $count = $count_matching( (string) $value );
                if ( $count > 0 ) {
                    $options[] = $this->facet_option( (string) $value, (string) $choice_label, $count );
                }
            }
        } else {
            $rows = $query->select(
                [
                    "{$subject} as value",
                    'COUNT(DISTINCT posts.ID) as post_count'
                ]
            )->group_by( [$subject] )->order_by( $subject, 'asc' )->limit( self::MAX_FACET_OPTIONS )->get();
            foreach ( $rows as $row ) {
                $value     = (string) $row->value;
                $options[] = $this->facet_option( $value, (string) ( $field->choices[$value] ?? $value ), (int) $row->post_count );
            }
        }
        return empty( $options ) ? null : $facet->set_kind( FacetDTO::KIND_CHOICE )->set_options( $options );
    }

    private function facet_option( string $value, string $label, int $count ): FacetOptionDTO {
        return ( new FacetOptionDTO() )->set_value( $value )->set_label( $label )->set_count( $count );
    }
}
