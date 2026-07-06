<?php

namespace Crafium\AppNatively\App\Http\Controllers;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class ShopController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $data = apply_filters(
            "craf_appna_shop_data", [
                "currency" => "USD"
            ] 
        );

        return Response::send( $data );
    }
}
