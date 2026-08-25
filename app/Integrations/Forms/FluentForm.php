<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\Helpers\Helpers;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class FluentForm extends Form
{
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
        if ( ! $form ) {
            return [];
        }

        $form_array = is_array( $form ) ? $form : ( method_exists( $form, 'toArray' ) ? $form->toArray() : (array) $form );

        if ( 'published' !== ( $form_array['status'] ?? '' ) ) {
            return [];
        }

        return $form_array;
    }

    private function map_field_type( string $type ) {
        $map = [
            'text'             => 'input_text',
            'number'           => 'input_number',
            'email'            => 'input_email',
            'url'              => 'input_url',
            'radio'            => 'input_radio',
            'checkbox'         => 'input_checkbox',
            'single_select'    => 'select',
            'rating'           => 'ratings',
            'date_time_picker' => 'input_date',
            'password'         => 'input_password',
            'gdpr_agreement'   => 'gdpr',
        ];

        $key = array_search( $type, $map, true );
        return false !== $key ? $key : null;
    }

    private function get_form_fields_array( array $form ): array {
        $form_fields = $form['form_fields'] ?? '';
        if ( is_array( $form_fields ) ) {
            return $form_fields;
        }
        $decoded = json_decode( $form_fields, true );
        return is_array( $decoded ) ? $decoded : [];
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

    private function get_date_time_picker_rules( array $field ): array {
        return $this->get_base_rules( $field );
    }

    private function get_gdpr_rules( array $field ): array {
        // GDPR consent must always be affirmatively given, regardless of whether the
        // plugin author happened to mark the field "required" in the form builder.
        return [ 'integer', 'in:1', 'required' ];
    }

    protected function get_validation_rules( array $form ): array {
        $form_fields = $this->get_form_fields_array( $form );
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
                case 'date_time_picker':
                    $field_rules = $this->get_date_time_picker_rules( $field );
                    break;
                case 'gdpr':
                    $field_rules = $this->get_gdpr_rules( $field );
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

    protected function get_validation_messages( array $form ): array {
        $form_fields = $this->get_form_fields_array( $form );
        if ( empty( $form_fields['fields'] ) ) {
            return [];
        }

        $flattened_fields = $this->extract_fluentform_fields( $form_fields['fields'] );
        $default_messages = [];
        if ( class_exists( '\FluentForm\App\Helpers\Helper' ) && method_exists( '\FluentForm\App\Helpers\Helper', 'getAllGlobalDefaultMessages' ) ) {
            $default_messages = \FluentForm\App\Helpers\Helper::getAllGlobalDefaultMessages();
        }
        $messages = [];

        $resolve_message = function ( array $rule, string $rule_key, string $fallback ) use ( $default_messages ): string {
            if ( ! empty( $rule['global'] ) ) {
                return $rule['global_message'] ?? ( $default_messages[$rule_key] ?? $fallback );
            }
            return $rule['message'] ?? ( $default_messages[$rule_key] ?? $fallback );
        };

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

            $validation_rules = $field['settings']['validation_rules'] ?? [];

            $is_required = $mapped_type === 'gdpr' || ! empty( $validation_rules['required']['value'] );
            if ( $is_required ) {
                $messages["{$field_name}.required"] = $resolve_message( $validation_rules['required'] ?? [], 'required', 'This field is required' );
            }

            switch ( $mapped_type ) {
                case 'email':
                    if ( ! empty( $validation_rules['email']['value'] ) ) {
                        $messages["{$field_name}.email"] = $resolve_message( $validation_rules['email'], 'email', 'This field must contain a valid email' );
                    }
                    break;
                case 'url':
                    $messages["{$field_name}.url"] = $default_messages['url'] ?? 'This field must contain a valid url';
                    break;
                case 'number':
                    $messages["{$field_name}.numeric"] = $default_messages['numeric'] ?? 'This field must contain numeric value';
                    if ( ! empty( $validation_rules['min']['value'] ) ) {
                        $messages["{$field_name}.min"] = $resolve_message( $validation_rules['min'], 'min', 'Validation fails for minimum value' );
                    }
                    if ( ! empty( $validation_rules['max']['value'] ) ) {
                        $messages["{$field_name}.max"] = $resolve_message( $validation_rules['max'], 'max', 'Validation fails for maximum value' );
                    }
                    break;
                case 'gdpr':
                    $gdpr_msg                          = $default_messages['required'] ?? 'This field is required';
                    $messages["{$field_name}.integer"] = $gdpr_msg;
                    $messages["{$field_name}.in"]      = $gdpr_msg;
                    break;
                case 'rating':
                    $messages["{$field_name}.integer"] = $default_messages['numeric'] ?? 'This field must contain numeric value';
                    if ( ! empty( $validation_rules['max']['value'] ) ) {
                        $messages["{$field_name}.max"] = $resolve_message( $validation_rules['max'], 'max', 'Validation fails for maximum value' );
                    }
                    break;
            }
        }

        return $messages;
    }

    private function extract_fluentform_fields( array $elements ): array {
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

    protected function prepare_request_for_validation( Request $request, array $form ): void {
        $form_fields = $this->get_form_fields_array( $form );
        if ( empty( $form_fields['fields'] ) ) {
            return;
        }

        $flattened_fields = $this->extract_fluentform_fields( $form_fields['fields'] );

        foreach ( $flattened_fields as $field ) {
            $element_type = $field['element'] ?? '';
            if ( $element_type !== 'gdpr_agreement' ) {
                continue;
            }

            $field_name = $field['attributes']['name'] ?? $field['name'] ?? '';
            if ( ! $field_name ) {
                continue;
            }

            $value = $request->get_param( $field_name );
            if ( $value !== null ) {
                $request->set_param( $field_name, (int) $value );
            }
        }
    }

    protected function submit( Request $request, array $form ) {
        $form_fields = $this->get_form_fields_array( $form );
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
                if ( $element_type === 'gdpr_agreement' && $value !== null ) {
                    $value = (int) $value ? 'on' : 'off';
                }
                if ( $value !== null && $value !== '' && $value !== [] ) {
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
        // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- firing Fluent Forms' own hook so its native notifications/feeds run, not defining a hook of our own.
        do_action( 'fluentform/submission_inserted', $submission_id, $form_data, $form_model ?: (object) $form );
    }

    public function get_forms(): array {
        // Query Fluent Forms' custom database table directly
        $forms = wpFluent()->table( 'fluentform_forms' )
            ->select( ['id', 'title', 'status'] )
            ->where( 'status', 'published' ) // Filters active forms
            ->get();

        $result = [];

        foreach ( $forms as $form ) {
            // Map the properties from the custom table object to your DTO
            $result[] = ( new FormDTO() )->set_id( (int) $form->id )
                ->set_title( $form->title )
                ->set_exclude_to_array( ['fields'] );
        }

        return $result;
    }

    protected function get_standardized_type( string $native_type ): ?string {
        $map = [
            'input_text'     => 'text',
            'input_number'   => 'number',
            'input_email'    => 'email',
            'input_url'      => 'url',
            'input_radio'    => 'radio',
            'input_checkbox' => 'checkbox',
            'select'         => 'single_select',
            'ratings'        => 'rating',
            'input_date'     => 'date_time_picker',
            'input_password' => 'password',
            'gdpr'           => 'gdpr',
            'input_hidden'   => 'text',
            'textarea'       => 'text',
            'input_textarea' => 'text',
        ];

        return $map[$native_type] ?? null;
    }

    protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO {
        $dto = new FormDTO();

        if ( in_array( 'id', $fields, true ) ) {
            $dto->set_id( (int) ( $raw_form['id'] ?? 0 ) );
        }

        if ( in_array( 'title', $fields, true ) ) {
            $dto->set_title( $raw_form['title'] ?? '' );
        }

        if ( in_array( 'fields', $fields, true ) ) {
            $form_fields = $this->get_form_fields_array( $raw_form );

            if ( ! empty( $form_fields['fields'] ) ) {
                $flattened  = $this->extract_fluentform_fields( $form_fields['fields'] );
                $field_dtos = [];

                foreach ( $flattened as $field ) {
                    $element_type = $field['element'] ?? '';
                    $std_type     = $this->get_standardized_type( $element_type );

                    if ( ! $std_type ) {
                        continue;
                    }

                    $field_name = $field['attributes']['name'] ?? $field['name'] ?? '';

                    if ( ! $field_name ) {
                        continue;
                    }

                    $fdto = new FormFieldDTO();
                    $fdto->set_id( $field_name )
                        ->set_type( $std_type )
                        ->set_required( ! empty( $field['settings']['validation_rules']['required']['value'] ) || ! empty( $field['required'] ) )
                        ->set_label( $field['settings']['label'] ?? $field['label'] ?? '' )
                        ->set_placeholder( $field['attributes']['placeholder'] ?? '' )
                        ->set_field_name( $field_name );

                    $options = ! empty( $field['settings']['advanced_options'] )
                        ? $field['settings']['advanced_options']
                        : ( $field['options'] ?? [] );

                    if ( ! empty( $options ) ) {
                        $items = [];

                        foreach ( $options as $key => $option ) {
                            if ( is_string( $option ) ) {
                                // Legacy {value => label} map
                                $items[] = [
                                    'id'    => (string) $key,
                                    'label' => $option,
                                    'value' => $option,
                                ];
                            } elseif ( is_array( $option ) ) {
                                $items[] = [
                                    'id'    => isset( $option['id'] ) ? (string) $option['id'] : (string) $key,
                                    'label' => $option['label'] ?? $option['value'] ?? (string) $key,
                                    'value' => $option['value'] ?? $option['label'] ?? (string) $key,
                                ];
                            }
                        }

                        if ( $items ) {
                            $fdto->set_items( $items );
                        }
                    }

                    if ( $std_type === 'number' ) {
                        $fdto->set_min_value( isset( $field['settings']['validation_rules']['min']['value'] ) ? (float) $field['settings']['validation_rules']['min']['value'] : null )
                            ->set_max_value( isset( $field['settings']['validation_rules']['max']['value'] ) ? (float) $field['settings']['validation_rules']['max']['value'] : null );
                    }

                    if ( $std_type === 'rating' ) {
                        $fdto->set_rating_max( 5 );
                    }

                    if ( $std_type === 'date_time_picker' ) {
                        $format = $field['settings']['date_format'] ?? 'd/m/Y';
                        $fdto->set_picker_type( $this->fluentform_date_picker_type( $format ) )
                            ->set_date_format( $this->flatpickr_to_date_fns_format( $format ) );
                    }

                    if ( $std_type === 'text' && ! empty( $field['settings']['validation_rules']['max']['value'] ) ) {
                        $fdto->set_character_limit( (int) $field['settings']['validation_rules']['max']['value'] );
                    }

                    $field_dtos[] = $fdto;
                }

                $dto->set_fields( $field_dtos );
            }
        }

        return $dto;
    }

    private function fluentform_date_picker_type( string $format ): string {
        $time_tokens = ['H', 'h', 'G', 'i', 'S', 's', 'K'];
        $date_tokens = ['d', 'D', 'l', 'j', 'J', 'w', 'W', 'F', 'm', 'n', 'M', 'U', 'Y', 'y', 'Z'];

        $has_time = false;
        foreach ( $time_tokens as $t ) {
            if ( strpos( $format, $t ) !== false ) {
                $has_time = true;
                break;
            }
        }

        $has_date = false;
        foreach ( $date_tokens as $t ) {
            if ( strpos( $format, $t ) !== false ) {
                $has_date = true;
                break;
            }
        }

        if ( $has_time && ! $has_date ) {
            return 'time';
        }
        if ( $has_time && $has_date ) {
            return 'both';
        }
        return 'date';
    }

    private function flatpickr_to_date_fns_format( string $format ): string {
        $map = [
            'Y' => 'yyyy',
            'y' => 'yy',
            'm' => 'MM',
            'n' => 'M',
            'M' => 'MMM',
            'F' => 'MMMM',
            'd' => 'dd',
            'j' => 'd',
            'D' => 'EEE',
            'l' => 'EEEE',
            'J' => 'do',
            'H' => 'HH',
            'G' => 'H',
            'h' => 'hh',
            'g' => 'h',
            'i' => 'mm',
            'S' => 'ss',
            's' => 'ss',
            'K' => 'a',
            'Z' => 'xxx',
        ];

        $out = '';
        for ( $k = 0, $len = strlen( $format ); $k < $len; $k++ ) {
            $out .= $map[$format[$k]] ?? $format[$k];
        }
        return $out;
    }
}