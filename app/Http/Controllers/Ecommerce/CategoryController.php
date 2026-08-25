<?php

namespace Crafium\AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\CategoryDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CategoryPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class CategoryController extends Controller {
    /**
     * The allowed fields for the resource.
     *
     * @var array
     */
    protected array $allowed_fields = [
        "id",
        "name",
        "slug",
        "description",
        "parent",
        "count",
        "image",
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
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration       = sanitize_text_field( $request->get_param( "integration" ) );
        $product_paginator = apply_filters( "craf_appna_ecommerce_{$integration}_categories", null, $request, $this->allowed_fields );

        if ( ! $product_paginator instanceof CategoryPaginatorDTO ) {
            throw new Exception( esc_html__( "Category paginator not found", 'appnatively' ) );
        }

        return Response::send( ["data" => $product_paginator] );
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
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $product     = apply_filters( "craf_appna_ecommerce_{$integration}_category", null, $request, $this->allowed_fields );

        if ( ! $product instanceof CategoryDTO ) {
            throw new Exception( esc_html__( "Category not found", 'appnatively' ) );
        }

        return Response::send(
            [
                "data" => $product
            ]
        );
    }
}