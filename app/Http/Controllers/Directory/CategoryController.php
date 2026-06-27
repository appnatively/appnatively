<?php

namespace AppNatively\App\Http\Controllers\Directory;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\Directory\CategoryPaginatorDTO;
use AppNatively\App\Http\Controllers\Controller;
use AppNatively\WpMVC\Exceptions\Exception;
use AppNatively\WpMVC\Routing\Response;
use AppNatively\WpMVC\RequestValidator\Request;

class CategoryController extends Controller {
    protected array $allowed_fields = [
        "id",
        "name",
        "slug",
        "description",
        "parent",
        "count",
        "image",
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

        $integration        = sanitize_text_field( $request->get_param( "integration" ) );
        $fields             = appnatively_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );
        $category_paginator = apply_filters( "appnatively_directory_{$integration}_categories", null, $request, $fields );

        if ( ! $category_paginator instanceof CategoryPaginatorDTO ) {
            throw new Exception( esc_html__( "Categories integration not found", "appnatively" ) );
        }

        return Response::send( ["data" => $category_paginator] );
    }
}
