<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Helpers\Helpers;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class ContactForm7 extends Form {
    private $cf7_form;

    public function get_key(): string {
        return 'contact-form-7';
    }

    public function boot(): void {
        if ( ! defined( 'WPCF7_VERSION' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        if ( 'publish' !== get_post_status( $id ) ) {
            return [];
        }

        $form = wpcf7_contact_form( $id );
        if ( $form ) {
            $this->cf7_form = $form;
            return [
                'id'    => $form->id(),
                'title' => $form->title(),
            ];
        }
        return [];
    }

    private function map_field_type( string $type ) {
        return $this->get_standardized_type( $type );
    }

    private function get_text_rules( \WPCF7_FormTag $tag ): array {
        $rules     = [ 'string' ];
        $maxlength = $tag->get_maxlength_option();
        if ( $maxlength ) {
            $rules[] = 'max:' . absint( $maxlength );
        }
        $minlength = $tag->get_minlength_option();
        if ( $minlength ) {
            $rules[] = 'min:' . absint( $minlength );
        }
        return $rules;
    }

    private function get_email_rules( \WPCF7_FormTag $tag ): array {
        $rules     = [ 'string', 'email' ];
        $maxlength = $tag->get_maxlength_option();
        if ( $maxlength ) {
            $rules[] = 'max:' . absint( $maxlength );
        }
        $minlength = $tag->get_minlength_option();
        if ( $minlength ) {
            $rules[] = 'min:' . absint( $minlength );
        }
        return $rules;
    }

    private function get_url_rules( \WPCF7_FormTag $tag ): array {
        $rules     = [ 'string', 'url' ];
        $maxlength = $tag->get_maxlength_option();
        if ( $maxlength ) {
            $rules[] = 'max:' . absint( $maxlength );
        }
        $minlength = $tag->get_minlength_option();
        if ( $minlength ) {
            $rules[] = 'min:' . absint( $minlength );
        }
        return $rules;
    }

    private function get_number_rules( \WPCF7_FormTag $tag ): array {
        $rules = [ 'numeric' ];
        $min   = $tag->get_option( 'min', 'signed_num', true );
        if ( false !== $min ) {
            $rules[] = 'min:' . $min;
        }
        $max = $tag->get_option( 'max', 'signed_num', true );
        if ( false !== $max ) {
            $rules[] = 'max:' . $max;
        }
        return $rules;
    }

    private function get_single_select_rules( \WPCF7_FormTag $tag ): array {
        return [ 'string', 'max:255' ];
    }

    private function get_date_time_picker_rules( \WPCF7_FormTag $tag ): array {
        return [ 'string' ];
    }

    private function get_checkbox_rules( \WPCF7_FormTag $tag ): array {
        return [ 'array' ];
    }

    private function get_radio_rules( \WPCF7_FormTag $tag ): array {
        return [ 'string', 'max:255' ];
    }

    private function get_gdpr_rules( \WPCF7_FormTag $tag ): array {
        return [ 'string', 'in:1' ];
    }

    private function get_password_rules( \WPCF7_FormTag $tag ): array {
        return [ 'string' ];
    }

    private function get_range_rules( \WPCF7_FormTag $tag ): array {
        return $this->get_number_rules( $tag );
    }

    protected function get_validation_rules( array $form ): array {
        if ( ! $this->cf7_form ) {
            return [];
        }

        $tags  = $this->cf7_form->scan_form_tags();
        $rules = [];

        foreach ( $tags as $tag ) {
            if ( empty( $tag->name ) || empty( $tag->basetype ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $tag->basetype );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_rules = [];

            switch ( $mapped_type ) {
                case 'text':
                    $field_rules = $this->get_text_rules( $tag );
                    break;
                case 'email':
                    $field_rules = $this->get_email_rules( $tag );
                    break;
                case 'url':
                    $field_rules = $this->get_url_rules( $tag );
                    break;
                case 'number':
                    $field_rules = $this->get_number_rules( $tag );
                    break;
                case 'single_select':
                    $field_rules = $this->get_single_select_rules( $tag );
                    break;
                case 'date_time_picker':
                    $field_rules = $this->get_date_time_picker_rules( $tag );
                    break;
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $tag );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $tag );
                    break;
                case 'password':
                    $field_rules = $this->get_password_rules( $tag );
                    break;
                case 'range':
                    $field_rules = $this->get_range_rules( $tag );
                    break;
                case 'gdpr':
                    $field_rules = $this->get_gdpr_rules( $tag );
                    if ( ! $tag->has_option( 'optional' ) ) {
                        $field_rules[] = 'required';
                    }
                    break;
                default:
                    continue 2;
            }

            if ( 'gdpr' !== $mapped_type && $tag->is_required() ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[$tag->name] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    protected function get_validation_messages( array $form ): array {
        if ( ! $this->cf7_form ) {
            return [];
        }

        $tags     = $this->cf7_form->scan_form_tags();
        $messages = [];

        foreach ( $tags as $tag ) {
            if ( empty( $tag->name ) || empty( $tag->basetype ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $tag->basetype );
            if ( ! $mapped_type ) {
                continue;
            }

            $name = $tag->name;

            $is_required = 'gdpr' === $mapped_type
                ? ! $tag->has_option( 'optional' )
                : $tag->is_required();

            if ( $is_required ) {
                $msg                            = $this->cf7_form->message( 'invalid_required' );
                $messages[ "{$name}.required" ] = $msg ?: 'Please fill out this field.';
            }

            switch ( $mapped_type ) {
                case 'email':
                    $msg                         = $this->cf7_form->message( 'invalid_email' );
                    $messages[ "{$name}.email" ] = $msg ?: 'The e-mail address entered is invalid.';
                    break;
                case 'url':
                    $msg                       = $this->cf7_form->message( 'invalid_url' );
                    $messages[ "{$name}.url" ] = $msg ?: 'The URL is invalid.';
                    break;
                case 'number':
                case 'range':
                    $msg                           = $this->cf7_form->message( 'invalid_number' );
                    $messages[ "{$name}.numeric" ] = $msg ?: 'The number format is invalid.';
                    break;
                case 'gdpr':
                    $msg                      = $this->cf7_form->message( 'accept_terms' );
                    $messages[ "{$name}.in" ] = $msg ?: 'You must accept the terms and conditions before sending your message.';
                    break;
                case 'date_time_picker':
                    $maxlength = $tag->get_maxlength_option();
                    if ( $maxlength ) {
                        $msg                       = $this->cf7_form->message( 'invalid_too_long' );
                        $messages[ "{$name}.max" ] = $msg ?: 'This field has a too long input.';
                    }
                    $minlength = $tag->get_minlength_option();
                    if ( $minlength ) {
                        $msg                       = $this->cf7_form->message( 'invalid_too_short' );
                        $messages[ "{$name}.min" ] = $msg ?: 'This field has a too short input.';
                    }
                    break;
            }
        }

        return $messages;
    }

    protected function prepare_request_for_validation( Request $request, array $form ): void {
        if ( ! $this->cf7_form ) {
            return;
        }

        $tags = $this->cf7_form->scan_form_tags();
        foreach ( $tags as $tag ) {
            if ( $tag->basetype === 'acceptance' && $tag->name ) {
                $value = $request->get_param( $tag->name );
                if ( $value !== null ) {
                    $request->set_param( $tag->name, (int) $value ? '1' : '' );
                }
            }
        }
    }

    protected function submit( Request $request, array $form ) {
        if ( ! $this->cf7_form ) {
            return;
        }

        $tags = $this->cf7_form->scan_form_tags();

        $posted_data = [
            '_wpcf7_unit_tag'       => 'appnatively',
            '_wpcf7_container_post' => '0',
        ];

        foreach ( $tags as $tag ) {
            if ( empty( $tag->name ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $tag->basetype );
            if ( ! $mapped_type ) {
                continue;
            }

            $value = $request->get_param( $tag->name );
            if ( $value !== null ) {
                // The app renders a choice by its label and sends that label
                // back. Translate it to the value the tag declares before CF7
                // sees the submission, so CF7's own check that a choice is one
                // it offered stays in force for anything genuinely off-list.
                $value = $this->resolve_choice_value( $tag, $value );

                if ( 'checkbox' === $mapped_type && is_array( $value ) ) {
                    $posted_data[$tag->name] = ! empty( $value ) ? array_combine( $value, $value ) : [];
                } else {
                    $posted_data[$tag->name] = $value;
                }
            }
        }

        foreach ( $tags as $tag ) {
            if ( $tag->basetype === 'acceptance' && $tag->name && ! isset( $posted_data[$tag->name] ) ) {
                $posted_data[$tag->name] = '0';
            }
        }

        // Contact Form 7's nonce guards against a browser being made to submit
        // a form using its owner's cookies. A request carrying a Bearer token
        // cannot be produced that way — a browser will not attach an
        // Authorization header on a third party's behalf — so supplying the
        // nonce here restores parity with a real submission rather than
        // removing a check. A cookie-authenticated request is exactly the case
        // the nonce exists for, so it is left to prove itself.
        if ( $this->cf7_form->nonce_is_active()
            && is_user_logged_in()
            && Auth::is_token_authenticated()
        ) {
            $posted_data['_wpnonce'] = wpcf7_create_nonce();
        }

        $original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        // WordPress slashes $_POST on a normal request and every consumer
        // unslashes on the way back out — CF7's own submission handler calls
        // wp_unslash() on (array) $_POST. REST parameters arrive unslashed, so
        // they have to be slashed here or that unslash strips characters the
        // user actually typed.
        $_POST = wp_slash( $posted_data );

        $original_server = $_SERVER;

        $http_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        if ( empty( $http_user_agent ) || strlen( $http_user_agent ) < 2 ) {
            $_SERVER['HTTP_USER_AGENT'] = 'AppNatively/1.0';
        }

        // Helpers::get_user_ip_address() honours HTTP_CLIENT_IP and
        // X-Forwarded-For before REMOTE_ADDR, so what CF7, Flamingo and
        // Akismet record here follows whichever of those headers is present.
        $ip = Helpers::get_user_ip_address();
        if ( $ip ) {
            $_SERVER['REMOTE_ADDR'] = $ip;
        }

        $remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        if ( ! \WP_Http::is_ip_address( $remote_addr ) ) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }

        try {
            $result = $this->cf7_form->submit();

            if ( 'mail_sent' !== $result['status'] ) {
                // Thrown as the framework's own exception rather than a native
                // one so the message actually reaches the caller: Route::callback()
                // masks anything without get_messages() as "Something went wrong",
                // which is no help at all for a field the sender can correct.
                // CF7's message is the copy the site owner configured and already
                // shows on the public form, so it is safe to relay.
                $message = is_string( $result['message'] ?? null ) && '' !== $result['message']
                    ? $result['message']
                    : __( 'The form could not be submitted. Please check your entries and try again.', 'appnatively' );

                throw new Exception( esc_html( $message ), 400 );
            }
        } finally {
            $_POST   = $original_post;
            $_SERVER = $original_server;
        }
    }

    /**
     * Translate a submitted choice from its display label to the value its form
     * tag declares.
     *
     * map_form_to_dto() hands the app `{label, value}` pairs and the app may
     * send either back. CF7 checks a choice submission against the values the
     * tag declared and rejects anything else — the check that stops a caller
     * putting an arbitrary value into a select, radio or checkbox — so a label
     * is resolved here rather than by suppressing that rejection.
     *
     * Anything matching neither a value nor a label is passed through
     * untouched, so CF7 still sees it and still refuses it.
     *
     * @param \WPCF7_FormTag $tag   The form tag being submitted.
     * @param mixed          $value The submitted value, or array of values.
     * @return mixed
     */
    private function resolve_choice_value( \WPCF7_FormTag $tag, $value ) {
        if ( empty( $tag->values ) ) {
            return $value;
        }

        if ( is_array( $value ) ) {
            return array_map(
                function ( $single ) use ( $tag ) {
                    return $this->resolve_single_choice( $tag, $single );
                },
                $value
            );
        }

        return $this->resolve_single_choice( $tag, $value );
    }

    /**
     * Resolve one submitted item against a tag's declared values and labels.
     *
     * @param \WPCF7_FormTag $tag    The form tag.
     * @param mixed          $single The submitted item.
     * @return mixed
     */
    private function resolve_single_choice( \WPCF7_FormTag $tag, $single ) {
        if ( ! is_string( $single ) && ! is_numeric( $single ) ) {
            return $single;
        }

        $single = (string) $single;
        $values = (array) $tag->values;

        // Already one of the declared values — nothing to translate.
        if ( in_array( $single, $values, true ) ) {
            return $single;
        }

        // A tag written as "Label|value" keeps the two in step by index.
        foreach ( (array) $tag->labels as $index => $label ) {
            if ( (string) $label === $single && isset( $values[ $index ] ) ) {
                return $values[ $index ];
            }
        }

        return $single;
    }

    public function get_forms(): array {
        // Contact form 7 uses 'wpcf7_contact_form' as its custom post type
        $posts = Post::select( "ID", "post_title" )
            ->where( 'post_type', 'wpcf7_contact_form' )
            ->where( 'post_status', 'publish' )
            ->get();

        $result = [];

        foreach ( $posts as $post ) {
            $result[] = ( new FormDTO() )
                ->set_id( (int) $post->ID )
                ->set_title( $post->post_title )
                ->set_exclude_to_array( ['fields'] );
        }

        return $result;
    }

    protected function get_standardized_type( string $native_type ): ?string {
        $map = [
            'text'       => 'text',
            'email'      => 'email',
            'url'        => 'url',
            'number'     => 'number',
            'date'       => 'date_time_picker',
            'checkbox'   => 'checkbox',
            'radio'      => 'radio',
            'select'     => 'single_select',
            'acceptance' => 'gdpr',
            'textarea'   => 'text',
            'tel'        => 'text',
            'password'   => 'password',
            'range'      => 'range',
            'quiz'       => 'text',
        ];

        return $map[$native_type] ?? null;
    }

    private function extract_cf7_labels( string $template ): array {
        $labels = [];

        if ( empty( $template ) ) {
            return $labels;
        }

        // Match all <label ...>...</label> blocks (case-insensitive, dotall).
        if ( ! preg_match_all( '/<label\b([^>]*)>(.*?)<\/label>/is', $template, $label_matches, PREG_SET_ORDER ) ) {
            return $labels;
        }

        $tag_name_regex = '/\[([a-zA-Z]+)(?:\*)?\s+([A-Za-z0-9_\-\.]+)/';

        // Resolve tag name for each id:option across the whole template (for the for/id pattern).
        $id_to_name = [];
        if ( preg_match_all( '/\[([a-zA-Z]+)(?:\*)?\s+([A-Za-z0-9_\-\.]+)([^\]]*)\]/', $template, $all_tags, PREG_SET_ORDER ) ) {
            foreach ( $all_tags as $t ) {
                $name = strtr( $t[2], '.', '_' );
                $rest = $t[3] ?? '';
                if ( preg_match( '/\bid:([A-Za-z0-9_\-]+)/', $rest, $idm ) ) {
                    $id_to_name[ $idm[1] ] = $name;
                }
            }
        }

        foreach ( $label_matches as $lm ) {
            $attrs = $lm[1];
            $inner = $lm[2];

            // Clean the label text: strip form-tag tokens and HTML tags, collapse whitespace.
            $text = preg_replace( '/\[[^\]]*\]/', '', $inner );
            $text = trim( wp_strip_all_tags( $text ) );
            $text = preg_replace( '/\s+/', ' ', $text );

            if ( $text === '' ) {
                continue;
            }

            // Pattern 1: a form-tag name appears inside the <label>.
            if ( preg_match_all( $tag_name_regex, $inner, $tags_inside, PREG_SET_ORDER ) ) {
                foreach ( $tags_inside as $t ) {
                    $name            = strtr( $t[2], '.', '_' );
                    $labels[ $name ] = $text;
                }
                continue;
            }

            // Pattern 2: <label for="x">...</label> paired with [... id:x].
            if ( preg_match( '/\bfor\s*=\s*["\']?([A-Za-z0-9_\-]+)/i', $attrs, $fm ) ) {
                $for_id = $fm[1];
                if ( isset( $id_to_name[ $for_id ] ) ) {
                    $labels[ $id_to_name[ $for_id ] ] = $text;
                }
            }
        }

        return $labels;
    }

    protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO {
        $dto = new FormDTO();

        if ( in_array( 'id', $fields, true ) ) {
            $dto->set_id( (int) ( $raw_form['id'] ?? 0 ) );
        }

        if ( in_array( 'title', $fields, true ) ) {
            $dto->set_title( $raw_form['title'] ?? '' );
        }

        if ( in_array( 'fields', $fields, true ) && ! empty( $raw_form['id'] ) ) {
            $cf7_form   = wpcf7_contact_form( (int) $raw_form['id'] );
            $field_dtos = [];

            if ( $cf7_form ) {
                $tags      = $cf7_form->scan_form_tags();
                $label_map = $this->extract_cf7_labels( (string) $cf7_form->prop( 'form' ) );

                foreach ( $tags as $tag ) {
                    if ( empty( $tag->name ) || empty( $tag->basetype ) ) {
                        continue;
                    }

                    $std_type = $this->get_standardized_type( $tag->basetype );

                    if ( ! $std_type ) {
                        continue;
                    }

                    $fdto = new FormFieldDTO();
                    $fdto->set_id( $tag->name )
                        ->set_type( $std_type )
                        ->set_required( $tag->is_required() )
                        ->set_label( $label_map[ $tag->name ] ?? '' )
                        ->set_placeholder( $tag->get_option( 'placeholder', '', true ) ?: '' )
                        ->set_field_name( $tag->name );

                    $maxlength = $tag->get_maxlength_option();
                    if ( $maxlength ) {
                        $fdto->set_max_length( (int) $maxlength );
                    }

                    $minlength = $tag->get_minlength_option();
                    if ( $minlength ) {
                        $fdto->set_min_length( (int) $minlength );
                    }

                    if ( $std_type === 'number' ) {
                        $min = $tag->get_option( 'min', 'signed_num', true );
                        $max = $tag->get_option( 'max', 'signed_num', true );

                        if ( false !== $min ) {
                            $fdto->set_min_value( (float) $min );
                        }
                        if ( false !== $max ) {
                            $fdto->set_max_value( (float) $max );
                        }
                    }

                    if ( in_array( $std_type, [ 'radio', 'checkbox', 'single_select' ], true ) ) {
                        $items = [];

                        foreach ( $tag->values as $key => $value ) {
                            $items[] = [
                                'id'    => (string) $key,
                                'label' => $tag->labels[$key] ?? $value,
                                'value' => $value,
                            ];
                        }

                        $fdto->set_items( $items );
                    }

                    $field_dtos[] = $fdto;
                }
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}