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
            'text'     => 'input',
            'email'    => 'email',
            'number'   => 'number',
            'url'      => 'url',
            'checkbox' => 'checkbox',
            'gdpr'     => 'gdpr',
            'select'   => 'dropdown',
        ];

        $mapped = array_search( $type, $map, true );
        return false !== $mapped ? $mapped : null;
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
        return [ 'string' ];
    }

    private function get_gdpr_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_select_rules( array $field ): array {
        return [ 'string', 'max:255' ];
    }

    private function build_sureforms_field_name( array $field, int &$dropdown_counter ): string {
        $type = $field['type'];

        if ( $type === 'dropdown' ) {
            $dropdown_counter++;
            $type_part = "dropdown-{$dropdown_counter}";
        } else {
            $type_part = $type;
        }

        $block_id     = $field['block_id'];
        $label        = ! empty( $field['label'] ) ? $field['label'] : $type;
        $base64_label = \SRFM\Inc\Helper::encrypt( $label );
        $block_slug   = $field['slug'];

        return "srfm-{$type_part}-{$block_id}-lbl-{$base64_label}-{$block_slug}";
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
                case 'gdpr':
                    $field_rules = $this->get_gdpr_rules( $field );
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

    /**
     * Build the custom validation messages pulled from the SureForms
     * "Form Messages" settings, so failed rules surface the admin-configured
     * text instead of the framework default. Mirrors the WPForms integration.
     *
     * Keys are `{slug}.{rule}` to match the rules array from get_validation_rules().
     *
     * @param array $form Form data.
     * @return array<string, string>
     */
    protected function get_validation_messages( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $messages = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['slug'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $slug = (string) $field['slug'];

            if ( ! empty( $field['required'] ) ) {
                $required_key = $this->get_required_message_key( $mapped_type );
                if ( ! empty( $required_key ) ) {
                    $messages["{$slug}.required"] = \SRFM\Inc\Helper::get_default_dynamic_block_option( $required_key );
                }
            }

            if ( $mapped_type === 'email' ) {
                $messages["{$slug}.email"] = \SRFM\Inc\Helper::get_default_dynamic_block_option( 'srfm_valid_email' );
            }

            if ( $mapped_type === 'url' ) {
                $messages["{$slug}.url"] = \SRFM\Inc\Helper::get_default_dynamic_block_option( 'srfm_valid_url' );
            }

            if ( $mapped_type === 'number' ) {
                if ( isset( $field['min'] ) && $field['min'] !== '' ) {
                    $msg = \SRFM\Inc\Helper::get_default_dynamic_block_option( 'srfm_input_min_value' );
                    $messages["{$slug}.min"] = str_replace( '%s', ':min', $msg );
                }

                if ( isset( $field['max'] ) && $field['max'] !== '' ) {
                    $msg = \SRFM\Inc\Helper::get_default_dynamic_block_option( 'srfm_input_max_value' );
                    $messages["{$slug}.max"] = str_replace( '%s', ':max', $msg );
                }
            }
        }

        return $messages;
    }

    /**
     * Map a mapped field type to its SureForms per-block "required" message
     * settings key. SureForms keeps one required message per block type,
     * unlike WPForms' single validation-required key.
     *
     * @param string $mapped_type Mapped field type.
     * @return string|null Settings key, or null when none applies.
     */
    private function get_required_message_key( string $mapped_type ): ?string {
        $map = [
            'text'     => 'srfm_input_block_required_text',
            'email'    => 'srfm_email_block_required_text',
            'number'   => 'srfm_number_block_required_text',
            'url'      => 'srfm_url_block_required_text',
            'checkbox' => 'srfm_checkbox_block_required_text',
            'gdpr'     => 'srfm_gdpr_block_required_text',
            'select'   => 'srfm_dropdown_block_required_text',
        ];

        return $map[$mapped_type] ?? null;
    }

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( 'form_id' ) );

        if ( ! $form ) {
            throw new \Exception( __( 'Form not found', 'appnatively' ) );
        }

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['slug'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $value = $request->get_param( $field['slug'] );

            if ( $value === null ) {
                continue;
            }

            if ( in_array( $mapped_type, [ 'checkbox', 'gdpr' ], true ) && is_array( $value ) ) {
                $request->set_param( $field['slug'], ! empty( $value ) ? (string) reset( $value ) : '' );
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
        $form_data = [
            'form-id' => $form['id'],
        ];

        $dropdown_counter = 0;

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['slug'] ) || empty( $field['type'] ) ) {
                continue;
            }

            $value = $request->get_param( $field['slug'] );

            if ( $value === null ) {
                continue;
            }

            $field_name = $this->build_sureforms_field_name( $field, $dropdown_counter );
            $form_data[$field_name] = $value;
        }

        \SRFM\Inc\Form_Submit::get_instance()->handle_form_entry( $form_data );
    }
}
