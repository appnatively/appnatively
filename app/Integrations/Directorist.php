<?php

namespace AppNatively\App\Integrations;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\Directory\ListingDTO;
use AppNatively\App\DTO\Directory\ListingPaginatorDTO;
use AppNatively\WpMVC\Contracts\Provider;
use AppNatively\WpMVC\RequestValidator\Request;
use Directorist\Helper;
use WP_Post;
use WP_Query;

class Directorist extends Provider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {}

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void {
        add_filter( "appnatively_directory_directorist_listings", [$this, "listings"], 10, 3 );
    }

    /**
     * Get listings paginator.
     *
     * @param ListingPaginatorDTO|null $listing_paginator The listing paginator.
     * @param Request                  $request The REST request instance.
     * @param array                    $fields The requested fields.
     * @return ListingPaginatorDTO
     */
    public function listings( ?ListingPaginatorDTO $listing_paginator, Request $request, array $fields = [] ): ListingPaginatorDTO {
        $page      = (int) $request->get_param( "page" ) ?: 1;
        $per_page  = (int) $request->get_param( "per_page" ) ?: 10;
        $search    = sanitize_text_field( (string) $request->get_param( "search" ) );
        $sort      = sanitize_text_field( (string) $request->get_param( "sort" ) );
        $post_type = defined( "ATBDP_POST_TYPE" ) ? ATBDP_POST_TYPE : "at_biz_dir";

        $order_by = "date";
        $order    = "DESC";

        if ( ! empty( $sort ) ) {
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

        $query = new WP_Query(
            [
                "post_type"      => $post_type,
                "post_status"    => "publish",
                "paged"          => $page,
                "posts_per_page" => $per_page,
                "s"              => $search,
                "orderby"        => $sort_map[$order_by] ?? "date",
                "order"          => $order,
            ]
        );

        $items = [];
        foreach ( $query->posts as $listing ) {
            if ( $listing instanceof WP_Post ) {
                $items[] = $this->map_listing_to_dto( $listing, $fields );
            }
        }

        return new ListingPaginatorDTO(
            $page,
            $per_page,
            (int) $query->found_posts,
            (int) $query->max_num_pages,
            $items
        );
    }

    /**
     * Map Directorist listing post to ListingDTO.
     *
     * @param WP_Post $listing The listing post.
     * @param array   $fields The requested fields.
     * @return ListingDTO
     */
    private function map_listing_to_dto( WP_Post $listing, array $fields ): ListingDTO {
        $dto = new ListingDTO();

        if ( in_array( "id", $fields, true ) ) {
            $dto->set_id( (int) $listing->ID );
        }
        if ( in_array( "title", $fields, true ) ) {
            $dto->set_title( get_the_title( $listing ) );
        }
        if ( in_array( "slug", $fields, true ) ) {
            $dto->set_slug( (string) $listing->post_name );
        }
        if ( in_array( "description", $fields, true ) ) {
            $dto->set_description( (string) apply_filters( "the_content", $listing->post_content ) );
        }
        if ( in_array( "excerpt", $fields, true ) ) {
            $dto->set_excerpt( (string) get_the_excerpt( $listing ) );
        }
        if ( in_array( "status", $fields, true ) ) {
            $dto->set_status( (string) $listing->post_status );
        }
        if ( in_array( "image", $fields, true ) ) {
            $dto->set_image( $this->get_listing_image( $listing->ID ) );
        }
        if ( in_array( "views_count", $fields, true ) ) {
            $dto->set_views_count( $this->get_listing_views_count( $listing->ID ) );
        }
        if ( in_array( "address", $fields, true ) ) {
            $dto->set_address( $this->get_meta_value( $listing->ID, ["_address", "address"] ) );
        }
        if ( in_array( "phone", $fields, true ) ) {
            $dto->set_phone( $this->get_meta_value( $listing->ID, ["_phone", "phone"] ) );
        }
        if ( in_array( "email", $fields, true ) ) {
            $dto->set_email( $this->get_meta_value( $listing->ID, ["_email", "email"] ) );
        }
        if ( in_array( "website", $fields, true ) ) {
            $dto->set_website( $this->get_meta_value( $listing->ID, ["_website", "website"] ) );
        }
        if ( in_array( "favorite", $fields, true ) ) {
            $dto->set_favorite( $this->is_favorite_listing( $listing->ID ) );
        }
        if ( in_array( "featured", $fields, true ) ) {
            $dto->set_featured( (bool) get_post_meta( $listing->ID, "_featured", true ) );
        }
        if ( in_array( "new", $fields, true ) ) {
            $dto->set_new( $this->is_new_listing( $listing->ID ) );
        }
        if ( in_array( "popular", $fields, true ) ) {
            $dto->set_popular( $this->is_popular_listing( $listing->ID ) );
        }
        if ( in_array( "pricing", $fields, true ) ) {
            $dto->set_pricing( $this->get_listing_pricing( $listing->ID ) );
        }
        if ( in_array( "categories", $fields, true ) ) {
            $dto->set_categories( $this->get_listing_terms( $listing->ID, defined( "ATBDP_CATEGORY" ) ? ATBDP_CATEGORY : "at_biz_dir-category" ) );
        }
        if ( in_array( "locations", $fields, true ) ) {
            $dto->set_locations( $this->get_listing_terms( $listing->ID, defined( "ATBDP_LOCATION" ) ? ATBDP_LOCATION : "at_biz_dir-location" ) );
        }
        if ( in_array( "tags", $fields, true ) ) {
            $dto->set_tags( $this->get_listing_terms( $listing->ID, defined( "ATBDP_TAGS" ) ? ATBDP_TAGS : "at_biz_dir-tags" ) );
        }
        if ( in_array( "rating", $fields, true ) ) {
            $dto->set_rating( $this->get_listing_rating( $listing->ID ) );
        }

        return $dto;
    }

    /**
     * Determine whether the listing is favorited by the current user.
     *
     * @param int $listing_id The listing ID.
     * @return bool
     */
    private function is_favorite_listing( int $listing_id ): bool {
        if ( ! function_exists( "directorist_get_user_favorites" ) ) {
            return false;
        }

        $favorites = directorist_get_user_favorites( get_current_user_id() );

        return in_array( $listing_id, $favorites, true );
    }

    /**
     * Get normalized listing view count.
     *
     * @param int $listing_id The listing ID.
     * @return int
     */
    private function get_listing_views_count( int $listing_id ): int {
        if ( function_exists( "directorist_get_listing_views_count" ) ) {
            return (int) directorist_get_listing_views_count( $listing_id );
        }

        return (int) $this->get_meta_value( $listing_id, ["_atbdp_post_views_count", "atbdp_post_views_count", "view_count"] );
    }

    /**
     * Determine whether a listing should show the new badge.
     *
     * @param int $listing_id The listing ID.
     * @return bool
     */
    private function is_new_listing( int $listing_id ): bool {
        if ( class_exists( Helper::class ) ) {
            return (bool) Helper::is_new( $listing_id );
        }

        return false;
    }

    /**
     * Determine whether a listing should show the popular badge.
     *
     * @param int $listing_id The listing ID.
     * @return bool
     */
    private function is_popular_listing( int $listing_id ): bool {
        if ( class_exists( Helper::class ) ) {
            return (bool) Helper::is_popular( $listing_id );
        }

        return false;
    }

    /**
     * Get normalized listing pricing data.
     *
     * @param int $listing_id The listing ID.
     * @return array
     */
    private function get_listing_pricing( int $listing_id ): array {
        return [
            "price"       => $this->clean_listing_value( get_post_meta( $listing_id, "_price", true ) ),
            "price_type"  => $this->clean_listing_value( get_post_meta( $listing_id, "_atbd_listing_pricing", true ) ),
            "price_range" => $this->clean_listing_value( get_post_meta( $listing_id, "_price_range", true ) ),
        ];
    }

    /**
     * Clean a Directorist listing value.
     *
     * @param mixed $value The raw value.
     * @return string
     */
    private function clean_listing_value( $value ): string {
        if ( function_exists( "directorist_clean" ) ) {
            return (string) directorist_clean( $value );
        }

        return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : "";
    }

    /**
     * Get the first non-empty meta value for a listing.
     *
     * @param int   $listing_id The listing ID.
     * @param array $keys Candidate meta keys.
     * @return string
     */
    private function get_meta_value( int $listing_id, array $keys ): string {
        foreach ( $keys as $key ) {
            $value = get_post_meta( $listing_id, $key, true );
            if ( "" !== $value && null !== $value ) {
                return is_scalar( $value ) ? (string) $value : "";
            }
        }

        return "";
    }

    /**
     * Get normalized listing image data.
     *
     * @param int $listing_id The listing ID.
     * @return array
     */
    private function get_listing_image( int $listing_id ): array {
        $image_id = get_post_thumbnail_id( $listing_id );
        if ( ! $image_id ) {
            $image_id = (int) get_post_meta( $listing_id, "_listing_prv_img", true );
        }
        $src      = $image_id ? wp_get_attachment_url( $image_id ) : "";

        if ( ! $src ) {
            return [];
        }

        return [
            "id"    => (int) $image_id,
            "src"   => (string) $src,
            "alt"   => (string) get_post_meta( $image_id, "_wp_attachment_image_alt", true ),
            "title" => (string) get_the_title( $image_id ),
        ];
    }

    /**
     * Get normalized listing rating.
     *
     * @param int $listing_id The listing ID.
     * @return float
     */
    private function get_listing_rating( int $listing_id ): float {
        if ( function_exists( "directorist_get_listing_rating" ) ) {
            return (float) directorist_get_listing_rating( $listing_id );
        }

        return (float) $this->get_meta_value( $listing_id, ["_directorist_listing_rating", "_average_rating", "average_rating", "_rating", "rating"] );
    }

    /**
     * Get normalized term data.
     *
     * @param int    $listing_id The listing ID.
     * @param string $taxonomy The taxonomy name.
     * @return array
     */
    private function get_listing_terms( int $listing_id, string $taxonomy ): array {
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
}
