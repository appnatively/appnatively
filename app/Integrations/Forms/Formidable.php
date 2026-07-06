<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

use FrmForm;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class Formidable extends Form {
    public function get_key(): string {
        return 'formidable';
    }

    public function boot(): void {
        if ( ! class_exists( 'FrmForm' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $form = \FrmForm::getOne( $id );
        if ( ! $form ) {
            return [];
        }

        $fields       = \FrmField::get_all_for_form( $id, 999 );
        $fields_array = [];

        foreach ( $fields as $field ) {
            error_log( 'field: ' . print_r( $field, true ), 0 );
            $fields_array[] = [
                'id'            => (int) $field->id,
                'type'          => $field->type,
                'name'          => $field->name,
                'field_key'     => $field->field_key,
                'required'      => ! empty( $field->required ),
                'options'       => $field->options,
                'field_options' => (array) ( $field->field_options ?? [] ),
            ];
        }

        return [
            'id'     => (int) $form->id,
            'key'    => $form->form_key,
            'name'   => $form->name,
            'fields' => $fields_array,
        ];
    }

    private function map_field_type( string $type ) {
        $map = [
            'text'     => 'text',
            'email'    => 'email',
            'url'      => 'url',
            'number'   => 'number',
            'checkbox' => 'checkbox',
            'radio'    => 'radio',
            'select'   => 'select',
            'gdpr'     => 'gdpr',
        ];

        $mapped = array_search( $type, $map, true );
        return false !== $mapped ? $mapped : null;
    }

    private function get_text_rules( array $field ): array {
        $rules = [ 'string' ];
        if ( ! empty( $field['max'] ) ) {
            $rules[] = 'max:' . absint( $field['max'] );
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
        return [ 'string', 'max:255' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_select_rules( array $field ): array {
        return [ 'string', 'max:255' ];
    }

    private function get_gdpr_rules( array $field ): array {
        return [ 'integer', 'in:0,1' ];
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['field_key'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_rules = [];
            $field_name  = $field['field_key'];

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
                case 'gdpr':
                    $field_rules = $this->get_gdpr_rules( $field );
                    break;
                default:
                    continue 2;
            }

            if ( ! empty( $field['required'] ) ) {
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
            'text'     => 'This field cannot be blank.',
            'email'    => 'This field cannot be blank.',
            'url'      => 'This field cannot be blank.',
            'number'   => 'This field cannot be blank.',
            'radio'    => 'Please select a value.',
            'checkbox' => 'Please select a value.',
            'select'   => 'Please select a value.',
            'gdpr'     => 'You must agree to proceed.',
        ];

        $messages = [];

        foreach ( $form['fields'] as $field ) {
            $name   = $field['field_key'];
            $type   = $field['type'];
            $mapped = $this->map_field_type( $type );

            if ( ! $mapped || ! $name ) {
                continue;
            }

            $field_options = $field['field_options'] ?? [];

            if ( ! empty( $field['required'] ) ) {
                $msg = ! empty( $field_options['blank'] )
                    ? str_replace( '[field_name]', $field['name'], $field_options['blank'] )
                    : ( $default_required[ $type ] ?? 'This field cannot be blank.' );

                $messages[ "{$name}.required" ] = $msg;
            }

            if ( $mapped === 'email' ) {
                $msg = ! empty( $field_options['invalid'] )
                    ? str_replace( '[field_name]', $field['name'], $field_options['invalid'] )
                    : 'This is not a valid email.';

                $messages[ "{$name}.email" ] = $msg;
            }

            if ( $mapped === 'url' ) {
                $msg = ! empty( $field_options['invalid'] )
                    ? str_replace( '[field_name]', $field['name'], $field_options['invalid'] )
                    : 'Please enter a valid URL.';

                $messages[ "{$name}.url" ] = $msg;
            }

            if ( $mapped === 'gdpr' ) {
                $gdpr_msg = ! empty( $field_options['blank'] )
                    ? str_replace( '[field_name]', $field['name'], $field_options['blank'] )
                    : 'You must agree to proceed.';

                $messages[ "{$name}.integer" ] = $gdpr_msg;
                $messages[ "{$name}.in" ]      = $gdpr_msg;
            }

            if ( $mapped === 'number' ) {
                $msg = ! empty( $field_options['invalid'] )
                    ? str_replace( '[field_name]', $field['name'], $field_options['invalid'] )
                    : 'This is not a valid number.';

                $messages[ "{$name}.numeric" ] = $msg;

                if ( isset( $field['min'] ) && $field['min'] !== '' ) {
                    $messages[ "{$name}.min" ] = ! empty( $field_options['invalid'] )
                        ? str_replace( '[field_name]', $field['name'], $field_options['invalid'] )
                        : 'Value must be at least :min.';
                }

                if ( isset( $field['max'] ) && $field['max'] !== '' ) {
                    $messages[ "{$name}.max" ] = ! empty( $field_options['invalid'] )
                        ? str_replace( '[field_name]', $field['name'], $field_options['invalid'] )
                        : 'Value must be at most :max.';
                }
            }
        }

        return $messages;
    }

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( 'form_id' ) );

        if ( ! $form ) {
            throw new \Exception( __( 'Form not found', 'appnatively' ) );
        }

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['field_key'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['field_key'];
            $value      = $request->get_param( $field_name );

            if ( $value === null ) {
                continue;
            }

            if ( $mapped_type === 'checkbox' && is_array( $value ) ) {
                $request->set_param( $field_name, ! empty( $value ) ? array_combine( $value, $value ) : [] );
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

        $form_id   = (int) $form['id'];
        $item_meta = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['field_key'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_id = (int) $field['id'];
            $value    = $request->get_param( $field['field_key'] );

            if ( $value !== null ) {
                $item_meta[ $field_id ] = $value;
            }
        }

        if ( ! empty( $item_meta ) ) {
            $entry_id = \FrmEntry::create(
                [
                    'form_id'   => $form_id,
                    'item_key'  => 'entry',
                    'item_meta' => $item_meta,
                ]
            );

            if ( $entry_id ) {
                do_action( 'frm_after_create_entry', $entry_id, $form_id );
            }
        }
    }

    public function get_forms(): array {
        // Formidable native model call to fetch all published, non-template forms
        $forms = FrmForm::get_published_forms();

        if ( ! $forms ) {
            return [];
        }

        $result = [];

        foreach ( $forms as $form ) {
            $result[] = ( new FormDTO() )
                ->set_id( (int) $form->id )       // Formidable uses lowercase 'id' properties
                ->set_title( $form->name )        // Formidable uses 'name' instead of 'post_title'
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
            'checkbox' => 'checkbox',
            'radio'    => 'radio',
            'select'   => 'single_select',
            'gdpr'     => 'gdpr',
            'textarea' => 'text',
            'password' => 'password',
            'date'     => 'date_time_picker',
            'range'    => 'range',
            'star'     => 'rating',
            'scale'    => 'rating',
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

        if ( in_array( 'status', $fields, true ) ) {
            $dto->set_status( $raw_form['status'] ?? 'publish' );
        }

        if ( in_array( 'date_created', $fields, true ) ) {
            $dto->set_date_created( $raw_form['date_created'] ?? '' );
        }

        if ( in_array( 'date_updated', $fields, true ) ) {
            $dto->set_date_updated( $raw_form['date_updated'] ?? '' );
        }

        if ( in_array( 'fields', $fields, true ) && ! empty( $raw_form['fields'] ) ) {
            $field_dtos = [];

            foreach ( $raw_form['fields'] as $field ) {
                $std_type = $this->get_standardized_type( $field['type'] ?? '' );

                if ( ! $std_type ) {
                    continue;
                }

                $fdto = new FormFieldDTO();
                $fdto->set_id( (string) $field['field_key'] )
                    ->set_type( $std_type )
                    ->set_required( ! empty( $field['required'] ) )
                    ->set_label( $field['name'] ?? '' )
                    ->set_fieldName( $field['field_key'] ?? '' );

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
                    $field_options = $field['field_options'] ?? [];
                    $fdto->set_minValue( isset( $field_options['minnum'] ) ? (float) $field_options['minnum'] : null )
                        ->set_maxValue( isset( $field_options['maxnum'] ) ? (float) $field_options['maxnum'] : null );
                }

                if ( $std_type === 'date_time_picker' ) {
                    $fdto->set_pickerType( 'date' )->set_dateFormat( 'yyyy-MM-dd' );
                }

                if ( $std_type === 'rating' ) {
                    $fdto->set_ratingMax( 5 );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
