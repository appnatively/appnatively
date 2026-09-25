<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\Catalog;

defined( "ABSPATH" ) || exit;

use WP_REST_Request;
use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFacetDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFacetOptionDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFilterSourceDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFiltersDTO;
use Crafium\AppNatively\WpMVC\Database\Query\Builder;

/**
 * Product lists and the product filter's facets for one store (see ProductCatalog).
 *
 * A request narrows the products in two layers:
 *
 * - Page context: `categories[]` (term ids, with their children), `tags[]`
 *   (term ids), `search`, and a list's own `in_stock` setting.
 * - The shopper's filter selection, keyed by facet id: `values[<facet>][]`,
 *   `ranges[<facet>][min|max]` and `sort`.
 *
 * Facet ids: `category`, `tag`, `taxonomy:<name>`, `attribute:<name>`,
 * `meta:<key>`, `price`, `rating` and `availability` (`in_stock` / `on_sale`).
 * Values are OR-ed within a facet and AND-ed across facets.
 */
class ProductQuery {
    /** Facets computed per filters request at most. */
    private const MAX_FACETS = 8;

    /** Options returned per choice facet at most. */
    private const MAX_FACET_OPTIONS = 100;

    /** Values read per facet at most, and their longest accepted length. */
    private const MAX_SELECTED_VALUES = 50;
    private const MAX_VALUE_LENGTH    = 200;

    /** Seconds a filters response is cached for one context. */
    private const FACETS_CACHE_TTL   = 5 * MINUTE_IN_SECONDS;
    private const FACETS_CACHE_GROUP = 'craf_appna_pf';

    /** Discovered meta keys listed to the builder at most. */
    private const MAX_DISCOVERED_META_KEYS = 200;

    /** ACF field types that make sense as a shopper filter. */
    private const ACF_FILTERABLE_TYPES = [ 'text', 'number', 'range', 'select', 'checkbox', 'radio', 'button_group', 'true_false' ];

    /** How a checked true/false field may be stored. */
    private const TRUTHY_META_VALUES = [ '1', 'true', 'yes' ];

    private ProductCatalog $catalog;

    /** @var array|null Memoized ACF fields placed on the post type (see acf_fields()). */
    private ?array $acf_fields = null;

    /** @var string[]|null Memoized custom-field allow-list (see filterable_meta_keys()). */
    private ?array $meta_keys = null;

    public function __construct( ProductCatalog $catalog ) {
        $this->catalog = $catalog;
    }

    /**
     * Published, unprotected products the storefront lists, with the store's joins.
     *
     * @param bool $searching Whether the list is a search (catalog visibility differs).
     * @return Builder
     */
    public function base( bool $searching = false ): Builder {
        $query = Post::where( 'post_type', $this->catalog->post_type() )
            ->where( 'post_status', 'publish' )
            ->where( 'post_password', '' );
        $this->catalog->prepare( $query );
        $this->catalog->where_visible( $query, $searching );
        return $query;
    }

    /**
     * One page of the request's products (context, filter selection and sort), as ids in order.
     *
     * @param WP_REST_Request $request
     * @return array{ids: int[], page: int, per_page: int, total: int, last_page: int}
     */
    public function paginate( WP_REST_Request $request ): array {
        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 10 ) );
        $sorts    = $this->catalog->sorts();
        $sort     = $sorts[ $this->scalar( $request->get_param( 'sort' ) ) ] ?? $sorts['relevance'];

        // The id tiebreaker keeps pages stable when many products share a price or name.
        $paginator = $this->context_query( $request )
            ->select( [ 'posts.ID' ] )
            ->order_by( $sort[0], $sort[1] )
            ->order_by( 'posts.ID', 'desc' )
            ->paginate( $page, $per_page, 1 );

        $ids = [];
        foreach ( $paginator->items() as $post ) {
            $ids[] = (int) $post->ID;
        }

        return [
            'ids'       => $ids,
            'page'      => $page,
            'per_page'  => $per_page,
            'total'     => (int) $paginator->total(),
            'last_page' => max( 1, (int) $paginator->last_page() ),
        ];
    }

    /**
     * The facets a filters request asks for (`facets[]`, at most MAX_FACETS), with counts
     * for the request's context, plus the sort orders this store supports.
     *
     * Cached in the object cache; a context without search text or a selection (what a
     * filter opens with) is also kept as a transient, so the number of transients stays
     * bounded by the store's categories and tags.
     *
     * @param WP_REST_Request $request
     * @return ProductFiltersDTO
     */
    public function filters( WP_REST_Request $request ): ProductFiltersDTO {
        $dto       = ( new ProductFiltersDTO() )->set_sort_options( $this->sort_options() );
        $facet_ids = $this->requested_facet_ids( $request );
        $context   = $this->context_cache_params( $request );
        $cache_key = 'craf_appna_pf_' . md5( (string) wp_json_encode( [ $this->catalog->post_type(), $facet_ids, $context ] ) );
        $persist   = $context['search'] === '' && empty( $context['values'] ) && empty( $context['ranges'] );

        $cached = wp_cache_get( $cache_key, self::FACETS_CACHE_GROUP );
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

        wp_cache_set( $cache_key, $facets, self::FACETS_CACHE_GROUP, self::FACETS_CACHE_TTL );
        if ( $persist ) {
            set_transient( $cache_key, $facets, self::FACETS_CACHE_TTL );
        }

        return $dto->set_facets( $facets );
    }

    /**
     * What a merchant can add as filter rows in the app builder: extra taxonomies,
     * attribute taxonomies and custom fields. Custom fields outside the allow-list
     * are listed with `enabled: false` (filtering by them does nothing until the site
     * allows them, see filterable_meta_keys()).
     *
     * @return ProductFilterSourceDTO[]
     */
    public function filter_sources(): array {
        $sources = [];

        foreach ( $this->catalog->extra_taxonomies() as $taxonomy => $label ) {
            $sources[] = $this->source( 'taxonomy', $taxonomy, $label, 'choice', true );
        }
        foreach ( $this->catalog->attribute_taxonomies() as $taxonomy => $label ) {
            $sources[] = $this->source( 'attribute', $taxonomy, $label, 'choice', true );
        }

        $allowed = $this->filterable_meta_keys();
        foreach ( $allowed as $key ) {
            $sources[] = $this->source( 'meta', $key, $this->meta_label( $key ), $this->meta_value_type( $key ), true );
        }
        foreach ( array_diff( $this->discover_meta_keys(), $allowed ) as $key ) {
            $sources[] = $this->source( 'meta', $key, $this->meta_label( $key ), $this->meta_value_type( $key ), false );
        }

        return $sources;
    }

    /**
     * Sort tokens this store supports, in ProductFiltersDTO::SORT_TOKENS order.
     *
     * @return string[]
     */
    private function sort_options(): array {
        return array_values( array_intersect( ProductFiltersDTO::SORT_TOKENS, array_keys( $this->catalog->sorts() ) ) );
    }

    private function source( string $source, string $key, string $label, string $value_type, bool $enabled ): ProductFilterSourceDTO {
        return ( new ProductFilterSourceDTO() )->set_source( $source )->set_key( $key )->set_label( $label )
            ->set_value_type( $value_type )->set_enabled( $enabled );
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
        foreach ( is_array( $value ) ? $value : [ $value ] as $item ) {
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
                $result[ $facet_id ] = $list;
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
            foreach ( [ 'min', 'max' ] as $bound ) {
                if ( isset( $range[ $bound ] ) && is_numeric( $range[ $bound ] ) ) {
                    $bounds[ $bound ] = (float) $range[ $bound ];
                }
            }
            if ( ! empty( $bounds ) ) {
                $result[ $facet_id ] = $bounds;
            }
        }
        return $result;
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
     * The request params that narrow the product context, normalized for a cache key.
     *
     * @return array{search: string, categories: int[], tags: int[], in_stock: bool, values: array, ranges: array}
     */
    private function context_cache_params( WP_REST_Request $request ): array {
        return [
            'search'     => $this->scalar( $request->get_param( 'search' ) ),
            'categories' => $this->positive_ids( $request->get_param( 'categories' ) ),
            'tags'       => $this->positive_ids( $request->get_param( 'tags' ) ),
            'in_stock'   => $this->flag( $request->get_param( 'in_stock' ) ),
            'values'     => $this->selected_values( $request ),
            'ranges'     => $this->selected_ranges( $request ),
        ];
    }

    // -------------------------------------------------------------------------
    // Narrowing
    // -------------------------------------------------------------------------

    /**
     * Products matching the request's page context and filter selection.
     *
     * @param WP_REST_Request $request
     * @param string|null $exclude_facet A facet whose own selection is skipped (to count that facet's options).
     * @return Builder
     */
    private function context_query( WP_REST_Request $request, ?string $exclude_facet = null ): Builder {
        $search = $this->scalar( $request->get_param( 'search' ) );
        $query  = $this->base( $search !== '' );

        $categories = $this->positive_ids( $request->get_param( 'categories' ) );
        if ( ! empty( $categories ) ) {
            $this->where_has_terms( $query, $this->catalog->category_taxonomy(), $this->with_descendants( $categories, $this->catalog->category_taxonomy() ) );
        }

        $tags         = $this->positive_ids( $request->get_param( 'tags' ) );
        $tag_taxonomy = $this->catalog->tag_taxonomy();
        if ( ! empty( $tags ) && $tag_taxonomy ) {
            $this->where_has_terms( $query, $tag_taxonomy, $tags );
        }

        if ( $search !== '' ) {
            global $wpdb;
            $query->where( 'posts.post_title', 'like', '%' . $wpdb->esc_like( $search ) . '%' );
        }

        if ( $this->flag( $request->get_param( 'in_stock' ) ) ) {
            $query->where_raw( $this->catalog->in_stock_sql() );
        }

        foreach ( $this->selected_values( $request ) as $facet_id => $values ) {
            if ( $facet_id !== $exclude_facet ) {
                $this->apply_values( $query, $facet_id, $values );
            }
        }
        foreach ( $this->selected_ranges( $request ) as $facet_id => $range ) {
            if ( $facet_id !== $exclude_facet ) {
                $this->apply_range( $query, $facet_id, $range );
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
        if ( $facet_id === 'availability' ) {
            if ( in_array( 'in_stock', $values, true ) ) {
                $query->where_raw( $this->catalog->in_stock_sql() );
            }
            if ( in_array( 'on_sale', $values, true ) ) {
                $query->where_raw( $this->catalog->on_sale_sql() );
            }
            return;
        }

        $taxonomy = $this->term_facet_taxonomy( $facet_id );
        if ( $taxonomy ) {
            $this->where_term_slugs( $query, $taxonomy, $values );
            return;
        }

        $key = $this->meta_facet_key( $facet_id );
        if ( $key !== null ) {
            $this->where_meta( $query, $key, [ 'values' => $values ] );
        }
    }

    /**
     * Narrow by a range facet's bounds (currency units for price, stars for rating).
     *
     * @param array{min?: float, max?: float} $range
     */
    private function apply_range( Builder $query, string $facet_id, array $range ): void {
        if ( $facet_id === 'price' ) {
            // A product's price span overlaps the chosen range.
            $divisor = $this->catalog->price_divisor();
            if ( isset( $range['min'] ) ) {
                $query->where_raw( $this->catalog->price_max_sql() . ' >= %f', [ $range['min'] * $divisor ] );
            }
            if ( isset( $range['max'] ) ) {
                $query->where_raw( $this->catalog->price_min_sql() . ' <= %f', [ $range['max'] * $divisor ] );
            }
            return;
        }

        if ( $facet_id === 'rating' ) {
            $rating = $this->catalog->rating_sql();
            if ( $rating && isset( $range['min'] ) ) {
                $query->where_raw( $rating . ' >= %f', [ $range['min'] ] );
            }
            return;
        }

        $key = $this->meta_facet_key( $facet_id );
        if ( $key !== null ) {
            $this->where_meta( $query, $key, $range );
        }
    }

    /**
     * The taxonomy behind a term facet id, or null when it names none this store allows.
     */
    private function term_facet_taxonomy( string $facet_id ): ?string {
        if ( $facet_id === 'category' ) {
            return $this->catalog->category_taxonomy();
        }
        if ( $facet_id === 'tag' ) {
            return $this->catalog->tag_taxonomy();
        }
        if ( str_starts_with( $facet_id, 'taxonomy:' ) ) {
            $taxonomy = substr( $facet_id, 9 );
            return isset( $this->catalog->extra_taxonomies()[ $taxonomy ] ) ? $taxonomy : null;
        }
        if ( str_starts_with( $facet_id, 'attribute:' ) ) {
            $taxonomy = substr( $facet_id, 10 );
            return isset( $this->catalog->attribute_taxonomies()[ $taxonomy ] ) ? $taxonomy : null;
        }
        return null;
    }

    /**
     * The custom field behind a `meta:<key>` facet id, or null when it isn't allowed.
     */
    private function meta_facet_key( string $facet_id ): ?string {
        if ( ! str_starts_with( $facet_id, 'meta:' ) ) {
            return null;
        }
        $key = substr( $facet_id, 5 );
        return in_array( $key, $this->filterable_meta_keys(), true ) ? $key : null;
    }

    /**
     * Keep products with any of the term slugs (and, in a hierarchical taxonomy, their children).
     *
     * @param string[] $slugs
     */
    private function where_term_slugs( Builder $query, string $taxonomy, array $slugs ): void {
        $slugs    = array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );
        $term_ids = empty( $slugs ) ? [] : get_terms(
            [
                'taxonomy'   => $taxonomy,
                'slug'       => $slugs,
                'fields'     => 'ids',
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
            // The requested option(s) don't exist, so nothing can match.
            $query->where( 'posts.ID', '=', 0 );
            return;
        }

        $this->where_has_terms( $query, $taxonomy, $this->with_descendants( array_map( 'intval', $term_ids ), $taxonomy ) );
    }

    /**
     * `$term_ids` plus their descendants, for a hierarchical taxonomy.
     *
     * @param int[] $term_ids
     * @return int[]
     */
    private function with_descendants( array $term_ids, string $taxonomy ): array {
        if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
            return $term_ids;
        }
        $all = $term_ids;
        foreach ( $term_ids as $term_id ) {
            $children = get_term_children( $term_id, $taxonomy );
            if ( ! is_wp_error( $children ) ) {
                $all = array_merge( $all, array_map( 'intval', $children ) );
            }
        }
        return array_values( array_unique( $all ) );
    }

    /**
     * Keep products that have any of `$term_ids` in `$taxonomy`.
     *
     * @param int[] $term_ids
     */
    private function where_has_terms( Builder $query, string $taxonomy, array $term_ids ): void {
        $query->where_has(
            'terms', function( $q ) use ( $taxonomy, $term_ids ) {
                $q->where( 'taxonomy', $taxonomy )
                    ->where_in( 'term_id', array_values( $term_ids ) );
            }
        );
    }

    /**
     * Keep products whose custom field `$key` matches `$constraint`: any of `values`
     * (serialized multi-choice ACF values matched by their quoted entry), and/or a
     * numeric `min` / `max`. Values are bound as-is, so they match stored values exactly.
     *
     * @param array $constraint
     */
    private function where_meta( Builder $query, string $key, array $constraint ): void {
        global $wpdb;
        $conditions = [];
        $bindings   = [ $key ];

        $values = $this->scalar_list( $constraint['values'] ?? [] );
        if ( ! empty( $values ) ) {
            $acf_field = $this->acf_fields()[ $key ] ?? null;
            if ( $acf_field && $acf_field['type'] === 'true_false' ) {
                $values = self::TRUTHY_META_VALUES;
            }
            $matches = [];
            foreach ( $values as $value ) {
                $matches[]  = 'pm.meta_value = %s';
                $bindings[] = $value;
                if ( $acf_field && $acf_field['multiple'] ) {
                    $matches[]  = 'pm.meta_value LIKE %s';
                    $bindings[] = '%"' . $wpdb->esc_like( $value ) . '"%';
                }
            }
            $conditions[] = '(' . implode( ' OR ', $matches ) . ')';
        }

        foreach ( [ 'min' => '>=', 'max' => '<=' ] as $bound => $operator ) {
            if ( isset( $constraint[ $bound ] ) && is_numeric( $constraint[ $bound ] ) ) {
                $conditions[] = "CAST(pm.meta_value AS DECIMAL(20,4)) {$operator} %f";
                $bindings[]   = (float) $constraint[ $bound ];
            }
        }

        if ( empty( $conditions ) ) {
            return;
        }

        $query->where_raw(
            "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = posts.ID AND pm.meta_key = %s AND " . implode( ' AND ', $conditions ) . ')',
            $bindings
        );
    }

    // -------------------------------------------------------------------------
    // Facets
    // -------------------------------------------------------------------------

    /**
     * Build one facet by id, or null when the id is unknown, not allowed, or has
     * nothing to offer in the current context.
     */
    private function build_facet( string $facet_id, WP_REST_Request $request ): ?ProductFacetDTO {
        if ( $facet_id === 'price' ) {
            return $this->build_price_facet( $request );
        }
        if ( $facet_id === 'rating' ) {
            return $this->build_rating_facet( $request );
        }
        if ( $facet_id === 'availability' ) {
            return $this->build_availability_facet( $request );
        }

        $taxonomy = $this->term_facet_taxonomy( $facet_id );
        if ( $taxonomy ) {
            return $this->build_term_facet( $facet_id, $taxonomy, $this->taxonomy_label( $taxonomy ), $request );
        }

        $key = $this->meta_facet_key( $facet_id );
        return $key === null ? null : $this->build_meta_facet( $facet_id, $key, $request );
    }

    private function taxonomy_label( string $taxonomy ): string {
        $labels = $this->catalog->attribute_taxonomies() + $this->catalog->extra_taxonomies();
        if ( isset( $labels[ $taxonomy ] ) ) {
            return $labels[ $taxonomy ];
        }
        $object = get_taxonomy( $taxonomy );
        return $object ? (string) $object->labels->singular_name : $taxonomy;
    }

    private function build_price_facet( WP_REST_Request $request ): ?ProductFacetDTO {
        $query = $this->context_query( $request, 'price' );
        $min   = ( clone $query )->min( $this->catalog->price_min_sql() );
        $max   = $query->max( $this->catalog->price_max_sql() );
        if ( $min === null || $max === null ) {
            return null;
        }

        $divisor = $this->catalog->price_divisor();
        return ( new ProductFacetDTO() )->set_id( 'price' )->set_label( __( 'Price', 'appnatively' ) )
            ->set_kind( ProductFacetDTO::KIND_RANGE )->set_min( (float) $min / $divisor )->set_max( (float) $max / $divisor );
    }

    private function build_rating_facet( WP_REST_Request $request ): ?ProductFacetDTO {
        $rated = $this->catalog->rated_sql();
        if ( ! $this->catalog->rating_sql() || ! $rated || ! $this->context_query( $request, 'rating' )->where_raw( $rated )->exists() ) {
            return null;
        }

        return ( new ProductFacetDTO() )->set_id( 'rating' )->set_label( __( 'Rating', 'appnatively' ) )
            ->set_kind( ProductFacetDTO::KIND_RANGE )->set_min( 0 )->set_max( 5 );
    }

    private function build_availability_facet( WP_REST_Request $request ): ProductFacetDTO {
        $count = fn( string $condition ): int => (int) $this->context_query( $request, 'availability' )->where_raw( $condition )->count();

        return ( new ProductFacetDTO() )->set_id( 'availability' )->set_label( __( 'Availability', 'appnatively' ) )
            ->set_kind( ProductFacetDTO::KIND_CHOICE )
            ->set_options(
                [
                    $this->facet_option( 'in_stock', __( 'In stock', 'appnatively' ), $count( $this->catalog->in_stock_sql() ) ),
                    $this->facet_option( 'on_sale', __( 'On sale', 'appnatively' ), $count( $this->catalog->on_sale_sql() ) ),
                ]
            );
    }

    /**
     * Options of one taxonomy with product counts. Its own selection is left out of the
     * context, so counts show what adding another option would give (OR within a facet).
     *
     * In a hierarchical taxonomy, picking a term includes its children (see
     * where_term_slugs()), so parent terms are listed even without products of their
     * own, and counted with their descendants.
     */
    private function build_term_facet( string $facet_id, string $taxonomy, string $label, WP_REST_Request $request ): ?ProductFacetDTO {
        $rows = $this->context_query( $request, $facet_id )
            ->join( 'term_relationships', 'posts.ID', '=', 'term_relationships.object_id' )
            ->join( 'term_taxonomy', 'term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id' )
            ->join( 'terms', 'term_taxonomy.term_id', '=', 'terms.term_id' )
            ->where( 'term_taxonomy.taxonomy', $taxonomy )
            ->select( [ 'terms.term_id as term_id', 'terms.slug as value', 'terms.name as label', 'COUNT(DISTINCT posts.ID) as product_count' ] )
            ->group_by( [ 'terms.term_id', 'terms.slug', 'terms.name' ] )
            ->order_by( 'terms.name', 'asc' )
            ->limit( self::MAX_FACET_OPTIONS )
            ->get();

        $terms = [];
        foreach ( $rows as $row ) {
            $terms[ (int) $row->term_id ] = [ (string) $row->value, (string) $row->label, (int) $row->product_count ];
        }

        if ( ! empty( $terms ) && is_taxonomy_hierarchical( $taxonomy ) ) {
            $parents = [];
            foreach ( array_keys( $terms ) as $term_id ) {
                foreach ( get_ancestors( $term_id, $taxonomy, 'taxonomy' ) as $ancestor_id ) {
                    $parents[ (int) $ancestor_id ] = true;
                }
                $children = get_term_children( $term_id, $taxonomy );
                if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
                    $parents[ $term_id ] = true;
                }
            }
            foreach ( array_keys( $parents ) as $parent_id ) {
                $term = get_term( $parent_id, $taxonomy );
                if ( ! $term || is_wp_error( $term ) ) {
                    continue;
                }
                $query = $this->context_query( $request, $facet_id );
                $this->where_has_terms( $query, $taxonomy, $this->with_descendants( [ $parent_id ], $taxonomy ) );
                $terms[ $parent_id ] = [ (string) $term->slug, (string) $term->name, (int) $query->count() ];
            }
            uasort( $terms, fn( $a, $b ) => strcasecmp( $a[1], $b[1] ) );
        }

        $options = [];
        foreach ( $terms as [ $value, $option_label, $count ] ) {
            if ( $count > 0 ) {
                $options[] = $this->facet_option( $value, $option_label, $count );
            }
        }

        if ( empty( $options ) ) {
            return null;
        }

        return ( new ProductFacetDTO() )->set_id( $facet_id )->set_label( $label )
            ->set_kind( ProductFacetDTO::KIND_CHOICE )->set_options( array_slice( $options, 0, self::MAX_FACET_OPTIONS ) );
    }

    /**
     * A custom field's facet: a range for numbers, a toggle for true/false, or its
     * distinct values (ACF choices with their labels) with counts.
     */
    private function build_meta_facet( string $facet_id, string $key, WP_REST_Request $request ): ?ProductFacetDTO {
        $acf_field  = $this->acf_fields()[ $key ] ?? null;
        $value_type = $this->meta_value_type( $key );
        $label      = $this->meta_label( $key );
        $facet      = ( new ProductFacetDTO() )->set_id( $facet_id )->set_label( $label );

        // How many products in context (this facet's own selection left out) match one value.
        $count_matching = function ( string $value ) use ( $request, $facet_id, $key ): int {
            $query = $this->context_query( $request, $facet_id );
            $this->where_meta( $query, $key, [ 'values' => [ $value ] ] );
            return (int) $query->count();
        };

        $query = $this->context_query( $request, $facet_id )
            ->join( 'postmeta as facet_meta', 'posts.ID', '=', 'facet_meta.post_id' )
            ->where( 'facet_meta.meta_key', $key )
            ->where( 'facet_meta.meta_value', '!=', '' );

        if ( $value_type === 'number' ) {
            $cast = 'CAST(facet_meta.meta_value AS DECIMAL(20,4))';
            $min  = ( clone $query )->min( $cast );
            $max  = $query->max( $cast );
            if ( $min === null || $max === null ) {
                return null;
            }
            return $facet->set_kind( ProductFacetDTO::KIND_RANGE )->set_min( (float) $min )->set_max( (float) $max );
        }

        if ( $value_type === 'boolean' ) {
            return $facet->set_kind( ProductFacetDTO::KIND_TOGGLE )->set_options( [ $this->facet_option( '1', $label, $count_matching( '1' ) ) ] );
        }

        $options = [];
        if ( $acf_field && $acf_field['multiple'] ) {
            // Multi-choice ACF values are stored serialized, so they can't be grouped: count each choice.
            foreach ( array_slice( $acf_field['choices'], 0, self::MAX_FACET_OPTIONS, true ) as $value => $choice_label ) {
                $count = $count_matching( (string) $value );
                if ( $count > 0 ) {
                    $options[] = $this->facet_option( (string) $value, (string) $choice_label, $count );
                }
            }
        } else {
            $rows    = $query->select( [ 'facet_meta.meta_value as value', 'COUNT(DISTINCT posts.ID) as product_count' ] )
                ->group_by( [ 'facet_meta.meta_value' ] )
                ->order_by( 'facet_meta.meta_value', 'asc' )
                ->limit( self::MAX_FACET_OPTIONS )
                ->get();
            $choices = $acf_field['choices'] ?? [];
            foreach ( $rows as $row ) {
                $value     = (string) $row->value;
                $options[] = $this->facet_option( $value, (string) ( $choices[ $value ] ?? $value ), (int) $row->product_count );
            }
        }

        return empty( $options ) ? null : $facet->set_kind( ProductFacetDTO::KIND_CHOICE )->set_options( $options );
    }

    private function facet_option( string $value, string $label, int $count ): ProductFacetOptionDTO {
        return ( new ProductFacetOptionDTO() )->set_value( $value )->set_label( $label )->set_count( $count );
    }

    // -------------------------------------------------------------------------
    // Custom fields
    // -------------------------------------------------------------------------

    /**
     * Custom fields shoppers may filter by. Filtering by a field reveals its values (a
     * request can probe them), so only ACF fields placed on products are allowed by
     * default; a site allows other keys with the `craf_appna_product_filter_meta_keys`
     * filter.
     *
     * @return string[]
     */
    private function filterable_meta_keys(): array {
        if ( $this->meta_keys === null ) {
            /**
             * Filters which product custom fields the app may filter by.
             *
             * @param string[] $keys      Meta keys: the ACF fields placed on the product post type.
             * @param string   $post_type The store's product post type.
             */
            $keys            = apply_filters( 'craf_appna_product_filter_meta_keys', array_keys( $this->acf_fields() ), $this->catalog->post_type() );
            $this->meta_keys = array_values( array_unique( array_filter( (array) $keys, fn( $key ) => is_string( $key ) && $key !== '' ) ) );
        }
        return $this->meta_keys;
    }

    /**
     * Public (non-underscore) meta keys stored on products, for the builder to list.
     *
     * @return string[]
     */
    private function discover_meta_keys(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- builder-only listing.
        $keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type = %s AND pm.meta_key NOT LIKE %s
                LIMIT %d",
                $this->catalog->post_type(),
                $wpdb->esc_like( '_' ) . '%',
                self::MAX_DISCOVERED_META_KEYS
            )
        );
        return array_values( array_diff( (array) $keys, $this->catalog->internal_meta_keys() ) );
    }

    /**
     * Top-level ACF fields of field groups placed on the product post type, keyed by name.
     * Nested fields (repeaters, groups) store prefixed meta keys and are left out.
     *
     * @return array<string, array{label: string, type: string, choices: array, multiple: bool}>
     */
    private function acf_fields(): array {
        if ( $this->acf_fields !== null ) {
            return $this->acf_fields;
        }

        $this->acf_fields = [];
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return $this->acf_fields;
        }

        foreach ( acf_get_field_groups( [ 'post_type' => $this->catalog->post_type() ] ) as $group ) {
            foreach ( (array) acf_get_fields( $group ) as $field ) {
                if ( empty( $field['name'] ) || ! in_array( $field['type'] ?? '', self::ACF_FILTERABLE_TYPES, true ) ) {
                    continue;
                }
                $this->acf_fields[ $field['name'] ] = [
                    'label'    => (string) ( $field['label'] ?? $field['name'] ),
                    'type'     => (string) $field['type'],
                    'choices'  => is_array( $field['choices'] ?? null ) ? $field['choices'] : [],
                    'multiple' => $field['type'] === 'checkbox' || ! empty( $field['multiple'] ),
                ];
            }
        }

        return $this->acf_fields;
    }

    private function meta_label( string $key ): string {
        $field = $this->acf_fields()[ $key ] ?? null;
        return $field ? $field['label'] : ucwords( str_replace( [ '_', '-' ], ' ', $key ) );
    }

    /**
     * `number`, `boolean` or `choice`: from the ACF field type when there is one,
     * otherwise from a sample of stored values.
     */
    private function meta_value_type( string $key ): string {
        $field = $this->acf_fields()[ $key ] ?? null;
        if ( $field ) {
            if ( in_array( $field['type'], [ 'number', 'range' ], true ) ) {
                return 'number';
            }
            return $field['type'] === 'true_false' ? 'boolean' : 'choice';
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small sample; facets are cached by the caller.
        $sample = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value <> ''
                LIMIT 20",
                $this->catalog->post_type(),
                $key
            )
        );

        if ( ! empty( $sample ) && count( array_filter( $sample, 'is_numeric' ) ) === count( $sample ) ) {
            return 'number';
        }
        return 'choice';
    }
}
