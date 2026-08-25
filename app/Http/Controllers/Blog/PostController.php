<?php

namespace Crafium\AppNatively\App\Http\Controllers\Blog;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Blog\PostDTO;
use Crafium\AppNatively\App\DTO\Blog\PostPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use WP_Post;
use WP_Query;

class PostController extends Controller {
    /**
     * The allowed fields for the resource.
     *
     * @var array
     */
    protected array $allowed_fields = [
        "id",
        "title",
        "slug",
        "excerpt",
        "content",
        "status",
        "url",
        "thumbnail",
        "categories",
        "date",
    ];

    /**
     * Display a listing of the resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $request->validate(
            [
                "page"           => "nullable|integer|min:1",
                "per_page"       => "nullable|integer|min:1|max:100",
                "search"         => "nullable|string",
                "sort"           => "nullable|string",
                "fields"         => "nullable|string",
                "postCategoryId" => "nullable|integer",
            ]
        );

        $page             = (int) $request->get_param( "page" ) ?: 1;
        $per_page         = (int) $request->get_param( "per_page" ) ?: 10;
        $search           = sanitize_text_field( (string) $request->get_param( "search" ) );
        $post_category_id = (int) $request->get_param( "postCategoryId" );
        $fields           = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );

        $sort     = sanitize_text_field( (string) $request->get_param( "sort" ) );
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
            "id"    => "ID",
        ];

        $query_args = [
            "post_type"      => "post",
            "post_status"    => "publish",
            // Password-protected posts keep the `publish` status. WP_Query only
            // drops them on its own for search queries, so ask explicitly.
            "has_password"   => false,
            "paged"          => $page,
            "posts_per_page" => $per_page,
            "s"              => $search,
            "orderby"        => $sort_map[$order_by] ?? "date",
            "order"          => $order,
        ];

        if ( $post_category_id ) {
            //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering posts by category is the point of this query.
            $query_args["tax_query"] = [
                [
                    "taxonomy" => "category",
                    "field"    => "term_id",
                    "terms"    => $post_category_id,
                ],
            ];
        }

        $query = new WP_Query( $query_args );

        $items = [];
        foreach ( $query->posts as $post ) {
            if ( $post instanceof WP_Post ) {
                $items[] = $this->map_post_to_dto( $post, $fields );
            }
        }

        $post_paginator = new PostPaginatorDTO(
            $page,
            $per_page,
            (int) $query->found_posts,
            (int) $query->max_num_pages,
            $items
        );

        return Response::send( ["data" => $post_paginator] );
    }

    /**
     * Display posts related to the specified resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function related( Request $request ): array {
        $request->validate(
            [
                "id"       => "required|numeric",
                "page"     => "nullable|integer|min:1",
                "per_page" => "nullable|integer|min:1|max:100",
                "fields"   => "nullable|string",
            ]
        );

        $id       = (int) craf_appna_route_param( $request, "id" );
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $fields   = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );

        $category_ids = wp_get_post_categories( $id );

        $query_args = [
            "post_type"      => "post",
            "post_status"    => "publish",
            "has_password"   => false,
            "paged"          => $page,
            "posts_per_page" => $per_page,
            //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- small, bounded exclusion of the current post from its own "related" query.
            "post__not_in"   => [$id],
            "orderby"        => "date",
            "order"          => "DESC",
        ];

        if ( ! empty( $category_ids ) ) {
            $query_args["category__in"] = $category_ids;
        }

        $query = new WP_Query( $query_args );

        $items = [];
        foreach ( $query->posts as $post ) {
            if ( $post instanceof WP_Post ) {
                $items[] = $this->map_post_to_dto( $post, $fields );
            }
        }

        $post_paginator = new PostPaginatorDTO(
            $page,
            $per_page,
            (int) $query->found_posts,
            (int) $query->max_num_pages,
            $items
        );

        return Response::send( ["data" => $post_paginator] );
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function show( Request $request ): array {
        $request->validate(
            [
                "id" => "required|numeric",
            ]
        );

        $id   = (int) craf_appna_route_param( $request, "id" );
        $post = get_post( $id );

        if ( ! $post instanceof WP_Post || "post" !== $post->post_type || "publish" !== $post->post_status ) {
            throw new Exception( esc_html__( "Post not found", "appnatively" ) );
        }

        // Reported the same way as a missing post: whether a given id is
        // protected rather than absent is not something to disclose.
        if ( craf_appna_is_post_password_protected( $post ) ) {
            throw new Exception( esc_html__( "Post not found", "appnatively" ) );
        }

        return Response::send(
            [
                "data" => $this->map_post_to_dto( $post, $this->allowed_fields ),
            ]
        );
    }

    /**
     * Map a WP_Post to a PostDTO.
     *
     * @param WP_Post $post The post.
     * @param array   $fields The requested fields.
     * @return PostDTO
     */
    private function map_post_to_dto( WP_Post $post, array $fields ): PostDTO {
        $dto = new PostDTO();

        if ( in_array( "id", $fields, true ) ) {
            $dto->set_id( (int) $post->ID );
        }
        if ( in_array( "title", $fields, true ) ) {
            $dto->set_title( get_the_title( $post ) );
        }
        if ( in_array( "slug", $fields, true ) ) {
            $dto->set_slug( (string) $post->post_name );
        }
        if ( in_array( "excerpt", $fields, true ) ) {
            $dto->set_excerpt( (string) get_the_excerpt( $post ) );
        }
        if ( in_array( "content", $fields, true ) ) {
            //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking WordPress core's own "the_content" filter to render post content the same way a theme would, not defining a hook of our own.
            $dto->set_content( (string) apply_filters( "the_content", $post->post_content ) );
        }
        if ( in_array( "status", $fields, true ) ) {
            $dto->set_status( (string) $post->post_status );
        }
        if ( in_array( "url", $fields, true ) ) {
            $dto->set_url( (string) get_permalink( $post ) );
        }
        if ( in_array( "thumbnail", $fields, true ) ) {
            $dto->set_thumbnail( $this->get_post_thumbnail( $post->ID ) );
        }
        if ( in_array( "categories", $fields, true ) ) {
            $dto->set_categories( $this->get_post_categories( $post->ID ) );
        }
        if ( in_array( "date", $fields, true ) ) {
            $dto->set_date( (string) $post->post_date );
        }

        return $dto;
    }

    /**
     * Get normalized post thumbnail data.
     *
     * @param int $post_id The post ID.
     * @return array
     */
    private function get_post_thumbnail( int $post_id ): array {
        $image_id = get_post_thumbnail_id( $post_id );

        if ( ! $image_id ) {
            return [];
        }

        $src = wp_get_attachment_url( $image_id );

        if ( ! $src ) {
            return [];
        }

        return [
            "id"  => (int) $image_id,
            "src" => (string) $src,
            "alt" => (string) get_post_meta( $image_id, "_wp_attachment_image_alt", true ),
        ];
    }

    /**
     * Get normalized category terms for a post.
     *
     * @param int $post_id The post ID.
     * @return array
     */
    private function get_post_categories( int $post_id ): array {
        $terms = get_the_terms( $post_id, "category" );

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
