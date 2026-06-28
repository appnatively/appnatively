<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class HappyForms extends Form {
    public function get_key(): string {
        return 'happyforms';
    }

    public function boot(): void {
        if ( ! defined( 'HAPPYFORMS_VERSION' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $form = happyforms_get_form_controller()->get( $id );

        if ( ! $form || empty( $form['parts'] ) ) {
            return [];
        }

        return $form;
    }

    private function map_field_type( string $type ) {
        $map = [
            'single_line_text' => 'text',
            'email'            => 'email',
            'radio'            => 'radio',
            'checkbox'         => 'checkbox',
            'select'           => 'select',
            'number'           => 'number',
        ];

        return $map[ $type ] ?? null;
    }

    private function get_text_rules( array $part ): array {
        return [ 'string' ];
    }

    private function get_email_rules( array $part ): array {
        return [ 'string', 'email' ];
    }

    private function get_radio_rules( array $part ): array {
        return [ 'string' ];
    }

    private function get_checkbox_rules( array $part ): array {
        return [ 'array' ];
    }

    private function get_select_rules( array $part ): array {
        return [ 'string' ];
    }

    private function get_number_rules( array $part ): array {
        $rules = [ 'numeric' ];

        if ( isset( $part['min_value'] ) && $part['min_value'] !== '' ) {
            $rules[] = 'min:' . floatval( $part['min_value'] );
        }

        if ( isset( $part['max_value'] ) && $part['max_value'] !== '' ) {
            $rules[] = 'max:' . floatval( $part['max_value'] );
        }

        return $rules;
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['parts'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['parts'] as $part ) {
            if ( empty( $part['type'] ) || empty( $part['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $part['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name  = $part['id'];
            $field_rules = [];

            switch ( $mapped_type ) {
                case 'text':
                    $field_rules = $this->get_text_rules( $part );
                    break;
                case 'email':
                    $field_rules = $this->get_email_rules( $part );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $part );
                    break;
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $part );
                    break;
                case 'select':
                    $field_rules = $this->get_select_rules( $part );
                    break;
                case 'number':
                    $field_rules = $this->get_number_rules( $part );
                    break;
                default:
                    continue 2;
            }

            if ( ! empty( $part['required'] ) ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[ $field_name ] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    protected function get_validation_messages( array $form ): array {
        if ( empty( $form['parts'] ) ) {
            return [];
        }

        $messages = [];

        foreach ( $form['parts'] as $part ) {
            $name   = $part['id'];
            $type   = $part['type'];
            $mapped = $this->map_field_type( $type );

            if ( ! $mapped || ! $name ) {
                continue;
            }

            if ( ! empty( $part['required'] ) ) {
                $messages[ "{$name}.required" ] = happyforms_get_validation_message( 'field_empty' );
            }

            if ( $mapped === 'email' ) {
                $messages[ "{$name}.email" ] = happyforms_get_validation_message( 'field_invalid' );
            }

            if ( $mapped === 'number' ) {
                $messages[ "{$name}.numeric" ] = happyforms_get_validation_message( 'field_invalid' );

                if ( isset( $part['min_value'] ) && $part['min_value'] !== '' ) {
                    $messages[ "{$name}.min" ] = happyforms_get_validation_message( 'number_min_invalid' );
                }

                if ( isset( $part['max_value'] ) && $part['max_value'] !== '' ) {
                    $messages[ "{$name}.max" ] = happyforms_get_validation_message( 'number_max_invalid' );
                }
            }
        }

        return $messages;
    }

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( 'form_id' ) );

        if ( ! $form ) {
            throw new \Exception( __( 'Form not found', 'appnatively' ) );
        }

        foreach ( $form['parts'] as $part ) {
            if ( empty( $part['type'] ) || empty( $part['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $part['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $part['id'];
            $value      = $request->get_param( $field_name );

            if ( $value === null ) {
                continue;
            }

            if ( $mapped_type === 'checkbox' && is_array( $value ) ) {
                $request->set_param( $field_name, ! empty( $value ) ? array_combine( $value, $value ) : [] );
            }
        }

        $validation = $request->make(
            $request,
            $this->get_validation_rules( $form ),
            $this->get_validation_messages( $form )
        );
        $validation->throw_if_fails();
        $request->errors = $validation->errors();

        $this->submit( $request, $form );
    }

    protected function submit( Request $request, array $form ) {
        if ( empty( $form['parts'] ) ) {
            return;
        }

        $submission = [];

        foreach ( $form['parts'] as $part ) {
            if ( empty( $part['type'] ) || empty( $part['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $part['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $part_id = $part['id'];
            $value   = $request->get_param( $part_id );

            if ( $value === null ) {
                continue;
            }

            $submission[ $part_id ] = $value;
        }

        if ( empty( $submission ) ) {
            return;
        }

        do_action( 'happyforms_submission_success', $submission, $form, [] );

        $message_controller = happyforms_get_message_controller();

        if ( 1 === intval( $form['receive_email_alerts'] ) ) {
            $this->call_private_method( $message_controller, 'email_owner_confirmation', [ $form, $submission ] );
        }

        if ( 1 === intval( $form['send_confirmation_email'] ) ) {
            $this->call_private_method( $message_controller, 'email_user_confirmation', [ $form, $submission ] );
        }
    }

    private function call_private_method( $object, string $method, array $args = [] ) {
        $reflection = new \ReflectionMethod( $object, $method );
        $reflection->setAccessible( true );
        return $reflection->invokeArgs( $object, $args );
    }
}
