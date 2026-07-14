<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class GutenaForms extends Form {
    public function get_key(): string {
        return 'gutena-forms';
    }

    public function boot(): void {
        if ( ! defined( 'GUTENA_FORMS_VERSION' ) ) {
            return;
        }
        
        parent::boot();
    }

    protected function get_form( int $id ) {
        $block_form_id = get_post_meta( $id, 'gutena_form_id', true );

        if ( ! $block_form_id ) {
            return [];
        }

        $schema = gutena_forms_get_form_schema_option( $block_form_id );

        if ( empty( $schema ) || empty( $schema['form_attrs'] ) || empty( $schema['form_fields'] ) ) {
            return [];
        }

        $fields = [];

        foreach ( $schema['form_fields'] as $name_attr => $field ) {
            error_log( 'field: ' . print_r( $field, true ), 0 );
            if ( empty( $field['nameAttr'] ) ) {
                continue;
            }

            // Gutena persists its schema via parse_blocks(), which does NOT merge
            // registered block-attribute defaults. Re-apply them from the live
            // gutena/form-field block type so choice fields expose their option list
            // (and range fields their min/max) even when left at defaults.
            $field_block = \WP_Block_Type_Registry::get_instance()->get_registered( 'gutena/form-field' );
            if ( $field_block && ! empty( $field_block->attributes ) ) {
                foreach ( $field_block->attributes as $attr_name => $attr_def ) {
                    if ( ! isset( $field[ $attr_name ] ) && is_array( $attr_def ) && array_key_exists( 'default', $attr_def ) ) {
                        $field[ $attr_name ] = $attr_def['default'];
                    }
                }
            }

            $field_type = $field['fieldType'] ?? 'text';

            $normalized = [
                'name'     => $field['nameAttr'],
                'type'     => $field_type,
                'label'    => $field['fieldName'] ?? '',
                'required' => ! empty( $field['isRequired'] ),
            ];

            if ( in_array( $field_type, [ 'radio', 'checkbox', 'select' ], true ) ) {
                $options = [];

                if ( isset( $field['selectOptions'] ) && is_array( $field['selectOptions'] ) ) {
                    foreach ( $field['selectOptions'] as $option ) {
                        if ( is_string( $option ) ) {
                            $options[] = $option;
                        } elseif ( is_array( $option ) && isset( $option['value'] ) ) {
                            $options[] = $option['value'];
                        } elseif ( is_array( $option ) && isset( $option['label'] ) ) {
                            $options[] = $option['label'];
                        }
                    }
                }

                $normalized['options'] = $options;
            }

            if ( $field_type === 'range' ) {
                $min_max = ! empty( $field['minMaxStep'] ) ? $field['minMaxStep'] : [];

                if ( is_array( $min_max ) ) {
                    $normalized['min'] = $min_max['min'] ?? '';
                    $normalized['max'] = $min_max['max'] ?? '';
                }
            }

            $fields[] = $normalized;
        }

        return [
            'form_id'    => $block_form_id,
            'form_name'  => $schema['form_attrs']['formName'] ?? '',
            'form_attrs' => $schema['form_attrs'],
            'fields'     => $fields,
        ];
    }

    private function map_field_type( string $type ) {
        $map = [
            'text'     => 'text',
            'email'    => 'email',
            'number'   => 'number',
            'checkbox' => 'checkbox',
            'select'   => 'select',
            'radio'    => 'radio',
            'range'    => 'range',
        ];

        return $map[ $type ] ?? null;
    }

    private function get_text_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_email_rules( array $field ): array {
        return [ 'string', 'email' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_select_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_radio_rules( array $field ): array {
        return [ 'string' ];
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

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

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
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $field );
                    break;
                case 'select':
                    $field_rules = $this->get_select_rules( $field );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $field );
                    break;
                case 'range':
                case 'number':
                    $field_rules = $this->get_number_rules( $field );
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

        $base_messages = [
            'required_msg'        => __( 'Please fill in this field', 'gutena-forms' ),
            'required_msg_optin'  => __( 'Please check this checkbox', 'gutena-forms' ),
            'required_msg_select' => __( 'Please select an option', 'gutena-forms' ),
            'required_msg_check'  => __( 'Please check an option', 'gutena-forms' ),
            'invalid_email_msg'   => __( 'Please enter a valid email address', 'gutena-forms' ),
            'min_value_msg'       => __( 'Input value should be greater than', 'gutena-forms' ),
            'max_value_msg'       => __( 'Input value should be less than', 'gutena-forms' ),
        ];

        $global_messages = get_option( 'gutena_forms__form_validation_messages', [] );
        $form_messages   = isset( $form['form_attrs']['messages'] ) && is_array( $form['form_attrs']['messages'] )
            ? $form['form_attrs']['messages']
            : [];

        $effective_messages = array_merge(
            $base_messages,
            is_array( $global_messages ) ? $global_messages : [],
            $form_messages
        );

        $messages = [];

        foreach ( $form['fields'] as $field ) {
            $name   = $field['name'];
            $type   = $field['type'];
            $mapped = $this->map_field_type( $type );

            // error_log( 'field: ' . print_r( $mapped, true ), 0 );

            if ( ! $mapped || ! $name ) {
                continue;
            }

            if ( ! empty( $field['required'] ) ) {
                if ( $mapped === 'checkbox' ) {
                    $messages[ "{$name}.required" ] = $effective_messages['required_msg_check'];
                } elseif ( $type === 'select' ) {
                    $messages[ "{$name}.required" ] = $effective_messages['required_msg_select'];
                } else {
                    $messages[ "{$name}.required" ] = $effective_messages['required_msg'];
                }
            }

            if ( $mapped === 'text' ) {
                $messages[ "{$name}.string" ] = $effective_messages['required_msg'];
            }

            if ( $mapped === 'email' ) {
                $messages[ "{$name}.email" ] = $effective_messages['invalid_email_msg'];
            }

            if ( $mapped === 'number' || $mapped === 'range' ) {
                $messages[ "{$name}.numeric" ] = __( 'Please enter a valid number', 'gutena-forms' );

                if ( isset( $field['min'] ) && $field['min'] !== '' ) {
                    $messages[ "{$name}.min" ] = $effective_messages['min_value_msg'] . ' :min';
                }

                if ( isset( $field['max'] ) && $field['max'] !== '' ) {
                    $messages[ "{$name}.max" ] = $effective_messages['max_value_msg'] . ' :max';
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
            if ( empty( $field['type'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

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

            if ( $mapped_type === 'range' && is_array( $value ) ) {
                $request->set_param( $field_name, isset( $value['max'] ) && $value['max'] !== '' ? (int) $value['max'] : 0 );
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
        if ( empty( $form['fields'] ) ) {
            return;
        }

        $schema_fields = [];
        $submission    = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['name'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['name'];
            $value      = $request->get_param( $field_name );

            if ( $value === null ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $field_value = implode( ', ', $value );
            } else {
                $field_value = sanitize_textarea_field( wp_unslash( (string) $value ) );
            }

            $submission[ $field_name ] = $field_value;

            $schema_fields[ $field_name ] = [
                'nameAttr'  => $field['name'],
                'fieldName' => $field['label'],
                'fieldType' => $field['type'],
            ];
        }

        if ( empty( $submission ) ) {
            return;
        }

        $raw_data = [];

        foreach ( $form['fields'] as $field ) {
            $name_attr = $field['name'] ?? '';

            if ( ! $name_attr || ! isset( $submission[ $name_attr ] ) ) {
                continue;
            }

            $raw_data[ $name_attr ] = [
                'label'     => $field['label'],
                'value'     => $submission[ $name_attr ],
                'fieldType' => $field['type'],
                'raw_value' => $submission[ $name_attr ],
            ];
        }

        do_action( 'gutena_forms_submitted_data', $raw_data, $form['form_id'], $schema_fields );
        do_action(
            'gutena_forms_submission', [
                'formName'    => $form['form_name'],
                'formID'      => $form['form_id'],
                'submit_data' => $submission,
                'raw_data'    => $raw_data,
            ], $form['form_attrs'] 
        );

        $form_attrs = $form['form_attrs'];

        $skip_email = isset( $form_attrs['emailNotifyAdmin'] ) && ( '' === $form_attrs['emailNotifyAdmin'] || false === $form_attrs['emailNotifyAdmin'] || '0' === $form_attrs['emailNotifyAdmin'] );

        if ( ! $skip_email ) {
            $this->send_admin_notification( $form, $submission );
        }
    }

    private function send_admin_notification( array $form, array $submission ) {
        $form_attrs  = $form['form_attrs'];
        $blog_title  = get_bloginfo( 'name' );
        $from_name   = empty( $form_attrs['emailFromName'] ) ? $blog_title : $form_attrs['emailFromName'];
        $admin_email = sanitize_email( get_option( 'admin_email' ) );

        $to = empty( $form_attrs['adminEmails'] ) ? $admin_email : $form_attrs['adminEmails'];

        if ( ! is_array( $to ) ) {
            $to = explode( ',', $to );
        }

        foreach ( $to as $key => $to_email ) {
            $to[ $key ] = sanitize_email( wp_unslash( $to_email ) );
        }

        $subject = sanitize_text_field(
            empty( $form_attrs['adminEmailSubject'] )
                ? __( 'Form received', 'gutena-forms' ) . ' - ' . $blog_title
                : $form_attrs['adminEmailSubject']
        );

        $body = '';

        foreach ( $submission as $name_attr => $field_value ) {
            $label = $name_attr;

            foreach ( $form['fields'] as $field ) {
                if ( $field['name'] === $name_attr ) {
                    $label = $field['label'];
                    break;
                }
            }

            $body .= '<p><strong>' . esc_html( $label ) . '</strong> <br />' . esc_html( $field_value ) . ' </p>';
        }

        $body = apply_filters(
            'gutena_forms_submit_admin_notification', $body, [
                'formName'    => $form['form_name'],
                'formID'      => $form['form_id'],
                'submit_data' => $submission,
            ] 
        );

        $body = wpautop( $body, true );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . esc_html( $from_name ) . ' <' . $admin_email . '>',
        ];

        $reply_to = $form_attrs['replyToEmail'] ?? '';

        if ( ! empty( $reply_to ) && ! empty( $submission[ $reply_to ] ) ) {
            $reply_to_value = sanitize_email( wp_unslash( $submission[ $reply_to ] ) );

            if ( ! empty( $reply_to_value ) ) {
                $reply_to_name = '';

                if ( ! empty( $form_attrs['replyToName'] ) && ! empty( $submission[ $form_attrs['replyToName'] ] ) ) {
                    $reply_to_name = sanitize_text_field( wp_unslash( $submission[ $form_attrs['replyToName'] ] ) );
                }

                if ( ! empty( $form_attrs['replyToLastName'] ) && ! empty( $submission[ $form_attrs['replyToLastName'] ] ) ) {
                    $reply_to_name .= ' ' . sanitize_text_field( wp_unslash( $submission[ $form_attrs['replyToLastName'] ] ) );
                }

                $headers[] = 'Reply-To: ' . esc_html( trim( $reply_to_name ) ) . ' <' . $reply_to_value . '>';
            }
        }

        wp_mail( $to, $subject, $body, $headers );
    }

    public function get_forms(): array {
        if ( ! defined( 'GUTENA_FORMS_VERSION' ) ) {
            return [];
        }

        $posts = get_posts(
            [
                'post_type'      => 'gutena_forms',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ] 
        );

        if ( empty( $posts ) ) {
            return [];
        }

        $result = [];

        foreach ( $posts as $post ) {
            $block_form_id = get_post_meta( $post->ID, 'gutena_form_id', true );

            if ( ! $block_form_id ) {
                continue;
            }

            $schema = function_exists( 'gutena_forms_get_form_schema_option' )
                ? gutena_forms_get_form_schema_option( $block_form_id )
                : [];

            if ( empty( $schema ) || empty( $schema['form_attrs'] ) || empty( $schema['form_fields'] ) ) {
                continue;
            }

            $form_title = $schema['form_attrs']['formName'] ?? $post->post_title;

            $result[] = ( new FormDTO() )
                ->set_id( (int) $post->ID )
                ->set_title( $form_title )
                ->set_exclude_to_array( ['fields'] );
        }

        return $result;
    }

    protected function get_standardized_type( string $native_type ): ?string {
        $map = [
            'text'     => 'text',
            'email'    => 'email',
            'number'   => 'number',
            'checkbox' => 'checkbox',
            'select'   => 'single_select',
            'radio'    => 'radio',
            'range'    => 'range',
            'url'      => 'url',
            'tel'      => 'text',
            'textarea' => 'text',
        ];

        return $map[$native_type] ?? null;
    }

    protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO {
        $dto = new FormDTO();

        if ( in_array( 'id', $fields, true ) ) {
            $dto->set_id( (int) ( $raw_form['id'] ?? 0 ) );
        }

        if ( in_array( 'title', $fields, true ) ) {
            $dto->set_title( $raw_form['form_name'] ?? '' );
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
                $fdto->set_id( $field['name'] ?? '' )
                    ->set_type( $std_type )
                    ->set_required( ! empty( $field['required'] ) )
                    ->set_label( $field['label'] ?? '' )
                    ->set_field_name( $field['name'] ?? '' );

                if ( ! empty( $field['options'] ) ) {
                    $items = [];

                    foreach ( $field['options'] as $key => $value ) {
                        $items[] = [
                            'id'    => (string) $key,
                            'label' => $value,
                            'value' => $value,
                        ];
                    }

                    $fdto->set_items( $items );
                }

                if ( $std_type === 'number' || $std_type === 'range' ) {
                    $fdto->set_min_value( isset( $field['min'] ) && $field['min'] !== '' ? (float) $field['min'] : null )
                        ->set_max_value( isset( $field['max'] ) && $field['max'] !== '' ? (float) $field['max'] : null );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
