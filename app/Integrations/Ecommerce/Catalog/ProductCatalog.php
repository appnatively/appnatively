<?php

namespace Crafium\AppNatively\App\Integrations\Ecommerce\Catalog;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\WpMVC\Database\Query\Builder;

/**
 * Where one ecommerce plugin keeps what the product list and filter read:
 * its post type, taxonomies, and SQL for price, stock, sale and rating.
 * ProductQuery builds every product list and facet from this, so each store
 * only describes its data layout.
 *
 * SQL expressions run inside a query on the posts table aliased `posts`
 * (after prepare() added its joins). They are constant SQL: no user input
 * and no literal `%` (the builder prepares the final statement).
 */
abstract class ProductCatalog {
    /**
     * The products' post type.
     */
    abstract public function post_type(): string;

    /**
     * The taxonomy behind the `category` facet and a page's `categories[]`.
     */
    abstract public function category_taxonomy(): string;

    /**
     * The taxonomy behind the `tag` facet and a list's `tags[]`, when the store has tags.
     */
    public function tag_taxonomy(): ?string {
        return null;
    }

    /**
     * Attribute taxonomies (`attribute:<name>` facets), name => label.
     *
     * @return array<string, string>
     */
    public function attribute_taxonomies(): array {
        return [];
    }

    /**
     * Other taxonomies a shopper may filter by (`taxonomy:<name>` facets, e.g. brands), name => label.
     *
     * @return array<string, string>
     */
    public function extra_taxonomies(): array {
        return [];
    }

    /**
     * Public-looking meta keys the store uses internally: never offered as custom-field filters.
     *
     * @return string[]
     */
    public function internal_meta_keys(): array {
        return [];
    }

    /**
     * Add the joins the SQL expressions below rely on.
     */
    public function prepare( Builder $query ): void {}

    /**
     * Keep only products the storefront lists (catalog visibility and the like).
     */
    public function where_visible( Builder $query, bool $searching ): void {}

    /**
     * A product's lowest price, in stored units.
     */
    abstract public function price_min_sql(): string;

    /**
     * A product's highest price, in stored units.
     */
    abstract public function price_max_sql(): string;

    /**
     * Stored price units per currency unit (100 for stores that keep cents).
     */
    public function price_divisor(): int {
        return 1;
    }

    /**
     * Condition: the product can be bought now.
     */
    abstract public function in_stock_sql(): string;

    /**
     * Condition: the product is discounted.
     */
    abstract public function on_sale_sql(): string;

    /**
     * A product's average star rating (0-5), when the store has reviews.
     */
    public function rating_sql(): ?string {
        return null;
    }

    /**
     * Condition: the product has at least one rating.
     */
    public function rated_sql(): ?string {
        return null;
    }

    /**
     * A product's sales count, when the store tracks it.
     */
    public function popularity_sql(): ?string {
        return null;
    }

    /**
     * Sort token => [expression, direction], for the tokens this store supports.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function sorts(): array {
        $sorts = [
            'relevance'  => [ 'posts.post_date', 'desc' ],
            'newest'     => [ 'posts.post_date', 'desc' ],
            'oldest'     => [ 'posts.post_date', 'asc' ],
            'price_low'  => [ $this->price_min_sql(), 'asc' ],
            'price_high' => [ $this->price_max_sql(), 'desc' ],
            'name_az'    => [ 'posts.post_title', 'asc' ],
            'name_za'    => [ 'posts.post_title', 'desc' ],
        ];
        if ( $this->rating_sql() ) {
            $sorts['rating'] = [ $this->rating_sql(), 'desc' ];
        }
        if ( $this->popularity_sql() ) {
            $sorts['popularity'] = [ $this->popularity_sql(), 'desc' ];
        }
        return $sorts;
    }
}
