<?php

namespace Crafium\AppNatively\App\Integrations\Directory\Concerns;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Directory\TermPaginatorDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
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
            return [];
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
}
