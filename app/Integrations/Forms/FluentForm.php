<?php

namespace AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use AppNatively\WpMVC\Helpers\Helpers;
use AppNatively\WpMVC\RequestValidator\Request;

class FluentForm extends Form {
    public function get_key(): string {
        return 'fluentform';
    }
    
    public function boot(): void {
        if ( ! function_exists( 'fluentFormApi' ) && ! defined( 'FLUENTFORM' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $form = fluentFormApi( 'forms' )->find( $id );
        if ( $form ) {
            return is_array( $form ) ? $form : ( method_exists( $form, 'toArray' ) ? $form->toArray() : (array) $form );
        }
        return [];
    }

    private function map_field_type( string $type ) {
        $map = [
            'text'          => 'input_text',
            'number'        => 'input_number',
            'email'         => 'input_email',
            'url'           => 'input_url',
            'radio'         => 'input_radio',
            'checkbox'      => 'input_checkbox',
            'single_select' => 'select',
            'range'         => 'rangeslider',
            'rating'        => 'ratings',
            'switch'        => '',
            'password'      => 'input_password',
        ];

        $key = array_search( $type, $map, true );
        return false !== $key ? $key : null;
    }

    private function get_base_rules( array $field ): array {
        $field_rules      = [];
        $validation_rules = isset( $field['settings']['validation_rules'] ) ? $field['settings']['validation_rules'] : [];

        foreach ( $validation_rules as $rule_key => $rule_config ) {
            if ( ! empty( $rule_config['value'] ) ) {
                if ( 'required' === $rule_key ) {
                    $field_rules[] = 'required';
                } elseif ( 'email' === $rule_key ) {
                    $field_rules[] = 'email';
                } elseif ( 'numeric' === $rule_key ) {
                    $field_rules[] = 'numeric';
                } elseif ( 'url' === $rule_key ) {
                    $field_rules[] = 'url';
                } elseif ( 'min' === $rule_key ) {
                    $field_rules[] = 'min:' . $rule_config['value'];
                } elseif ( 'max' === $rule_key ) {
                    $field_rules[] = 'max:' . $rule_config['value'];
                }
            }
        }

        if ( ( isset( $field['required'] ) && $field['required'] ) || ( isset( $field['settings']['required'] ) && $field['settings']['required'] ) ) {
            $field_rules[] = 'required';
        }

        return $field_rules;
    }

    private function get_text_rules( array $field ): array {
        return $this->get_base_rules( $field );
    }

    private function get_number_rules( array $field ): array {
        $rules   = $this->get_base_rules( $field );
        $rules[] = 'numeric';
        return $rules;
    }

    private function get_email_rules( array $field ): array {
        $rules   = $this->get_base_rules( $field );
        $rules[] = 'email';
        return $rules;
    }

    private function get_url_rules( array $field ): array {
        $rules   = $this->get_base_rules( $field );
        $rules[] = 'url';
        return $rules;
    }

    private function get_radio_rules( array $field ): array {
        return $this->get_base_rules( $field );
    }

    private function get_checkbox_rules( array $field ): array {
        return $this->get_base_rules( $field );
    }

    private function get_single_select_rules( array $field ): array {
        return $this->get_base_rules( $field );
    }

    private function get_range_rules( array $field ): array {
        return $this->get_base_rules( $field );
    }

    private function get_rating_rules( array $field ): array {
        return $this->get_base_rules( $field );
    }

    private function get_switch_rules( array $field ): array {
        return $this->get_base_rules( $field );
    }

    private function get_password_rules( array $field ): array {
        return $this->get_base_rules( $field );
    }

    protected function get_validation_rules( array $form ) : array {
        if ( empty( $form['form_fields'] ) ) {
            return [];
        }

        $form_fields = json_decode( $form['form_fields'], true );
        if ( empty( $form_fields['fields'] ) ) {
            return [];
        }

        $flattened_fields = $this->extract_fluentform_fields( $form_fields['fields'] );
        $rules            = [];

        foreach ( $flattened_fields as $field ) {
            $field_name = $field['attributes']['name'] ?? $field['name'] ?? '';
            if ( ! $field_name ) {
                continue;
            }

            $element_type = $field['element'] ?? '';
            $mapped_type  = $this->map_field_type( $element_type );
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
                case 'url':
                    $field_rules = $this->get_url_rules( $field );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $field );
                    break;
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $field );
                    break;
                case 'single_select':
                    $field_rules = $this->get_single_select_rules( $field );
                    break;
                case 'range':
                    $field_rules = $this->get_range_rules( $field );
                    break;
                case 'rating':
                    $field_rules = $this->get_rating_rules( $field );
                    break;
                case 'switch':
                    $field_rules = $this->get_switch_rules( $field );
                    break;
                case 'password':
                    $field_rules = $this->get_password_rules( $field );
                    break;
                default:
                    continue 2;
            }

            if ( ! empty( $field_rules ) ) {
                $rules[$field_name] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    private function extract_fluentform_fields( array $elements ) : array {
        $fields = [];
        foreach ( $elements as $element ) {
            if ( ! empty( $element['columns'] ) ) {
                foreach ( $element['columns'] as $column ) {
                    if ( ! empty( $column['fields'] ) ) {
                        $fields = array_merge( $fields, $this->extract_fluentform_fields( $column['fields'] ) );
                    }
                }
            } elseif ( ! empty( $element['fields'] ) ) {
                $fields = array_merge( $fields, $this->extract_fluentform_fields( $element['fields'] ) );
            } elseif ( ! empty( $element['inputs'] ) ) {
                foreach ( $element['inputs'] as $input ) {
                    $fields[] = $input;
                }
            } else {
                $fields[] = $element;
            }
        }
        return $fields;
    }

    protected function submit( Request $request, array $form ) {
        $form_fields = json_decode( $form['form_fields'], true );
        if ( empty( $form_fields['fields'] ) ) {
            return;
        }

        $flattened_fields = $this->extract_fluentform_fields( $form_fields['fields'] );
        $form_data        = [];

        foreach ( $flattened_fields as $field ) {
            $element_type = $field['element'] ?? '';
            if ( ! $this->map_field_type( $element_type ) ) {
                continue;
            }

            $field_name = $field['attributes']['name'] ?? $field['name'] ?? '';
            if ( $field_name ) {
                $value = $request->get_param( $field_name );
                if ( $value !== null ) {
                    $form_data[$field_name] = $value;
                }
            }
        }

        // Resolve Client IP
        $ip = Helpers::get_user_ip_address();

        // Insert using Fluent Forms native models/methods
        $serial_number = 1;
        $previous_item = \FluentForm\App\Models\Submission::select( 'serial_number' )
            ->where( 'form_id', (int) $form['id'] )
            ->orderBy( 'id', 'DESC' )
            ->first();

        if ( $previous_item ) {
            $serial_number = (int) $previous_item->serial_number + 1;
        }

        $submission_id = \FluentForm\App\Models\Submission::insertGetId(
            [
                'form_id'       => (int) $form['id'],
                'serial_number' => $serial_number,
                'response'      => wp_json_encode( $form_data ),
                'status'        => 'unread',
                'ip'            => $ip,
                'user_id'       => is_user_logged_in() ? wp_get_current_user()->ID : null,
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
            ] 
        );

        // Insert into wp_fluentform_entry_details using Fluent Forms native service
        ( new \FluentForm\App\Services\Submission\SubmissionService() )->recordEntryDetails( $submission_id, (int) $form['id'], $form_data );

        // Resolve Form model
        $form_model = \FluentForm\App\Models\Form::find( (int) $form['id'] );

        // Trigger the submission inserted hook so notifications/feeds run
        // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
        do_action( 'fluentform/submission_inserted', $submission_id, $form_data, $form_model ?: (object) $form );
    }
}