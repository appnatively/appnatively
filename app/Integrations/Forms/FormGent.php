<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Helpers\Helpers;
use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;

use Crafium\AppNatively\App\Models\Post;
class FormGent extends Form {
    public function get_key(): string {
        return 'formgent';
    }

    public function boot(): void {
        if ( ! function_exists( 'formgent_get_form_by_id' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $form = formgent_get_form_by_id( $id, true );
        return $form ? (array) $form : [];
    }

    private function map_field_type( string $type ) {
        // appnatively => formgent
        $map = [
            'text'             => 'text',
            'number'           => 'number',
            'email'            => 'email',
            'url'              => 'website',
            'radio'            => 'single-choice',
            'checkbox'         => 'multiple-choice',
            'single_select'    => 'dropdown',
            'range'            => 'range-slider',
            'rating'           => 'rating',
            'date_time_picker' => 'date-picker',
            'gdpr'             => 'gdpr',
        ];

        $key = array_search( $type, $map, true );
        return false !== $key ? $key : null;
    }

    private function get_text_rules( array $field ): array {
        $rules = [ 'string' ];
        if ( ! empty( $field['character_limit'] ) && ! empty( $field['limit'] ) ) {
            $rules[] = 'max:' . absint( $field['limit'] );
        }
        return $rules;
    }

    private function get_email_rules( array $field ): array {
        $rules = [ 'string', 'email' ];
        if ( ! empty( $field['character_limit'] ) && ! empty( $field['limit'] ) ) {
            $rules[] = 'max:' . absint( $field['limit'] );
        }
        return $rules;
    }

    private function get_url_rules( array $field ): array {
        $rules = [ 'string', 'url' ];
        if ( ! empty( $field['character_limit'] ) && ! empty( $field['limit'] ) ) {
            $rules[] = 'max:' . absint( $field['limit'] );
        }
        return $rules;
    }

    private function get_number_rules( array $field ): array {
        $rules = [];
        if ( isset( $field['format'] ) && 'non_decimal' === $field['format'] ) {
            $rules[] = 'integer';
        } else {
            $rules[] = 'numeric';
        }

        if ( ! empty( $field['limit'] ) ) {
            if ( isset( $field['min'] ) ) {
                $rules[] = 'min:' . absint( $field['min'] );
            }
            if ( isset( $field['max'] ) ) {
                $rules[] = 'max:' . absint( $field['max'] );
            }
        }
        return $rules;
    }

    private function get_radio_rules( array $field ): array {
        return [ 'string', 'max:255' ];
    }

    private function get_single_select_rules( array $field ): array {
        return [ 'string', 'max:255' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_range_rules( array $field ): array {
        $rules       = [];
        $slider_type = isset( $field['slider_type'] ) ? $field['slider_type'] : 'number';
        if ( 'number' === $slider_type ) {
            $rules[] = 'numeric';
            if ( isset( $field['min_value'] ) && isset( $field['max_value'] ) 
                 && is_numeric( $field['min_value'] ) && is_numeric( $field['max_value'] ) ) {
                $rules[] = 'min:' . floatval( $field['min_value'] );
                $rules[] = 'max:' . floatval( $field['max_value'] );
            }
        } else {
            $rules[] = 'string';
        }
        return $rules;
    }

    private function get_rating_rules( array $field ): array {
        $rules = [ 'integer' ];
        if ( isset( $field['rating_limit'] ) ) {
            $rules[] = 'max:' . absint( $field['rating_limit'] );
        }
        return $rules;
    }

    private function get_date_time_picker_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_gdpr_rules( array $field ): array {
        return [ 'integer', 'in:1' ];
    }

    protected function get_validation_rules( array $form ) : array {
        if ( empty( $form ) ) {
            return [];
        }

        $form_object = (object) $form;
        $fields      = formgent_get_form_fields( $form_object );
        $rules       = [];

        foreach ( $fields as $field ) {
            if ( empty( $field['name'] ) || empty( $field['field_type'] ) ) {
                continue;
            }

            $type        = $field['field_type'];
            $mapped_type = $this->map_field_type( $type );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name  = $field['name'];
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
                case 'single_select':
                    $field_rules = $this->get_single_select_rules( $field );
                    break;
                case 'range':
                    $field_rules = $this->get_range_rules( $field );
                    break;
                case 'rating':
                    $field_rules = $this->get_rating_rules( $field );
                    break;
                case 'date_time_picker':
                    $field_rules = $this->get_date_time_picker_rules( $field );
                    break;
                case 'gdpr':
                    $field_rules   = $this->get_gdpr_rules( $field );
                    $field_rules[] = 'required';
                    break;
                default:
                    continue 2;
            }

            if ( isset( $field['required'] ) && $field['required'] ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[$field_name] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    protected function get_validation_messages( array $form ): array {
        if ( empty( $form ) ) {
            return [];
        }

        $form_object         = (object) $form;
        $fields              = formgent_get_form_fields( $form_object );
        $validation_messages = formgent_get_setting( 'validation_messages', [] );
        $messages            = [];

        foreach ( $fields as $field ) {
            if ( empty( $field['name'] ) || empty( $field['field_type'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['field_type'] );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['name'];

            if ( isset( $field['required'] ) && $field['required'] ) {
                $messages[ "{$field_name}.required" ] = $validation_messages['required'] ?? 'This field is required';
            }

            switch ( $mapped_type ) {
                case 'email':
                    $messages[ "{$field_name}.email" ] = $validation_messages['email'] ?? 'This field must contain a valid email';
                    break;
                case 'url':
                    $messages[ "{$field_name}.url" ] = $validation_messages['url'] ?? 'Please enter a valid URL';
                    break;
                case 'number':
                    $messages[ "{$field_name}.numeric" ] = $validation_messages['number'] ?? 'This field must contain numeric value';
                    if ( isset( $field['min_value'] ) && $field['min_value'] !== '' ) {
                        $min_msg                         = str_replace( '{limit}', $field['min_value'], $validation_messages['min'] ?? 'This value is below the minimum {limit}' );
                        $messages[ "{$field_name}.min" ] = $min_msg;
                    }
                    if ( isset( $field['max_value'] ) && $field['max_value'] !== '' ) {
                        $max_msg                         = str_replace( '{limit}', $field['max_value'], $validation_messages['max'] ?? 'This value exceeds the maximum {limit}' );
                        $messages[ "{$field_name}.max" ] = $max_msg;
                    }
                    break;
                case 'gdpr':
                    $gdpr_msg                             = $validation_messages['gdpr'] ?? 'You must agree to proceed';
                    $messages[ "{$field_name}.required" ] = $gdpr_msg;
                    $messages[ "{$field_name}.integer" ]  = $gdpr_msg;
                    $messages[ "{$field_name}.in" ]       = $gdpr_msg;
                    break;
                case 'rating':
                    $messages[ "{$field_name}.integer" ] = $validation_messages['number'] ?? 'This field must contain numeric value';
                    if ( ! empty( $field['rating_limit'] ) ) {
                        $max_msg                         = str_replace( '{limit}', $field['rating_limit'], $validation_messages['max'] ?? 'This value exceeds the maximum {limit}' );
                        $messages[ "{$field_name}.max" ] = $max_msg;
                    }
                    break;
            }
        }

        return $messages;
    }

    protected function prepare_request_for_validation( Request $request, array $form ): void {
        $form_object = (object) $form;
        $fields      = formgent_get_form_fields( $form_object );

        foreach ( $fields as $field ) {
            if ( empty( $field['name'] ) || empty( $field['field_type'] ) ) {
                continue;
            }

            $type        = $field['field_type'];
            $mapped_type = $this->map_field_type( $type );
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

            if ( $mapped_type === 'gdpr' ) {
                $request->set_param( $field_name, (int) $value );
            }
        }
    }

    protected function submit( Request $request, array $form ) {
        if ( empty( $form ) ) {
            return;
        }

        $form_id = (int) ( isset( $form['id'] ) ? $form['id'] : ( isset( $form['ID'] ) ? $form['ID'] : 0 ) );

        $response_dto = new \FormGent\App\DTO\ResponseDTO();
        $response_dto->set_status( \FormGent\App\EnumeratedList\ResponseStatus::PUBLISH )
            ->set_is_completed( 1 )
            ->set_form_id( $form_id );

        if ( 'no' === formgent_settings_repository()->get_by_key( 'disable_ip_logging', 'no' ) ) {
            $response_dto->set_ip( Helpers::get_user_ip_address() );
        }

        if ( is_user_logged_in() ) {
            $response_dto->set_created_by( wp_get_current_user()->ID );
        }

        $user_agent = $request->get_header( 'user-agent' );
        if ( $user_agent ) {
            $which_browser = new \FormGent\WhichBrowser\Parser( $user_agent );
            $browser       = $which_browser->browser;
            if ( $browser ) {
                $response_dto->set_browser( $browser->name );
                $response_dto->set_browser_version( $browser->version instanceof \FormGent\WhichBrowser\Model\Version ? $browser->version->value : null );
                $response_dto->set_device( $which_browser->os->name );
            }
        }

        $response_repository = formgent_response_repository();
        $response_id         = $response_repository->create( $response_dto );

        $form_object = (object) $form;
        $fields      = formgent_get_form_fields( $form_object );
        $field_dtos  = [];

        foreach ( $fields as $field ) {
            if ( empty( $field['name'] ) || empty( $field['field_type'] ) ) {
                continue;
            }

            $type        = $field['field_type'];
            $mapped_type = $this->map_field_type( $type );
            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['name'];
            $value      = $request->get_param( $field_name );

            if ( $value !== null ) {
                $answer_dto = new \FormGent\App\DTO\AnswerDTO();
                $answer_dto->set_form_id( $form_id )
                    ->set_field_type( $type )
                    ->set_field_name( $field_name )
                    ->set_value( $value );

                $field_dtos[] = $answer_dto;
            }
        }

        if ( ! empty( $field_dtos ) ) {
            $answer_repository = formgent_singleton( \FormGent\App\Repositories\AnswerRepository::class );
            $answer_repository->creates( $response_id, $field_dtos );
        }

        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- firing FormGent's own hook so its native post-submit behavior runs, not defining a hook of our own.
        do_action( "formgent_after_create_form_response", $response_id, $form_object, $request );
    }

    public function get_forms(): array {

        $posts = Post::select( "ID", "post_title" )->where( 'post_type', 'formgent_form' )
            ->where( 'post_status', 'publish' )
            ->get();

        $result = [];

        foreach ( $posts as $post ) {
            $result[] = ( new FormDTO() )->set_id( (int) $post->ID )
                ->set_title( $post->post_title )
                ->set_exclude_to_array( ['fields'] );
        }

        return $result;
    }

    protected function get_standardized_type( string $native_type ): ?string {
        $map = [
            'text'            => 'text',
            'number'          => 'number',
            'email'           => 'email',
            'website'         => 'url',
            'single-choice'   => 'radio',
            'multiple-choice' => 'checkbox',
            'dropdown'        => 'single_select',
            'range-slider'    => 'range',
            'rating'          => 'rating',
            'date-picker'     => 'date_time_picker',
            'gdpr'            => 'gdpr',
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

        if ( in_array( 'fields', $fields, true ) ) {
            $form_object = (object) $raw_form;
            $form_fields = formgent_get_form_fields( $form_object );

            if ( ! empty( $form_fields ) ) {
                $field_dtos = [];

                foreach ( $form_fields as $field ) {
                    $std_type = $this->get_standardized_type( $field['field_type'] ?? '' );

                    if ( ! $std_type ) {
                        continue;
                    }

                    $fdto = new FormFieldDTO();
                    $fdto->set_id( $field['name'] ?? '' )
                        ->set_type( $std_type )
                        ->set_required( $std_type === 'gdpr' ? true : ! empty( $field['required'] ) )
                        ->set_label( $field['label'] ?? $field['name'] ?? '' )
                        ->set_placeholder( $field['placeholder'] ?? '' )
                        ->set_field_name( $field['name'] ?? '' );

                    if ( ! empty( $field['options'] ) ) {
                        $items = [];

                        foreach ( $field['options'] as $key => $option ) {
                            $items[] = [
                                'id'    => (string) $key,
                                'label' => is_string( $option ) ? $option : ( $option['label'] ?? '' ),
                                'value' => is_string( $option ) ? $option : ( $option['value'] ?? '' ),
                            ];
                        }

                        $fdto->set_items( $items );
                    }

                    if ( $std_type === 'range' ) {
                        $fdto->set_min_value( isset( $field['min_value'] ) ? (float) $field['min_value'] : null )
                            ->set_max_value( isset( $field['max_value'] ) ? (float) $field['max_value'] : null )
                            ->set_range_step( isset( $field['step'] ) ? (float) $field['step'] : null );
                    }

                    if ( $std_type === 'rating' ) {
                        $fdto->set_rating_max( isset( $field['rating_limit'] ) ? (int) $field['rating_limit'] : 5 );
                    }

                    if ( $std_type === 'date_time_picker' ) {
                        $fdto->set_picker_type( $field['option'] ?? 'date' );
                    }

                    if ( $std_type === 'gdpr' ) {
                        $fdto->set_label( $field['description'] ?? $field['label'] ?? $field['name'] ?? '' );
                    }

                    if ( $std_type === 'text' && ! empty( $field['character_limit'] ) && ! empty( $field['limit'] ) ) {
                        $fdto->set_character_limit( (int) $field['limit'] );
                    }

                    $field_dtos[] = $fdto;
                }

                $dto->set_fields( $field_dtos );
            }
        }

        return $dto;
    }
}