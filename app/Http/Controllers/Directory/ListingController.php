<?php

namespace Crafium\AppNatively\App\Http\Controllers\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\App\DTO\Directory\ListingDTO;
use Crafium\AppNatively\App\DTO\Directory\ListingPaginatorDTO;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class ListingController extends Controller {
    /**
     * The allowed fields for the resource.
     *
     * @var array
     */
    protected array $allowed_fields = [
        "id",
        "title",
        "slug",
        "description",
        "excerpt",
        "status",
        "image",
        "views_count",
        "address",
        "latitude",
        "longitude",
        "phone",
        "email",
        "website",
        "favorite",
        "featured",
        "new",
        "popular",
        "pricing",
        "categories",
        "locations",
        "tags",
        "rating",
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
                "page"        => "nullable|integer|min:1",
                "per_page"    => "nullable|integer|min:1|max:100",
                "search"      => "nullable|string",
                "sort"        => "nullable|string",
                "fields"      => "nullable|string",
                "integration" => "required|string",
                "categories"  => "nullable|array",
                "tags"        => "nullable|array",
                "locations"   => "nullable|array",
                "isFeatured"  => "nullable|boolean",
            ]
        );

        $integration       = sanitize_text_field( $request->get_param( "integration" ) );
        $fields            = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );
        $listing_paginator = apply_filters( "craf_appna_directory_{$integration}_listings", null, $request, $fields );

        if ( ! $listing_paginator instanceof ListingPaginatorDTO ) {
            throw new Exception( esc_html__( "Listings integration not found", "appnatively" ) );
        }

        return Response::send( ["data" => $listing_paginator] );
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
                "id"          => "required|numeric",
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $fields      = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );

        if ( empty( $fields ) ) {
            $fields = $this->allowed_fields;
        }

        $listing = apply_filters( "craf_appna_directory_{$integration}_listing", null, $request, $fields );

        if ( ! $listing instanceof ListingDTO ) {
            throw new Exception( esc_html__( "Listing not found", "appnatively" ) );
        }

        return Response::send(
            [
                "data" => $listing
            ]
        );
    }

    /**
     * Display approved reviews for the specified listing.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function reviews( Request $request ): array {
        $request->validate(
            [
                "id"          => "required|numeric",
                "page"        => "nullable|integer|min:1",
                "per_page"    => "nullable|integer|min:1|max:100",
                "integration" => "required|string",
            ]
        );

        $listing_id = (int) $request->get_param( "id" );
        $page       = (int) $request->get_param( "page" ) ?: 1;
        $per_page   = (int) $request->get_param( "per_page" ) ?: 10;
        $post_type  = defined( "ATBDP_POST_TYPE" ) ? ATBDP_POST_TYPE : "at_biz_dir";
        $post       = get_post( $listing_id );

        if ( ! $post instanceof \WP_Post || $post->post_type !== $post_type || $post->post_status !== "publish" ) {
            throw new Exception( esc_html__( "Listing not found", "appnatively" ) );
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
        $items         = array_map( [$this, "map_review_comment"], $comments );
        $average       = function_exists( "directorist_get_listing_rating" )
            ? (float) directorist_get_listing_rating( $listing_id )
            : $this->calculate_average_rating( $rating_counts );
        $review_count  = function_exists( "directorist_get_listing_review_count" )
            ? (int) directorist_get_listing_review_count( $listing_id )
            : $total;

        return Response::send(
            [
                "data" => [
                    "current_page"   => $page,
                    "per_page"       => $per_page,
                    "total"          => $total,
                    "last_page"      => max( 1, (int) ceil( $total / $per_page ) ),
                    "average_rating" => $average,
                    "review_count"   => $review_count,
                    "rating_counts"  => $rating_counts,
                    "items"          => $items,
                ],
            ]
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
            "reviewer"     => (string) $comment->comment_author,
            "review"       => (string) $comment->comment_content,
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
        $counts = get_post_meta( $listing_id, "_directorist_listing_rating_counts", true );
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
