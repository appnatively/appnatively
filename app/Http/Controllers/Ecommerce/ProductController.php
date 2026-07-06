<?php

namespace Crafium\AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

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
     * Additional fields only fetched for a single product's detail view
     * (variation data — too heavy to include on every list item).
     *
     * @var array
     */
    protected array $detail_only_fields = [
        "type",
        "variants",
        "options"
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
                "categoryId"  => "nullable",
                "integration" => "required|string",
            ]
        );

        $integration       = sanitize_text_field( $request->get_param( "integration" ) );
        $product_paginator = apply_filters( "craf_appna_ecommerce_{$integration}_products", null, $request, $this->allowed_fields );

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
        $fields      = array_merge( $this->allowed_fields, $this->detail_only_fields );
        $product     = apply_filters( "craf_appna_ecommerce_{$integration}_product", null, $request, $fields );

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