<?php

namespace AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use AppNatively\WpMVC\RequestValidator\Request;

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

        $fields = \FrmField::get_all_for_form( $id, 999 );
        $fields_array = [];

        foreach ( $fields as $field ) {
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

            // error_log( "Value for field " . $field_name . ": " . print_r( $value, true ) );

            if ( $value === null ) {
                continue;
            }

            if ( $mapped_type === 'checkbox' && is_array( $value ) ) {
                $request->set_param( $field_name, ! empty( $value ) ? array_combine( $value, $value ) : [] );
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
}
