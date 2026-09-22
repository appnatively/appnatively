<?php

namespace Crafium\AppNatively\App\Integrations\Directory\Concerns;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Directory\TermPaginatorDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use WP_Comment;
use WP_Post;

trait ListingIntegrationHelpers {
    /**
     * Apply the_content filters to a listing post outside the main loop.
     *
     * @param WP_Post $post The listing post.
     * @return string
     */
    private function apply_listing_content_filters( WP_Post $post ): string {
        $previous_post   = $GLOBALS["post"] ?? null;
        $GLOBALS["post"] = $post;

        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking WordPress core's own "the_content" filter to render the listing the same way a theme would, not defining a hook of our own.
        $content = (string) apply_filters( "the_content", $post->post_content );

        if ( null === $previous_post ) {
            unset( $GLOBALS["post"] );
        } else {
            $GLOBALS["post"] = $previous_post;
        }

        return $content;
    }

    /**
     * Get a listing post's excerpt outside the main loop.
     *
     * @param WP_Post $post The listing post.
     * @return string
     */
    private function get_listing_excerpt( WP_Post $post ): string {
        $previous_post   = $GLOBALS["post"] ?? null;
        $GLOBALS["post"] = $post;

        $excerpt = (string) get_the_excerpt( $post );

        if ( null === $previous_post ) {
            unset( $GLOBALS["post"] );
        } else {
            $GLOBALS["post"] = $previous_post;
        }

        return $excerpt;
    }

    /**
     * Normalize a coordinate value within bounds.
     *
     * @param mixed $value The raw coordinate value.
     * @param float $min   The minimum allowed value.
     * @param float $max   The maximum allowed value.
     * @return float|null
     */
    private function normalize_coordinate( $value, float $min, float $max ): ?float {
        if ( "" === $value || null === $value || ! is_numeric( $value ) ) {
            return null;
        }

        $coordinate = (float) $value;
        return ( $coordinate >= $min && $coordinate <= $max ) ? $coordinate : null;
    }

    /**
     * Normalize a value into a list of positive integer ids.
     *
     * @param mixed $value The raw ids value.
     * @return array
     */
    private function positive_ids( $value ): array {
        if ( ! is_array( $value ) ) {
            $value = [$value];
        }

        return array_values( array_filter( array_map( "intval", $value ), fn( int $id ): bool => $id > 0 ) );
    }

    /**
     * Build an empty term paginator DTO for the requested page.
     *
     * @param Request $request The REST request instance.
     * @return TermPaginatorDTO
     */
    private function empty_term_paginator( Request $request ): TermPaginatorDTO {
        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;

        return new TermPaginatorDTO( $page, $per_page, 0, 1, [] );
    }

    /**
     * Query listing review comments with AppNatively's shared review filters.
     *
     * Directory providers store ratings differently, so callers provide the
     * rating resolver while this helper owns paging, exact-star buckets, and
     * cross-provider sort behavior.
     *
     * @param array    $base_args       Base get_comments() arguments.
     * @param Request  $request         The REST request instance.
     * @param callable $rating_resolver Receives WP_Comment and returns a numeric rating.
     * @return array{current_page:int,per_page:int,total:int,last_page:int,rows:array<int,array{comment:WP_Comment,rating:float}>}
     */
    private function query_directory_review_rows( array $base_args, Request $request, callable $rating_resolver ): array {
        $page          = max( 1, (int) $request->get_param( "page" ) ?: 1 );
        $per_page      = max( 1, min( 100, (int) $request->get_param( "per_page" ) ?: 10 ) );
        $rating_filter = $this->directory_review_rating_filter( $request );
        $orderby       = $this->directory_review_orderby( $request );
        $comments      = get_comments(
            array_merge(
                $base_args,
                [
                    "orderby" => "comment_date_gmt",
                    "order"   => "DESC",
                ]
            )
        );
        $rows          = [];

        foreach ( $comments as $comment ) {
            if ( ! $comment instanceof WP_Comment ) {
                continue;
            }

            $rating = (float) call_user_func( $rating_resolver, $comment );
            if ( null !== $rating_filter && ( $rating <= 0 || $this->directory_review_rating_bucket( $rating ) !== $rating_filter ) ) {
                continue;
            }

            $rows[] = [
                "comment" => $comment,
                "rating"  => $rating,
            ];
        }

        usort(
            $rows,
            function( array $left, array $right ) use ( $orderby ): int {
                $date_compare = strcmp( (string) $right["comment"]->comment_date_gmt, (string) $left["comment"]->comment_date_gmt );

                if ( "rating_desc" === $orderby ) {
                    $rating_compare = $right["rating"] <=> $left["rating"];
                    return 0 !== $rating_compare ? $rating_compare : $date_compare;
                }

                if ( "rating_asc" === $orderby ) {
                    $rating_compare = $left["rating"] <=> $right["rating"];
                    return 0 !== $rating_compare ? $rating_compare : $date_compare;
                }

                return $date_compare;
            }
        );

        $total = count( $rows );

        return [
            "current_page" => $page,
            "per_page"     => $per_page,
            "total"        => $total,
            "last_page"    => max( 1, (int) ceil( $total / $per_page ) ),
            "rows"         => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
        ];
    }

    /**
     * Resolve an exact star filter from the request.
     *
     * @param Request $request The REST request instance.
     * @return int|null
     */
    private function directory_review_rating_filter( Request $request ): ?int {
        $rating = $request->get_param( "rating" );
        if ( null === $rating || "" === $rating || ! is_numeric( $rating ) ) {
            return null;
        }

        $rating = (int) $rating;
        return $rating >= 1 && $rating <= 5 ? $rating : null;
    }

    /**
     * Resolve the requested directory review sort order.
     *
     * @param Request $request The REST request instance.
     * @return string
     */
    private function directory_review_orderby( Request $request ): string {
        $orderby = sanitize_key( (string) $request->get_param( "orderby" ) );
        return in_array( $orderby, ["newest", "rating_desc", "rating_asc"], true ) ? $orderby : "newest";
    }

    /**
     * Convert a raw review rating into the visible star bucket.
     *
     * @param float $rating Raw provider rating.
     * @return int
     */
    private function directory_review_rating_bucket( float $rating ): int {
        return max( 1, min( 5, (int) round( $rating ) ) );
    }
}
