<?php

namespace AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use AppNatively\WpMVC\Helpers\Helpers;
use AppNatively\WpMVC\RequestValidator\Request;

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
        $rules = [ 'string' ];
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
        $rules = [ 'string', 'email' ];
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
        $rules = [ 'string', 'url' ];
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
        $min = $tag->get_option( 'min', 'signed_num', true );
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

    protected function get_validation_rules( array $form ): array {
        if ( ! $this->cf7_form ) {
            return [];
        }

        $tags = $this->cf7_form->scan_form_tags();
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
                default:
                    continue 2;
            }

            if ( $tag->is_required() ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[$tag->name] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( 'form_id' ) );
        if ( ! $form ) {
            throw new \Exception( __( 'Form not found', 'appnatively' ) );
        }

        $request->validate( $this->get_validation_rules( $form ) );

        $this->submit( $request, $form );
    }

    protected function submit( Request $request, array $form ) {
        if ( ! $this->cf7_form ) {
            return;
        }

        $tags = $this->cf7_form->scan_form_tags();

        $posted_data = [
            '_wpcf7_unit_tag' => 'appnatively',
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

        if (
            $this->cf7_form->nonce_is_active() &&
            is_user_logged_in()
        ) {
            $posted_data['_wpnonce'] = wpcf7_create_nonce();
        }

        $original_post = $_POST;
        $_POST = $posted_data;

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
            $clean = new \WPCF7_Validation();

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
            $this->cf7_form->submit();
        } finally {
            remove_filter( 'wpcf7_validate', $filter, 10 );
            $_POST = $original_post;
            $_SERVER = $original_server;
        }
    }
}