<?php

namespace AppNatively\App\Integrations\Forms;

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\RequestValidator\Request;

class EverestForms extends Form {
    public function get_key(): string {
        return 'everest-forms';
    }

    public function boot(): void {
        if ( ! function_exists( 'evf' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $form_data = evf()->form->get( $id, [ 'content_only' => true ] );

        if ( ! $form_data || empty( $form_data['form_fields'] ) ) {
            return [];
        }

        $fields = [];

        foreach ( $form_data['form_fields'] as $field_id => $field ) {
            $fields[] = [
                'id'                             => $field_id,
                'type'                           => $field['type'] ?? '',
                'name'                           => $field['label'] ?? '',
                'meta_key'                       => $field['meta-key'] ?? '',
                'required'                       => ! empty( $field['required'] ),
                'min_value'                      => $field['min_value'] ?? '',
                'max_value'                      => $field['max_value'] ?? '',
                'number_of_stars'                => $field['number_of_stars'] ?? 5,
                'datetime_format'                => $field['datetime_format'] ?? 'date',
                'required_field_message_setting' => $field['required_field_message_setting'] ?? 'global',
                'required_field_message'         => $field['required-field-message'] ?? '',
            ];
        }

        return [
            'id'     => $id,
            'name'   => $form_data['settings']['form_title'] ?? '',
            'fields' => $fields,
        ];
    }

    private function map_field_type( string $type ) {
        $map = [
            'text'      => 'text',
            'email'     => 'email',
            'url'       => 'url',
            'number'    => 'number',
            'radio'     => 'radio',
            'checkbox'  => 'checkbox',
            'select'    => 'select',
            'date-time' => 'date',
            'rating'    => 'rating',
        ];

        return $map[ $type ] ?? null;
    }

    private function get_text_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_email_rules( array $field ): array {
        return [ 'string', 'email' ];
    }

    private function get_url_rules( array $field ): array {
        return [ 'string', 'url' ];
    }

    private function get_number_rules( array $field ): array {
        $rules = [ 'numeric' ];

        if ( isset( $field['min_value'] ) && $field['min_value'] !== '' ) {
            $rules[] = 'min:' . floatval( $field['min_value'] );
        }

        if ( isset( $field['max_value'] ) && $field['max_value'] !== '' ) {
            $rules[] = 'max:' . floatval( $field['max_value'] );
        }

        return $rules;
    }

    private function get_radio_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_select_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_date_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_rating_rules( array $field ): array {
        $rules = [ 'integer' ];

        if ( ! empty( $field['number_of_stars'] ) ) {
            $rules[] = 'max:' . absint( $field['number_of_stars'] );
        }

        return $rules;
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name  = $field['id'];
            $field_rules = [];

            switch ( $mapped_type ) {
                case 'text':
                    $field_rules = $this->get_text_rules( $field );
                    break;
                case 'number':
                    $field_rules = $this->get_number_rules( $field );
                    break;
                case 'email':
                    $field_rules = $this->get_email_rules( $field );
                    break;
                case 'url':
                    $field_rules = $this->get_url_rules( $field );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $field );
                    break;
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $field );
                    break;
                case 'select':
                    $field_rules = $this->get_select_rules( $field );
                    break;
                case 'date':
                    $field_rules = $this->get_date_rules( $field );
                    break;
                case 'rating':
                    $field_rules = $this->get_rating_rules( $field );
                    break;
                default:
                    continue 2;
            }

            if ( ! empty( $field['required'] ) ) {
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

        $global_required = get_option( 'everest_forms_required_validation', 'This field is required.' );

        $default_format = [
            'email'  => get_option( 'everest_forms_email_validation', 'Please enter a valid email address.' ),
            'url'    => get_option( 'everest_forms_url_validation', 'Please enter a valid URL.' ),
            'number' => get_option( 'everest_forms_number_validation', 'Please enter a valid number.' ),
        ];

        $messages = [];

        foreach ( $form['fields'] as $field ) {
            $name   = $field['id'];
            $type   = $field['type'];
            $mapped = $this->map_field_type( $type );

            if ( ! $mapped || ! $name ) {
                continue;
            }

            if ( ! empty( $field['required'] ) ) {
                if ( isset( $field['required_field_message_setting'] ) && $field['required_field_message_setting'] === 'individual' && ! empty( $field['required_field_message'] ) ) {
                    $messages[ "{$name}.required" ] = $field['required_field_message'];
                } else {
                    $messages[ "{$name}.required" ] = $global_required;
                }
            }

            if ( $mapped === 'email' ) {
                $messages[ "{$name}.email" ] = $default_format['email'];
            }

            if ( $mapped === 'url' ) {
                $messages[ "{$name}.url" ] = $default_format['url'];
            }

            if ( $mapped === 'number' ) {
                $messages[ "{$name}.numeric" ] = $default_format['number'];

                if ( isset( $field['min_value'] ) && $field['min_value'] !== '' ) {
                    $messages[ "{$name}.min" ] = 'Please enter a value greater than or equal to :min.';
                }

                if ( isset( $field['max_value'] ) && $field['max_value'] !== '' ) {
                    $messages[ "{$name}.max" ] = 'Please enter a value less than or equal to :max.';
                }
            }

            if ( $mapped === 'rating' ) {
                $messages[ "{$name}.integer" ] = $default_format['number'];

                if ( ! empty( $field['number_of_stars'] ) ) {
                    $messages[ "{$name}.max" ] = 'Please select a value up to :max.';
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

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['id'];
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

        $entry_fields = [];
        $form_id      = (int) $form['id'];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_id = $field['id'];
            $value    = $request->get_param( $field_id );

            if ( $value === null ) {
                continue;
            }

            $formatted_value = $value;

            if ( $field['type'] === 'rating' && is_numeric( $value ) ) {
                $formatted_value = [
                    'value'            => (int) $value,
                    'type'             => 'rating',
                    'number_of_rating' => (int) ( $field['number_of_stars'] ?? 5 ),
                    'icon'             => 'star',
                ];
            }

            $entry_fields[ $field_id ] = [
                'id'       => $field_id,
                'name'     => $field['name'],
                'meta_key' => $field['meta_key'],
                'type'     => $field['type'],
                'value'    => $formatted_value,
            ];
        }

        if ( empty( $entry_fields ) ) {
            return;
        }

        $form_data = evf()->form->get( $form_id, [ 'content_only' => true ] );

        if ( ! $form_data ) {
            return;
        }

        $entry = [
            'form_id' => $form_id,
            'user_id' => get_current_user_id(),
            'status'  => 'publish',
        ];

        $entry_id = evf()->task->entry_save( $entry_fields, $entry, $form_id, $form_data );

        if ( $entry_id && ! is_wp_error( $entry_id ) ) {
            evf()->task->entry_email( $entry_fields, $entry, $form_data, $entry_id );
        }
    }
}
