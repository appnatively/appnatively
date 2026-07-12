<?php

namespace Crafium\AppNatively\App\Http\Controllers\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Directory\TermPaginatorDTO;
use Crafium\AppNatively\App\DTO\Directory\TermDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class LocationController extends Controller {
    protected array $allowed_fields = [
        "id",
        "name",
        "slug",
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
        $location_paginator = apply_filters( "craf_appna_directory_{$integration}_locations", null, $request, $fields );

        if ( ! $location_paginator instanceof TermPaginatorDTO ) {
            throw new Exception( esc_html__( "Locations integration not found", "appnatively" ) );
        }

        return Response::send( ["data" => $location_paginator] );
    }

    public function show( Request $request ): array {
        $request->validate(
            [
                "id"          => "required|numeric",
                "integration" => "required|string",
                "fields"      => "nullable|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $fields      = craf_appna_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields );

        if ( empty( $fields ) ) {
            $fields = $this->allowed_fields;
        }

        $location = apply_filters( "craf_appna_directory_{$integration}_location", null, $request, $fields );

        if ( ! $location instanceof TermDTO ) {
            throw new Exception( esc_html__( "Location not found", "appnatively" ) );
        }

        return Response::send( ["data" => $location] );
    }
}
