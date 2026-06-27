<?php

namespace AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use AppNatively\WpMVC\RequestValidator\Request;

class WeForms extends Form {
    public function get_key(): string {
        return 'weforms';
    }

    public function boot(): void {
        if ( ! function_exists( 'weforms' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $form = weforms()->form->get( $id );

        if ( ! $form || ! $form->id ) {
            return [];
        }

        return [
            'id'       => (int) $form->id,
            'name'     => $form->name,
            'fields'   => $form->get_fields(),
            'settings' => $form->get_settings(),
        ];
    }

    private function map_field_type( string $type ) {
        $map = [
            'text_field'     => 'text',
            'email_address'  => 'email',
            'dropdown_field' => 'select',
            'radio_field'    => 'radio',
            'checkbox_field' => 'checkbox',
            'website_url'    => 'url',
            'date_field'     => 'date_time_picker',
        ];

        return $map[$type] ?? null;
    }

    private function get_text_rules( array $field ): array {
        $rules = [ 'string' ];
        if ( ! empty( $field['word_restriction'] ) ) {
            $rules[] = 'max:' . absint( $field['word_restriction'] );
        }
        return $rules;
    }

    private function get_email_rules( array $field ): array {
        return [ 'string', 'email' ];
    }

    private function get_select_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_radio_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_url_rules( array $field ): array {
        return [ 'string', 'url' ];
    }

    private function get_date_time_picker_rules( array $field ): array {
        return [ 'string' ];
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['template'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['template'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name  = $field['name'];
            $field_rules = [];

            switch ( $mapped_type ) {
                case 'text':
                    $field_rules = $this->get_text_rules( $field );
                    break;
                case 'email':
                    $field_rules = $this->get_email_rules( $field );
                    break;
                case 'select':
                    $field_rules = $this->get_select_rules( $field );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $field );
                    break;
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $field );
                    break;
                case 'url':
                    $field_rules = $this->get_url_rules( $field );
                    break;
                case 'date_time_picker':
                    $field_rules = $this->get_date_time_picker_rules( $field );
                    break;
                default:
                    continue 2;
            }

            if ( isset( $field['required'] ) && $field['required'] === 'yes' ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[ $field_name ] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    protected function get_validation_messages( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $default_required = [
            'text'             => 'This field cannot be blank.',
            'email'            => 'This field cannot be blank.',
            'select'           => 'Please select a value.',
            'radio'            => 'Please select a value.',
            'checkbox'         => 'Please select a value.',
            'url'              => 'This field cannot be blank.',
            'date_time_picker' => 'This field cannot be blank.',
        ];

        $messages = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['template'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['template'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['name'];

            if ( isset( $field['required'] ) && $field['required'] === 'yes' ) {
                $messages[ "{$field_name}.required" ] = $default_required[ $mapped_type ] ?? 'This field is required.';
            }

            if ( $mapped_type === 'email' ) {
                $messages[ "{$field_name}.email" ] = 'This is not a valid email.';
            }

            if ( $mapped_type === 'url' ) {
                $messages[ "{$field_name}.url" ] = 'Please enter a valid URL.';
            }

            if ( $mapped_type === 'text' && ! empty( $field['word_restriction'] ) ) {
                $messages[ "{$field_name}.max" ] = 'This field cannot exceed :max characters.';
            }
        }

        return $messages;
    }

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( 'form_id' ) );

        if ( ! $form ) {
            throw new \Exception( __( 'Form not found', 'appnatively' ) );
        }

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['template'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['template'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['name'];
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
        if ( empty( $form['fields'] ) || empty( $form['id'] ) ) {
            return;
        }

        $form_id = (int) $form['id'];
        $fields  = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['template'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['template'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['name'];
            $value      = $request->get_param( $field_name );

            if ( $value !== null ) {
                if ( $mapped_type === 'checkbox' && is_array( $value ) ) {
                    $labels = [];
                    foreach ( $value as $key => $val ) {
                        $labels[] = $field['options'][ $key ] ?? $key;
                    }
                    $fields[ $field_name ] = implode( \WeForms::$field_separator, $labels );
                } else {
                    $fields[ $field_name ] = $value;
                }
            }
        }

        if ( ! empty( $fields ) ) {
            $entry_id = weforms_insert_entry(
                [
                    'form_id' => $form_id,
                ],
                $fields
            );

            if ( $entry_id && ! is_wp_error( $entry_id ) ) {
                do_action( 'weforms_entry_submission', $entry_id, $form_id, 0, $form['settings'] ?? [] );

                $notification = new \WeForms_Notification(
                    [
                        'form_id'  => $form_id,
                        'page_id'  => 0,
                        'entry_id' => $entry_id,
                    ]
                );
                $notification->send_notifications();
            }
        }
    }
}
