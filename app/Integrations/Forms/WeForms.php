<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class WeForms extends Form {
    public function get_key(): string {
        return 'weforms';
    }

    public function boot(): void {
        if ( ! function_exists( 'weforms' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $form = weforms()->form->get( $id );

        if ( ! $form || ! $form->id || 'publish' !== ( $form->data->post_status ?? '' ) ) {
            return [];
        }

        return [
            'id'       => (int) $form->id,
            'name'     => $form->name,
            'fields'   => $form->get_fields(),
            'settings' => $form->get_settings(),
        ];
    }

    private function map_field_type( string $type ) {
        return $this->get_standardized_type( $type );
    }

    private function get_text_rules( array $field ): array {
        $rules = [ 'string' ];
        if ( ! empty( $field['word_restriction'] ) ) {
            $rules[] = 'max:' . absint( $field['word_restriction'] );
        }
        return $rules;
    }

    private function get_email_rules( array $field ): array {
        return [ 'string', 'email' ];
    }

    private function get_single_select_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_radio_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_url_rules( array $field ): array {
        return [ 'string', 'url' ];
    }

    private function get_number_rules( array $field ): array {
        return [ 'numeric' ];
    }

    private function get_date_time_picker_rules( array $field ): array {
        return [ 'string' ];
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['template'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['template'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name  = $field['name'];
            $field_rules = [];

            switch ( $mapped_type ) {
                case 'text':
                    $field_rules = $this->get_text_rules( $field );
                    break;
                case 'email':
                    $field_rules = $this->get_email_rules( $field );
                    break;
                case 'single_select':
                    $field_rules = $this->get_single_select_rules( $field );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $field );
                    break;
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $field );
                    break;
                case 'url':
                    $field_rules = $this->get_url_rules( $field );
                    break;
                case 'number':
                    $field_rules = $this->get_number_rules( $field );
                    break;
                case 'date_time_picker':
                    $field_rules = $this->get_date_time_picker_rules( $field );
                    break;
                default:
                    continue 2;
            }

            if ( isset( $field['required'] ) && $field['required'] === 'yes' ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[ $field_name ] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    protected function get_validation_messages( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $default_required = [
            'text'             => 'This field cannot be blank.',
            'email'            => 'This field cannot be blank.',
            'single_select'    => 'Please select a value.',
            'radio'            => 'Please select a value.',
            'checkbox'         => 'Please select a value.',
            'url'              => 'This field cannot be blank.',
            'number'           => 'This field cannot be blank.',
            'date_time_picker' => 'This field cannot be blank.',
        ];

        $messages = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['template'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['template'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['name'];

            if ( isset( $field['required'] ) && $field['required'] === 'yes' ) {
                $messages[ "{$field_name}.required" ] = $default_required[ $mapped_type ] ?? 'This field is required.';
            }

            if ( $mapped_type === 'email' ) {
                $messages[ "{$field_name}.email" ] = 'This is not a valid email.';
            }

            if ( $mapped_type === 'url' ) {
                $messages[ "{$field_name}.url" ] = 'Please enter a valid URL.';
            }

            if ( $mapped_type === 'text' && ! empty( $field['word_restriction'] ) ) {
                $messages[ "{$field_name}.max" ] = 'This field cannot exceed :max characters.';
            }
        }

        return $messages;
    }

    protected function prepare_request_for_validation( Request $request, array $form ): void {
        if ( empty( $form['fields'] ) ) {
            return;
        }

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['template'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['template'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['name'];
            $value      = $request->get_param( $field_name );

            if ( $value === null ) {
                continue;
            }

            if ( $mapped_type === 'checkbox' && is_array( $value ) ) {
                $request->set_param( $field_name, ! empty( $value ) ? array_combine( $value, $value ) : [] );
            }
        }
    }

    protected function submit( Request $request, array $form ) {
        if ( empty( $form['fields'] ) || empty( $form['id'] ) ) {
            return;
        }

        $form_id = (int) $form['id'];
        $fields  = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['template'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['template'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['name'];
            $value      = $request->get_param( $field_name );

            if ( $value !== null ) {
                if ( $mapped_type === 'checkbox' && is_array( $value ) ) {
                    $labels = [];
                    foreach ( $value as $key => $val ) {
                        $labels[] = $field['options'][ $key ] ?? $key;
                    }
                    $fields[ $field_name ] = implode( \WeForms::$field_separator, $labels );
                } else {
                    $fields[ $field_name ] = $value;
                }
            }
        }

        if ( ! empty( $fields ) ) {
            $entry_id = weforms_insert_entry(
                [
                    'form_id' => $form_id,
                ],
                $fields
            );

            if ( $entry_id && ! is_wp_error( $entry_id ) ) {
                //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- firing weForms' own hook so its native post-entry behavior runs, not defining a hook of our own.
                do_action( 'weforms_entry_submission', $entry_id, $form_id, 0, $form['settings'] ?? [] );

                $notification = new \WeForms_Notification(
                    [
                        'form_id'  => $form_id,
                        'page_id'  => 0,
                        'entry_id' => $entry_id,
                    ]
                );
                $notification->send_notifications();
            }
        }
    }

    public function get_forms(): array {

        $forms = weforms()->form->get_forms();

        if ( empty( $forms['forms'] ) ) {
            return [];
        }

        $result = [];

        foreach ( $forms['forms'] as $form ) {
            $form_obj = weforms()->form->get( $form->id );

            if ( ! $form_obj || ! $form_obj->id ) {
                continue;
            }

            $result[] = ( new FormDTO() )
                ->set_id( (int) $form_obj->id )
                ->set_title( $form_obj->name );
        }

        return $result;
    }

    protected function get_standardized_type( string $native_type ): ?string {
        $map = [
            'text_field'     => 'text',
            'email_address'  => 'email',
            'dropdown_field' => 'single_select',
            'radio_field'    => 'radio',
            'checkbox_field' => 'checkbox',
            'website_url'    => 'url',
            'date_field'     => 'date_time_picker',
            'textarea_field' => 'text',
            'number_field'   => 'number',
            'section_break'  => null,
            'custom_html'    => null,
        ];

        return $map[$native_type] ?? null;
    }

    protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO {
        $dto = new FormDTO();

        if ( in_array( 'id', $fields, true ) ) {
            $dto->set_id( (int) ( $raw_form['id'] ?? 0 ) );
        }

        if ( in_array( 'title', $fields, true ) ) {
            $dto->set_title( $raw_form['name'] ?? '' );
        }

        if ( in_array( 'fields', $fields, true ) && ! empty( $raw_form['fields'] ) ) {
            $field_dtos = [];

            foreach ( $raw_form['fields'] as $field ) {
                $std_type = $this->get_standardized_type( $field['template'] ?? '' );

                if ( ! $std_type ) {
                    continue;
                }

                $fdto = new FormFieldDTO();
                $fdto->set_id( $field['name'] ?? '' )
                    ->set_type( $std_type )
                    ->set_required( isset( $field['required'] ) && $field['required'] === 'yes' )
                    ->set_label( $field['label'] ?? '' )
                    ->set_placeholder( $field['placeholder'] ?? '' )
                    ->set_field_name( $field['name'] ?? '' );

                if ( ! empty( $field['options'] ) ) {
                    $items = [];

                    foreach ( $field['options'] as $key => $option ) {
                        $items[] = [
                            'id'    => (string) $key,
                            'label' => $option,
                            'value' => $option,
                        ];
                    }

                    $fdto->set_items( $items );
                }

                if ( $std_type === 'text' && ! empty( $field['word_restriction'] ) ) {
                    $fdto->set_character_limit( (int) $field['word_restriction'] );
                }

                if ( $std_type === 'date_time_picker' ) {
                    $fdto->set_picker_type( 'date' )->set_date_format( 'yyyy-MM-dd' );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
