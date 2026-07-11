<?php

namespace Crafium\AppNatively\App\Http\Controllers\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Directory\CategoryDTO;
use Crafium\AppNatively\App\DTO\Directory\CategoryPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

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
        $fields             = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );
        $category_paginator = apply_filters( "craf_appna_directory_{$integration}_categories", null, $request, $fields );

        if ( ! $category_paginator instanceof CategoryPaginatorDTO ) {
            throw new Exception( esc_html__( "Categories not found", "appnatively" ) );
        }

        return Response::send( ["data" => $category_paginator] );
    }

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

        $category = apply_filters( "craf_appna_directory_{$integration}_category", null, $request, $fields );

        if ( ! $category instanceof CategoryDTO ) {
            throw new Exception( esc_html__( "Category not found", "appnatively" ) );
        }

        return Response::send( ["data" => $category] );
    }
}
