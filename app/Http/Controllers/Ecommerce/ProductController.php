<?php

namespace AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\Ecommerce\ProductDTO;
use AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use AppNatively\App\Http\Controllers\Controller;
use AppNatively\WpMVC\Exceptions\Exception;
use AppNatively\WpMVC\Routing\Response;
use AppNatively\WpMVC\RequestValidator\Request;

class ProductController extends Controller {
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
        "short_description",
        "sku",
        "price",
        "regular_price",
        "sale_price",
        "on_sale",
        "status",
        "stock_status",
        "images",
        "categories"
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
                "integration" => "required|string",
            ]
        );

        $integration       = sanitize_text_field( $request->get_param( "integration" ) );
        $product_paginator = apply_filters( "appnatively_ecommerce_{$integration}_products", null, $request, $this->allowed_fields );

        if ( ! $product_paginator instanceof ProductPaginatorDTO ) {
            throw new Exception( esc_html__( "Products integration not found", 'appnatively' ) );
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
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $product     = apply_filters( "appnatively_ecommerce_{$integration}_product", null, $request, $this->allowed_fields );

        if ( ! $product instanceof ProductDTO ) {
            throw new Exception( esc_html__( "Product not found", 'appnatively' ) );
        }

        return Response::send(
            [
                "data" => $product
            ]
        );
    }
}