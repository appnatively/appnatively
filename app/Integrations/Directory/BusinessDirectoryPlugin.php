<?php

namespace Crafium\AppNatively\App\Integrations\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Directory\CategoryDTO;
use Crafium\AppNatively\App\DTO\Directory\CategoryPaginatorDTO;
use Crafium\AppNatively\App\DTO\Directory\ListingDTO;
use Crafium\AppNatively\App\DTO\Directory\ListingPaginatorDTO;
use Crafium\AppNatively\App\DTO\Directory\TermDTO;
use Crafium\AppNatively\App\DTO\Directory\TermPaginatorDTO;
use Crafium\AppNatively\App\Integrations\Directory\Concerns\ListingIntegrationHelpers;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use WP_Post;
use WP_Query;

class BusinessDirectoryPlugin extends Provider {
    use ListingIntegrationHelpers;

    public function register() {}

    public function boot(): void {
        if ( ! $this->is_loaded() ) {
            return;
        }

        add_filter( "craf_appna_directory_business-directory-plugin_listings", [$this, "listings"], 10, 3 );
        add_filter( "craf_appna_directory_business-directory-plugin_listing", [$this, "listing"], 10, 3 );
        add_filter( "craf_appna_directory_business-directory-plugin_related_listings", [$this, "related_listings"], 10, 3 );
        add_filter( "craf_appna_directory_business-directory-plugin_reviews", [$this, "reviews"], 10, 2 );
        add_filter( "craf_appna_directory_business-directory-plugin_categories", [$this, "categories"], 10, 3 );
        add_filter( "craf_appna_directory_business-directory-plugin_category", [$this, "category"], 10, 3 );
        add_filter( "craf_appna_directory_business-directory-plugin_tags", [$this, "tags"], 10, 3 );
        add_filter( "craf_appna_directory_business-directory-plugin_locations", [$this, "locations"], 10, 3 );
        add_filter( "craf_appna_directory_business-directory-plugin_location", [$this, "location"], 10, 3 );
    }

    public function listings( ?ListingPaginatorDTO $listing_paginator, Request $request, array $fields = [] ): ListingPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $args     = [
            "post_type"      => $this->post_type(),
            "post_status"    => "publish",
            "has_password"   => false,
            "paged"          => $page,
            "posts_per_page" => $per_page,
            "s"              => sanitize_text_field( (string) $request->get_param( "search" ) ),
        ];

        $this->apply_sort_args( $args, (string) $request->get_param( "sort" ) );
        $this->apply_tax_filters( $args, $request );

        if ( filter_var( (string) $request->get_param( "isFeatured" ), FILTER_VALIDATE_BOOLEAN ) ) {
            $args["meta_query"][] = [
                "key"   => "_wpbdp[sticky]",
                "value" => "1",
            ];
        }

        $query = new WP_Query( $args );
        $items = [];
        foreach ( $query->posts as $post ) {
            if ( $post instanceof WP_Post ) {
                $items[] = $this->map_listing_to_dto( $post, $fields );
            }
        }

        return new ListingPaginatorDTO( $page, $per_page, (int) $query->found_posts, max( 1, (int) $query->max_num_pages ), $items );
    }

    public function listing( ?ListingDTO $listing, Request $request, array $fields = [] ): ?ListingDTO {
        $post = $this->get_listing_post( (int) craf_appna_route_param( $request, "id" ) );
        return $post ? $this->map_listing_to_dto( $post, $fields ) : null;
    }

    public function related_listings( ?ListingPaginatorDTO $listing_paginator, Request $request, array $fields = [] ): ListingPaginatorDTO {
        $listing_id = (int) craf_appna_route_param( $request, "id" );
        $page       = (int) $request->get_param( "page" ) ?: 1;
        $per_page   = (int) $request->get_param( "per_page" ) ?: 10;

        if ( ! $this->get_listing_post( $listing_id ) ) {
            return new ListingPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $tax_query = ["relation" => "OR"];
        foreach ( [$this->category_taxonomy(), $this->tag_taxonomy()] as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $ids = wp_get_post_terms( $listing_id, $taxonomy, ["fields" => "ids"] );
            if ( ! is_wp_error( $ids ) && ! empty( $ids ) ) {
                $tax_query[] = [
                    "taxonomy" => $taxonomy,
                    "field"    => "term_id",
                    "terms"    => array_map( "intval", $ids ),
                ];
            }
        }

        if ( 1 === count( $tax_query ) ) {
            return new ListingPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $query = new WP_Query(
            [
                "post_type"      => $this->post_type(),
                "post_status"    => "publish",
                "has_password"   => false,
                "paged"          => $page,
                "posts_per_page" => $per_page,
                //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- small, bounded exclusion of the current listing from its own "related" query.
                "post__not_in"   => [$listing_id],
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering listings by taxonomy is the point of this query.
                "tax_query"      => $tax_query,
                "orderby"        => "date",
                "order"          => "DESC",
            ]
        );
        $items = [];
        foreach ( $query->posts as $post ) {
            if ( $post instanceof WP_Post ) {
                $items[] = $this->map_listing_to_dto( $post, $fields );
            }
        }

        return new ListingPaginatorDTO( $page, $per_page, (int) $query->found_posts, max( 1, (int) $query->max_num_pages ), $items );
    }

    public function reviews( ?array $reviews, Request $request ): ?array {
        return $this->get_listing_post( (int) craf_appna_route_param( $request, "id" ) ) ? $this->empty_reviews( $request ) : null;
    }

    public function categories( ?CategoryPaginatorDTO $category_paginator, Request $request, array $fields = [] ): CategoryPaginatorDTO {
        return $this->query_categories( $this->category_taxonomy(), $request, $fields );
    }

    public function category( ?CategoryDTO $category, Request $request, array $fields = [] ): ?CategoryDTO {
        $term = get_term( (int) craf_appna_route_param( $request, "id" ), $this->category_taxonomy() );
        return ( $term && ! is_wp_error( $term ) ) ? $this->map_category_to_dto( $term, $fields ) : null;
    }

    public function tags( ?TermPaginatorDTO $tag_paginator, Request $request, array $fields = [] ): TermPaginatorDTO {
        return $this->query_terms( $this->tag_taxonomy(), $request, $fields );
    }

    public function locations( ?TermPaginatorDTO $location_paginator, Request $request, array $fields = [] ): TermPaginatorDTO {
        return $this->empty_term_paginator( $request );
    }

    public function location( ?TermDTO $location, Request $request, array $fields = [] ): ?TermDTO {
        return null;
    }

    private function is_loaded(): bool {
        return defined( "WPBDP_POST_TYPE" ) || function_exists( "wpbdp_get_listing" );
    }

    private function post_type(): string {
        return defined( "WPBDP_POST_TYPE" ) ? WPBDP_POST_TYPE : "wpbdp_listing";
    }

    private function category_taxonomy(): string {
        return defined( "WPBDP_CATEGORY_TAX" ) ? WPBDP_CATEGORY_TAX : "wpbdp_category";
    }

    private function tag_taxonomy(): string {
        return defined( "WPBDP_TAGS_TAX" ) ? WPBDP_TAGS_TAX : "wpbdp_tag";
    }

    private function get_listing_post( int $listing_id ): ?WP_Post {
        $post = get_post( $listing_id );
        if ( ! $post instanceof WP_Post || $post->post_type !== $this->post_type() || "publish" !== $post->post_status || craf_appna_is_post_password_protected( $post ) ) {
            return null;
        }

        return $post;
    }

    private function apply_sort_args( array &$args, string $sort ): void {
        $order_by = "date";
        $order    = "DESC";
        if ( "" !== $sort ) {
            if ( 0 === strpos( $sort, "-" ) ) {
                $order_by = ltrim( $sort, "-" );
                $order    = "DESC";
            } else {
                $order_by = $sort;
                $order    = "ASC";
            }
        }

        $map             = ["date" => "date", "title" => "title", "name" => "name", "id" => "ID"];
        $args["orderby"] = $map[$order_by] ?? "date";
        $args["order"]   = $order;
    }

    private function apply_tax_filters( array &$args, Request $request ): void {
        $tax_query = [];
        foreach ( ["categories" => $this->category_taxonomy(), "tags" => $this->tag_taxonomy()] as $key => $taxonomy ) {
            $ids = $this->positive_ids( $request->get_param( $key ) );
            if ( empty( $ids ) || ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }
            $tax_query[] = ["taxonomy" => $taxonomy, "field" => "term_id", "terms" => $ids];
        }
        if ( ! empty( $tax_query ) ) {
            //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering listings by taxonomy is the point of this query.
            $args["tax_query"] = $tax_query;
        }
    }

    private function map_listing_to_dto( WP_Post $post, array $fields ): ListingDTO {
        $dto = new ListingDTO();

        if ( in_array( "id", $fields, true ) ) {
            $dto->set_id( (int) $post->ID );
        }
        if ( in_array( "url", $fields, true ) ) {
            $dto->set_url( (string) get_permalink( $post ) );
        }
        if ( in_array( "title", $fields, true ) ) {
            $dto->set_title( get_the_title( $post ) );
        }
        if ( in_array( "slug", $fields, true ) ) {
            $dto->set_slug( (string) $post->post_name );
        }
        if ( in_array( "description", $fields, true ) ) {
            $dto->set_description( $this->apply_listing_content_filters( $post ) );
        }
        if ( in_array( "excerpt", $fields, true ) ) {
            $dto->set_excerpt( $this->get_listing_excerpt( $post ) );
        }
        if ( in_array( "status", $fields, true ) ) {
            $dto->set_status( (string) $post->post_status );
        }
        if ( in_array( "image", $fields, true ) ) {
            $dto->set_image( $this->get_listing_image( (int) $post->ID ) );
        }
        if ( in_array( "images", $fields, true ) ) {
            $dto->set_images( $this->get_listing_images( (int) $post->ID, in_array( "image", $fields, true ) ) );
        }
        if ( in_array( "views_count", $fields, true ) ) {
            $dto->set_views_count( (int) $this->get_meta_value( $post->ID, ["_wpbdp[views]", "views", "_views"] ) );
        }
        if ( in_array( "address", $fields, true ) ) {
            $dto->set_address( $this->get_meta_value( $post->ID, ["_wpbdp[address]", "address", "_address"] ) );
        }
        if ( in_array( "latitude", $fields, true ) ) {
            $dto->set_latitude( $this->normalize_coordinate( $this->get_meta_value( $post->ID, ["latitude", "_latitude", "lat"] ), -90, 90 ) );
        }
        if ( in_array( "longitude", $fields, true ) ) {
            $dto->set_longitude( $this->normalize_coordinate( $this->get_meta_value( $post->ID, ["longitude", "_longitude", "lng"] ), -180, 180 ) );
        }
        if ( in_array( "phone", $fields, true ) ) {
            $dto->set_phone( $this->get_meta_value( $post->ID, ["_wpbdp[phone]", "phone", "_phone"] ) );
        }
        if ( in_array( "email", $fields, true ) ) {
            $dto->set_email( $this->get_meta_value( $post->ID, ["_wpbdp[email]", "email", "_email"] ) );
        }
        if ( in_array( "website", $fields, true ) ) {
            $dto->set_website( $this->get_meta_value( $post->ID, ["_wpbdp[website]", "website", "_website"] ) );
        }
        if ( in_array( "favorite", $fields, true ) ) {
            $dto->set_favorite( false );
        }
        if ( in_array( "featured", $fields, true ) ) {
            $dto->set_featured( (bool) $this->get_meta_value( $post->ID, ["_wpbdp[sticky]", "sticky", "_sticky"] ) );
        }
        if ( in_array( "new", $fields, true ) ) {
            $dto->set_new( false );
        }
        if ( in_array( "popular", $fields, true ) ) {
            $dto->set_popular( false );
        }
        if ( in_array( "pricing", $fields, true ) ) {
            $dto->set_pricing( ["price" => $this->get_meta_value( $post->ID, ["price", "_price"] ), "price_type" => "", "price_range" => ""] );
        }
        if ( in_array( "categories", $fields, true ) ) {
            $dto->set_categories( $this->get_listing_terms( $post->ID, $this->category_taxonomy() ) );
        }
        if ( in_array( "locations", $fields, true ) ) {
            $dto->set_locations( [] );
        }
        if ( in_array( "tags", $fields, true ) ) {
            $dto->set_tags( $this->get_listing_terms( $post->ID, $this->tag_taxonomy() ) );
        }
        if ( in_array( "rating", $fields, true ) ) {
            $dto->set_rating( 0.0 );
        }

        return $dto;
    }

    private function query_categories( string $taxonomy, Request $request, array $fields ): CategoryPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new CategoryPaginatorDTO( $page, $per_page, 0, 1, [] );
        }
        $terms = $this->get_terms_page( $taxonomy, $request, $page, $per_page );
        $items = [];
        foreach ( $terms["items"] as $term ) {
            $items[] = $this->map_category_to_dto( $term, $fields );
        }
        return new CategoryPaginatorDTO( $page, $per_page, $terms["total"], $terms["last_page"], $items );
    }

    private function query_terms( string $taxonomy, Request $request, array $fields ): TermPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new TermPaginatorDTO( $page, $per_page, 0, 1, [] );
        }
        $terms = $this->get_terms_page( $taxonomy, $request, $page, $per_page );
        $items = [];
        foreach ( $terms["items"] as $term ) {
            $items[] = $this->map_term_to_dto( $term, $fields );
        }
        return new TermPaginatorDTO( $page, $per_page, $terms["total"], $terms["last_page"], $items );
    }

    private function get_terms_page( string $taxonomy, Request $request, int $page, int $per_page ): array {
        $sort  = sanitize_text_field( (string) $request->get_param( "sort" ) );
        $desc  = 0 === strpos( $sort, "-" );
        $key   = $desc ? ltrim( $sort, "-" ) : $sort;
        $map   = ["name" => "name", "id" => "term_id", "slug" => "slug", "count" => "count"];
        $args  = [
            "taxonomy"   => $taxonomy,
            "hide_empty" => false,
            "number"     => $per_page,
            "offset"     => ( $page - 1 ) * $per_page,
            "search"     => sanitize_text_field( (string) $request->get_param( "search" ) ),
            "orderby"    => $map[$key] ?? "name",
            "order"      => $desc ? "DESC" : "ASC",
        ];
        $items = get_terms( $args );
        $total = wp_count_terms( ["taxonomy" => $taxonomy, "hide_empty" => false, "search" => $args["search"]] );
        return [
            "items"     => is_wp_error( $items ) ? [] : $items,
            "total"     => is_wp_error( $total ) ? 0 : (int) $total,
            "last_page" => max( 1, (int) ceil( ( is_wp_error( $total ) ? 0 : (int) $total ) / $per_page ) ),
        ];
    }

    private function map_category_to_dto( $term, array $fields ): CategoryDTO {
        $dto = new CategoryDTO();
        if ( in_array( "id", $fields, true ) ) {
            $dto->set_id( (int) $term->term_id );
        }
        if ( in_array( "name", $fields, true ) ) {
            $dto->set_name( (string) $term->name );
        }
        if ( in_array( "slug", $fields, true ) ) {
            $dto->set_slug( (string) $term->slug );
        }
        if ( in_array( "description", $fields, true ) ) {
            $dto->set_description( (string) $term->description );
        }
        if ( in_array( "parent", $fields, true ) ) {
            $dto->set_parent( (int) $term->parent );
        }
        if ( in_array( "count", $fields, true ) ) {
            $dto->set_count( (int) $term->count );
        }
        if ( in_array( "image", $fields, true ) ) {
            $dto->set_image( $this->get_term_image( (int) $term->term_id ) );
        }
        return $dto;
    }

    private function map_term_to_dto( $term, array $fields ): TermDTO {
        $dto = new TermDTO();
        if ( in_array( "id", $fields, true ) ) {
            $dto->set_id( (int) $term->term_id );
        }
        if ( in_array( "name", $fields, true ) ) {
            $dto->set_name( (string) $term->name );
        }
        if ( in_array( "slug", $fields, true ) ) {
            $dto->set_slug( (string) $term->slug );
        }
        if ( in_array( "count", $fields, true ) ) {
            $dto->set_count( (int) $term->count );
        }
        return $dto;
    }

    private function get_listing_image( int $post_id ): array {
        $image_id = $this->get_listing_image_id( $post_id );
        return $image_id ? $this->map_attachment_image( $image_id ) : [];
    }

    private function get_listing_images( int $post_id, bool $exclude_cover = false ): array {
        $image_ids = $this->get_listing_image_ids( $post_id );
        if ( $exclude_cover ) {
            $cover_id  = $this->get_listing_image_id( $post_id );
            $image_ids = array_values( array_filter( $image_ids, fn( int $image_id ): bool => $image_id !== $cover_id ) );
        }

        return array_values( array_filter( array_map( [$this, "map_attachment_image"], $image_ids ) ) );
    }

    private function get_listing_image_id( int $post_id ): int {
        $listing = class_exists( "\\WPBDP_Listing" ) ? \WPBDP_Listing::get( $post_id ) : null;
        if ( $listing && method_exists( $listing, "get_thumbnail_id" ) ) {
            $image_id = (int) $listing->get_thumbnail_id();
            if ( $image_id > 0 ) {
                return $image_id;
            }
        }

        $image_id = (int) get_post_thumbnail_id( $post_id );
        if ( $image_id > 0 ) {
            return $image_id;
        }

        $image_ids = $this->get_listing_image_ids( $post_id );
        return $image_ids ? (int) reset( $image_ids ) : 0;
    }

    private function get_listing_image_ids( int $post_id ): array {
        $listing = class_exists( "\\WPBDP_Listing" ) ? \WPBDP_Listing::get( $post_id ) : null;
        if ( $listing && method_exists( $listing, "get_images" ) ) {
            return $this->positive_ids( $listing->get_images( "ids", true ) );
        }

        $image_ids    = $this->positive_ids( get_post_meta( $post_id, "_wpbdp[images]", true ) );
        $thumbnail_id = (int) get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id > 0 ) {
            array_unshift( $image_ids, $thumbnail_id );
        }

        return array_values( array_unique( array_filter( $image_ids ) ) );
    }

    private function map_attachment_image( int $image_id ): array {
        $src = wp_get_attachment_url( $image_id );
        return $src ? ["id" => (int) $image_id, "src" => esc_url_raw( (string) $src ), "alt" => sanitize_text_field( (string) get_post_meta( $image_id, "_wp_attachment_image_alt", true ) ), "title" => (string) get_the_title( $image_id )] : [];
    }

    private function get_term_image( int $term_id ): array {
        foreach ( ["thumbnail_id", "image", "_thumbnail_id"] as $key ) {
            $value = get_term_meta( $term_id, $key, true );
            $id    = is_numeric( $value ) ? (int) $value : 0;
            $src   = $id ? wp_get_attachment_url( $id ) : ( is_string( $value ) ? $value : "" );
            if ( $src && filter_var( $src, FILTER_VALIDATE_URL ) ) {
                return ["id" => $id, "src" => esc_url_raw( $src )];
            }
        }
        return [];
    }

    private function get_listing_terms( int $post_id, string $taxonomy ): array {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return [];
        }
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return [];
        }
        return array_map( fn( $term ): array => ["id" => (int) $term->term_id, "name" => (string) $term->name, "slug" => (string) $term->slug], $terms );
    }

    private function get_meta_value( int $post_id, array $keys ): string {
        foreach ( $keys as $key ) {
            $value = get_post_meta( $post_id, $key, true );
            if ( "" !== $value && null !== $value ) {
                return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : "";
            }
        }
        return "";
    }

    private function empty_reviews( Request $request ): array {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        return ["current_page" => $page, "per_page" => $per_page, "total" => 0, "last_page" => 1, "average_rating" => 0.0, "review_count" => 0, "rating_counts" => ["1" => 0, "2" => 0, "3" => 0, "4" => 0, "5" => 0], "items" => []];
    }
}
