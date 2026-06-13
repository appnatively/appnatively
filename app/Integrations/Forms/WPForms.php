<?php

namespace AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use AppNatively\WpMVC\RequestValidator\Request;

class WPForms extends Form {
    private $field_name_to_id = [];

    public function get_key(): string {
        return 'wpforms';
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
            'text'          => 'single_line_text',
            'number'        => 'number',
            'email'         => 'email',
            'checkbox'      => 'checkboxes',
            'select'        => 'dropdown',
            'number-slider' => 'number_slider',
        ];

        return $map[$type] ?? null;
    }

    private function get_field_name( array $field, array &$used_names ): string {
        $label = ! empty( $field['label'] ) ? $field['label'] : $field['type'] . '_' . $field['id'];
        $name  = str_replace( '-', '_', sanitize_title( $label ) );

        if ( empty( $name ) ) {
            $name = $field['type'] . '_' . $field['id'];
        }

        $original = $name;
        $counter  = 1;

        while ( isset( $used_names[$name] ) ) {
            $name = $original . '_' . ( $counter++ );
        }

        $used_names[$name] = $field['id'];

        return $name;
    }

    private function get_single_line_text_rules( array $field ): array {
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

    private function get_website_rules( array $field ): array {
        return [ 'string', 'url' ];
    }

    private function get_checkboxes_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_dropdown_rules( array $field ): array {
        $rules = [ 'string' ];

        if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
            $labels = [];
            foreach ( $field['choices'] as $choice ) {
                if ( isset( $choice['label'] ) && $choice['label'] !== '' ) {
                    $labels[] = $choice['label'];
                }
            }
            if ( ! empty( $labels ) ) {
                $rules[] = 'in:' . implode( ',', $labels );
            }
        }

        return $rules;
    }

    private function get_number_slider_rules( array $field ): array {
        $rules = [ 'numeric' ];

        if ( isset( $field['min'] ) && $field['min'] !== '' ) {
            $rules[] = 'min:' . floatval( $field['min'] );
        }

        if ( isset( $field['max'] ) && $field['max'] !== '' ) {
            $rules[] = 'max:' . floatval( $field['max'] );
        }

        return $rules;
    }

    private function get_rating_rules( array $field ): array {
        $rules = [ 'integer' ];

        if ( isset( $field['max_rating'] ) ) {
            $rules[] = 'max:' . absint( $field['max_rating'] );
        }

        return $rules;
    }

    private function get_date_time_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_password_rules( array $field ): array {
        return [ 'string' ];
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $rules       = [];
        $used_names  = [];
        $this->field_name_to_id = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $this->get_field_name( $field, $used_names );
            $this->field_name_to_id[$field_name] = $field['id'];

            $method = 'get_' . $mapped_type . '_rules';

            if ( ! method_exists( $this, $method ) ) {
                continue;
            }

            $field_rules = $this->$method( $field );

            if ( ! empty( $field['required'] ) && $field['required'] === '1' ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[$field_name] = implode( '|', array_unique( $field_rules ) );
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
        $entry = [
            'id'     => (int) $form['id'],
            'fields' => [],
        ];

        if ( is_user_logged_in() ) {
            $entry['nonce'] = wp_create_nonce( "wpforms::form_{$form['id']}" );
        }

        $mapping = empty( $this->field_name_to_id ) ? $this->build_field_name_to_id( $form ) : $this->field_name_to_id;

        foreach ( $request->get_params() as $param_name => $value ) {
            if ( isset( $mapping[ $param_name ] ) ) {
                $field_id                     = $mapping[ $param_name ];
                $entry['fields'][ $field_id ] = $value;
            }
        }

        wpforms()->obj( 'process' )->process( $entry );
    }

    private function build_field_name_to_id( array $form ): array {
        $used_names = [];
        $mapping    = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || ! $this->map_field_type( $field['type'] ) ) {
                continue;
            }
            $name               = $this->get_field_name( $field, $used_names );
            $mapping[ $name ]   = $field['id'];
        }

        return $mapping;
    }
}
