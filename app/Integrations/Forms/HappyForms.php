<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class HappyForms extends Form
{
    public function get_key(): string {
        return 'happyforms';
    }

    public function boot(): void {
        if ( ! defined( 'HAPPYFORMS_VERSION' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $form = happyforms_get_form_controller()->get( $id );

        if ( ! $form || empty( $form['parts'] ) ) {
            return [];
        }

        return $form;
    }

    private function map_field_type( string $type ) {
        $map = [
            'single_line_text' => 'text',
            'email'            => 'email',
            'radio'            => 'radio',
            'checkbox'         => 'checkbox',
            'select'           => 'select',
            'number'           => 'number',
        ];

        return $map[$type] ?? null;
    }

    private function get_text_rules( array $part ): array {
        return ['string'];
    }

    private function get_email_rules( array $part ): array {
        return ['string', 'email'];
    }

    private function get_radio_rules( array $part ): array {
        return ['string'];
    }

    private function get_checkbox_rules( array $part ): array {
        return ['array'];
    }

    private function get_select_rules( array $part ): array {
        return ['string'];
    }

    private function get_number_rules( array $part ): array {
        $rules = ['numeric'];

        if ( isset( $part['min_value'] ) && $part['min_value'] !== '' ) {
            $rules[] = 'min:' . floatval( $part['min_value'] );
        }

        if ( isset( $part['max_value'] ) && $part['max_value'] !== '' ) {
            $rules[] = 'max:' . floatval( $part['max_value'] );
        }

        return $rules;
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['parts'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['parts'] as $part ) {
            if ( empty( $part['type'] ) || empty( $part['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $part['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name  = $part['id'];
            $field_rules = [];

            switch ( $mapped_type ) {
                case 'text':
                    $field_rules = $this->get_text_rules( $part );
                    break;
                case 'email':
                    $field_rules = $this->get_email_rules( $part );
                    break;
                case 'radio':
                    $field_rules = $this->get_radio_rules( $part );
                    break;
                case 'checkbox':
                    $field_rules = $this->get_checkbox_rules( $part );
                    break;
                case 'select':
                    $field_rules = $this->get_select_rules( $part );
                    break;
                case 'number':
                    $field_rules = $this->get_number_rules( $part );
                    break;
                default:
                    continue 2;
            }

            if ( ! empty( $part['required'] ) ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[$field_name] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    protected function get_validation_messages( array $form ): array {
        if ( empty( $form['parts'] ) ) {
            return [];
        }

        $messages = [];

        foreach ( $form['parts'] as $part ) {
            $name   = $part['id'];
            $type   = $part['type'];
            $mapped = $this->map_field_type( $type );

            if ( ! $mapped || ! $name ) {
                continue;
            }

            if ( ! empty( $part['required'] ) ) {
                $messages["{$name}.required"] = happyforms_get_validation_message( 'field_empty' );
            }

            if ( $mapped === 'email' ) {
                $messages["{$name}.email"] = happyforms_get_validation_message( 'field_invalid' );
            }

            if ( $mapped === 'number' ) {
                $messages["{$name}.numeric"] = happyforms_get_validation_message( 'field_invalid' );

                if ( isset( $part['min_value'] ) && $part['min_value'] !== '' ) {
                    $messages["{$name}.min"] = happyforms_get_validation_message( 'number_min_invalid' );
                }

                if ( isset( $part['max_value'] ) && $part['max_value'] !== '' ) {
                    $messages["{$name}.max"] = happyforms_get_validation_message( 'number_max_invalid' );
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

        foreach ( $form['parts'] as $part ) {
            if ( empty( $part['type'] ) || empty( $part['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $part['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $part['id'];
            $value      = $request->get_param( $field_name );

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
        if ( empty( $form['parts'] ) ) {
            return;
        }

        $submission = [];

        foreach ( $form['parts'] as $part ) {
            if ( empty( $part['type'] ) || empty( $part['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $part['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $part_id = $part['id'];
            $value   = $request->get_param( $part_id );

            if ( $value === null ) {
                continue;
            }

            $submission[$part_id] = $value;
        }

        if ( empty( $submission ) ) {
            return;
        }

        do_action( 'happyforms_submission_success', $submission, $form, [] );

        $message_controller = happyforms_get_message_controller();

        if ( 1 === intval( $form['receive_email_alerts'] ) ) {
            $this->call_private_method( $message_controller, 'email_owner_confirmation', [$form, $submission] );
        }

        if ( 1 === intval( $form['send_confirmation_email'] ) ) {
            $this->call_private_method( $message_controller, 'email_user_confirmation', [$form, $submission] );
        }
    }

    private function call_private_method( $object, string $method, array $args = [] ) {
        $reflection = new \ReflectionMethod( $object, $method );
        $reflection->setAccessible( true );
        return $reflection->invokeArgs( $object, $args );
    }

    public function get_forms(): array {
        // HappyForms registers its custom post type as 'happyform'
        $posts = Post::select( "ID", "post_title" )
            ->where( 'post_type', 'happyform' )
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
            'single_line_text' => 'text',
            'email'            => 'email',
            'radio'            => 'radio',
            'checkbox'         => 'checkbox',
            'select'           => 'single_select',
            'number'           => 'number',
            'paragraph_text'   => 'text',
            'url'              => 'url',
            'date'             => 'date_time_picker',
            'phone'            => 'text',
            'placeholder'      => 'text',
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

        if ( in_array( 'fields', $fields, true ) && ! empty( $raw_form['parts'] ) ) {
            $field_dtos = [];

            foreach ( $raw_form['parts'] as $part ) {
                $std_type = $this->get_standardized_type( $part['type'] ?? '' );

                if ( ! $std_type ) {
                    continue;
                }

                $fdto = new FormFieldDTO();
                $fdto->set_id( (string) $part['id'] )
                    ->set_type( $std_type )
                    ->set_required( ! empty( $part['required'] ) )
                    ->set_label( $part['label'] ?? '' )
                    ->set_placeholder( $part['placeholder'] ?? '' )
                    ->set_fieldName( (string) $part['id'] );

                if ( ! empty( $part['options'] ) ) {
                    $items = [];

                    foreach ( $part['options'] as $key => $option ) {
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
                    $fdto->set_minValue( isset( $part['min_value'] ) && $part['min_value'] !== '' ? (float) $part['min_value'] : null )
                        ->set_maxValue( isset( $part['max_value'] ) && $part['max_value'] !== '' ? (float) $part['max_value'] : null );
                }

                if ( $std_type === 'date_time_picker' ) {
                    $fdto->set_pickerType( 'date' )->set_dateFormat( 'yyyy-MM-dd' );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
