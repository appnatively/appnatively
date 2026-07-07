<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class Forminator extends Form {
    public function get_key(): string {
        return 'forminator';
    }

    public function boot(): void {
        if ( ! defined( 'FORMINATOR_VERSION' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        if ( ! class_exists( 'Forminator_API' ) ) {
            return [];
        }

        $form = \Forminator_API::get_form( $id );

        error_log( 'Forminator form: ' . print_r( $form, true ), 0 );

        if ( ! $form || is_wp_error( $form ) ) {
            return [];
        }

        $form_array       = $form->to_array();
        $form_array['id'] = $id;

        if ( ! empty( $form_array['fields'] ) ) {
            $fields = [];

            foreach ( $form_array['fields'] as $field ) {
                if ( is_object( $field ) && method_exists( $field, 'to_array' ) ) {
                    $fields[] = $field->to_array();
                }
            }

            $form_array['fields'] = $fields;
        }

        return $form_array;
    }

    private function map_field_type( string $type ) {
        $map = [
            'text'     => 'text',
            'email'    => 'email',
            'url'      => 'url',
            'number'   => 'number',
            'radio'    => 'radio',
            'checkbox' => 'checkbox',
            'select'   => 'select',
            'date'     => 'date',
            'rating'   => 'rating',
            'slider'   => 'slider',
            'time'     => 'date',
            'consent'  => 'gdpr',
        ];

        return $map[$type] ?? null;
    }

    private function get_text_rules( array $field ): array {
        $rules = [ 'string' ];

        if ( ! empty( $field['text_limit'] ) && ! empty( $field['limit'] ) ) {
            $rules[] = 'max:' . absint( $field['limit'] );
        }

        return $rules;
    }

    private function get_email_rules( array $field ): array {
        return [ 'string', 'email' ];
    }

    private function get_url_rules( array $field ): array {
        return [ 'string', 'url' ];
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

    private function get_radio_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_select_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_rating_rules( array $field ): array {
        $rules = [ 'integer' ];

        if ( isset( $field['max_rating'] ) ) {
            $rules[] = 'max:' . absint( $field['max_rating'] );
        }

        return $rules;
    }

    private function get_date_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_gdpr_rules( array $field ): array {
        return [ 'integer', 'in:0,1' ];
    }

    private function get_slider_rules( array $field ): array {
        $rules = [ 'numeric' ];

        if ( isset( $field['min'] ) && $field['min'] !== '' ) {
            $rules[] = 'min:' . floatval( $field['min'] );
        }

        if ( isset( $field['max'] ) && $field['max'] !== '' ) {
            $rules[] = 'max:' . floatval( $field['max'] );
        }

        return $rules;
    }

    protected function get_validation_messages( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $default_required = [
            'text'     => 'This field is required. Please enter text.',
            'email'    => 'This field is required. Please input a valid email.',
            'url'      => 'This field is required. Please input a valid URL.',
            'number'   => 'This field is required. Please enter number.',
            'radio'    => 'This field is required. Please select a value.',
            'checkbox' => 'This field is required. Please select a value.',
            'select'   => 'This field is required. Please select a value.',
            'date'     => 'This field is required.',
            'rating'   => 'This field is required. Please select a rating.',
            'slider'   => 'This field is required.',
        ];

        $default_format = [
            'email'  => 'This is not a valid email.',
            'url'    => 'Please enter a valid Website URL (e.g. https://wpmudev.com/).',
            'number' => 'This is not a valid number.',
            'date'   => 'Please enter a valid date.',
        ];

        $messages = [];

        foreach ( $form['fields'] as $field ) {
            error_log( 'Processing field for validation messages: ' . json_encode( $field ) );
            $name   = $field['id'] ?? '';
            $type   = $field['type'] ?? '';
            $mapped = $this->map_field_type( $type );

            if ( ! $mapped || ! $name ) {
                continue;
            }

            if ( isset( $field['required'] ) && filter_var( $field['required'], FILTER_VALIDATE_BOOLEAN ) ) {
                if ( ! empty( $field['required_message'] ) ) {
                    $messages[ "{$name}.required" ] = $field['required_message'];
                } elseif ( isset( $default_required[ $type ] ) ) {
                    $messages[ "{$name}.required" ] = $default_required[ $type ];
                }
            }

            if ( in_array( $mapped, [ 'email', 'url' ], true ) ) {
                $messages[ "{$name}.{$mapped}" ] = ! empty( $field['validation_message'] )
                    ? $field['validation_message']
                    : $default_format[ $mapped ];
            }

            if ( in_array( $mapped, [ 'number', 'rating', 'slider' ], true ) ) {
                $messages[ "{$name}.numeric" ] = $default_format['number'];
            }

            if ( $mapped === 'date' ) {
                $messages[ "{$name}.date" ] = $default_format['date'];
            }

            if ( $mapped === 'gdpr' ) {
                $gdpr_msg                      = ! empty( $field['required_message'] )
                    ? $field['required_message']
                    : __( 'This field is required. Please check it.', 'forminator' );
                $messages[ "{$name}.integer" ] = $gdpr_msg;
                $messages[ "{$name}.in" ]      = $gdpr_msg;
            }

            if ( in_array( $mapped, [ 'number', 'slider' ], true ) ) {
                if ( ! empty( $field['limit_min_message'] ) ) {
                    $messages[ "{$name}.min" ] = str_replace( '{0}', ':min', $field['limit_min_message'] );
                }
                if ( ! empty( $field['limit_max_message'] ) ) {
                    $messages[ "{$name}.max" ] = str_replace( '{0}', ':max', $field['limit_max_message'] );
                }
            }
        }

        return $messages;
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['element_id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name  = $field['element_id'];
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
                case 'select':
                    $field_rules = $this->get_select_rules( $field );
                    break;
                case 'rating':
                    $field_rules = $this->get_rating_rules( $field );
                    break;
                case 'date':
                    $field_rules = $this->get_date_rules( $field );
                    break;
                case 'slider':
                    $field_rules = $this->get_slider_rules( $field );
                    break;
                case 'gdpr':
                    $field_rules = $this->get_gdpr_rules( $field );
                    break;
                default:
                    continue 2;
            }

            if ( isset( $field['required'] ) && filter_var( $field['required'], FILTER_VALIDATE_BOOLEAN ) ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[ $field_name ] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( 'form_id' ) );

        if ( ! $form ) {
            throw new \Exception( __( 'Form not found', 'appnatively' ) );
        }

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['element_id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['element_id'];
            $value      = $request->get_param( $field_name );

            if ( $value === null ) {
                continue;
            }

            if ( $mapped_type === 'checkbox' && is_array( $value ) ) {
                $request->set_param( $field_name, ! empty( $value ) ? array_combine( $value, $value ) : [] );
            }

            if ( $mapped_type === 'slider' && is_array( $value ) ) {
                $request->set_param( $field_name, isset( $value['max'] ) && $value['max'] !== '' ? (int) $value['max'] : 0 );
            }

            if ( $mapped_type === 'gdpr' ) {
                $request->set_param( $field_name, (int) $value );
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
        if ( empty( $form['fields'] ) || empty( $form['id'] ) ) {
            return;
        }

        $form_id       = (int) $form['id'];
        $entry_meta    = [];
        $prepared_data = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['element_id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['element_id'];
            $value      = $request->get_param( $field_name );

            if ( $value !== null ) {
                if ( $mapped_type === 'gdpr' ) {
                    $value = (int) $value ? 'checked' : '';
                }

                $entry_meta[] = [
                    'name'  => $field_name,
                    'value' => $value,
                ];

                $prepared_data[ $field_name ] = $value;
            }
        }

        if ( ! empty( $entry_meta ) ) {
            $entry_id = \Forminator_API::add_form_entry( $form_id, $entry_meta );

            if ( ! is_wp_error( $entry_id ) ) {
                $form_model = \Forminator_Base_Form_Model::get_model( $form_id );
                $entry      = new \Forminator_Form_Entry_Model( $entry_id );

                \Forminator_CForm_Front_Action::$prepared_data = $prepared_data;

                $mail_sender = new \Forminator_CForm_Front_Mail();
                $mail_sender->process_mail( $form_model, $entry );
            }
        }
    }

    public function get_forms(): array {
        // Forminator registers its custom post type as 'forminator_forms'
        $posts = Post::select( "ID", "post_title" )
            ->where( 'post_type', 'forminator_forms' )
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
            'text'     => 'text',
            'email'    => 'email',
            'url'      => 'url',
            'number'   => 'number',
            'radio'    => 'radio',
            'checkbox' => 'checkbox',
            'select'   => 'single_select',
            'date'     => 'date_time_picker',
            'rating'   => 'rating',
            'slider'   => 'range',
            'time'     => 'date_time_picker',
            'consent'  => 'gdpr',
            'password' => 'password',
            'textarea' => 'text',
            'phone'    => 'text',
        ];

        return $map[$native_type] ?? null;
    }

    protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO {
        $dto = new FormDTO();

        if ( in_array( 'id', $fields, true ) ) {
            $dto->set_id( (int) ( $raw_form['id'] ?? 0 ) );
        }

        if ( in_array( 'title', $fields, true ) ) {
            $dto->set_title( $raw_form['name'] ?? $raw_form['title'] ?? '' );
        }

        if ( in_array( 'status', $fields, true ) ) {
            $dto->set_status( $raw_form['status'] ?? $raw_form['form_status'] ?? 'publish' );
        }

        if ( in_array( 'date_created', $fields, true ) ) {
            $dto->set_date_created( $raw_form['created_at'] ?? $raw_form['date_created'] ?? '' );
        }

        if ( in_array( 'date_updated', $fields, true ) ) {
            $dto->set_date_updated( $raw_form['updated_at'] ?? $raw_form['date_updated'] ?? '' );
        }

        if ( in_array( 'fields', $fields, true ) && ! empty( $raw_form['fields'] ) ) {
            $field_dtos = [];

            foreach ( $raw_form['fields'] as $field ) {
                $std_type = $this->get_standardized_type( $field['type'] ?? '' );

                if ( ! $std_type ) {
                    continue;
                }

                $fdto = new FormFieldDTO();
                $fdto->set_id( $field['element_id'] ?? '' )
                    ->set_type( $std_type )
                    ->set_required( ! empty( $field['required'] ) && filter_var( $field['required'], FILTER_VALIDATE_BOOLEAN ) )
                    ->set_label( ( $std_type === 'gdpr' && ! empty( $field['consent_description'] ) ) ? $field['consent_description'] : ( $field['field_label'] ?? $field['label'] ?? '' ) )
                    ->set_placeholder( $field['placeholder'] ?? '' )
                    ->set_field_name( $field['element_id'] ?? '' );

                if ( ! empty( $field['options'] ) ) {
                    $items = [];

                    foreach ( $field['options'] as $key => $option ) {
                        if ( is_string( $option ) ) {
                            $items[] = [
                                'id'    => (string) $key,
                                'label' => $option,
                                'value' => $option,
                            ];
                        } elseif ( is_array( $option ) ) {
                            $items[] = [
                                'id'    => $option['value'] ?? (string) $key,
                                'label' => $option['label'] ?? '',
                                'value' => $option['value'] ?? '',
                            ];
                        }
                    }

                    $fdto->set_items( $items );
                }

                if ( $std_type === 'number' ) {
                    $fdto->set_min_value( isset( $field['min'] ) && $field['min'] !== '' ? (float) $field['min'] : null )
                        ->set_max_value( isset( $field['max'] ) && $field['max'] !== '' ? (float) $field['max'] : null );
                }

                if ( $std_type === 'range' ) {
                    $fdto->set_min_value( isset( $field['min'] ) && $field['min'] !== '' ? (float) $field['min'] : null )
                        ->set_max_value( isset( $field['max'] ) && $field['max'] !== '' ? (float) $field['max'] : null );
                }

                if ( $std_type === 'rating' ) {
                    $fdto->set_rating_max( isset( $field['max_rating'] ) ? (int) $field['max_rating'] : 5 );
                }

                if ( $std_type === 'date_time_picker' ) {
                    if ( ( $field['type'] ?? '' ) === 'time' ) {
                        $fdto->set_picker_type( 'time' )->set_date_format( 'hh:mm a' );
                    } else {
                        $fdto->set_picker_type( 'date' )->set_date_format( 'yyyy-MM-dd' );
                    }
                }

                if ( $std_type === 'text' && ! empty( $field['text_limit'] ) && ! empty( $field['limit'] ) ) {
                    $fdto->set_character_limit( (int) $field['limit'] );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
