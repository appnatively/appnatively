<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

use Crafium\AppNatively\App\Models\Post;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
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
        $form = wpcf7_contact_form( $id );
        error_log(print_r( $form, true ) );
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
        $map = [
            'text'          => 'text',
            'email'         => 'email',
            'url'           => 'url',
            'number'        => 'number',
            'date'          => 'date',
            'checkbox'      => 'checkbox',
            'radio'         => 'radio',
            'single_select' => 'select',
            'gdpr'          => 'acceptance',
        ];

        $mapped = array_search( $type, $map, true );

        if ( false !== $mapped ) {
            return $mapped;
        }

        $extra = [
            'range' => 'number',
        ];

        return $extra[$type] ?? null;
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

    private function get_select_rules( \WPCF7_FormTag $tag ): array {
        return [ 'string', 'max:255' ];
    }

    private function get_date_rules( \WPCF7_FormTag $tag ): array {
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
                    $field_rules = $this->get_select_rules( $tag );
                    break;
                case 'date':
                    $field_rules = $this->get_date_rules( $tag );
                    break;
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $tag );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $tag );
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
                    $msg                           = $this->cf7_form->message( 'invalid_number' );
                    $messages[ "{$name}.numeric" ] = $msg ?: 'The number format is invalid.';
                    break;
                case 'gdpr':
                    $msg                      = $this->cf7_form->message( 'accept_terms' );
                    $messages[ "{$name}.in" ] = $msg ?: 'You must accept the terms and conditions before sending your message.';
                    break;
                case 'date':
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

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( 'form_id' ) );
        if ( ! $form ) {
            throw new \Exception( __( 'Form not found', 'appnatively' ) );
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

        if ( $this->cf7_form->nonce_is_active() &&
            is_user_logged_in()
        ) {
            $posted_data['_wpnonce'] = wpcf7_create_nonce();
        }

        $original_post = $_POST;
        $_POST         = $posted_data;

        $original_server = $_SERVER;

        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) || strlen( $_SERVER['HTTP_USER_AGENT'] ) < 2 ) {
            $_SERVER['HTTP_USER_AGENT'] = 'AppNatively/1.0';
        }

        $ip = Helpers::get_user_ip_address();
        if ( $ip ) {
            $_SERVER['REMOTE_ADDR'] = $ip;
        }

        if ( ! \WP_Http::is_ip_address( $_SERVER['REMOTE_ADDR'] ?? '' ) ) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }

        $filter = function ( $result, $tags ) {
            $invalid = $result->get_invalid_fields();
            $clean   = new \WPCF7_Validation();

            foreach ( $invalid as $field_name => $error ) {
                if ( str_contains( $error['reason'], 'Undefined value' ) ) {
                    continue;
                }
                $clean->invalidate( $field_name, $error['reason'] );
            }

            return $clean;
        };

        add_filter( 'wpcf7_validate', $filter, 10, 2 );

        try {
            $result = $this->cf7_form->submit();

            if ( 'mail_sent' !== $result['status'] ) {
                throw new \Exception(
                    sprintf(
                        /* translators: %1$s: CF7 submission status, %2$s: CF7 response message */
                        __( 'CF7 submission failed (status: %1$s) — %2$s', 'appnatively' ),
                        $result['status'],
                        $result['message']
                    )
                );
            }
        } finally {
            remove_filter( 'wpcf7_validate', $filter, 10 );
            $_POST   = $original_post;
            $_SERVER = $original_server;
        }
    }

    public function get_forms(): array {
        // Contact form 7 uses 'wpcf7_contact_form' as its custom post type
        $posts = Post::select("ID", "post_title")
            ->where( 'post_type', 'wpcf7_contact_form' )
            ->where( 'post_status', 'publish' )
            ->get();

        $result = [];

        foreach ( $posts as $post ) {
            $result[] = (new FormDTO())
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
            $text = trim( strip_tags( $text ) );
            $text = preg_replace( '/\s+/', ' ', $text );

            if ( $text === '' ) {
                continue;
            }

            // Pattern 1: a form-tag name appears inside the <label>.
            if ( preg_match_all( $tag_name_regex, $inner, $tags_inside, PREG_SET_ORDER ) ) {
                foreach ( $tags_inside as $t ) {
                    $name             = strtr( $t[2], '.', '_' );
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

        if ( in_array( 'status', $fields, true ) ) {
            $dto->set_status( $raw_form['status'] ?? 'publish' );
        }

        if ( in_array( 'date_created', $fields, true ) ) {
            $dto->set_date_created( $raw_form['date_created'] ?? '' );
        }

        if ( in_array( 'date_updated', $fields, true ) ) {
            $dto->set_date_updated( $raw_form['date_updated'] ?? '' );
        }

        if ( in_array( 'fields', $fields, true ) && ! empty( $raw_form['id'] ) ) {
            $cf7_form  = wpcf7_contact_form( (int) $raw_form['id'] );
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
                        ->set_fieldName( $tag->name );

                    $maxlength = $tag->get_maxlength_option();
                    if ( $maxlength ) {
                        $fdto->set_maxLength( (int) $maxlength );
                    }

                    $minlength = $tag->get_minlength_option();
                    if ( $minlength ) {
                        $fdto->set_minLength( (int) $minlength );
                    }

                    if ( $std_type === 'number' ) {
                        $min = $tag->get_option( 'min', 'signed_num', true );
                        $max = $tag->get_option( 'max', 'signed_num', true );

                        if ( false !== $min ) {
                            $fdto->set_minValue( (float) $min );
                        }
                        if ( false !== $max ) {
                            $fdto->set_maxValue( (float) $max );
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