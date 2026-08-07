<?php

namespace Crafium\AppNatively\App\Http\Controllers;

use Crafium\AppNatively\App\DTO\Forms\FormsDTO;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class FormController extends Controller {
    public function index( Request $request ): array {
        $request->validate(
            [
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $forms       = apply_filters( "craf_appna_form_{$integration}_forms", null, $request );

        if ( ! $forms instanceof FormsDTO ) {
            throw new Exception( esc_html__( "Forms integration not found", 'appnatively' ) );
        }
        return Response::send( ["data" => $forms] );
    }

    public function show( Request $request ): array {
        $request->validate(
            [
                "id"          => "required|numeric",
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $form        = apply_filters( "craf_appna_form_{$integration}_form", null, $request );

        if ( ! $form instanceof FormDTO ) {
            throw new Exception( esc_html__( "Form not found", 'appnatively' ) );
        }

        return Response::send(
            [
                "data" => $form,
            ]
        );
    }

    public function store( Request $request ): array {
        $request->validate(
            [
                "form_id"     => "required|integer",
                "integration" => "required|string",
            ]
        );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );

        do_action( "craf_appna_form_{$integration}_submit", $request );

        $error_response = $this->get_processing_errors( $integration );

        if ( $error_response !== null ) {
            return $error_response;
        }

        return Response::send( ['message' => __( 'Form submitted successfully', 'appnatively' ), 'success' => true] );
    }

    /**
     * Some integrations don't throw on failure (e.g. a plugin's own spam filter
     * silently rejects the entry), so give each integration a chance to report
     * a post-submit error via its own {integration}_submit_errors filter.
     */
    private function get_processing_errors( string $integration ): ?array {
        $error_message = apply_filters( "craf_appna_form_{$integration}_submit_errors", null );

        if ( empty( $error_message ) || ! is_string( $error_message ) ) {
            return null;
        }

        return Response::send(
            [
                'message' => $error_message,
                'success' => false,
            ],
            400
        );
    }
}
