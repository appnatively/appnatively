<?php

namespace Crafium\AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\App\Http\Controllers\Concerns\ServesFilters;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class ProductController extends Controller {
    use ServesFilters;

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
        "compare_at_price",
        "on_sale",
        "status",
        "stock_status",
        "images",
        "categories",
        "average_rating",
        "rating_count"
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
     * Rules for the params that narrow the product list and its filters (see ServesFilters).
     *
     * @return array
     */
    protected function context_filter_rules(): array {
        return $this->filter_context_rules( craf_appna_get_ecommerce_integrations(), [ "in_stock" ] );
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $request->validate( array_merge( $this->context_filter_rules(), $this->filter_list_rules() ) );

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
        return $this->send_filters( $request, $this->context_filter_rules(), "craf_appna_ecommerce_%s_products_filters" );
    }

    /**
     * List what the app builder can offer as filter rows (taxonomies,
     * attributes, custom fields) for the active integration.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function filter_sources( Request $request ): array {
        return $this->send_filter_sources( $request, craf_appna_get_ecommerce_integrations(), "craf_appna_ecommerce_%s_products_filter_sources" );
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

    public function reviews( Request $request ): array {
        $request->validate(
            [
                'id'          => 'required|numeric',
                'page'        => 'nullable|integer|min:1',
                'per_page'    => 'nullable|integer|min:1|max:100',
                'integration' => 'required|string|' . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() )
            ] 
        );

        $integration = sanitize_text_field( $request->get_param( 'integration' ) );
        $reviews     = apply_filters( "craf_appna_ecommerce_{$integration}_product_reviews", null, $request );

        if ( ! is_array( $reviews ) || ! isset( $reviews['items'], $reviews['rating_counts'] ) ) {
            throw new Exception( esc_html__( 'Reviews integration not found', 'appnatively' ) );
        }

        return Response::send( [ 'data' => $reviews ] );
    }

    /**
     * Display related products for the specified resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function related( Request $request ): array {
        $request->validate(
            [
                'id'          => 'required|numeric',
                'page'        => 'nullable|integer|min:1',
                'per_page'    => 'nullable|integer|min:1|max:100',
                'fields'      => 'nullable|string',
                'integration' => 'required|string|' . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration       = sanitize_text_field( $request->get_param( 'integration' ) );
        $fields            = craf_appna_get_verified_fields( $request->get_param( 'fields' ), $this->allowed_fields );
        $product_paginator = apply_filters( "craf_appna_ecommerce_{$integration}_related_products", null, $request, $fields );

        if ( ! $product_paginator instanceof ProductPaginatorDTO ) {
            throw new Exception( esc_html__( 'Related products integration not found', 'appnatively' ) );
        }

        return Response::send( [ 'data' => $product_paginator ] );
    }
}
