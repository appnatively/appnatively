<?php

namespace Crafium\AppNatively\App\Http\Controllers\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Directory\TermPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TagController extends Controller {
    protected array $allowed_fields = [
        "id",
        "name",
        "slug",
    ];

    public function index( Request $request ): array {
        $request->validate(
            [
                "page"        => "nullable|integer|min:1",
                "per_page"    => "nullable|integer|min:1|max:100",
                "search"      => "nullable|string",
                "sort"        => "nullable|string",
                "fields"      => "nullable|string",
                "integration" => "required|string",
            ]
        );

        $integration    = sanitize_text_field( $request->get_param( "integration" ) );
        $fields         = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );
        $tag_paginator  = apply_filters( "craf_appna_directory_{$integration}_tags", null, $request, $fields );

        if ( ! $tag_paginator instanceof TermPaginatorDTO ) {
            throw new Exception( esc_html__( "Tags integration not found", "appnatively" ) );
        }

        return Response::send( ["data" => $tag_paginator] );
    }
}
