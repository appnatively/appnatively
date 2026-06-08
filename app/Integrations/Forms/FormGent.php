<?php

namespace AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use AppNatively\WpMVC\Helpers\Helpers;
use AppNatively\WpMVC\RequestValidator\Request;

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
        $map = [
            'text'          => 'text',
            'number'        => 'number',
            'email'         => 'email',
            'url'           => 'website',
            'radio'         => 'single-choice',
            'checkbox'      => 'multiple-choice',
            'single_select' => 'dropdown',
            'range'         => 'range-slider',
            'rating'        => 'rating',
            'switch'        => 'gdpr',
            'password'      => 'password',
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

    private function get_password_rules( array $field ): array {
        $rules = [ 'string' ];
        if ( ! empty( $field['character_limit'] ) && ! empty( $field['limit'] ) ) {
            $rules[] = 'max:' . absint( $field['limit'] );
        }
        if ( ! empty( $field['limit_min'] ) && ! empty( $field['min'] ) ) {
            $rules[] = 'min:' . absint( $field['min'] );
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

    private function get_switch_rules( array $field ): array {
        return [ 'integer', 'in:0,1' ];
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
                case 'switch':
                    $field_rules = $this->get_switch_rules( $field );
                    break;
                case 'password':
                    $field_rules = $this->get_password_rules( $field );
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

    protected function submit( Request $request, array $form ) {
        if ( empty( $form ) ) {
            return;
        }

        $form_id = (int) ( isset( $form['id'] ) ? $form['id'] : ( isset( $form['ID'] ) ? $form['ID'] : 0 ) );

        // Create ResponseDTO
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

        // Parse form fields and construct AnswerDTO instances
        $form_object = (object) $form;
        $fields      = formgent_get_form_fields( $form_object );
        $field_dtos  = [];

        foreach ( $fields as $field ) {
            if ( empty( $field['name'] ) || empty( $field['field_type'] ) ) {
                continue;
            }

            $type = $field['field_type'];
            if ( ! $this->map_field_type( $type ) ) {
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

        // Trigger FormGent submission hooks so email notifications and integrations run
        do_action( "formgent_after_create_form_response", $response_id, $form_object, $request );
    }
}