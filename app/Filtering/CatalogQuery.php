<?php

namespace Crafium\AppNatively\App\Filtering;

defined( "ABSPATH" ) || exit;
use Closure;
use WP_REST_Request;
use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\DTO\Filter\FilterSourceDTO;
use Crafium\AppNatively\App\DTO\Filter\FiltersDTO;
use Crafium\AppNatively\WpMVC\Database\Query\Builder;
/**
 * Lists and their filter's facets for one plugin's catalog (products or
 * directory listings, see Catalog).
 *
 * A request narrows the posts in two layers:
 *
 * - Page context: `categories[]`, `tags[]`, `locations[]` (term ids; hierarchical
 *   taxonomies include children), `search`, and a list's own flags
 *   (Catalog::context_flags(), e.g. `in_stock`, `featured`).
 * - The shopper's filter selection, keyed by facet id: `values[<facet>][]`,
 *   `ranges[<facet>][min|max]`, `sort`, and `near[lat|lng]` for distance.
 *
 * Facet ids: term facets (`category`, `tag`, `location`), `taxonomy:<name>`,
 * `attribute:<name>`, ranges (`price`, `rating`, and `distance` in km),
 * flags (`availability`, `status`), `meta:<key>` (ACF and allowed meta) and
 * `field:<key>` (the plugin's own fields). Term and field values are OR-ed
 * within a facet, flags are AND-ed, and facets are AND-ed.
 *
 * A term facet is backed by a TermSource, which need not be a taxonomy: it
 * narrows by term id for the page context and by slug for the selection, and
 * mints the option values itself.
 */
class CatalogQuery
{
    use Concerns\NormalizesFilterSelection, Concerns\DiscoversCatalogFields, Concerns\BuildsCatalogFacets;
    /** Facets computed per filters request at most. */
    private const MAX_FACETS = 8;
    /** Options returned per choice facet at most. */
    private const MAX_FACET_OPTIONS = 100;
    /** Values read per facet at most, and their longest accepted length. */
    private const MAX_SELECTED_VALUES = 50;
    private const MAX_VALUE_LENGTH    = 200;
    /** Seconds a filters response is cached for one context (one with time-dependent facets: a minute). */
    private const FACETS_CACHE_TTL   = 5 * MINUTE_IN_SECONDS;
    private const FACETS_CACHE_GROUP = 'craf_appna_filters';
    /** Discovered meta keys listed to the builder at most. */
    private const MAX_DISCOVERED_META_KEYS = 200;
    /** ACF field types that make sense as a filter. */
    private const ACF_FILTERABLE_TYPES = [
        'text',
        'number',
        'range',
        'select',
        'checkbox',
        'radio',
        'button_group',
        'true_false'
    ];
    /** Context params narrowing by terms => the term facet they narrow by. */
    private const CONTEXT_TERM_PARAMS = [
        'categories' => 'category',
        'tags'       => 'tag',
        'locations'  => 'location'
    ];
    private const EARTH_RADIUS_KM     = 6371;

    private Catalog $catalog;

    /** @var array<string, Field>|null Memoized ACF fields placed on the post types (see acf_fields()). */
    private ?array $acf_fields = null;

    /** @var string[]|null Memoized custom-field allow-list (see allowed_fields()). */
    private ?array $allowed_fields = null;

    public function __construct( Catalog $catalog ) {
        $this->catalog = $catalog;
    }

    /**
     * Published, unprotected posts the site lists, with the catalog's joins.
     *
     * @param bool $searching Whether the list is a search (visibility can differ).
     * @return Builder
     */
    public function base( bool $searching = false ): Builder {
        $query = Post::where_in( 'posts.post_type', $this->post_types() )->where( 'posts.post_status', 'publish' )->where( 'posts.post_password', '' );
        $this->catalog->prepare( $query );
        $this->catalog->where_visible( $query, $searching );
        return $query;
    }

    /**
     * One page of the request's posts (context, filter selection and sort), as ids in order.
     *
     * @param WP_REST_Request $request
     * @return array{ids: int[], page: int, per_page: int, total: int, last_page: int}
     */
    public function paginate( WP_REST_Request $request ): array {
        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 10 ) );
        $query    = $this->context_query( $request )->select( ['posts.ID'] );
        foreach ( $this->sort_orders( $request ) as [$expression, $direction] ) {
            // The builder quotes a bare `table.column`; any other expression is parenthesized so it isn't.
            $query->order_by( preg_match( '/^[A-Za-z_][\w]*(\.[A-Za-z_][\w]*)?$/', $expression ) ? $expression : "({$expression})", $direction );
        }
        // The id tiebreaker keeps pages stable when many posts share a price or name.
        $paginator = $query->order_by( 'posts.ID', 'desc' )->paginate( $page, $per_page, 1 );
        $ids       = [];
        foreach ( $paginator->items() as $post ) {
            $ids[] = (int) $post->ID;
        }
        return [
            'ids'       => $ids,
            'page'      => $page,
            'per_page'  => $per_page,
            'total'     => (int) $paginator->total(),
            'last_page' => max( 1, (int) $paginator->last_page() )
        ];
    }

    /**
     * The facets a filters request asks for (`facets[]`, at most MAX_FACETS), with counts
     * for the request's context, plus the sort orders this catalog supports.
     *
     * Cached in the object cache; a context without search text, a selection or a location
     * (what a filter opens with) is also kept as a transient, so the number of transients
     * stays bounded by the site's terms and locations. Time-dependent facets (open now) are
     * cached for a minute and never as a transient.
     *
     * @param WP_REST_Request $request
     * @return FiltersDTO
     */
    public function filters( WP_REST_Request $request ): FiltersDTO {
        $dto       = ( new FiltersDTO() )->set_sort_options( $this->sort_options() );
        $facet_ids = $this->requested_facet_ids( $request );
        $context   = $this->context_cache_params( $request );
        $volatile  = ! empty( array_intersect( $facet_ids, $this->catalog->volatile_facets() ) );
        $key_parts = [
            $this->post_types(),
            $facet_ids,
            $context
        ];
        if ( $volatile ) {
            $key_parts[] = (int) floor( time() / MINUTE_IN_SECONDS );
        }
        $cache_key = 'craf_appna_filters_' . md5( (string) wp_json_encode( $key_parts ) );
        $persist   = ! $volatile && $context['search'] === '' && empty( $context['values'] ) && empty( $context['ranges'] ) && $context['near'] === null;
        $cached    = wp_cache_get( $cache_key, self::FACETS_CACHE_GROUP );
        if ( ! is_array( $cached ) && $persist ) {
            $cached = get_transient( $cache_key );
        }
        if ( is_array( $cached ) ) {
            return $dto->set_facets( $cached );
        }
        $facets = [];
        foreach ( $facet_ids as $facet_id ) {
            $facet = $this->build_facet( $facet_id, $request );
            if ( $facet ) {
                $facets[] = $facet;
            }
        }
        wp_cache_set( $cache_key, $facets, self::FACETS_CACHE_GROUP, $volatile ? MINUTE_IN_SECONDS : self::FACETS_CACHE_TTL );
        if ( $persist ) {
            set_transient( $cache_key, $facets, self::FACETS_CACHE_TTL );
        }
        return $dto->set_facets( $facets );
    }

    /**
     * What a merchant can add as filter rows in the app builder: extra and attribute
     * taxonomies, the plugin's own fields, and custom fields. Fields outside the
     * allow-list are listed with `enabled: false` (filtering by them does nothing
     * until the site allows them, see allowed_fields()).
     *
     * @return FilterSourceDTO[]
     */
    public function filter_sources(): array {
        $sources = [];
        foreach ( $this->catalog->taxonomy_groups() as $prefix => $taxonomies ) {
            foreach ( $taxonomies as $taxonomy => $label ) {
                $sources[] = $this->source( $prefix, $taxonomy, $label, Field::TYPE_CHOICE, true );
            }
        }
        $allowed = $this->allowed_fields();
        foreach ( $this->catalog->fields() as $key => $field ) {
            $sources[] = $this->source( 'field', $key, $field->label, $field->value_type, in_array( "field:{$key}", $allowed, true ) );
        }
        $meta_keys = array_keys( $this->acf_fields() );
        foreach ( $allowed as $facet_id ) {
            if ( str_starts_with( $facet_id, 'meta:' ) ) {
                $meta_keys[] = substr( $facet_id, 5 );
            }
        }
        $meta_keys = array_values( array_unique( $meta_keys ) );
        foreach ( array_merge( $meta_keys, array_diff( $this->discover_meta_keys(), $meta_keys ) ) as $key ) {
            $field     = $this->meta_field( $key );
            $sources[] = $this->source( 'meta', $key, $field->label, $field->value_type, in_array( "meta:{$key}", $allowed, true ) );
        }
        return $sources;
    }

    private function source( string $source, string $key, string $label, string $value_type, bool $enabled ): FilterSourceDTO {
        return ( new FilterSourceDTO() )->set_source( $source )->set_key( $key )->set_label( $label )->set_value_type( $value_type )->set_enabled( $enabled );
    }

    /**
     * The catalog's post types; a catalog without any (no directory set up yet) lists nothing.
     *
     * @return string[]
     */
    private function post_types(): array {
        return $this->catalog->post_types() ?: [''];
    }

    // -------------------------------------------------------------------------
    // Narrowing
    // -------------------------------------------------------------------------

    /**
     * Posts matching the request's page context and filter selection.
     *
     * @param WP_REST_Request $request
     * @param string|null $exclude_facet A facet whose own selection is skipped (to count that facet's options).
     * @return Builder
     */
    private function context_query( WP_REST_Request $request, ?string $exclude_facet = null ): Builder {
        $search      = $this->scalar( $request->get_param( 'search' ) );
        $query       = $this->base( $search !== '' );
        $term_facets = $this->catalog->term_facets();
        foreach ( self::CONTEXT_TERM_PARAMS as $param => $facet_id ) {
            $ids    = $this->positive_ids( $request->get_param( $param ) );
            $source = $term_facets[$facet_id] ?? null;
            if ( ! empty( $ids ) && $source ) {
                $source->where_ids( $query, $ids );
            }
        }
        if ( $search !== '' ) {
            global $wpdb;
            $columns = $this->catalog->search_columns();
            $query->where_raw( '(' . implode( ' OR ', array_map( fn( $column ) => "{$column} LIKE %s", $columns ) ) . ')', array_fill( 0, count( $columns ), '%' . $wpdb->esc_like( $search ) . '%' ) );
        }
        foreach ( $this->catalog->context_flags() as $flag => $condition ) {
            if ( $this->flag( $request->get_param( $flag ) ) ) {
                $query->where_raw( $condition );
            }
        }
        foreach ( $this->selected_values( $request ) as $facet_id => $values ) {
            if ( $facet_id !== $exclude_facet ) {
                $this->apply_values( $query, $facet_id, $values );
            }
        }
        foreach ( $this->selected_ranges( $request ) as $facet_id => $range ) {
            if ( $facet_id !== $exclude_facet ) {
                $this->apply_range( $query, $facet_id, $range, $request );
            }
        }
        return $query;
    }

    /**
     * Narrow by a choice facet's selected values. Unknown or disallowed facets narrow nothing.
     *
     * @param string[] $values
     */
    private function apply_values( Builder $query, string $facet_id, array $values ): void {
        $flag = $this->catalog->flags()[$facet_id] ?? null;
        if ( $flag ) {
            foreach ( $values as $value ) {
                if ( isset( $flag['options'][$value] ) ) {
                    $query->where_raw( $this->condition( $flag['options'][$value][1] ) );
                }
            }
            return;
        }
        $source = $this->term_source( $facet_id );
        if ( $source ) {
            $source->where_slugs( $query, $values );
            return;
        }
        $field = $this->facet_field( $facet_id );
        if ( $field ) {
            $this->where_field( $query, $field, ['values' => $values] );
        }
    }

    /**
     * Narrow by a range facet's bounds (shown units: currency, stars, kilometres).
     *
     * @param array{min?: float, max?: float} $range
     */
    private function apply_range( Builder $query, string $facet_id, array $range, WP_REST_Request $request ): void {
        if ( $facet_id === 'distance' ) {
            $distance = $this->distance_sql( $request );
            if ( $distance && isset( $range['max'] ) ) {
                $query->where_raw( $distance . ' <= %f', [$range['max']] );
            }
            return;
        }
        $range_facet = $this->catalog->ranges()[$facet_id] ?? null;
        if ( $range_facet ) {
            // A post's span overlaps the chosen range.
            if ( isset( $range['min'] ) ) {
                $query->where_raw( $range_facet->max_sql . ' >= %f', [$range['min'] * $range_facet->divisor] );
            }
            if ( isset( $range['max'] ) ) {
                $query->where_raw( $range_facet->min_sql . ' <= %f', [$range['max'] * $range_facet->divisor] );
            }
            return;
        }
        $field = $this->facet_field( $facet_id );
        if ( $field ) {
            $this->where_field( $query, $field, $range );
        }
    }

    /**
     * Kilometres between a post and the request's `near` point, or null without one
     * (or when the catalog has no locations). Posts without coordinates are NULL.
     */
    private function distance_sql( WP_REST_Request $request ): ?string {
        $near        = $this->near( $request );
        $coordinates = $this->catalog->coordinates();
        if ( ! $near || ! $coordinates ) {
            return null;
        }
        [$lat, $lng] = $coordinates;
        $near_lat    = sprintf( '%.6F', $near[0] );
        $near_lng    = sprintf( '%.6F', $near[1] );
        // NULLIF: a missing coordinate is stored as 0 or empty, never a real 0,0 listing.
        return sprintf( '(%d * ACOS(LEAST(1, COS(RADIANS(%s)) * COS(RADIANS(NULLIF(%s, 0))) * COS(RADIANS(NULLIF(%s, 0)) - RADIANS(%s)) + SIN(RADIANS(%s)) * SIN(RADIANS(NULLIF(%s, 0))))))', self::EARTH_RADIUS_KM, $near_lat, $lat, $lng, $near_lng, $near_lat, $lat );
    }

    /**
     * A flag condition as SQL (a Closure builds a costly one only when used).
     *
     * @param string|Closure $condition
     */
    private function condition( $condition ): string {
        return $condition instanceof Closure ? (string) $condition() : $condition;
    }

    /**
     * Keep posts whose field matches `$constraint`: any of `values` (a multiple field
     * matched by its list pattern, a true/false field by any truthy value), and/or a
     * numeric `min` / `max`. Values are bound as-is, so they match stored values exactly.
     *
     * @param array $constraint
     */
    private function where_field( Builder $query, Field $field, array $constraint ): void {
        global $wpdb;
        $subject    = $field->meta_key !== null ? 'pm.meta_value' : $field->column;
        $conditions = [];
        $bindings   = [];
        $values     = $this->scalar_list( $constraint['values'] ?? [] );
        if ( ! empty( $values ) ) {
            if ( $field->value_type === Field::TYPE_BOOLEAN ) {
                $values = Field::TRUTHY_VALUES;
            }
            $matches = [];
            foreach ( $values as $value ) {
                $matches[]  = "{$subject} = %s";
                $bindings[] = $value;
                if ( $field->multiple ) {
                    $matches[]  = "{$subject} LIKE %s";
                    $bindings[] = '%' . str_replace( '{value}', $wpdb->esc_like( $value ), $field->list_pattern ) . '%';
                }
            }
            $conditions[] = '(' . implode( ' OR ', $matches ) . ')';
        }
        foreach ( [
            'min' => '>=',
            'max' => '<='
        ] as $bound => $operator ) {
            if ( isset( $constraint[$bound] ) && is_numeric( $constraint[$bound] ) ) {
                $conditions[] = "CAST({$subject} AS DECIMAL(20,4)) {$operator} %f";
                $bindings[]   = (float) $constraint[$bound];
            }
        }
        if ( empty( $conditions ) ) {
            return;
        }
        if ( $field->meta_key === null ) {
            $query->where_raw( '(' . implode( ' AND ', $conditions ) . ')', $bindings );
            return;
        }
        $query->where_raw( "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = posts.ID AND pm.meta_key = %s AND " . implode( ' AND ', $conditions ) . ')', array_merge( [$field->meta_key], $bindings ) );
    }
}
