<?php

namespace Crafium\AppNatively\App\Http\Controllers\PostType;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\PostType\PostDTO;
use Crafium\AppNatively\App\DTO\PostType\PostPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\App\Support\ContentTypes;
use Crafium\AppNatively\App\Support\CustomFields;
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
        "date",
        "post_type",
        "terms",
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
                "post_type"      => "nullable|string",
                "taxonomy"       => "nullable|string",
                "term_id"        => "nullable|integer",
                "custom_fields"  => "nullable|string",
            ]
        );

        $post_type = $this->resolve_post_type( $request );

        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $search   = sanitize_text_field( (string) $request->get_param( "search" ) );
        $term_id = (int) $request->get_param( "term_id" );
        $fields  = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );

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
            "post_type"      => $post_type,
            "post_status"    => "publish",
            // Password-protected posts keep the `publish` status. WP_Query only
            // drops them on its own for search queries, so ask explicitly.
            "has_password"   => false,
            // Sticky posts are prepended by core on page 1 through a query that skips the
            // `has_password` and sort clauses above, so they are not asked for.
            "ignore_sticky_posts" => true,
            "paged"          => $page,
            "posts_per_page" => $per_page,
            "s"              => $search,
            "orderby"        => $sort_map[$order_by] ?? "date",
            "order"          => $order,
        ];

        if ( $term_id ) {
            //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering posts by term is the point of this query.
            $query_args["tax_query"] = [
                [
                    "taxonomy" => $this->resolve_taxonomy( $request, $post_type ),
                    "field"    => "term_id",
                    "terms"    => $term_id,
                ],
            ];
        }

        $query         = new WP_Query( $query_args );
        $custom_fields = $this->requested_custom_fields( $request, $post_type );

        $items = $this->map_query_to_dtos( $query, $fields, $custom_fields );

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
                "id"            => "required|numeric",
                "page"          => "nullable|integer|min:1",
                "per_page"      => "nullable|integer|min:1|max:100",
                "fields"        => "nullable|string",
                "post_type"     => "nullable|string",
                "custom_fields" => "nullable|string",
            ]
        );

        $id       = (int) craf_appna_route_param( $request, "id" );
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $fields   = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );

        // Related entries share the source post's type, so it is resolved from
        // the post itself; a mismatching `post_type` is treated as not found.
        $source    = $this->find_post( $id, $request );
        $post_type = (string) $source->post_type;
        $taxonomy  = ContentTypes::primary_taxonomy( $post_type );
        $term_ids  = $taxonomy ? wp_get_object_terms( $id, $taxonomy, [ "fields" => "ids" ] ) : [];

        $query_args = [
            "post_type"      => $post_type,
            "post_status"    => "publish",
            "has_password"   => false,
            "ignore_sticky_posts" => true,
            "paged"          => $page,
            "posts_per_page" => $per_page,
            //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- small, bounded exclusion of the current post from its own "related" query.
            "post__not_in"   => [$id],
            "orderby"        => "date",
            "order"          => "DESC",
        ];

        if ( $taxonomy && ! empty( $term_ids ) && ! is_wp_error( $term_ids ) ) {
            //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- related means sharing a term with the source post.
            $query_args["tax_query"] = [
                [
                    "taxonomy" => $taxonomy,
                    "field"    => "term_id",
                    "terms"    => $term_ids,
                ],
            ];
        } else {
            // A post with no terms shares none with anything: nothing is related to it, rather
            // than the newest posts of the type.
            $query_args["post__in"] = [0];
        }

        $query         = new WP_Query( $query_args );
        $custom_fields = $this->requested_custom_fields( $request, $post_type );

        $items = $this->map_query_to_dtos( $query, $fields, $custom_fields );

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
                "id"            => "required|numeric",
                "post_type"     => "nullable|string",
                "custom_fields" => "nullable|string",
            ]
        );

        $post = $this->find_post( (int) craf_appna_route_param( $request, "id" ), $request );

        return Response::send(
            [
                "data" => $this->map_post_to_dto(
                    $post,
                    $this->allowed_fields,
                    // Every field the site exposes for the type, unless the request names some.
                    null === $request->get_param( "custom_fields" ) || "" === (string) $request->get_param( "custom_fields" )
                        ? ContentTypes::allowed_fields( (string) $post->post_type )
                        : $this->requested_custom_fields( $request, (string) $post->post_type )
                ),
            ]
        );
    }

    /**
     * Find a published, readable post of an allowed type.
     *
     * Every refusal is reported the same way as a missing post: whether a given
     * id is protected, or of a type the app may not read, rather than absent is
     * not something to disclose.
     *
     * @param int     $id      The post id.
     * @param Request $request The REST request; its `post_type`, when given, must match.
     * @return WP_Post
     * @throws Exception
     */
    private function find_post( int $id, Request $request ): WP_Post {
        $post      = get_post( $id );
        $requested = sanitize_key( (string) $request->get_param( "post_type" ) );

        if ( ! $post instanceof WP_Post
            || "publish" !== $post->post_status
            || ! ContentTypes::is_allowed_post_type( (string) $post->post_type )
            || ( "" !== $requested && $requested !== $post->post_type )
            || craf_appna_is_post_password_protected( $post )
        ) {
            throw new Exception( esc_html__( "Post not found", "appnatively" ) );
        }

        return $post;
    }

    /**
     * The requested post type, `post` when none is given.
     *
     * @param Request $request The REST request.
     * @return string
     * @throws Exception When the app may not read that post type.
     */
    private function resolve_post_type( Request $request ): string {
        $post_type = sanitize_key( (string) $request->get_param( "post_type" ) ) ?: ContentTypes::DEFAULT_POST_TYPE;

        if ( ! ContentTypes::is_allowed_post_type( $post_type ) ) {
            throw new Exception( esc_html__( "Post type not found", "appnatively" ) );
        }

        return $post_type;
    }

    /**
     * The requested taxonomy, the post type's primary one when none is given.
     *
     * @param Request $request   The REST request.
     * @param string  $post_type The resolved post type.
     * @return string
     * @throws Exception When the app may not read that taxonomy for the type.
     */
    private function resolve_taxonomy( Request $request, string $post_type ): string {
        $taxonomy = sanitize_key( (string) $request->get_param( "taxonomy" ) ) ?: (string) ContentTypes::primary_taxonomy( $post_type );

        if ( ! in_array( $taxonomy, ContentTypes::allowed_taxonomies( $post_type ), true ) ) {
            throw new Exception( esc_html__( "Taxonomy not found", "appnatively" ) );
        }

        return $taxonomy;
    }

    /**
     * Field definitions named by `custom_fields=a,b`, limited to the allowed
     * ones. Lists only carry the fields their cards render, to stay small.
     *
     * @param Request $request   The REST request.
     * @param string  $post_type The resolved post type.
     * @return array<string, array>
     */
    private function requested_custom_fields( Request $request, string $post_type ): array {
        $requested = (string) $request->get_param( "custom_fields" );

        if ( "" === $requested ) {
            return [];
        }

        return array_intersect_key(
            ContentTypes::allowed_fields( $post_type ),
            array_flip( array_map( "trim", explode( ",", $requested ) ) )
        );
    }

    /**
     * The DTOs of a query's posts. Password-protected posts are skipped even though the query
     * excludes them: a post that slips past a query clause must still not be served.
     *
     * @param WP_Query $query         The query.
     * @param array    $fields        The requested fields.
     * @param array    $custom_fields Custom field definitions to include.
     * @return PostDTO[]
     */
    private function map_query_to_dtos( WP_Query $query, array $fields, array $custom_fields ): array {
        // One query for every thumbnail instead of one per post.
        update_post_thumbnail_cache( $query );

        $items = [];
        foreach ( $query->posts as $post ) {
            if ( $post instanceof WP_Post && ! craf_appna_is_post_password_protected( $post ) ) {
                $items[] = $this->map_post_to_dto( $post, $fields, $custom_fields );
            }
        }

        return $items;
    }

    /**
     * Map a WP_Post to a PostDTO.
     *
     * @param WP_Post $post The post.
     * @param array   $fields The requested fields.
     * @param array   $custom_fields Custom field definitions to include, keyed by key.
     * @return PostDTO
     */
    private function map_post_to_dto( WP_Post $post, array $fields, array $custom_fields = [] ): PostDTO {
        $post_type = (string) $post->post_type;

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
            $dto->set_content( $this->render_content( $post ) );
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
        if ( in_array( "date", $fields, true ) ) {
            $dto->set_date( (string) $post->post_date );
        }
        if ( in_array( "post_type", $fields, true ) ) {
            $dto->set_post_type( $post_type );
        }
        if ( in_array( "terms", $fields, true ) ) {
            $terms = [];
            foreach ( ContentTypes::allowed_taxonomies( $post_type ) as $taxonomy ) {
                $terms[ $taxonomy ] = $this->get_post_terms( $post->ID, $taxonomy );
            }
            $dto->set_terms( $terms );
        }
        if ( ! empty( $custom_fields ) ) {
            $dto->set_custom_fields( CustomFields::read( $post, $custom_fields ) );
        }

        return $dto;
    }

    /**
     * The post's content as a theme would render it. Blocks and shortcodes read the current post
     * (`get_the_ID()`), so it is set for the duration and put back after.
     *
     * @param WP_Post $post The post.
     * @return string
     */
    private function render_content( WP_Post $post ): string {
        $previous = $GLOBALS["post"] ?? null;

        $GLOBALS["post"] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
        setup_postdata( $post );

        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking WordPress core's own "the_content" filter to render post content the same way a theme would, not defining a hook of our own.
        $html = (string) apply_filters( "the_content", $post->post_content );

        $GLOBALS["post"] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        if ( $previous instanceof WP_Post ) {
            setup_postdata( $previous );
        } else {
            wp_reset_postdata();
        }

        return $html;
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

        $src = CustomFields::is_public_attachment( (int) $image_id ) ? wp_get_attachment_url( $image_id ) : false;

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
     * Get normalized terms of one taxonomy for a post.
     *
     * @param int    $post_id  The post ID.
     * @param string $taxonomy The taxonomy.
     * @return array
     */
    private function get_post_terms( int $post_id, string $taxonomy ): array {
        $terms = get_the_terms( $post_id, $taxonomy );

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
