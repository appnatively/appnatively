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

class GeoDirectory extends Provider {
    use ListingIntegrationHelpers;

    public function register() {}

    public function boot(): void {
        if ( ! $this->is_loaded() ) {
            return;
        }

        add_filter( "craf_appna_directory_geodirectory_listings", [$this, "listings"], 10, 3 );
        add_filter( "craf_appna_directory_geodirectory_listing", [$this, "listing"], 10, 3 );
        add_filter( "craf_appna_directory_geodirectory_related_listings", [$this, "related_listings"], 10, 3 );
        add_filter( "craf_appna_directory_geodirectory_reviews", [$this, "reviews"], 10, 2 );
        add_filter( "craf_appna_directory_geodirectory_categories", [$this, "categories"], 10, 3 );
        add_filter( "craf_appna_directory_geodirectory_category", [$this, "category"], 10, 3 );
        add_filter( "craf_appna_directory_geodirectory_tags", [$this, "tags"], 10, 3 );
        add_filter( "craf_appna_directory_geodirectory_locations", [$this, "locations"], 10, 3 );
        add_filter( "craf_appna_directory_geodirectory_location", [$this, "location"], 10, 3 );
    }

    public function listings( ?ListingPaginatorDTO $listing_paginator, Request $request, array $fields = [] ): ListingPaginatorDTO {
        return $this->query_listings( $request, $fields );
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

        if ( ! $post ) {
            return new ListingPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        return $this->query_related_listings( $listing_id, $request, $fields );
    }

    public function reviews( ?array $reviews, Request $request ): ?array {
        $listing_id = (int) craf_appna_route_param( $request, "id" );

        if ( ! $this->get_listing_post( $listing_id ) ) {
            return null;
        }

        return $this->query_comment_reviews( $listing_id, $request, ["rating", "_rating", "geodir_overallrating", "_geodir_overallrating", "overall_rating"] );
    }

    public function categories( ?CategoryPaginatorDTO $category_paginator, Request $request, array $fields = [] ): CategoryPaginatorDTO {
        return $this->query_categories( $this->category_taxonomy(), $request, $fields, ["ct_cat_icon", "ct_cat_default_img", "thumbnail_id"] );
    }

    public function category( ?CategoryDTO $category, Request $request, array $fields = [] ): ?CategoryDTO {
        $term = get_term( (int) craf_appna_route_param( $request, "id" ), $this->category_taxonomy() );
        return ( $term && ! is_wp_error( $term ) ) ? $this->map_category_to_dto( $term, $fields, ["ct_cat_icon", "ct_cat_default_img", "thumbnail_id"] ) : null;
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
        return function_exists( "geodir_get_post_info" ) || function_exists( "geodir_get_posttypes" ) || class_exists( "GeoDirectory" );
    }

    private function post_type(): string {
        if ( function_exists( "geodir_get_posttypes" ) ) {
            $post_types = geodir_get_posttypes( "array" );
            if ( is_array( $post_types ) && ! empty( $post_types ) ) {
                $post_type = (string) array_key_first( $post_types );
                if ( "" !== $post_type ) {
                    return $post_type;
                }
            }
        }

        return "gd_place";
    }

    private function category_taxonomy(): string {
        return $this->post_type() . "category";
    }

    private function tag_taxonomy(): string {
        return $this->post_type() . "_tags";
    }

    private function get_listing_post( int $listing_id ): ?WP_Post {
        $post = get_post( $listing_id );

        if ( ! $post instanceof WP_Post || $post->post_type !== $this->post_type() || "publish" !== $post->post_status || craf_appna_is_post_password_protected( $post ) ) {
            return null;
        }

        return $post;
    }

    private function query_listings( Request $request, array $fields ): ListingPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $args     = [
            "post_type"      => $this->post_type(),
            "post_status"    => "publish",
            "has_password"    => false,
            "paged"          => $page,
            "posts_per_page" => $per_page,
            "s"              => sanitize_text_field( (string) $request->get_param( "search" ) ),
        ];

        $this->apply_sort_args( $args, (string) $request->get_param( "sort" ) );
        $this->apply_tax_filters( $args, $request );

        if ( filter_var( (string) $request->get_param( "isFeatured" ), FILTER_VALIDATE_BOOLEAN ) ) {
            $args["meta_query"][] = [
                "key"   => "is_featured",
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

    private function query_related_listings( int $listing_id, Request $request, array $fields ): ListingPaginatorDTO {
        $page      = (int) $request->get_param( "page" ) ?: 1;
        $per_page  = (int) $request->get_param( "per_page" ) ?: 10;
        $tax_query = ["relation" => "OR"];

        foreach ( [$this->category_taxonomy(), $this->tag_taxonomy()] as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $term_ids = wp_get_post_terms( $listing_id, $taxonomy, ["fields" => "ids"] );
            if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) {
                $tax_query[] = [
                    "taxonomy" => $taxonomy,
                    "field"    => "term_id",
                    "terms"    => array_map( "intval", $term_ids ),
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
                "has_password"    => false,
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

    private function apply_tax_filters( array &$args, Request $request ): void {
        $tax_query = [];
        $map       = [
            "categories" => $this->category_taxonomy(),
            "tags"       => $this->tag_taxonomy(),
        ];

        foreach ( $map as $request_key => $taxonomy ) {
            $ids = $this->positive_ids( $request->get_param( $request_key ) );
            if ( empty( $ids ) || ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $tax_query[] = [
                "taxonomy" => $taxonomy,
                "field"    => "term_id",
                "terms"    => $ids,
            ];
        }

        if ( ! empty( $tax_query ) ) {
            //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering listings by taxonomy is the point of this query.
            $args["tax_query"] = $tax_query;
        }
    }

    private function map_listing_to_dto( WP_Post $post, array $fields ): ListingDTO {
        $dto  = new ListingDTO();
        $info = $this->get_post_info( (int) $post->ID );

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
        if ( in_array( "views_count", $fields, true ) ) {
            $dto->set_views_count( 0 );
        }
        if ( in_array( "address", $fields, true ) ) {
            $dto->set_address( $this->get_value( $info, ["address", "street", "street2"] ) );
        }
        if ( in_array( "latitude", $fields, true ) ) {
            $dto->set_latitude( $this->normalize_coordinate( $this->get_value( $info, ["latitude", "post_latitude"] ), -90, 90 ) );
        }
        if ( in_array( "longitude", $fields, true ) ) {
            $dto->set_longitude( $this->normalize_coordinate( $this->get_value( $info, ["longitude", "post_longitude"] ), -180, 180 ) );
        }
        if ( in_array( "phone", $fields, true ) ) {
            $dto->set_phone( $this->get_value( $info, ["phone"] ) );
        }
        if ( in_array( "email", $fields, true ) ) {
            $dto->set_email( $this->get_value( $info, ["email"] ) );
        }
        if ( in_array( "website", $fields, true ) ) {
            $dto->set_website( $this->get_value( $info, ["website"] ) );
        }
        if ( in_array( "favorite", $fields, true ) ) {
            $dto->set_favorite( false );
        }
        if ( in_array( "featured", $fields, true ) ) {
            $dto->set_featured( (bool) $this->get_value( $info, ["is_featured", "featured"] ) );
        }
        if ( in_array( "new", $fields, true ) ) {
            $dto->set_new( false );
        }
        if ( in_array( "popular", $fields, true ) ) {
            $dto->set_popular( false );
        }
        if ( in_array( "pricing", $fields, true ) ) {
            $dto->set_pricing( $this->get_pricing( $post->ID, $info ) );
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
            $dto->set_rating( $this->get_rating( $post->ID ) );
        }

        return $dto;
    }

    private function get_post_info( int $post_id ) {
        return function_exists( "geodir_get_post_info" ) ? geodir_get_post_info( $post_id ) : null;
    }

    private function get_value( $info, array $keys ): string {
        $values = is_object( $info ) ? get_object_vars( $info ) : ( is_array( $info ) ? $info : [] );

        foreach ( $keys as $key ) {
            if ( array_key_exists( $key, $values ) && "" !== $values[$key] && null !== $values[$key] ) {
                return is_scalar( $values[$key] ) ? sanitize_text_field( (string) $values[$key] ) : "";
            }
        }

        return "";
    }

    private function get_pricing( int $post_id, $info ): array {
        return [
            "price"       => $this->get_value( $info, ["price", "price_from"] ),
            "price_type"  => $this->get_value( $info, ["price_type"] ),
            "price_range" => $this->get_value( $info, ["price_range"] ),
        ];
    }

    private function get_listing_image( int $post_id ): array {
        $image_id = get_post_thumbnail_id( $post_id );
        $src      = $image_id ? wp_get_attachment_url( $image_id ) : "";

        if ( ! $src ) {
            return [];
        }

        return [
            "id"    => (int) $image_id,
            "src"   => esc_url_raw( (string) $src ),
            "alt"   => sanitize_text_field( (string) get_post_meta( $image_id, "_wp_attachment_image_alt", true ) ),
            "title" => (string) get_the_title( $image_id ),
        ];
    }

    private function get_rating( int $post_id ): float {
        if ( function_exists( "geodir_get_post_rating" ) ) {
            return (float) geodir_get_post_rating( $post_id );
        }

        return (float) get_post_meta( $post_id, "overall_rating", true );
    }

    private function query_categories( string $taxonomy, Request $request, array $fields, array $image_keys ): CategoryPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new CategoryPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $terms = $this->get_terms_page( $taxonomy, $request, $page, $per_page );
        $items = [];
        foreach ( $terms["items"] as $term ) {
            $items[] = $this->map_category_to_dto( $term, $fields, $image_keys );
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
            $items[] = $this->map_term_to_dto( $term, $fields, [] );
        }

        return new TermPaginatorDTO( $page, $per_page, $terms["total"], $terms["last_page"], $items );
    }

    private function get_terms_page( string $taxonomy, Request $request, int $page, int $per_page ): array {
        $sort     = sanitize_text_field( (string) $request->get_param( "sort" ) );
        $order_by = "name";
        $order    = "ASC";

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
            "orderby"    => $sort_map[$order_by] ?? "name",
            "order"      => $order,
        ];

        $items = get_terms( $args );
        $total = wp_count_terms(
            [
                "taxonomy"   => $taxonomy,
                "hide_empty" => false,
                "search"     => $args["search"],
            ]
        );

        if ( is_wp_error( $items ) ) {
            $items = [];
        }
        if ( is_wp_error( $total ) ) {
            $total = 0;
        }

        $total = (int) $total;

        return [
            "items"     => $items,
            "total"     => $total,
            "last_page" => max( 1, (int) ceil( $total / $per_page ) ),
        ];
    }

    private function map_category_to_dto( $term, array $fields, array $image_keys ): CategoryDTO {
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
            $dto->set_image( $this->get_term_image( (int) $term->term_id, $image_keys ) );
        }

        return $dto;
    }

    private function map_term_to_dto( $term, array $fields, array $image_keys ): TermDTO {
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
            $dto->set_image( $this->get_term_image( (int) $term->term_id, $image_keys ) );
        }

        return $dto;
    }

    private function get_term_image( int $term_id, array $keys ): array {
        foreach ( $keys as $key ) {
            $image = $this->normalize_image_value( get_term_meta( $term_id, $key, true ) );
            if ( ! empty( $image ) ) {
                return $image;
            }
        }

        return [];
    }

    private function normalize_image_value( $value ): array {
        if ( empty( $value ) ) {
            return [];
        }

        if ( is_array( $value ) ) {
            $id  = (int) ( $value["id"] ?? $value["attachment_id"] ?? 0 );
            $src = (string) ( $value["src"] ?? $value["url"] ?? "" );
        } else {
            $id  = is_numeric( $value ) ? (int) $value : 0;
            $src = is_numeric( $value ) ? (string) wp_get_attachment_url( (int) $value ) : (string) $value;
        }

        if ( ! $src || ! filter_var( $src, FILTER_VALIDATE_URL ) ) {
            return [];
        }

        return [
            "id"  => $id,
            "src" => esc_url_raw( $src ),
        ];
    }

    private function get_listing_terms( int $listing_id, string $taxonomy ): array {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return [];
        }

        $terms = get_the_terms( $listing_id, $taxonomy );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return [];
        }

        return array_map(
            function( $term ): array {
                return [
                    "id"   => (int) $term->term_id,
                    "name" => (string) $term->name,
                    "slug" => (string) $term->slug,
                ];
            },
            $terms
        );
    }

    private function query_comment_reviews( int $listing_id, Request $request, array $rating_meta_keys ): array {
        $page          = (int) $request->get_param( "page" ) ?: 1;
        $per_page      = (int) $request->get_param( "per_page" ) ?: 10;
        $base          = [
            "post_id" => $listing_id,
            "status"  => "approve",
        ];
        $total         = (int) get_comments( array_merge( $base, ["count" => true] ) );
        $comments      = get_comments(
            array_merge(
                $base,
                [
                    "number"  => $per_page,
                    "offset"  => ( $page - 1 ) * $per_page,
                    "orderby" => "comment_date_gmt",
                    "order"   => "DESC",
                ]
            )
        );
        $items         = [];
        $rating_counts = $this->empty_rating_counts();

        foreach ( $comments as $comment ) {
            $rating = $this->get_comment_rating( (int) $comment->comment_ID, $rating_meta_keys );
            if ( $rating > 0 ) {
                $rating_counts[(string) max( 1, min( 5, (int) round( $rating ) ) )]++;
            }
            $items[] = $this->map_review_comment( $comment, $rating );
        }

        $stored_rating_counts = $this->get_stored_review_rating_counts( $listing_id );
        if ( null !== $stored_rating_counts ) {
            $rating_counts = $stored_rating_counts;
        }

        return [
            "current_page"   => $page,
            "per_page"       => $per_page,
            "total"          => $total,
            "last_page"      => max( 1, (int) ceil( $total / $per_page ) ),
            "average_rating" => $this->get_rating( $listing_id ),
            "review_count"   => $total,
            "rating_counts"  => $rating_counts,
            "items"          => $items,
        ];
    }

    private function map_review_comment( $comment, float $rating ): array {
        return [
            "id"           => (int) $comment->comment_ID,
            "reviewer"     => sanitize_text_field( (string) $comment->comment_author ),
            "review"       => wp_kses_post( (string) $comment->comment_content ),
            "rating"       => $rating,
            "date_created" => (string) get_comment_date( DATE_ATOM, $comment ),
            "avatar_url"   => (string) get_avatar_url( $comment, ["size" => 96] ),
        ];
    }

    private function get_comment_rating( int $comment_id, array $keys ): float {
        if ( class_exists( "\GeoDir_Comments" ) && method_exists( "\GeoDir_Comments", "get_comment_rating" ) ) {
            $rating = \GeoDir_Comments::get_comment_rating( $comment_id );
            if ( is_numeric( $rating ) ) {
                return (float) $rating;
            }
        }

        if ( class_exists( "\GeoDir_Comments" ) && method_exists( "\GeoDir_Comments", "get_review" ) ) {
            $review = \GeoDir_Comments::get_review( $comment_id );
            if ( is_object( $review ) && isset( $review->rating ) && is_numeric( $review->rating ) ) {
                return (float) $review->rating;
            }
        }

        foreach ( $keys as $key ) {
            $rating = get_comment_meta( $comment_id, $key, true );
            if ( is_numeric( $rating ) ) {
                return (float) $rating;
            }
        }

        return 0.0;
    }

    private function get_stored_review_rating_counts( int $listing_id ): ?array {
        $rating_counts = $this->empty_rating_counts();

        if ( class_exists( "\GeoDir_Comments" ) && method_exists( "\GeoDir_Comments", "get_post_review_rating_counts" ) ) {
            $counts = \GeoDir_Comments::get_post_review_rating_counts( $listing_id, 1 );
            if ( is_array( $counts ) ) {
                foreach ( $counts as $rating => $count ) {
                    $bucket                  = (string) max( 1, min( 5, (int) round( (float) $rating ) ) );
                    $rating_counts[$bucket] += (int) $count;
                }

                return $rating_counts;
            }
        }

        return null;
    }

    private function empty_rating_counts(): array {
        return [
            "1" => 0,
            "2" => 0,
            "3" => 0,
            "4" => 0,
            "5" => 0,
        ];
    }
}
