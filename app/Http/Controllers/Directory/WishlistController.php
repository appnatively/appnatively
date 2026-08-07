<?php

namespace Crafium\AppNatively\App\Http\Controllers\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Directory\ListingPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class WishlistController extends Controller {
    /**
     * The allowed fields for the resource — mirrors ListingController's list
     * view fields, since the wishlist renders the same listing card.
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
     * Resolve device-local wishlist listing IDs into full listing records.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function index( Request $request ): array {
        $request->validate(
            [
                "ids"         => "required|array|max:100",
                "integration" => "required|string|in:" . implode( ",", craf_appna_get_directory_integrations() ),
            ]
        );

        $integration       = sanitize_text_field( $request->get_param( "integration" ) );
        $listing_paginator = apply_filters( "craf_appna_directory_{$integration}_wishlist", null, $request, $this->allowed_fields );

        if ( ! $listing_paginator instanceof ListingPaginatorDTO ) {
            throw new Exception( esc_html__( "Wishlist integration not found", 'appnatively' ) );
        }

        return Response::send( ["data" => $listing_paginator] );
    }
}
