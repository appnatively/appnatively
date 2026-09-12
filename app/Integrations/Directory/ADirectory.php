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
use WP_Comment;
use WP_Post;
use WP_Query;

class ADirectory extends Provider {
    use ListingIntegrationHelpers;

    private string $category_taxonomy = "adqs_category";

    private string $location_taxonomy = "adqs_location";

    private string $tag_taxonomy = "adqs_tags";

    public function register() {}

    public function boot(): void {
        if ( ! $this->is_loaded() ) {
            return;
        }

        add_filter( "craf_appna_directory_adirectory_listings", [$this, "listings"], 10, 3 );
        add_filter( "craf_appna_directory_adirectory_listing", [$this, "listing"], 10, 3 );
        add_filter( "craf_appna_directory_adirectory_related_listings", [$this, "related_listings"], 10, 3 );
        add_filter( "craf_appna_directory_adirectory_reviews", [$this, "reviews"], 10, 2 );
        add_filter( "craf_appna_directory_adirectory_categories", [$this, "categories"], 10, 3 );
        add_filter( "craf_appna_directory_adirectory_category", [$this, "category"], 10, 3 );
        add_filter( "craf_appna_directory_adirectory_tags", [$this, "tags"], 10, 3 );
        add_filter( "craf_appna_directory_adirectory_locations", [$this, "locations"], 10, 3 );
        add_filter( "craf_appna_directory_adirectory_location", [$this, "location"], 10, 3 );
    }

    public function listings( ?ListingPaginatorDTO $listing_paginator, Request $request, array $fields = [] ): ListingPaginatorDTO {
        $page       = (int) $request->get_param( "page" ) ?: 1;
        $per_page   = (int) $request->get_param( "per_page" ) ?: 10;
        $post_types = $this->post_types();

        if ( empty( $post_types ) ) {
            return new ListingPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $args = [
            "post_type"      => $post_types,
            "post_status"    => "publish",
            "has_password"   => false,
            "paged"          => $page,
            "posts_per_page" => $per_page,
            "s"              => sanitize_text_field( (string) $request->get_param( "search" ) ),
        ];

        $this->apply_sort_args( $args, (string) $request->get_param( "sort" ) );
        $this->apply_category_filter( $args, $request );

        if ( filter_var( (string) $request->get_param( "isFeatured" ), FILTER_VALIDATE_BOOLEAN ) ) {
            $args["meta_query"][] = [
                "key"   => "_is_featured",
                "value" => "yes",
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
        $post       = $this->get_listing_post( $listing_id );

        if ( ! $post || ! taxonomy_exists( $this->category_taxonomy ) ) {
            return new ListingPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $category_ids = wp_get_post_terms( $listing_id, $this->category_taxonomy, ["fields" => "ids"] );
        if ( is_wp_error( $category_ids ) || empty( $category_ids ) ) {
            return new ListingPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $query = new WP_Query(
            [
                "post_type"      => $this->post_types(),
                "post_status"    => "publish",
                "has_password"   => false,
                "paged"          => $page,
                "posts_per_page" => $per_page,
                //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- small, bounded exclusion of the current listing from its own "related" query.
                "post__not_in"   => [$listing_id],
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering listings by taxonomy is the point of this query.
                "tax_query"      => [
                    [
                        "taxonomy" => $this->category_taxonomy,
                        "field"    => "term_id",
                        "terms"    => array_map( "intval", $category_ids ),
                    ],
                ],
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
        $listing_id = (int) craf_appna_route_param( $request, "id" );

        if ( ! $this->get_listing_post( $listing_id ) ) {
            return null;
        }

        return $this->query_comment_reviews( $listing_id, $request );
    }

    public function categories( ?CategoryPaginatorDTO $category_paginator, Request $request, array $fields = [] ): CategoryPaginatorDTO {
        return $this->query_categories( $request, $fields );
    }

    public function category( ?CategoryDTO $category, Request $request, array $fields = [] ): ?CategoryDTO {
        $term = get_term( (int) craf_appna_route_param( $request, "id" ), $this->category_taxonomy );
        return ( $term && ! is_wp_error( $term ) ) ? $this->map_category_to_dto( $term, $fields ) : null;
    }

    public function tags( ?TermPaginatorDTO $tag_paginator, Request $request, array $fields = [] ): TermPaginatorDTO {
        return $this->query_terms( $this->tag_taxonomy, $request, $fields );
    }

    public function locations( ?TermPaginatorDTO $location_paginator, Request $request, array $fields = [] ): TermPaginatorDTO {
        return $this->query_terms( $this->location_taxonomy, $request, $fields );
    }

    public function location( ?TermDTO $location, Request $request, array $fields = [] ): ?TermDTO {
        $term = get_term( (int) craf_appna_route_param( $request, "id" ), $this->location_taxonomy );
        return ( $term && ! is_wp_error( $term ) ) ? $this->map_term_to_dto( $term, $fields ) : null;
    }

    private function is_loaded(): bool {
        return defined( "ADQS_DIRECTORY_VERSION" ) || function_exists( "adqs_get_directory_post_types" );
    }

    private function post_types(): array {
        return function_exists( "adqs_get_directory_post_types" ) ? (array) adqs_get_directory_post_types() : [];
    }

    private function get_listing_post( int $listing_id ): ?WP_Post {
        $post = get_post( $listing_id );
        if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, $this->post_types(), true ) || "publish" !== $post->post_status || craf_appna_is_post_password_protected( $post ) ) {
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

        $sort_map = [
            "date"  => "date",
            "title" => "title",
            "name"  => "name",
            "id"    => "ID",
        ];

        $args["orderby"] = $sort_map[$order_by] ?? "date";
        $args["order"]   = $order;
    }

    private function apply_category_filter( array &$args, Request $request ): void {
        $ids = $this->positive_ids( $request->get_param( "categories" ) );
        if ( empty( $ids ) || ! taxonomy_exists( $this->category_taxonomy ) ) {
            return;
        }

        $args["tax_query"][] = [
            "taxonomy" => $this->category_taxonomy,
            "field"    => "term_id",
            "terms"    => $ids,
        ];
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
            $dto->set_views_count( 0 );
        }
        if ( in_array( "address", $fields, true ) ) {
            $dto->set_address( $this->meta( $post->ID, "_address" ) );
        }
        if ( in_array( "latitude", $fields, true ) ) {
            $dto->set_latitude( $this->normalize_coordinate( get_post_meta( $post->ID, "_map_lat", true ), -90, 90 ) );
        }
        if ( in_array( "longitude", $fields, true ) ) {
            $dto->set_longitude( $this->normalize_coordinate( get_post_meta( $post->ID, "_map_lon", true ), -180, 180 ) );
        }
        if ( in_array( "phone", $fields, true ) ) {
            $dto->set_phone( $this->meta( $post->ID, "_phone" ) );
        }
        if ( in_array( "email", $fields, true ) ) {
            $dto->set_email( $this->meta( $post->ID, "_email" ) );
        }
        if ( in_array( "website", $fields, true ) ) {
            $dto->set_website( $this->meta( $post->ID, "_website" ) );
        }
        if ( in_array( "favorite", $fields, true ) ) {
            $dto->set_favorite( false );
        }
        if ( in_array( "featured", $fields, true ) ) {
            $dto->set_featured( "yes" === $this->meta( $post->ID, "_is_featured" ) );
        }
        if ( in_array( "new", $fields, true ) ) {
            $dto->set_new( false );
        }
        if ( in_array( "popular", $fields, true ) ) {
            $dto->set_popular( false );
        }
        if ( in_array( "pricing", $fields, true ) ) {
            $dto->set_pricing(
                [
                    "price"       => $this->meta( $post->ID, "_price" ),
                    "price_type"  => $this->meta( $post->ID, "_price_type" ),
                    "price_range" => $this->meta( $post->ID, "_price_range" ),
                ]
            );
        }
        if ( in_array( "categories", $fields, true ) ) {
            $dto->set_categories( $this->get_listing_terms( $post->ID, $this->category_taxonomy ) );
        }
        if ( in_array( "locations", $fields, true ) ) {
            $dto->set_locations( $this->get_listing_terms( $post->ID, $this->location_taxonomy ) );
        }
        if ( in_array( "tags", $fields, true ) ) {
            $dto->set_tags( $this->get_listing_terms( $post->ID, $this->tag_taxonomy ) );
        }
        if ( in_array( "rating", $fields, true ) ) {
            $dto->set_rating( $this->get_listing_rating( (int) $post->ID ) );
        }

        return $dto;
    }

    private function meta( int $post_id, string $key ): string {
        $value = get_post_meta( $post_id, $key, true );
        return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : "";
    }

    private function query_categories( Request $request, array $fields ): CategoryPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;

        if ( ! taxonomy_exists( $this->category_taxonomy ) ) {
            return new CategoryPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $terms = $this->get_terms_page( $this->category_taxonomy, $request, $page, $per_page );
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
        $sort = sanitize_text_field( (string) $request->get_param( "sort" ) );
        $desc = 0 === strpos( $sort, "-" );
        $key  = $desc ? ltrim( $sort, "-" ) : $sort;
        $map  = [
            "name"  => "name",
            "id"    => "term_id",
            "slug"  => "slug",
            "count" => "count",
        ];
        $args = [
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

        if ( is_wp_error( $items ) ) {
            $items = [];
        }
        if ( is_wp_error( $total ) ) {
            $total = 0;
        }

        return [
            "items"     => $items,
            "total"     => (int) $total,
            "last_page" => max( 1, (int) ceil( (int) $total / $per_page ) ),
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
        if ( in_array( "image", $fields, true ) ) {
            $dto->set_image( $this->get_term_image( (int) $term->term_id ) );
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
        $image_id = (int) get_post_thumbnail_id( $post_id );
        if ( $image_id > 0 ) {
            return $image_id;
        }

        $image_ids = $this->get_listing_image_ids( $post_id );
        return $image_ids ? (int) reset( $image_ids ) : 0;
    }

    private function get_listing_image_ids( int $post_id ): array {
        $image_ids    = $this->positive_ids( get_post_meta( $post_id, "_images", true ) );
        $thumbnail_id = (int) get_post_thumbnail_id( $post_id );

        if ( $thumbnail_id > 0 ) {
            array_unshift( $image_ids, $thumbnail_id );
        }

        return array_values( array_unique( array_filter( $image_ids ) ) );
    }

    private function map_attachment_image( int $image_id ): array {
        $src = wp_get_attachment_url( $image_id );

        return $src ? [
            "id"    => (int) $image_id,
            "src"   => esc_url_raw( (string) $src ),
            "alt"   => sanitize_text_field( (string) get_post_meta( $image_id, "_wp_attachment_image_alt", true ) ),
            "title" => (string) get_the_title( $image_id ),
        ] : [];
    }

    private function get_term_image( int $term_id ): array {
        foreach ( ["term_image_id", "image", "thumbnail_id", "_thumbnail_id"] as $key ) {
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

        return array_map(
            function( $term ): array {
                return ["id" => (int) $term->term_id, "name" => (string) $term->name, "slug" => (string) $term->slug];
            },
            $terms
        );
    }

    private function query_comment_reviews( int $listing_id, Request $request ): array {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $base     = [
            "post_id" => $listing_id,
            "status"  => "approve",
            "parent"  => 0,
        ];

        $total    = (int) get_comments( array_merge( $base, ["count" => true] ) );
        $comments = get_comments(
            array_merge(
                $base, [
                    "number"  => $per_page,
                    "offset"  => ( $page - 1 ) * $per_page,
                    "orderby" => "comment_date_gmt",
                    "order"   => "DESC",
                ]
            )
        );

        $items = [];
        foreach ( $comments as $comment ) {
            $rating  = $this->get_comment_rating( (int) $comment->comment_ID );
            $items[] = $this->map_review_comment( $comment, $rating );
        }

        $rating_counts = $this->get_review_rating_counts( $listing_id );
        $average       = $this->calculate_average_rating( $rating_counts );

        return [
            "current_page"   => $page,
            "per_page"       => $per_page,
            "total"          => $total,
            "last_page"      => max( 1, (int) ceil( $total / $per_page ) ),
            "average_rating" => $average,
            "review_count"   => $total,
            "rating_counts"  => $rating_counts,
            "items"          => $items,
        ];
    }

    private function get_comment_rating( int $comment_id ): float {
        $rating = get_comment_meta( $comment_id, "adqs_review_rating", true );
        return is_numeric( $rating ) ? (float) $rating : 0.0;
    }

    private function get_review_rating_counts( int $listing_id ): array {
        $rating_counts = $this->empty_rating_counts();
        $comments      = get_comments( ["post_id" => $listing_id, "status" => "approve", "parent" => 0] );

        foreach ( $comments as $comment ) {
            $rating = $this->get_comment_rating( (int) $comment->comment_ID );
            if ( $rating > 0 ) {
                $rating_counts[(string) max( 1, min( 5, (int) round( $rating ) ) )]++;
            }
        }

        return $rating_counts;
    }

    private function calculate_average_rating( array $rating_counts ): float {
        $total = 0;
        $sum   = 0;
        foreach ( $rating_counts as $rating => $count ) {
            $total += (int) $count;
            $sum   += (int) $rating * (int) $count;
        }
        return $total > 0 ? round( $sum / $total, 1 ) : 0.0;
    }

    private function get_listing_rating( int $post_id ): float {
        return $this->calculate_average_rating( $this->get_review_rating_counts( $post_id ) );
    }

    private function map_review_comment( WP_Comment $comment, float $rating ): array {
        return [
            "id"           => (int) $comment->comment_ID,
            "reviewer"     => sanitize_text_field( (string) $comment->comment_author ),
            "review"       => wp_kses_post( (string) $comment->comment_content ),
            "rating"       => $rating,
            "date_created" => (string) get_comment_date( DATE_ATOM, $comment ),
            "avatar_url"   => (string) get_avatar_url( $comment, ["size" => 96] ),
        ];
    }

    private function empty_rating_counts(): array {
        return ["1" => 0, "2" => 0, "3" => 0, "4" => 0, "5" => 0];
    }
}
