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
use Crafium\AppNatively\App\Models\Term;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Directorist\Helper;
use WP_Post;
use WP_Query;

class Directorist extends Provider {
    use ListingIntegrationHelpers;

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
        add_filter( "craf_appna_directory_directorist_listings", [$this, "listings"], 10, 3 );
        add_filter( "craf_appna_directory_directorist_listing", [$this, "listing"], 10, 3 );
        add_filter( "craf_appna_directory_directorist_related_listings", [$this, "related_listings"], 10, 3 );
        add_filter( "craf_appna_directory_directorist_reviews", [$this, "reviews"], 10, 2 );
        add_filter( "craf_appna_directory_directorist_wishlist", [$this, "wishlist"], 10, 3 );
        add_filter( "craf_appna_directory_directorist_categories", [$this, "categories"], 10, 3 );
        add_filter( "craf_appna_directory_directorist_category", [$this, "category"], 10, 3 );
        add_filter( "craf_appna_directory_directorist_tags", [$this, "tags"], 10, 3 );
        add_filter( "craf_appna_directory_directorist_locations", [$this, "locations"], 10, 3 );
        add_filter( "craf_appna_directory_directorist_location", [$this, "location"], 10, 3 );
    }

    /**
     * Get the Directorist listing post type slug.
     *
     * @return string
     */
    private function get_post_type(): string {
        return defined( "ATBDP_POST_TYPE" ) ? ATBDP_POST_TYPE : "at_biz_dir";
    }

    /**
     * Get the Directorist category taxonomy slug.
     *
     * @return string
     */
    private function get_category_taxonomy(): string {
        return defined( "ATBDP_CATEGORY" ) ? ATBDP_CATEGORY : "at_biz_dir-category";
    }

    /**
     * Get the Directorist tags taxonomy slug.
     *
     * @return string
     */
    private function get_tags_taxonomy(): string {
        return defined( "ATBDP_TAGS" ) ? ATBDP_TAGS : "at_biz_dir-tags";
    }

    /**
     * Get the Directorist location taxonomy slug.
     *
     * @return string
     */
    private function get_location_taxonomy(): string {
        return defined( "ATBDP_LOCATION" ) ? ATBDP_LOCATION : "at_biz_dir-location";
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
        $post_type = $this->get_post_type();

        $categories  = $request->get_param( "categories" );
        $tags        = $request->get_param( "tags" );
        $locations   = $request->get_param( "locations" );
        $is_featured = $request->get_param( "isFeatured" );

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

        $wp_query_args = [
            "post_type"      => $post_type,
            "post_status"    => "publish",
            "has_password"   => false,
            "paged"          => $page,
            "posts_per_page" => $per_page,
            "s"              => $search,
            "orderby"        => $sort_map[$order_by] ?? "date",
            "order"          => $order,
        ];

        $tax_query = [];

        if ( ! empty( $categories ) && is_array( $categories ) ) {
            $tax_query[] = [
                "taxonomy" => $this->get_category_taxonomy(),
                "field"    => "term_id",
                "terms"    => array_map( "intval", $categories ),
            ];
        }

        if ( ! empty( $tags ) && is_array( $tags ) ) {
            $tax_query[] = [
                "taxonomy" => $this->get_tags_taxonomy(),
                "field"    => "term_id",
                "terms"    => array_map( "intval", $tags ),
            ];
        }

        if ( ! empty( $locations ) && is_array( $locations ) ) {
            $tax_query[] = [
                "taxonomy" => $this->get_location_taxonomy(),
                "field"    => "term_id",
                "terms"    => array_map( "intval", $locations ),
            ];
        }

        if ( ! empty( $tax_query ) ) {
            //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering listings by taxonomy is the point of this query.
            $wp_query_args["tax_query"] = $tax_query;
        }

        if ( ! empty( $is_featured ) && filter_var( $is_featured, FILTER_VALIDATE_BOOLEAN ) ) {
            //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtering listings by the featured flag is the point of this query.
            $wp_query_args["meta_query"] = [
                [
                    "key"   => "_featured",
                    "value" => "1",
                ],
            ];
        }

        $query = new WP_Query( $wp_query_args );

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
     * Get single listing.
     *
     * @param ListingDTO|null $listing The listing DTO.
     * @param Request         $request The REST request instance.
     * @param array           $fields The requested fields.
     * @return ListingDTO|null
     */
    public function listing( ?ListingDTO $listing, Request $request, array $fields = [] ): ?ListingDTO {
        $listing_id = (int) craf_appna_route_param( $request, "id" );
        $post_type  = $this->get_post_type();
        $post       = get_post( $listing_id );

        if ( ! $post instanceof WP_Post || $post->post_type !== $post_type || $post->post_status !== "publish" || craf_appna_is_post_password_protected( $post ) ) {
            return null;
        }

        return $this->map_listing_to_dto( $post, $fields );
    }

    /**
     * Get related listings paginator.
     *
     * @param ListingPaginatorDTO|null $listing_paginator The listing paginator.
     * @param Request                  $request The REST request instance.
     * @param array                    $fields The requested fields.
     * @return ListingPaginatorDTO
     */
    public function related_listings( ?ListingPaginatorDTO $listing_paginator, Request $request, array $fields = [] ): ListingPaginatorDTO {
        $listing_id = (int) craf_appna_route_param( $request, "id" );
        $page       = (int) $request->get_param( "page" ) ?: 1;
        $per_page   = (int) $request->get_param( "per_page" ) ?: 10;
        $post_type  = $this->get_post_type();
        $post       = get_post( $listing_id );

        if ( ! $post instanceof WP_Post || $post->post_type !== $post_type || $post->post_status !== "publish" || craf_appna_is_post_password_protected( $post ) ) {
            return new ListingPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $category_taxonomy = $this->get_category_taxonomy();
        $tag_taxonomy      = $this->get_tags_taxonomy();
        $category_ids      = wp_get_post_terms( $listing_id, $category_taxonomy, ["fields" => "ids"] );
        $tag_ids           = wp_get_post_terms( $listing_id, $tag_taxonomy, ["fields" => "ids"] );

        if ( is_wp_error( $category_ids ) ) {
            $category_ids = [];
        }

        if ( is_wp_error( $tag_ids ) ) {
            $tag_ids = [];
        }

        $tax_query = [
            "relation" => "OR",
        ];

        if ( ! empty( $category_ids ) ) {
            $tax_query[] = [
                "taxonomy" => $category_taxonomy,
                "field"    => "term_id",
                "terms"    => array_map( "intval", $category_ids ),
            ];
        }

        if ( ! empty( $tag_ids ) ) {
            $tax_query[] = [
                "taxonomy" => $tag_taxonomy,
                "field"    => "term_id",
                "terms"    => array_map( "intval", $tag_ids ),
            ];
        }

        if ( count( $tax_query ) === 1 ) {
            return new ListingPaginatorDTO( $page, $per_page, 0, 1, [] );
        }

        $query = new WP_Query(
            [
                "post_type"      => $post_type,
                "post_status"    => "publish",
                "has_password"   => false,
                "paged"          => $page,
                "posts_per_page" => $per_page,
                //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- small, bounded exclusion of the current listing from its own "related" query.
                "post__not_in"   => [ $listing_id ],
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering listings by taxonomy is the point of this query.
                "tax_query"      => $tax_query,
                "orderby"        => "date",
                "order"          => "DESC",
            ]
        );

        $items = [];
        foreach ( $query->posts as $related_listing ) {
            if ( $related_listing instanceof WP_Post ) {
                $items[] = $this->map_listing_to_dto( $related_listing, $fields );
            }
        }

        return new ListingPaginatorDTO(
            $page,
            $per_page,
            (int) $query->found_posts,
            max( 1, (int) $query->max_num_pages ),
            $items
        );
    }

    /**
     * Get approved reviews for a Directorist listing.
     *
     * @param array|null $reviews The review payload.
     * @param Request    $request The REST request instance.
     * @return array|null
     */
    public function reviews( ?array $reviews, Request $request ): ?array {
        $listing_id = (int) craf_appna_route_param( $request, "id" );
        $page       = (int) $request->get_param( "page" ) ?: 1;
        $per_page   = (int) $request->get_param( "per_page" ) ?: 10;
        $post_type  = $this->get_post_type();
        $post       = get_post( $listing_id );

        if ( ! $post instanceof WP_Post || $post->post_type !== $post_type || $post->post_status !== "publish" || craf_appna_is_post_password_protected( $post ) ) {
            return null;
        }

        $base_args = [
            "post_id" => $listing_id,
            "status"  => "approve",
            "type"    => "review",
        ];

        $total = (int) get_comments(
            array_merge(
                $base_args,
                [
                    "count" => true,
                ]
            )
        );

        $comments = get_comments(
            array_merge(
                $base_args,
                [
                    "number"  => $per_page,
                    "offset"  => ( $page - 1 ) * $per_page,
                    "orderby" => "comment_date_gmt",
                    "order"   => "DESC",
                ]
            )
        );

        $rating_counts = $this->get_review_rating_counts( $listing_id );
        $average       = function_exists( "directorist_get_listing_rating" )
            ? (float) directorist_get_listing_rating( $listing_id )
            : $this->calculate_average_rating( $rating_counts );
        $review_count  = function_exists( "directorist_get_listing_review_count" )
            ? (int) directorist_get_listing_review_count( $listing_id )
            : $total;

        return [
            "current_page"   => $page,
            "per_page"       => $per_page,
            "total"          => $total,
            "last_page"      => max( 1, (int) ceil( $total / $per_page ) ),
            "average_rating" => $average,
            "review_count"   => $review_count,
            "rating_counts"  => $rating_counts,
            "items"          => array_map( [$this, "map_review_comment"], $comments ),
        ];
    }

     /**
     * Resolve device-local wishlist listing IDs into full listing records.
     *
     * @param ListingPaginatorDTO|null $listing_paginator The listing paginator.
     * @param Request                  $request The REST request instance.
     * @param array                    $fields The requested fields.
     * @return ListingPaginatorDTO
     */
    public function wishlist( ?ListingPaginatorDTO $listing_paginator, Request $request, array $fields = [] ): ListingPaginatorDTO {
        $ids = (array) $request->get_param( "ids" );
        $ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

        if ( empty( $ids ) ) {
            return new ListingPaginatorDTO( 1, 0, 0, 1, [] );
        }

        $post_type = $this->get_post_type();

        $query = new WP_Query(
            [
                "post_type"      => $post_type,
                "post_status"    => "publish",
                "has_password"   => false,
                "posts_per_page" => count( $ids ),
                "post__in"       => $ids,
                "orderby"        => "post__in",
            ]
        );

        $items = [];
        foreach ( $query->posts as $listing ) {
            if ( $listing instanceof WP_Post ) {
                $items[] = $this->map_listing_to_dto( $listing, $fields );
            }
        }

        return new ListingPaginatorDTO( 1, count( $items ), count( $items ), 1, $items );
    }

    /**
     * Get categories paginator.
     *
     * @param CategoryPaginatorDTO|null $category_paginator The category paginator.
     * @param Request                   $request The REST request instance.
     * @param array                     $fields The requested fields.
     * @return CategoryPaginatorDTO
     */
    public function categories( ?CategoryPaginatorDTO $category_paginator, Request $request, array $fields = [] ): CategoryPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $search   = sanitize_text_field( (string) $request->get_param( "search" ) );
        $taxonomy = $this->get_category_taxonomy();

        $query = Term::join( "term_taxonomy", "terms.term_id", "=", "term_taxonomy.term_id" )
            ->where( "term_taxonomy.taxonomy", $taxonomy );

        $columns = $this->get_category_columns_from_fields( $fields );
        $query->select( $columns );

        if ( ! empty( $search ) ) {
            global $wpdb;
            $search = $wpdb->esc_like( $search );
            $query->where( "terms.name", "like", "%$search%" );
        }

        $sort     = sanitize_text_field( (string) $request->get_param( "sort" ) );
        $order_by = "name";
        $order    = "ASC";

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
            "name"  => "terms.name",
            "id"    => "terms.term_id",
            "slug"  => "terms.slug",
            "count" => "term_taxonomy.count",
        ];

        $query->order_by( $sort_map[$order_by] ?? "terms.name", $order );
        $paginator = $query->paginate( $page, $per_page );

        $items = [];
        foreach ( $paginator->items() as $term ) {
            $items[] = $this->map_term_to_category_dto( $term, $fields );
        }

        return new CategoryPaginatorDTO(
            $page,
            $per_page,
            $paginator->total(),
            $paginator->last_page(),
            $items
        );
    }

    /**
     * Get single category.
     *
     * @param CategoryDTO|null $category The category DTO.
     * @param Request          $request The REST request instance.
     * @param array            $fields The requested fields.
     * @return CategoryDTO|null
     */
    public function category( ?CategoryDTO $category, Request $request, array $fields = [] ): ?CategoryDTO {
        $category_id = (int) craf_appna_route_param( $request, "id" );
        $taxonomy    = $this->get_category_taxonomy();
        $term        = get_term( $category_id, $taxonomy );

        if ( ! $term || is_wp_error( $term ) ) {
            return null;
        }

        return $this->map_term_to_category_dto( $term, $fields );
    }

    /**
     * Get tags paginator.
     *
     * @param TermPaginatorDTO|null $tag_paginator The tag paginator.
     * @param Request               $request The REST request instance.
     * @param array                 $fields The requested fields.
     * @return TermPaginatorDTO
     */
    public function tags( ?TermPaginatorDTO $tag_paginator, Request $request, array $fields = [] ): TermPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $search   = sanitize_text_field( (string) $request->get_param( "search" ) );
        $taxonomy = $this->get_tags_taxonomy();

        $query = Term::join( "term_taxonomy", "terms.term_id", "=", "term_taxonomy.term_id" )
            ->where( "term_taxonomy.taxonomy", $taxonomy )
            ->select( ["terms.term_id", "terms.name", "terms.slug", "term_taxonomy.count"] );

        if ( ! empty( $search ) ) {
            global $wpdb;
            $search = $wpdb->esc_like( $search );
            $query->where( "terms.name", "like", "%$search%" );
        }

        $sort     = sanitize_text_field( (string) $request->get_param( "sort" ) );
        $order_by = "name";
        $order    = "ASC";

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
            "name" => "terms.name",
            "id"   => "terms.term_id",
            "slug" => "terms.slug",
        ];

        $query->order_by( $sort_map[$order_by] ?? "terms.name", $order );
        $paginator = $query->paginate( $page, $per_page );

        $items = [];
        foreach ( $paginator->items() as $term ) {
            $items[] = $this->map_term_to_dto( $term, $fields );
        }

        return new TermPaginatorDTO(
            $page,
            $per_page,
            $paginator->total(),
            $paginator->last_page(),
            $items
        );
    }

    /**
     * Get locations paginator.
     *
     * @param TermPaginatorDTO|null $location_paginator The location paginator.
     * @param Request               $request The REST request instance.
     * @param array                 $fields The requested fields.
     * @return TermPaginatorDTO
     */
    public function locations( ?TermPaginatorDTO $location_paginator, Request $request, array $fields = [] ): TermPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $search   = sanitize_text_field( (string) $request->get_param( "search" ) );
        $taxonomy = $this->get_location_taxonomy();

        $query = Term::join( "term_taxonomy", "terms.term_id", "=", "term_taxonomy.term_id" )
            ->where( "term_taxonomy.taxonomy", $taxonomy )
            ->select( ["terms.term_id", "terms.name", "terms.slug", "term_taxonomy.count"] );

        if ( ! empty( $search ) ) {
            global $wpdb;
            $search = $wpdb->esc_like( $search );
            $query->where( "terms.name", "like", "%$search%" );
        }

        $sort     = sanitize_text_field( (string) $request->get_param( "sort" ) );
        $order_by = "name";
        $order    = "ASC";

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
            "name" => "terms.name",
            "id"   => "terms.term_id",
            "slug" => "terms.slug",
        ];

        $query->order_by( $sort_map[$order_by] ?? "terms.name", $order );
        $paginator = $query->paginate( $page, $per_page );

        $items = [];
        foreach ( $paginator->items() as $term ) {
            $items[] = $this->map_term_to_dto( $term, $fields );
        }

        return new TermPaginatorDTO(
            $page,
            $per_page,
            $paginator->total(),
            $paginator->last_page(),
            $items
        );
    }

    public function location( ?TermDTO $location, Request $request, array $fields = [] ): ?TermDTO {
        $location_id = (int) craf_appna_route_param( $request, "id" );
        $taxonomy    = $this->get_location_taxonomy();
        $term        = get_term( $location_id, $taxonomy );

        if ( ! $term || is_wp_error( $term ) ) {
            return null;
        }

        return $this->map_term_to_dto( $term, $fields );
    }

    /**
     * Map term result to TermDTO.
     *
     * @param mixed $term The term result.
     * @param array $fields The requested fields.
     * @return TermDTO
     */
    private function map_term_to_dto( $term, array $fields ): TermDTO {
        $dto = new TermDTO();

        if ( in_array( "id", $fields, true ) ) {
            $dto->set_id( (int) $term->term_id );
        }
        if ( in_array( "name", $fields, true ) ) {
            $dto->set_name( $term->name );
        }
        if ( in_array( "slug", $fields, true ) ) {
            $dto->set_slug( $term->slug );
        }
        if ( in_array( "count", $fields, true ) ) {
            $dto->set_count( (int) $term->count );
        }
        if ( in_array( "image", $fields, true ) ) {
            $image = $this->get_term_image( (int) $term->term_id, ["location_img", "category_img", "thumbnail_id", "image"] );
            if ( ! empty( $image ) ) {
                $dto->set_image( $image );
            }
        }

        return $dto;
    }

    private function get_term_image( int $term_id, array $meta_keys ): array {
        foreach ( $meta_keys as $meta_key ) {
            $image = $this->normalize_term_image_meta( get_term_meta( $term_id, $meta_key, true ) );
            if ( empty( $image ) ) {
                continue;
            }

            return $image;
        }

        return [];
    }

    private function normalize_term_image_meta( $image_meta ): array {
        if ( empty( $image_meta ) ) {
            return [];
        }

        if ( is_array( $image_meta ) ) {
            $image_id = (int) ( $image_meta["id"] ?? $image_meta["attachment_id"] ?? $image_meta["attachmentId"] ?? 0 );
            $src      = (string) ( $image_meta["src"] ?? $image_meta["url"] ?? "" );

            if ( ! $src && $image_id ) {
                $src = (string) wp_get_attachment_url( $image_id );
            }

            if ( $src ) {
                return [
                    "id"  => $image_id,
                    "src" => esc_url_raw( $src ),
                ];
            }

            return [];
        }

        if ( is_numeric( $image_meta ) ) {
            $image_id = (int) $image_meta;
            $src      = (string) wp_get_attachment_url( $image_id );

            return $src ? [
                "id"  => $image_id,
                "src" => esc_url_raw( $src ),
            ] : [];
        }

        if ( is_string( $image_meta ) && filter_var( $image_meta, FILTER_VALIDATE_URL ) ) {
            return [
                "id"  => 0,
                "src" => esc_url_raw( $image_meta ),
            ];
        }

        return [];
    }

    /**
     * Get SQL columns from fields for categories.
     *
     * @param array $fields The requested fields.
     * @return array
     */
    private function get_category_columns_from_fields( array $fields ): array {
        $map = [
            "id"          => "terms.term_id",
            "name"        => "terms.name",
            "slug"        => "terms.slug",
            "description" => "term_taxonomy.description",
            "parent"      => "term_taxonomy.parent",
            "count"       => "term_taxonomy.count",
        ];

        $columns = ["terms.term_id"];
        foreach ( $fields as $field ) {
            if ( isset( $map[$field] ) ) {
                $columns[] = $map[$field];
            }
        }

        return array_unique( $columns );
    }

    /**
     * Map term result to CategoryDTO.
     *
     * @param mixed $term The term result.
     * @param array $fields The requested fields.
     * @return CategoryDTO
     */
    private function map_term_to_category_dto( $term, array $fields ): CategoryDTO {
        $dto = new CategoryDTO();

        if ( in_array( "id", $fields, true ) ) {
            $dto->set_id( (int) $term->term_id );
        }
        if ( in_array( "name", $fields, true ) ) {
            $dto->set_name( $term->name );
        }
        if ( in_array( "slug", $fields, true ) ) {
            $dto->set_slug( $term->slug );
        }
        if ( in_array( "description", $fields, true ) ) {
            $dto->set_description( $term->description );
        }
        if ( in_array( "parent", $fields, true ) ) {
            $dto->set_parent( (int) $term->parent );
        }
        if ( in_array( "count", $fields, true ) ) {
            $dto->set_count( (int) $term->count );
        }
        if ( in_array( "image", $fields, true ) ) {
            $image = $this->get_term_image( (int) $term->term_id, ["image"] );
            if ( ! empty( $image ) ) {
                $dto->set_image( $image );
            }
        }

        return $dto;
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
        if ( in_array( "url", $fields, true ) ) {
            $dto->set_url( (string) get_permalink( $listing ) );
        }
        if ( in_array( "title", $fields, true ) ) {
            $dto->set_title( get_the_title( $listing ) );
        }
        if ( in_array( "slug", $fields, true ) ) {
            $dto->set_slug( (string) $listing->post_name );
        }
        if ( in_array( "description", $fields, true ) ) {
            $dto->set_description( $this->apply_listing_content_filters( $listing ) );
        }
        if ( in_array( "excerpt", $fields, true ) ) {
            $dto->set_excerpt( $this->get_listing_excerpt( $listing ) );
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
        if ( in_array( "latitude", $fields, true ) ) {
            $dto->set_latitude( $this->normalize_coordinate( $this->get_meta_value( $listing->ID, ["_manual_lat", "manual_lat"] ), -90, 90 ) );
        }
        if ( in_array( "longitude", $fields, true ) ) {
            $dto->set_longitude( $this->normalize_coordinate( $this->get_meta_value( $listing->ID, ["_manual_lng", "manual_lng"] ), -180, 180 ) );
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
            $dto->set_categories( $this->get_listing_terms( $listing->ID, $this->get_category_taxonomy() ) );
        }
        if ( in_array( "locations", $fields, true ) ) {
            $dto->set_locations( $this->get_listing_terms( $listing->ID, $this->get_location_taxonomy() ) );
        }
        if ( in_array( "tags", $fields, true ) ) {
            $dto->set_tags( $this->get_listing_terms( $listing->ID, $this->get_tags_taxonomy() ) );
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
                return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : "";
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
        $src = $image_id ? wp_get_attachment_url( $image_id ) : "";

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

    /**
     * Map a WordPress review comment to the mobile API shape.
     *
     * @param \WP_Comment $comment The review comment.
     * @return array
     */
    private function map_review_comment( \WP_Comment $comment ): array {
        return [
            "id"           => (int) $comment->comment_ID,
            "reviewer"     => sanitize_text_field( (string) $comment->comment_author ),
            "review"       => wp_kses_post( (string) $comment->comment_content ),
            "rating"       => (float) get_comment_meta( $comment->comment_ID, "rating", true ),
            "date_created" => (string) get_comment_date( DATE_ATOM, $comment ),
            "avatar_url"   => (string) get_avatar_url(
                $comment,
                [
                    "size" => 96,
                ]
            ),
        ];
    }

    /**
     * Get rating distribution for a listing.
     *
     * @param int $listing_id The listing ID.
     * @return array
     */
    private function get_review_rating_counts( int $listing_id ): array {
        $counts     = get_post_meta( $listing_id, "_directorist_listing_rating_counts", true );
        $normalized = [
            "1" => 0,
            "2" => 0,
            "3" => 0,
            "4" => 0,
            "5" => 0,
        ];

        if ( is_array( $counts ) ) {
            foreach ( $normalized as $rating => $count ) {
                $normalized[$rating] = (int) ( $counts[$rating] ?? 0 );
            }

            return $normalized;
        }

        $comments = get_comments(
            [
                "post_id" => $listing_id,
                "status"  => "approve",
                "type"    => "review",
            ]
        );

        foreach ( $comments as $comment ) {
            $rating = (int) round( (float) get_comment_meta( $comment->comment_ID, "rating", true ) );
            $rating = max( 1, min( 5, $rating ) );
            $normalized[(string) $rating]++;
        }

        return $normalized;
    }

    /**
     * Calculate the average rating from distribution counts.
     *
     * @param array $rating_counts The rating counts.
     * @return float
     */
    private function calculate_average_rating( array $rating_counts ): float {
        $total = 0;
        $sum   = 0;

        foreach ( $rating_counts as $rating => $count ) {
            $total += (int) $count;
            $sum   += (int) $rating * (int) $count;
        }

        return $total > 0 ? round( $sum / $total, 1 ) : 0.0;
    }
}
