<?php

namespace Crafium\AppNatively\App\Http\Controllers\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class WishlistController extends Controller {
    /**
     * The allowed fields for the resource — mirrors ProductController's list
     * view fields, since the wishlist renders the same product card.
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
        "categories"
    ];

    /**
     * Resolve device-local wishlist product IDs into full product records.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function index( Request $request ): array {
        $request->validate(
            [
                "ids"         => "required|array|max:100",
                "integration" => "required|string|" . craf_appna_in_rule( craf_appna_get_ecommerce_integrations() ),
            ]
        );

        $integration       = sanitize_text_field( $request->get_param( "integration" ) );
        $product_paginator = apply_filters( "craf_appna_ecommerce_{$integration}_wishlist", null, $request, $this->allowed_fields );

        if ( ! $product_paginator instanceof ProductPaginatorDTO ) {
            throw new Exception( esc_html__( "Wishlist integration not found", 'appnatively' ) );
        }

        return Response::send( ["data" => $product_paginator] );
    }
}
