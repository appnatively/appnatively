<?php

namespace Crafium\AppNatively\App\Filtering;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\WpMVC\Database\Query\Builder;

/**
 * Where one plugin keeps what a list and its filter read: post types,
 * taxonomies, custom fields and the SQL behind ranges, flags and sorts.
 * CatalogQuery builds every list and facet from this, so each plugin only
 * describes its data layout.
 *
 * SQL expressions run inside a query on the posts table aliased `posts`
 * (after prepare() added its joins). They are constant SQL: no user input
 * and no literal `%` (the builder prepares the final statement).
 */
abstract class Catalog {
    /**
     * The post types listed.
     *
     * @return string[]
     */
    abstract public function post_types(): array;

    /**
     * Term facets by id (`category`, `tag`, `location`) => taxonomy. The same taxonomies
     * narrow a page's context through `categories[]`, `tags[]` and `locations[]`.
     *
     * @return array<string, string>
     */
    abstract public function term_facets(): array;

    /**
     * Other filterable taxonomies by facet prefix: `taxonomy` (brands, regions and the
     * like) and `attribute` (product attributes), each name => label.
     *
     * @return array<string, array<string, string>>
     */
    public function taxonomy_groups(): array {
        return [];
    }

    /**
     * Custom fields the plugin defines itself (`field:<key>` facets), key => Field.
     *
     * @return array<string, Field>
     */
    public function fields(): array {
        return [];
    }

    /**
     * Public-looking meta keys the plugin uses internally: never offered as custom-field filters.
     *
     * @return string[]
     */
    public function internal_meta_keys(): array {
        return [];
    }

    /**
     * Whether a public-looking meta key is internal to the plugin (see internal_meta_keys()).
     */
    public function is_internal_meta_key( string $key ): bool {
        return in_array( $key, $this->internal_meta_keys(), true );
    }

    /**
     * Add the joins the SQL expressions rely on.
     */
    public function prepare( Builder $query ): void {}

    /**
     * Keep only posts the site lists (catalog visibility and the like).
     */
    public function where_visible( Builder $query, bool $searching ): void {}

    /**
     * Columns search text is matched against.
     *
     * @return string[]
     */
    public function search_columns(): array {
        return [ 'posts.post_title' ];
    }

    /**
     * Range facets by id (`price`, `rating`).
     *
     * @return array<string, RangeFacet>
     */
    public function ranges(): array {
        return [];
    }

    /**
     * Flag facets by id: a label and options, value => [label, condition]. Selected
     * options must all hold. A condition is SQL, or a callable returning SQL for one
     * that is costly to build (only called when used).
     *
     * @return array<string, array{label: string, options: array<string, array{0: string, 1: string|callable}>}>
     */
    public function flags(): array {
        return [];
    }

    /**
     * Request flags a list sets for itself (`in_stock`, `featured`) => condition.
     *
     * @return array<string, string>
     */
    public function context_flags(): array {
        return [];
    }

    /**
     * Flag facets whose counts change with the time of day (they are cached briefly).
     *
     * @return string[]
     */
    public function volatile_facets(): array {
        return [];
    }

    /**
     * A post's latitude and longitude, when the plugin stores a location.
     *
     * @return array{0: string, 1: string}|null
     */
    public function coordinates(): ?array {
        return null;
    }

    /**
     * A post's popularity (sales, views), when the plugin tracks it.
     */
    public function popularity_sql(): ?string {
        return null;
    }

    /**
     * Sort token => [expression, direction], for the tokens this catalog supports.
     * `distance` is resolved by CatalogQuery from coordinates() and the request.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function sorts(): array {
        $sorts = [
            'relevance' => [ 'posts.post_date', 'desc' ],
            'newest'    => [ 'posts.post_date', 'desc' ],
            'oldest'    => [ 'posts.post_date', 'asc' ],
            'name_az'   => [ 'posts.post_title', 'asc' ],
            'name_za'   => [ 'posts.post_title', 'desc' ],
        ];

        $ranges = $this->ranges();
        if ( isset( $ranges['price'] ) ) {
            $sorts['price_low']  = [ $ranges['price']->min_sql, 'asc' ];
            $sorts['price_high'] = [ $ranges['price']->max_sql, 'desc' ];
        }
        if ( isset( $ranges['rating'] ) ) {
            $sorts['rating'] = [ $ranges['rating']->max_sql, 'desc' ];
        }
        if ( $this->popularity_sql() ) {
            $sorts['popularity'] = [ $this->popularity_sql(), 'desc' ];
        }
        return $sorts;
    }
}
