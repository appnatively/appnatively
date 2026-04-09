<?php

namespace AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use AppNatively\App\DTO\Ecommerce\CategoryDTO;
use AppNatively\App\DTO\Ecommerce\CategoryPaginatorDTO;
use AppNatively\App\Http\Controllers\Controller;
use AppNatively\WpMVC\Exceptions\Exception;
use AppNatively\WpMVC\Routing\Response;
use AppNatively\WpMVC\RequestValidator\Request;

class CategoryController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $product_paginator = apply_filters( "appnatively_ecommerce_category_paginator", null, $request );

        if ( ! $product_paginator instanceof CategoryPaginatorDTO ) {
            throw new Exception( esc_html__( "Category paginator not found" ) );
        }

        $product_paginator = new CategoryPaginatorDTO( 1, 10, 100, 10, [] );

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
                "id" => "required|numeric"
            ]
        );

        $product = apply_filters( "appnatively_ecommerce_category", null, $request );

        if ( ! $product instanceof CategoryDTO ) {
            throw new Exception( esc_html__( "Category not found" ) );
        }

        return Response::send(
            [
                "data" => $product
            ]
        );
    }
}