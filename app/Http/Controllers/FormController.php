<?php

namespace AppNatively\App\Http\Controllers;

defined( "ABSPATH" ) || exit;

use AppNatively\App\Http\Controllers\Controller;
use AppNatively\WpMVC\Routing\Response;
use AppNatively\WpMVC\RequestValidator\Request;

class FormController extends Controller {
    public function store( Request $request ): array {
        $request->validate(
            [
                "form_id"     => "required|integer",
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );

        do_action( "appnatively_form_{$integration}_submit", $request );

        return Response::send( ['message' => __( 'Form submitted successfully', 'appnatively' ), 'success' => true] );
    }
}