<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class WPForms extends Form {
    public function get_key(): string {
        return 'wpforms-lite';
    }

    public function boot(): void {
        if ( ! function_exists( 'wpforms' ) ) {
            return;
        }
        parent::boot();
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
        $map = [
            'text'          => 'text',
            'number'        => 'number',
            'email'         => 'email',
            'checkbox'      => 'checkbox',
            'select'        => 'select',
            'number-slider' => 'number_slider',
            'gdpr-checkbox' => 'gdpr',
        ];

        return $map[$type] ?? null;
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

    private function get_gdpr_rules( array $field ): array {
        return [ 'integer', 'in:0,1' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_select_rules( array $field ): array {
        return [ 'string', 'max:255' ];
    }

    private function get_number_slider_rules( array $field ): array {
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
                case 'select':
                    $field_rules = $this->get_select_rules( $field );
                    break;
                case 'gdpr':
                    $field_rules = $this->get_gdpr_rules( $field );
                    break;
                case 'number_slider':
                    $field_rules = $this->get_number_slider_rules( $field );
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

            if ( ! empty( $field['required'] ) && $field['required'] === '1' ) {
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

            if ( $mapped_type === 'number' || $mapped_type === 'number_slider' ) {
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

        return in_array( $mapped_type, [ 'number', 'number_slider' ], true );
    }

    private function field_has_max_rule( array $field, string $mapped_type ): bool {
        if ( ! isset( $field['max'] ) || $field['max'] === '' ) {
            return false;
        }

        return in_array( $mapped_type, [ 'number', 'number_slider' ], true );
    }

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( 'form_id' ) );
        if ( ! $form ) {
            throw new \Exception( __( 'Form not found', 'appnatively' ) );
        }

        if ( ! empty( $form['fields'] ) ) {
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

                if ( $mapped_type === 'number_slider' && is_array( $value ) ) {
                    $request->set_param( $field['id'], isset( $value['max'] ) && $value['max'] !== '' ? (int) $value['max'] : 0 );
                }

                if ( $mapped_type === 'gdpr' ) {
                    $request->set_param( $field_name, (int) $value );
                }
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

        wpforms()->obj( 'process' )->process( $entry );

        // Restore the original action to avoid side effects.
        unset( $_POST['action'] );

        remove_filter( 'wpforms_process_anti_spam_direct_post_bypass', '__return_true' );
        remove_filter( 'wpforms_field_choices_allow_unknown_value', '__return_true' );
    }
}
