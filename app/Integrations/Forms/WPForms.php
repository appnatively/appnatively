<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class WPForms extends Form {
    public function get_key(): string {
        return 'wpforms';
    }

    public function boot(): void {
        if ( ! function_exists( 'wpforms' ) ) {
            return;
        }
        parent::boot();
        add_filter( "craf_appna_form_{$this->get_key()}_submit_errors", [ $this, 'get_submit_errors' ] );
    }

    /**
     * WPForms' own spam/anti-spam checks reject an entry silently (no exception),
     * so surface that failure through the generic per-integration submit-errors hook.
     */
    public function get_submit_errors() {
        $process = wpforms()->obj( 'process' );

        if ( ! empty( $process->errors ) ) {
            $messages = $this->collect_error_messages( $process->errors );

            if ( ! empty( $messages ) ) {
                return implode( ' ', $messages );
            }
        }

        if ( ! empty( $process->spam_errors ) && empty( $process->entry_id ) ) {
            return __( 'Form submission was flagged as spam. Please try again.', 'appnatively' );
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

    protected function get_form( int $id ) {
        $form_post = wpforms()->obj( 'form' )->get( $id );

        if ( ! $form_post || $form_post->post_type !== 'wpforms' || $form_post->post_status !== 'publish' ) {
            return [];
        }

        $form_data = wpforms_decode( $form_post->post_content );

        if ( ! is_array( $form_data ) ) {
            return [];
        }

        $form_data['id'] = $form_post->ID;

        return $form_data;
    }

    private function map_field_type( string $type ) {
        return $this->get_standardized_type( $type );
    }

    private function get_text_rules( array $field ): array {
        $rules = [ 'string' ];

        if ( ! empty( $field['limit_enabled'] ) && ! empty( $field['limit_count'] ) ) {
            $rules[] = 'max:' . absint( $field['limit_count'] );
        }

        return $rules;
    }

    private function get_number_rules( array $field ): array {
        $rules = [ 'numeric' ];

        if ( isset( $field['min'] ) && $field['min'] !== '' ) {
            $rules[] = 'min:' . floatval( $field['min'] );
        }

        if ( isset( $field['max'] ) && $field['max'] !== '' ) {
            $rules[] = 'max:' . floatval( $field['max'] );
        }

        return $rules;
    }

    private function get_email_rules( array $field ): array {
        return [ 'string', 'email' ];
    }

    private function get_url_rules( array $field ): array {
        return [ 'string', 'url' ];
    }

    private function get_password_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_date_time_picker_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_rating_rules( array $field ): array {
        $rules = [ 'integer' ];

        if ( ! empty( $field['rating_max'] ) ) {
            $rules[] = 'max:' . absint( $field['rating_max'] );
        }

        return $rules;
    }

    private function get_gdpr_rules( array $field ): array {
        // GDPR consent must always be affirmatively given, regardless of whether the
        // plugin author happened to mark the field "required" in the form builder.
        return [ 'integer', 'in:1', 'required' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_radio_rules( array $field ): array {
        return [ 'string', 'max:255' ];
    }

    private function get_single_select_rules( array $field ): array {
        return [ 'string', 'max:255' ];
    }

    private function get_range_rules( array $field ): array {
        $rules       = [];
        $slider_type = isset( $field['slider_type'] ) ? $field['slider_type'] : 'number';
        if ( 'number' === $slider_type ) {
            $rules[] = 'numeric';
            if ( isset( $field['min'] ) && isset( $field['max'] )
                 && is_numeric( $field['min'] ) && is_numeric( $field['max'] ) ) {
                $rules[] = 'min:' . floatval( $field['min'] );
                $rules[] = 'max:' . floatval( $field['max'] );
            }
        } else {
            $rules[] = 'string';
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
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $field );
                    break;
                case 'single_select':
                    $field_rules = $this->get_single_select_rules( $field );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $field );
                    break;
                case 'url':
                    $field_rules = $this->get_url_rules( $field );
                    break;
                case 'password':
                    $field_rules = $this->get_password_rules( $field );
                    break;
                case 'date_time_picker':
                    $field_rules = $this->get_date_time_picker_rules( $field );
                    break;
                case 'rating':
                    $field_rules = $this->get_rating_rules( $field );
                    break;
                case 'gdpr':
                    $field_rules = $this->get_gdpr_rules( $field );
                    break;
                case 'range':
                    $field_rules = $this->get_range_rules( $field );
                    break;
                default:
                    continue 2;
            }

            if ( ! empty( $field['required'] ) && $field['required'] === '1' ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[ $field['id'] ] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    protected function get_validation_messages( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $messages = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_id = (string) $field['id'];

            $is_required = $mapped_type === 'gdpr' || ( ! empty( $field['required'] ) && $field['required'] === '1' );

            if ( $is_required ) {
                $messages["{$field_id}.required"] = wpforms_setting(
                    'validation-required',
                    __( 'This field is required.', 'wpforms-lite' )
                );
            }

            if ( $mapped_type === 'email' ) {
                $messages["{$field_id}.email"] = wpforms_setting(
                    'validation-email',
                    __( 'Please enter a valid email address.', 'wpforms-lite' )
                );
            }

            if ( $mapped_type === 'number' || $mapped_type === 'range' ) {
                $messages["{$field_id}.numeric"] = wpforms_setting(
                    'validation-number',
                    __( 'Please enter a valid number.', 'wpforms-lite' )
                );
            }

            if ( $mapped_type === 'gdpr' ) {
                $gdpr_msg                        = wpforms_setting(
                    'validation-required',
                    __( 'This field is required.', 'wpforms-lite' )
                );
                $messages["{$field_id}.integer"] = $gdpr_msg;
                $messages["{$field_id}.in"]      = $gdpr_msg;
            }

            if ( $mapped_type === 'text' && ! empty( $field['limit_enabled'] ) && ! empty( $field['limit_count'] ) ) {
                $msg = wpforms_setting(
                    'validation-character-limit',
                    ''
                );
                if ( ! empty( $msg ) ) {
                    $msg                         = str_replace( [ '{limit}', '{remaining}' ], [ ':max', '' ], $msg );
                    $msg                         = trim( preg_replace( '/\s+/', ' ', $msg ), " \t\n\r\0\x0B," );
                    $messages["{$field_id}.max"] = $msg;
                }
            } else {
                if ( $this->field_has_min_rule( $field, $mapped_type ) ) {
                    $msg                         = wpforms_setting(
                        'validation-min',
                        __( 'Please enter a value greater than or equal to {value}.', 'wpforms-lite' )
                    );
                    $messages["{$field_id}.min"] = str_replace( '{value}', ':min', $msg );
                }

                if ( $this->field_has_max_rule( $field, $mapped_type ) ) {
                    $msg                         = wpforms_setting(
                        'validation-max',
                        __( 'Please enter a value less than or equal to {value}.', 'wpforms-lite' )
                    );
                    $messages["{$field_id}.max"] = str_replace( '{value}', ':max', $msg );
                }
            }
        }

        return $messages;
    }

    private function field_has_min_rule( array $field, string $mapped_type ): bool {
        if ( ! isset( $field['min'] ) || $field['min'] === '' ) {
            return false;
        }

        return in_array( $mapped_type, [ 'number', 'range' ], true );
    }

    private function field_has_max_rule( array $field, string $mapped_type ): bool {
        if ( ! isset( $field['max'] ) || $field['max'] === '' ) {
            return false;
        }

        return in_array( $mapped_type, [ 'number', 'range' ], true );
    }

    protected function prepare_request_for_validation( Request $request, array $form ): void {
        if ( empty( $form['fields'] ) ) {
            return;
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

            if ( $mapped_type === 'range' && is_array( $value ) ) {
                $request->set_param( $field['id'], isset( $value['max'] ) && $value['max'] !== '' ? (int) $value['max'] : 0 );
            }

            if ( $mapped_type === 'gdpr' ) {
                $request->set_param( $field_name, (int) $value );
            }
        }
    }

    protected function submit( Request $request, array $form ) {
        $entry = [
            'id'     => (int) $form['id'],
            'fields' => [],
        ];

        if ( is_user_logged_in() ) {
            $entry['nonce'] = wp_create_nonce( "wpforms::form_{$form['id']}" );
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
            if ( $value !== null ) {
                if ( $mapped_type === 'gdpr' ) {
                    if ( (int) $value ) {
                        $entry['fields'][ $field['id'] ] = $field['choices'][1]['label'] ?? '1';
                    }
                } else {
                    $entry['fields'][ $field['id'] ] = $value;
                }
            }
        }

        // WPForms checks $_POST['action'] === 'wpforms_submit' when AJAX submission is enabled.
        // REST API requests don't set this, so we set it manually to bypass the check.
        $_POST['action'] = 'wpforms_submit';

        // Bypass the direct POST request check that blocks non-AJAX POST requests
        // when AJAX submission + anti-spam v3 are enabled.
        add_filter( 'wpforms_process_anti_spam_direct_post_bypass', '__return_true' );

        add_filter( 'wpforms_field_choices_allow_unknown_value', '__return_true' );

        try {
            wpforms()->obj( 'process' )->process( $entry );
        } finally {
            // Restore the original action to avoid side effects.
            unset( $_POST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

            remove_filter( 'wpforms_process_anti_spam_direct_post_bypass', '__return_true' );
            remove_filter( 'wpforms_field_choices_allow_unknown_value', '__return_true' );
        }
    }

    public function get_forms(): array {

        $forms = wpforms()->obj( 'form' )->get(
            '', [ 
                'post_type'   => 'wpforms',
                'post_status' => 'publish', // Fetches active forms
                'nopaging'    => true       // Ensures you fetch all forms, not just the first 10
            ] 
        );

        if ( ! $forms ) {
            return [];
        }

        $result = [];

        foreach ( $forms as $form_post ) {

            $result[] = ( new FormDTO() )
                ->set_id( (int) $form_post->ID )
                ->set_title( $form_post->post_title );
        }

        return $result;
    }

    protected function get_standardized_type( string $native_type ): ?string {
        $map = [
            'text'          => 'text',
            'number'        => 'number',
            'email'         => 'email',
            'checkbox'      => 'checkbox',
            'select'        => 'single_select',
            'radio'         => 'radio',
            'number-slider' => 'range',
            'gdpr-checkbox' => 'gdpr',
            'url'           => 'url',
            'password'      => 'password',
            'date-time'     => 'date_time_picker',
            'rating'        => 'rating',
            'file-upload'   => 'text',
            'hidden'        => 'text',
        ];

        return $map[$native_type] ?? null;
    }

    protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO {
        $dto = new FormDTO();

        if ( in_array( 'id', $fields, true ) ) {
            $dto->set_id( (int) $raw_form['id'] );
        }

        if ( in_array( 'title', $fields, true ) ) {
            $dto->set_title( $raw_form['title'] ?? '' );
        }

        if ( in_array( 'fields', $fields, true ) && ! empty( $raw_form['fields'] ) ) {
            $field_dtos = [];

            foreach ( $raw_form['fields'] as $field ) {
                $std_type = $this->get_standardized_type( $field['type'] ?? '' );

                if ( ! $std_type ) {
                    continue;
                }

                $fdto = new FormFieldDTO();
                $fdto->set_id( (string) $field['id'] )
                    ->set_type( $std_type )
                    ->set_required( ! empty( $field['required'] ) && $field['required'] === '1' )
                    ->set_label( $field['label'] ?? '' )
                    ->set_placeholder( $field['placeholder'] ?? '' )
                    ->set_field_name( (string) $field['id'] );

                if ( ! empty( $field['choices'] ) ) {
                    $items = [];

                    foreach ( $field['choices'] as $key => $choice ) {
                        $items[] = [
                            'id'    => (string) $key,
                            'label' => $choice['label'] ?? '',
                            'value' => $choice['value'] ?? $choice['label'] ?? '',
                        ];
                    }

                    $fdto->set_items( $items );
                }

                if ( $std_type === 'range' ) {
                    $fdto->set_min_value( isset( $field['min'] ) ? (float) $field['min'] : null )
                        ->set_max_value( isset( $field['max'] ) ? (float) $field['max'] : null );
                }

                if ( $std_type === 'rating' ) {
                    $fdto->set_rating_max( isset( $field['rating_max'] ) ? (int) $field['rating_max'] : 5 );
                }

                if ( $std_type === 'date_time_picker' ) {
                    $fdto->set_picker_type( 'date' )
                        ->set_date_format( 'yyyy-MM-dd' );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
