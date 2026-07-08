<?php

namespace Crafium\AppNatively\App\Http\Controllers\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Controller;
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
}
