<?php

namespace Crafium\AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductFiltersDTO;
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
        "options",
        "url"
    ];

    /**
     * Validation rules shared by every action that accepts a product-filtering
     * context (category, search, price, availability, rating, attributes) —
     * `index()` and `filters()` both narrow the same product set the same way.
     *
     * @return array
     */
    protected function context_filter_rules(): array {
        return [
            "search"      => "nullable|string",
            "categoryId"  => "nullable|integer",
            "price_min"   => "nullable|numeric|min:0",
            "price_max"   => "nullable|numeric|min:0",
            "on_sale"     => "nullable|boolean",
            "in_stock"    => "nullable|boolean",
            "rating_min"  => "nullable|numeric|min:0|max:5",
            "attributes"  => "nullable|array",
            "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $request->validate(
            array_merge(
                $this->context_filter_rules(),
                [
                    "page"     => "nullable|integer|min:1",
                    "per_page" => "nullable|integer|min:1|max:100",
                    "sort"     => "nullable|string|in:" . implode( ',', ProductFiltersDTO::SORT_TOKENS ),
                ]
            )
        );

        $integration       = sanitize_text_field( $request->get_param( "integration" ) );
        $product_paginator = apply_filters( "craf_appna_ecommerce_{$integration}_products", null, $request, $this->allowed_fields );

        if ( ! $product_paginator instanceof ProductPaginatorDTO ) {
            throw new Exception( esc_html__( "Products integration not found", 'appnatively' ) );
        }

        return Response::send( ["data" => $product_paginator] );
    }

    /**
     * Describe which filters are available for the current context, with per-option counts.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function filters( Request $request ): array {
        $request->validate( $this->context_filter_rules() );

        $integration     = sanitize_text_field( $request->get_param( "integration" ) );
        $product_filters = apply_filters( "craf_appna_ecommerce_{$integration}_products_filters", null, $request );

        if ( ! $product_filters instanceof ProductFiltersDTO ) {
            throw new Exception( esc_html__( "Products integration not found", 'appnatively' ) );
        }

        return Response::send( ["data" => $product_filters] );
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