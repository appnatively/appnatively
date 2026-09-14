<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\App\Support\Auth;
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
                    __( 'This field is required.', 'appnatively' )
                );
            }

            if ( $mapped_type === 'email' ) {
                $messages["{$field_id}.email"] = wpforms_setting(
                    'validation-email',
                    __( 'Please enter a valid email address.', 'appnatively' )
                );
            }

            if ( $mapped_type === 'number' || $mapped_type === 'range' ) {
                $messages["{$field_id}.numeric"] = wpforms_setting(
                    'validation-number',
                    __( 'Please enter a valid number.', 'appnatively' )
                );
            }

            if ( $mapped_type === 'gdpr' ) {
                $gdpr_msg                        = wpforms_setting(
                    'validation-required',
                    __( 'This field is required.', 'appnatively' )
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
                        __( 'Please enter a value greater than or equal to {value}.', 'appnatively' )
                    );
                    $messages["{$field_id}.min"] = str_replace( '{value}', ':min', $msg );
                }

                if ( $this->field_has_max_rule( $field, $mapped_type ) ) {
                    $msg                         = wpforms_setting(
                        'validation-max',
                        __( 'Please enter a value less than or equal to {value}.', 'appnatively' )
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

        // WPForms' nonce guards against a browser being made to submit using
        // its owner's cookies. A Bearer-token request cannot be produced that
        // way, so supplying the nonce restores parity with a real submission.
        // A cookie-authenticated request is the case the nonce exists for and
        // is left to present a real one.
        if ( is_user_logged_in() && Auth::is_token_authenticated() ) {
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
                        $consent = isset( $field['choices'][1] )
                            ? $this->expected_choice_token( $field, (array) $field['choices'][1], 1 )
                            : '1';

                        $entry['fields'][ $field['id'] ] = $consent;
                    }
                } else {
                    // Translate a label back to the token WPForms declared, so
                    // its choice-allowlist check passes for a real choice and
                    // still rejects anything off-list.
                    $entry['fields'][ $field['id'] ] = $this->resolve_choice_submission( $field, $value );
                }
            }
        }

        // WPForms identifies its own submissions by $_POST['action'] when AJAX
        // submission is enabled. A REST request has no such field, so it is set
        // for the duration of the call and restored afterwards.
        // Stashed only to be written back verbatim in the finally block below.
        //phpcs:ignore WordPress.Security.NonceVerification.Missing
        $had_action = array_key_exists( 'action', $_POST );
        //phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $original_action = $had_action ? $_POST['action'] : null;
        $_POST['action'] = 'wpforms_submit';

        // wpforms_process_anti_spam_direct_post_bypass is WPForms' own public
        // filter for integrations that submit outside the browser: its v3
        // anti-spam token is minted by JavaScript when the form is rendered,
        // which a native app never does. WPForms' remaining spam checks still
        // run and are surfaced to the caller via get_submit_errors(), and this
        // endpoint is throttled per client address before it is ever reached.
        add_filter( 'wpforms_process_anti_spam_direct_post_bypass', '__return_true' );

        try {
            wpforms()->obj( 'process' )->process( $entry );
        } finally {
            if ( $had_action ) {
                $_POST['action'] = $original_action;
            } else {
                unset( $_POST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }

            remove_filter( 'wpforms_process_anti_spam_direct_post_bypass', '__return_true' );
        }
    }

    /**
     * The token WPForms expects a given choice to be submitted as.
     *
     * Mirrors WPForms' own build_choices_allowlist(): a field with
     * `show_values` enabled is submitted by value, every other choice field is
     * submitted by label, and a choice carrying neither falls back to
     * "Choice {key}". Sending anything else is what made WPForms' allowlist
     * look like a check to switch off rather than a payload to match.
     *
     * @param array      $field  The field configuration.
     * @param array      $choice The choice configuration.
     * @param int|string $key    The choice key as stored in form_data.
     * @return string
     */
    private function expected_choice_token( array $field, array $choice, $key ): string {
        if ( ! empty( $field['show_values'] ) && isset( $choice['value'] ) && '' !== $choice['value'] ) {
            return (string) $choice['value'];
        }

        if ( isset( $choice['label'] ) && '' !== $choice['label'] ) {
            return (string) $choice['label'];
        }

        // Must match the string WPForms builds, translation included, or the
        // allowlist comparison fails on a non-English site.
        /* translators: %s - choice number. */
        return sprintf( __( 'Choice %s', 'appnatively' ), $key ); //phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
    }

    /**
     * Translate a submitted choice into the token WPForms declared for it.
     *
     * Anything matching no configured choice is returned untouched, so WPForms
     * still sees it and still refuses it.
     *
     * @param array $field The field configuration.
     * @param mixed $value The submitted value, or array of values.
     * @return mixed
     */
    private function resolve_choice_submission( array $field, $value ) {
        if ( empty( $field['choices'] ) || ! is_array( $field['choices'] ) ) {
            return $value;
        }

        if ( is_array( $value ) ) {
            return array_map(
                function ( $single ) use ( $field ) {
                    return $this->resolve_single_choice( $field, $single );
                },
                $value
            );
        }

        return $this->resolve_single_choice( $field, $value );
    }

    /**
     * Resolve one submitted item against a field's configured choices.
     *
     * @param array $field  The field configuration.
     * @param mixed $single The submitted item.
     * @return mixed
     */
    private function resolve_single_choice( array $field, $single ) {
        if ( ! is_string( $single ) && ! is_numeric( $single ) ) {
            return $single;
        }

        $single   = (string) $single;
        $expected = [];

        foreach ( $field['choices'] as $key => $choice ) {
            $expected[ $key ] = $this->expected_choice_token( $field, (array) $choice, $key );
        }

        // Already the declared token — nothing to translate.
        if ( in_array( $single, $expected, true ) ) {
            return $single;
        }

        // Label or value next, then the choice key. Ordered so a label can
        // never be shadowed by another choice whose key happens to match it.
        foreach ( [ 'label', 'value' ] as $property ) {
            foreach ( $field['choices'] as $key => $choice ) {
                $choice = (array) $choice;

                if ( isset( $choice[ $property ] ) && '' !== $choice[ $property ] && (string) $choice[ $property ] === $single ) {
                    return $expected[ $key ];
                }
            }
        }

        foreach ( $field['choices'] as $key => $choice ) {
            if ( (string) $key === $single ) {
                return $expected[ $key ];
            }
        }

        return $single;
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
                            'id'          => (string) $key,
                            'optionLabel' => $choice['label'] ?? '',
                            // Hand the app the token WPForms will actually
                            // accept back. Reading $choice['value'] blindly
                            // yields an empty string on any field that doesn't
                            // use explicit values, which is most of them.
                            'optionValue' => $this->expected_choice_token( $field, (array) $choice, $key ),
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
                        ->set_date_format( 'YYYY-MM-DD' );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
