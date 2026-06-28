<?php

namespace Crafium\AppNatively\App\Http\Controllers;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class FormController extends Controller {
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

    private function get_processing_errors( string $integration ): ?array {
        if ( $integration !== 'wpforms' || ! function_exists( 'wpforms' ) ) {
            return null;
        }

        $process = wpforms()->obj( 'process' );

        if ( ! empty( $process->errors ) ) {
            $messages = $this->collect_error_messages( $process->errors );

            if ( ! empty( $messages ) ) {
                return Response::send(
                    [
                        'message' => implode( ' ', $messages ),
                        'success' => false,
                    ],
                    400
                );
            }
        }

        if ( ! empty( $process->spam_errors ) && empty( $process->entry_id ) ) {
            return Response::send(
                [
                    'message' => __( 'Form submission was flagged as spam. Please try again.', 'appnatively' ),
                    'success' => false,
                ],
                400
            );
        }

        return null;
    }

    private function collect_error_messages( array $errors ): array {
        $messages = [];

        foreach ( $errors as $form_id => $error_data ) {
            if ( ! empty( $error_data['header'] ) ) {
                $messages[] = $error_data['header'];
            }
            if ( ! empty( $error_data['footer'] ) ) {
                $messages[] = $error_data['footer'];
            }
            if ( ! empty( $error_data['recaptcha'] ) ) {
                $messages[] = $error_data['recaptcha'];
            }
            if ( ! empty( $error_data['footer_styled'] ) ) {
                $messages[] = $error_data['footer_styled'];
            }

            foreach ( $error_data as $field_id => $field_message ) {
                if ( is_string( $field_message ) && ! in_array( $field_id, [ 'header', 'footer', 'recaptcha', 'footer_styled' ], true ) ) {
                    $messages[] = $field_message;
                }
            }
        }

        return $messages;
    }
}