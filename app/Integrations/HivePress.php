<?php

namespace Crafium\AppNatively\App\Integrations;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Directory\CategoryDTO;
use Crafium\AppNatively\App\DTO\Directory\CategoryPaginatorDTO;
use Crafium\AppNatively\App\DTO\Directory\ListingDTO;
use Crafium\AppNatively\App\DTO\Directory\ListingPaginatorDTO;
use Crafium\AppNatively\App\DTO\Directory\TermDTO;
use Crafium\AppNatively\App\DTO\Directory\TermPaginatorDTO;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use WP_Post;
use WP_Query;

class HivePress extends Provider {
    private string $post_type = "hp_listing";

    private string $category_taxonomy = "hp_listing_category";

    public function register() {}

    public function boot(): void {
        if ( ! $this->is_loaded() ) {
            return;
        }

        add_filter( "craf_appna_directory_hivepress_listings", [$this, "listings"], 10, 3 );
        add_filter( "craf_appna_directory_hivepress_listing", [$this, "listing"], 10, 3 );
        add_filter( "craf_appna_directory_hivepress_related_listings", [$this, "related_listings"], 10, 3 );
        add_filter( "craf_appna_directory_hivepress_reviews", [$this, "reviews"], 10, 2 );
        add_filter( "craf_appna_directory_hivepress_categories", [$this, "categories"], 10, 3 );
        add_filter( "craf_appna_directory_hivepress_category", [$this, "category"], 10, 3 );
        add_filter( "craf_appna_directory_hivepress_tags", [$this, "tags"], 10, 3 );
        add_filter( "craf_appna_directory_hivepress_locations", [$this, "locations"], 10, 3 );
        add_filter( "craf_appna_directory_hivepress_location", [$this, "location"], 10, 3 );
    }

    public function listings( ?ListingPaginatorDTO $listing_paginator, Request $request, array $fields = [] ): ListingPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $args     = [
            "post_type"      => $this->post_type,
            "post_status"    => "publish",
            "paged"          => $page,
            "posts_per_page" => $per_page,
            "s"              => sanitize_text_field( (string) $request->get_param( "search" ) ),
        ];

        $this->apply_sort_args( $args, (string) $request->get_param( "sort" ) );
        $this->apply_category_filter( $args, $request );

        if ( filter_var( $request->get_param( "isFeatured" ), FILTER_VALIDATE_BOOLEAN ) ) {
            $args["meta_query"][] = [
                "key"   => "_hp_featured",
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
        $post = $this->get_listing_post( (int) $request->get_param( "id" ) );
        return $post ? $this->map_listing_to_dto( $post, $fields ) : null;
    }

    public function related_listings( ?ListingPaginatorDTO $listing_paginator, Request $request, array $fields = [] ): ListingPaginatorDTO {
        $listing_id = (int) $request->get_param( "id" );
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
                "post_type"      => $this->post_type,
                "post_status"    => "publish",
                "paged"          => $page,
                "posts_per_page" => $per_page,
                "post__not_in"   => [$listing_id],
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
        return $this->get_listing_post( (int) $request->get_param( "id" ) ) ? $this->empty_reviews( $request ) : null;
    }

    public function categories( ?CategoryPaginatorDTO $category_paginator, Request $request, array $fields = [] ): CategoryPaginatorDTO {
        return $this->query_categories( $request, $fields );
    }

    public function category( ?CategoryDTO $category, Request $request, array $fields = [] ): ?CategoryDTO {
        $term = get_term( (int) $request->get_param( "id" ), $this->category_taxonomy );
        return ( $term && ! is_wp_error( $term ) ) ? $this->map_category_to_dto( $term, $fields ) : null;
    }

    public function tags( ?TermPaginatorDTO $tag_paginator, Request $request, array $fields = [] ): TermPaginatorDTO {
        return $this->empty_term_paginator( $request );
    }

    public function locations( ?TermPaginatorDTO $location_paginator, Request $request, array $fields = [] ): TermPaginatorDTO {
        return $this->empty_term_paginator( $request );
    }

    public function location( ?TermDTO $location, Request $request, array $fields = [] ): ?TermDTO {
        return null;
    }

    private function is_loaded(): bool {
        return post_type_exists( $this->post_type ) || class_exists( "HivePress\\Models\\Listing" ) || defined( "HIVEPRESS_VERSION" );
    }

    private function get_listing_post( int $listing_id ): ?WP_Post {
        $post = get_post( $listing_id );
        if ( ! $post instanceof WP_Post || $post->post_type !== $this->post_type || "publish" !== $post->post_status ) {
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
        if ( in_array( "title", $fields, true ) ) {
            $dto->set_title( get_the_title( $post ) );
        }
        if ( in_array( "slug", $fields, true ) ) {
            $dto->set_slug( (string) $post->post_name );
        }
        if ( in_array( "description", $fields, true ) ) {
            $dto->set_description( (string) apply_filters( "the_content", $post->post_content ) );
        }
        if ( in_array( "excerpt", $fields, true ) ) {
            $dto->set_excerpt( (string) get_the_excerpt( $post ) );
        }
        if ( in_array( "status", $fields, true ) ) {
            $dto->set_status( (string) $post->post_status );
        }
        if ( in_array( "image", $fields, true ) ) {
            $dto->set_image( $this->get_listing_image( (int) $post->ID ) );
        }
        if ( in_array( "views_count", $fields, true ) ) {
            $dto->set_views_count( (int) $this->get_meta_value( $post->ID, ["_hp_view_count", "hp_view_count", "_view_count"] ) );
        }
        if ( in_array( "address", $fields, true ) ) {
            $dto->set_address( $this->get_meta_value( $post->ID, ["_hp_address", "hp_address", "address"] ) );
        }
        if ( in_array( "latitude", $fields, true ) ) {
            $dto->set_latitude( $this->normalize_coordinate( $this->get_meta_value( $post->ID, ["_hp_latitude", "hp_latitude", "latitude"] ), -90, 90 ) );
        }
        if ( in_array( "longitude", $fields, true ) ) {
            $dto->set_longitude( $this->normalize_coordinate( $this->get_meta_value( $post->ID, ["_hp_longitude", "hp_longitude", "longitude"] ), -180, 180 ) );
        }
        if ( in_array( "phone", $fields, true ) ) {
            $dto->set_phone( $this->get_meta_value( $post->ID, ["_hp_phone", "hp_phone", "phone"] ) );
        }
        if ( in_array( "email", $fields, true ) ) {
            $dto->set_email( $this->get_meta_value( $post->ID, ["_hp_email", "hp_email", "email"] ) );
        }
        if ( in_array( "website", $fields, true ) ) {
            $dto->set_website( $this->get_meta_value( $post->ID, ["_hp_website", "hp_website", "website"] ) );
        }
        if ( in_array( "favorite", $fields, true ) ) {
            $dto->set_favorite( false );
        }
        if ( in_array( "featured", $fields, true ) ) {
            $dto->set_featured( (bool) $this->get_meta_value( $post->ID, ["_hp_featured", "hp_featured"] ) );
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
                    "price"       => $this->get_meta_value( $post->ID, ["_hp_price", "hp_price", "price"] ),
                    "price_type"  => $this->get_meta_value( $post->ID, ["_hp_price_type", "hp_price_type"] ),
                    "price_range" => $this->get_meta_value( $post->ID, ["_hp_price_range", "hp_price_range"] ),
                ]
            );
        }
        if ( in_array( "categories", $fields, true ) ) {
            $dto->set_categories( $this->get_listing_terms( $post->ID, $this->category_taxonomy ) );
        }
        if ( in_array( "locations", $fields, true ) ) {
            $dto->set_locations( [] );
        }
        if ( in_array( "tags", $fields, true ) ) {
            $dto->set_tags( [] );
        }
        if ( in_array( "rating", $fields, true ) ) {
            $dto->set_rating( 0.0 );
        }

        return $dto;
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

    private function get_terms_page( string $taxonomy, Request $request, int $page, int $per_page ): array {
        $sort  = sanitize_text_field( (string) $request->get_param( "sort" ) );
        $desc  = 0 === strpos( $sort, "-" );
        $key   = $desc ? ltrim( $sort, "-" ) : $sort;
        $map   = [
            "name"  => "name",
            "id"    => "term_id",
            "slug"  => "slug",
            "count" => "count",
        ];
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

    private function get_listing_image( int $post_id ): array {
        $image_id = get_post_thumbnail_id( $post_id );
        $src      = $image_id ? wp_get_attachment_url( $image_id ) : "";

        return $src ? [
            "id"    => (int) $image_id,
            "src"   => (string) $src,
            "alt"   => (string) get_post_meta( $image_id, "_wp_attachment_image_alt", true ),
            "title" => (string) get_the_title( $image_id ),
        ] : [];
    }

    private function get_term_image( int $term_id ): array {
        foreach ( ["image", "thumbnail_id", "_thumbnail_id"] as $key ) {
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

    private function get_meta_value( int $post_id, array $keys ): string {
        foreach ( $keys as $key ) {
            $value = get_post_meta( $post_id, $key, true );
            if ( "" !== $value && null !== $value ) {
                return is_scalar( $value ) ? (string) $value : "";
            }
        }

        return "";
    }

    private function empty_reviews( Request $request ): array {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;

        return [
            "current_page"   => $page,
            "per_page"       => $per_page,
            "total"          => 0,
            "last_page"      => 1,
            "average_rating" => 0.0,
            "review_count"   => 0,
            "rating_counts"  => ["1" => 0, "2" => 0, "3" => 0, "4" => 0, "5" => 0],
            "items"          => [],
        ];
    }

    private function empty_term_paginator( Request $request ): TermPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;

        return new TermPaginatorDTO( $page, $per_page, 0, 1, [] );
    }

    private function positive_ids( $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }

        return array_values( array_filter( array_map( "intval", $value ), fn( int $id ): bool => $id > 0 ) );
    }

    private function normalize_coordinate( $value, float $min, float $max ): ?float {
        if ( "" === $value || null === $value || ! is_numeric( $value ) ) {
            return null;
        }

        $coordinate = (float) $value;
        return ( $coordinate >= $min && $coordinate <= $max ) ? $coordinate : null;
    }
}
