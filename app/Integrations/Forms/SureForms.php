<?php

namespace AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use AppNatively\WpMVC\RequestValidator\Request;

class SureForms extends Form {
    public function get_key(): string {
        return 'sureforms';
    }

    public function boot(): void {
        if ( ! defined( 'SRFM_VER' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'sureforms_form' || $post->post_status !== 'publish' ) {
            return [];
        }

        $blocks = parse_blocks( $post->post_content );
        $fields = [];

        foreach ( $blocks as $block ) {
            if ( empty( $block['blockName'] ) || strpos( $block['blockName'], 'srfm/' ) !== 0 ) {
                continue;
            }

            $block_type = str_replace( 'srfm/', '', $block['blockName'] );
            $attrs      = $block['attrs'] ?? [];

            $fields[] = [
                'type'     => $block_type,
                'label'    => $attrs['label'] ?? '',
                'block_id' => $attrs['block_id'] ?? '',
                'slug'     => $attrs['slug'] ?? $block_type,
                'required' => ! empty( $attrs['required'] ),
                'options'  => $attrs['options'] ?? [],
                'min'      => $attrs['minValue'] ?? '',
                'max'      => $attrs['maxValue'] ?? '',
                'text_length' => $attrs['textLength'] ?? '',
                'placeholder' => $attrs['placeholder'] ?? '',
                'default_value' => $attrs['defaultValue'] ?? '',
            ];
        }

        if ( empty( $fields ) ) {
            return [];
        }

        return [
            'id'     => $post->ID,
            'fields' => $fields,
        ];
    }

    private function map_field_type( string $type ) {
        $map = [
            'input'    => 'text',
            'email'    => 'email',
            'number'   => 'number',
            'url'      => 'url',
            'checkbox' => 'checkbox',
            'gdpr'     => 'checkbox',
            'dropdown' => 'select',
        ];

        return $map[$type] ?? null;
    }

    private function get_text_rules( array $field ): array {
        $rules = [ 'string' ];
        if ( ! empty( $field['text_length'] ) ) {
            $rules[] = 'max:' . absint( $field['text_length'] );
        }
        return $rules;
    }

    private function get_email_rules( array $field ): array {
        return [ 'string', 'email' ];
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

    private function get_url_rules( array $field ): array {
        return [ 'string', 'url' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_select_rules( array $field ): array {
        return [ 'string', 'max:255' ];
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['slug'] ) ) {
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
                case 'email':
                    $field_rules = $this->get_email_rules( $field );
                    break;
                case 'number':
                    $field_rules = $this->get_number_rules( $field );
                    break;
                case 'url':
                    $field_rules = $this->get_url_rules( $field );
                    break;
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $field );
                    break;
                case 'select':
                    $field_rules = $this->get_select_rules( $field );
                    break;
                default:
                    continue 2;
            }

            if ( ! empty( $field['required'] ) ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[$field['slug']] = implode( '|', array_unique( $field_rules ) );
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
        $form_data = [
            'form-id' => $form['id'],
        ];

        foreach ( $form['fields'] as $field ) {
            error_log( 'Processing field: ' . print_r( $field, true ) );
            if ( empty( $field['slug'] ) ) {
                continue;
            }

            $value = $request->get_param( $field['slug'] );

            if ( $value === null ) {
                continue;
            }

            $form_data[$field['slug']] = $value;
        }

        error_log( 'Submitting to SureForms with data: ' . print_r( $form_data, true ) );

        \SRFM\Inc\Form_Submit::get_instance()->handle_form_entry( $form_data );
    }
}
