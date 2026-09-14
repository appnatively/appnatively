<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class SureForms extends Form
{
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

            $registered = \WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );
            if ( $registered && isset( $registered->attributes ) ) {
                foreach ( $registered->attributes as $key => $definition ) {
                    if ( ! array_key_exists( $key, $attrs ) && array_key_exists( 'default', $definition ) ) {
                        $attrs[$key] = $definition['default'];
                    }
                }
            }

            $fields[] = [
                'type'          => $block_type,
                'label'         => $attrs['label'] ?? '',
                'block_id'      => $attrs['block_id'] ?? '',
                'slug'          => $attrs['slug'] ?? $block_type,
                'required'      => ! empty( $attrs['required'] ),
                'options'       => $attrs['options'] ?? [],
                'min'           => $attrs['minValue'] ?? '',
                'max'           => $attrs['maxValue'] ?? '',
                'text_length'   => $attrs['textLength'] ?? '',
                'placeholder'   => $attrs['placeholder'] ?? '',
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

    private function map_field_type( string $type ): ?string {
        return $this->get_standardized_type( $type );
    }

    private function get_text_rules( array $field ): array {
        $rules = ['string'];
        if ( ! empty( $field['text_length'] ) ) {
            $rules[] = 'max:' . absint( $field['text_length'] );
        }
        return $rules;
    }

    private function get_email_rules( array $field ): array {
        return ['string', 'email'];
    }

    private function get_number_rules( array $field ): array {
        $rules = ['numeric'];
        if ( isset( $field['min'] ) && $field['min'] !== '' ) {
            $rules[] = 'min:' . floatval( $field['min'] );
        }
        if ( isset( $field['max'] ) && $field['max'] !== '' ) {
            $rules[] = 'max:' . floatval( $field['max'] );
        }
        return $rules;
    }

    private function get_url_rules( array $field ): array {
        return ['string', 'url'];
    }

    private function get_checkbox_rules( array $field ): array {
        return ['string'];
    }

    private function get_gdpr_rules( array $field ): array {
        // GDPR consent must always be affirmatively given, regardless of whether the
        // plugin author happened to mark the field "required" in the form builder.
        return ['string', 'required'];
    }

    private function get_single_select_rules( array $field ): array {
        return ['string', 'max:255'];
    }

    private function get_radio_rules( array $field ): array {
        return ['string'];
    }

    private function get_date_time_picker_rules( array $field ): array {
        return ['string'];
    }

    private function build_sureforms_field_name( array $field, int &$dropdown_counter ): string {
        $type = $field['type'];

        if ( $type === 'dropdown' ) {
            $dropdown_counter++;
            $type_part = "dropdown-{$dropdown_counter}";
        } elseif ( $type === 'multi-choice' ) {
            $type_part = 'input-multi-choice';
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
                case 'single_select':
                    $field_rules = $this->get_single_select_rules( $field );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $field );
                    break;
                case 'date_time_picker':
                    $field_rules = $this->get_date_time_picker_rules( $field );
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

            $is_required = $mapped_type === 'gdpr' || ! empty( $field['required'] );

            if ( $is_required ) {
                $required_key = $this->get_required_message_key( $mapped_type, $field['type'] ?? '' );
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
                    $msg                     = \SRFM\Inc\Helper::get_default_dynamic_block_option( 'srfm_input_min_value' );
                    $messages["{$slug}.min"] = str_replace( '%s', ':min', $msg );
                }

                if ( isset( $field['max'] ) && $field['max'] !== '' ) {
                    $msg                     = \SRFM\Inc\Helper::get_default_dynamic_block_option( 'srfm_input_max_value' );
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
     * @param string $native_type Native SureForms block type (for multi-choice override).
     * @return string|null Settings key, or null when none applies.
     */
    private function get_required_message_key( string $mapped_type, string $native_type = '' ): ?string {
        if ( $native_type === 'multi-choice' ) {
            return 'srfm_multi_choice_block_required_text';
        }

        $map = [
            'text'          => 'srfm_input_block_required_text',
            'email'         => 'srfm_email_block_required_text',
            'number'        => 'srfm_number_block_required_text',
            'url'           => 'srfm_url_block_required_text',
            'checkbox'      => 'srfm_checkbox_block_required_text',
            'gdpr'          => 'srfm_gdpr_block_required_text',
            'single_select' => 'srfm_dropdown_block_required_text',
        ];

        return $map[$mapped_type] ?? null;
    }

    protected function prepare_request_for_validation( Request $request, array $form ): void {
        if ( empty( $form['fields'] ) ) {
            return;
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

            if ( in_array( $mapped_type, ['checkbox', 'gdpr'], true ) && is_array( $value ) ) {
                $request->set_param( $field['slug'], ! empty( $value ) ? (string) reset( $value ) : '' );
            }

            if ( $mapped_type === 'checkbox' && $value === '0' ) {
                $request->set_param( $field['slug'], '' );
            }
        }
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

            $field_name             = $this->build_sureforms_field_name( $field, $dropdown_counter );
            $form_data[$field_name] = $value;
        }

        \SRFM\Inc\Form_Submit::get_instance()->handle_form_entry( $form_data );
    }

    public function get_forms(): array {
        $posts = Post::select( "ID", "post_title" )
            ->where( 'post_type', 'sureforms_form' )
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
            'input'        => 'text',
            'email'        => 'email',
            'number'       => 'number',
            'url'          => 'url',
            'checkbox'     => 'checkbox',
            'multi-choice' => 'checkbox',
            'gdpr'         => 'gdpr',
            'dropdown'     => 'single_select',
            'radio'        => 'radio',
            'textarea'     => 'text',
            'phone'        => 'text',
            'date'         => 'date_time_picker',
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

        if ( in_array( 'fields', $fields, true ) && ! empty( $raw_form['fields'] ) ) {
            $field_dtos = [];

            foreach ( $raw_form['fields'] as $field ) {
                $std_type = $this->get_standardized_type( $field['type'] ?? '' );

                if ( ! $std_type ) {
                    continue;
                }

                $fdto = new FormFieldDTO();
                $fdto->set_id( $field['slug'] ?? '' )
                    ->set_type( $std_type )
                    ->set_required( ! empty( $field['required'] ) )
                    ->set_label( $field['label'] ?? '' )
                    ->set_placeholder( $field['placeholder'] ?? '' )
                    ->set_field_name( $field['slug'] ?? '' );

                if ( ! empty( $field['options'] ) ) {
                    $items = [];

                    foreach ( $field['options'] as $key => $option ) {
                        if ( is_string( $option ) ) {
                            $items[] = [
                                'id'          => (string) $key,
                                'optionLabel' => $option,
                                'optionValue' => $option,
                            ];
                        } elseif ( is_array( $option ) ) {
                            $option_label = $option['label'] ?? $option['optionTitle'] ?? '';
                            $items[]      = [
                                'id'          => $option['value'] ?? (string) $key,
                                'optionLabel' => $option_label,
                                'optionValue' => $option['value'] ?? $option_label,
                            ];
                        }
                    }

                    $fdto->set_items( $items );
                }

                if ( $std_type === 'number' ) {
                    $fdto->set_min_value( isset( $field['min'] ) && $field['min'] !== '' ? (float) $field['min'] : null )
                        ->set_max_value( isset( $field['max'] ) && $field['max'] !== '' ? (float) $field['max'] : null );
                }

                if ( $std_type === 'text' && ! empty( $field['text_length'] ) ) {
                    $fdto->set_character_limit( (int) $field['text_length'] );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
