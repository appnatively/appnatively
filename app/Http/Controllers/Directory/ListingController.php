<?php

namespace Crafium\AppNatively\App\Http\Controllers\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\App\Http\Controllers\Concerns\ServesFilters;
use Crafium\AppNatively\App\DTO\Directory\ListingDTO;
use Crafium\AppNatively\App\DTO\Directory\ListingPaginatorDTO;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class ListingController extends Controller {
    use ServesFilters;

    /**
     * The allowed fields for the resource.
     *
     * @var array
     */
    protected array $allowed_fields = [
        "id",
        "url",
        "title",
        "slug",
        "description",
        "excerpt",
        "status",
        "image",
        "images",
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
        "rating_count",
        "review_count",
    ];

    /**
     * Rules for the params that narrow the listing list and its filters (see ServesFilters).
     *
     * @return array
     */
    protected function context_filter_rules(): array {
        return $this->filter_context_rules( craf_appna_get_directory_integrations(), [ "featured" ] );
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $request->validate( array_merge( $this->context_filter_rules(), $this->filter_list_rules(), [ "fields" => "nullable|string" ] ) );

        $integration       = sanitize_text_field( $request->get_param( "integration" ) );
        $fields            = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );
        $listing_paginator = apply_filters( "craf_appna_directory_{$integration}_listings", null, $request, $fields );

        if ( ! $listing_paginator instanceof ListingPaginatorDTO ) {
            throw new Exception( esc_html__( "Listings integration not found", "appnatively" ) );
        }

        return Response::send( ["data" => $listing_paginator] );
    }

    /**
     * Describe which filters are available for the current context, with per-option counts.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function filters( Request $request ): array {
        return $this->send_filters( $request, $this->context_filter_rules(), "craf_appna_directory_%s_listings_filters" );
    }

    /**
     * List what the app builder can offer as filter rows (taxonomies, the plugin's
     * own fields, custom fields) for the active integration.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function filter_sources( Request $request ): array {
        return $this->send_filter_sources( $request, craf_appna_get_directory_integrations(), "craf_appna_directory_%s_listings_filter_sources" );
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
                "integration" => "required|string|in:" . implode( ",", craf_appna_get_directory_integrations() ),
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
     * Display related listings for the specified resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function related( Request $request ): array {
        $request->validate(
            [
                "id"          => "required|numeric",
                "page"        => "nullable|integer|min:1",
                "per_page"    => "nullable|integer|min:1|max:100",
                "fields"      => "nullable|string",
                "integration" => "required|string|in:" . implode( ",", craf_appna_get_directory_integrations() ),
            ]
        );

        $integration       = sanitize_text_field( $request->get_param( "integration" ) );
        $fields            = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );
        $listing_paginator = apply_filters( "craf_appna_directory_{$integration}_related_listings", null, $request, $fields );

        if ( ! $listing_paginator instanceof ListingPaginatorDTO ) {
            throw new Exception( esc_html__( "Related listings integration not found", "appnatively" ) );
        }

        return Response::send( ["data" => $listing_paginator] );
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
                "rating"      => "nullable|integer|min:1|max:5",
                "orderby"     => "nullable|string|in:newest,rating_desc,rating_asc",
                "integration" => "required|string|in:" . implode( ",", craf_appna_get_directory_integrations() ),
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $hook        = "craf_appna_directory_{$integration}_reviews";

        if ( ! has_filter( $hook ) ) {
            throw new Exception( esc_html__( "Reviews integration not found", "appnatively" ) );
        }

        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- $hook is built from the "craf_appna_directory_" literal above; the sniff just can't see through the interpolation.
        $reviews = apply_filters( $hook, null, $request );

        if ( null === $reviews ) {
            throw new Exception( esc_html__( "Listing not found", "appnatively" ) );
        }

        if ( ! $this->is_valid_review_payload( $reviews ) ) {
            throw new Exception( esc_html__( "Reviews integration not found", "appnatively" ) );
        }

        return Response::send( ["data" => $reviews] );
    }

    /**
     * Validate provider review payload shape.
     *
     * @param mixed $reviews The provider review payload.
     * @return bool
     */
    private function is_valid_review_payload( $reviews ): bool {
        if ( ! is_array( $reviews ) ) {
            return false;
        }

        foreach ( ["current_page", "per_page", "total", "last_page", "average_rating", "review_count", "rating_counts", "items"] as $key ) {
            if ( ! array_key_exists( $key, $reviews ) ) {
                return false;
            }
        }

        return is_array( $reviews["rating_counts"] ) && is_array( $reviews["items"] );
    }
}
