<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class EverestForms extends Form {
    public function get_key(): string {
        return 'everest-forms';
    }

    public function boot(): void {
        if ( ! function_exists( 'evf' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        if ( 'publish' !== get_post_status( $id ) ) {
            return [];
        }

        $form_data = evf()->form->get( $id, [ 'content_only' => true ] );

        if ( ! $form_data || empty( $form_data['form_fields'] ) ) {
            return [];
        }

        $fields = [];

        foreach ( $form_data['form_fields'] as $field_id => $field ) {
            $fields[] = [
                'id'                             => $field_id,
                'type'                           => $field['type'] ?? '',
                'name'                           => $field['label'] ?? '',
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- DTO array key, not a WP_Query arg.
                'meta_key'                       => $field['meta-key'] ?? '',
                'required'                       => ! empty( $field['required'] ),
                'min_value'                      => $field['min_value'] ?? '',
                'max_value'                      => $field['max_value'] ?? '',
                'number_of_stars'                => $field['number_of_stars'] ?? 5,
                'datetime_format'                => $field['datetime_format'] ?? 'date',
                'date_format'                    => $field['date_format'] ?? 'Y-m-d',
                'time_format'                    => $field['time_format'] ?? 'g:i A',
                'required_field_message_setting' => $field['required_field_message_setting'] ?? 'global',
                'required_field_message'         => $field['required-field-message'] ?? '',
                'options'                        => isset( $field['choices'] ) && is_array( $field['choices'] )
                    ? $this->normalize_choices( $field['choices'], ! empty( $field['show_values'] ) )
                    : [],
            ];
        }

        return [
            'id'     => $id,
            'name'   => $form_data['settings']['form_title'] ?? '',
            'fields' => $fields,
        ];
    }

    private function normalize_choices( array $choices, bool $show_values ): array {
        $options = [];

        foreach ( $choices as $key => $choice ) {
            if ( ! is_array( $choice ) ) {
                continue;
            }

            $label = $choice['label'] ?? '';
            $value = $show_values ? ( $choice['value'] ?? '' ) : $label;

            $options[ $key ] = [
                'label' => $label,
                'value' => $value !== '' ? $value : $label,
            ];
        }

        return $options;
    }

    private function php_to_date_fns_format( string $format ): string {
        $map = [
            'Y' => 'YYYY',
            'y' => 'YY',
            'm' => 'MM',
            'n' => 'M',
            'M' => 'MMM',
            'F' => 'MMMM',
            'd' => 'DD',
            'j' => 'd',
            'D' => 'EEE',
            'l' => 'EEEE',
            'H' => 'HH',
            'G' => 'H',
            'h' => 'hh',
            'g' => 'h',
            'i' => 'mm',
            's' => 'ss',
            'A' => 'A',
            'a' => 'A',
        ];

        $out = '';
        for ( $k = 0, $len = strlen( $format ); $k < $len; $k++ ) {
            $out .= $map[ $format[ $k ] ] ?? $format[ $k ];
        }

        return $out;
    }

    private function map_field_type( string $type ) {
        return $this->get_standardized_type( $type );
    }

    private function get_text_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_email_rules( array $field ): array {
        return [ 'string', 'email' ];
    }

    private function get_url_rules( array $field ): array {
        return [ 'string', 'url' ];
    }

    private function get_number_rules( array $field ): array {
        $rules = [ 'numeric' ];

        if ( isset( $field['min_value'] ) && $field['min_value'] !== '' ) {
            $rules[] = 'min:' . floatval( $field['min_value'] );
        }

        if ( isset( $field['max_value'] ) && $field['max_value'] !== '' ) {
            $rules[] = 'max:' . floatval( $field['max_value'] );
        }

        return $rules;
    }

    private function get_radio_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_checkbox_rules( array $field ): array {
        return [ 'array' ];
    }

    private function get_single_select_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_date_time_picker_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_password_rules( array $field ): array {
        return [ 'string' ];
    }

    private function get_rating_rules( array $field ): array {
        $rules = [ 'integer' ];

        if ( ! empty( $field['number_of_stars'] ) ) {
            $rules[] = 'max:' . absint( $field['number_of_stars'] );
        }

        return $rules;
    }

    protected function get_validation_rules( array $form ): array {
        if ( empty( $form['fields'] ) ) {
            return [];
        }

        $rules = [];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name  = $field['id'];
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
                case 'date_time_picker':
                    $field_rules = $this->get_date_time_picker_rules( $field );
                    break;
                case 'rating':
                    $field_rules = $this->get_rating_rules( $field );
                    break;
                case 'password':
                    $field_rules = $this->get_password_rules( $field );
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

        $global_required = get_option( 'everest_forms_required_validation', 'This field is required.' );

        $default_format = [
            'email'  => get_option( 'everest_forms_email_validation', 'Please enter a valid email address.' ),
            'url'    => get_option( 'everest_forms_url_validation', 'Please enter a valid URL.' ),
            'number' => get_option( 'everest_forms_number_validation', 'Please enter a valid number.' ),
        ];

        $messages = [];

        foreach ( $form['fields'] as $field ) {
            $name   = $field['id'];
            $type   = $field['type'];
            $mapped = $this->map_field_type( $type );

            if ( ! $mapped || ! $name ) {
                continue;
            }

            if ( ! empty( $field['required'] ) ) {
                if ( isset( $field['required_field_message_setting'] ) && $field['required_field_message_setting'] === 'individual' && ! empty( $field['required_field_message'] ) ) {
                    $messages[ "{$name}.required" ] = $field['required_field_message'];
                } else {
                    $messages[ "{$name}.required" ] = $global_required;
                }
            }

            if ( $mapped === 'email' ) {
                $messages[ "{$name}.email" ] = $default_format['email'];
            }

            if ( $mapped === 'url' ) {
                $messages[ "{$name}.url" ] = $default_format['url'];
            }

            if ( $mapped === 'number' ) {
                $messages[ "{$name}.numeric" ] = $default_format['number'];

                if ( isset( $field['min_value'] ) && $field['min_value'] !== '' ) {
                    $messages[ "{$name}.min" ] = 'Please enter a value greater than or equal to :min.';
                }

                if ( isset( $field['max_value'] ) && $field['max_value'] !== '' ) {
                    $messages[ "{$name}.max" ] = 'Please enter a value less than or equal to :max.';
                }
            }

            if ( $mapped === 'rating' ) {
                $messages[ "{$name}.integer" ] = $default_format['number'];

                if ( ! empty( $field['number_of_stars'] ) ) {
                    $messages[ "{$name}.max" ] = 'Please select a value up to :max.';
                }
            }
        }

        return $messages;
    }

    protected function prepare_request_for_validation( Request $request, array $form ): void {
        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_name = $field['id'];
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

        $entry_fields = [];
        $form_id      = (int) $form['id'];

        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['type'] ) || empty( $field['id'] ) ) {
                continue;
            }

            $mapped_type = $this->map_field_type( $field['type'] );

            if ( ! $mapped_type ) {
                continue;
            }

            $field_id = $field['id'];
            $value    = $request->get_param( $field_id );

            if ( $value === null ) {
                continue;
            }

            $formatted_value = $value;

            if ( $field['type'] === 'rating' && is_numeric( $value ) ) {
                $formatted_value = [
                    'value'            => (int) $value,
                    'type'             => 'rating',
                    'number_of_rating' => (int) ( $field['number_of_stars'] ?? 5 ),
                    'icon'             => 'star',
                ];
            }

            $entry_fields[ $field_id ] = [
                'id'       => $field_id,
                'name'     => $field['name'],
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- DTO array key, not a WP_Query arg.
                'meta_key' => $field['meta_key'],
                'type'     => $field['type'],
                'value'    => $formatted_value,
            ];
        }

        if ( empty( $entry_fields ) ) {
            return;
        }

        $form_data = evf()->form->get( $form_id, [ 'content_only' => true ] );

        if ( ! $form_data ) {
            return;
        }

        $entry = [
            'form_id' => $form_id,
            'user_id' => get_current_user_id(),
            'status'  => 'publish',
        ];

        $entry_id = evf()->task->entry_save( $entry_fields, $entry, $form_id, $form_data );

        if ( $entry_id && ! is_wp_error( $entry_id ) ) {
            evf()->task->entry_email( $entry_fields, $entry, $form_data, $entry_id );
        }
    }

    public function get_forms(): array {
        // Everest Forms uses 'everest_form' as its custom post type
        $posts = Post::select( "ID", "post_title" )
            ->where( 'post_type', 'everest_form' )
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
            'text'      => 'text',
            'email'     => 'email',
            'url'       => 'url',
            'number'    => 'number',
            'radio'     => 'radio',
            'checkbox'  => 'checkbox',
            'select'    => 'single_select',
            'date-time' => 'date_time_picker',
            'rating'    => 'rating',
            'textarea'  => 'text',
            'password'  => 'password',
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
                $std_type = $this->get_standardized_type( $field['type'] ?? '' );

                if ( ! $std_type ) {
                    continue;
                }

                $fdto = new FormFieldDTO();
                $fdto->set_id( (string) $field['id'] )
                    ->set_type( $std_type )
                    ->set_required( ! empty( $field['required'] ) )
                    ->set_label( $field['name'] ?? '' )
                    ->set_field_name( (string) $field['id'] );

                if ( ! empty( $field['options'] ) ) {
                    $items = [];

                    foreach ( $field['options'] as $key => $option ) {
                        $items[] = [
                            'id'          => (string) $key,
                            'optionLabel' => is_string( $option ) ? $option : ( $option['label'] ?? '' ),
                            'optionValue' => is_string( $option ) ? $option : ( $option['value'] ?? '' ),
                        ];
                    }

                    $fdto->set_items( $items );
                }

                if ( $std_type === 'number' ) {
                    $fdto->set_min_value( isset( $field['min_value'] ) && $field['min_value'] !== '' ? (float) $field['min_value'] : null )
                        ->set_max_value( isset( $field['max_value'] ) && $field['max_value'] !== '' ? (float) $field['max_value'] : null );
                }

                if ( $std_type === 'rating' ) {
                    $fdto->set_rating_max( isset( $field['number_of_stars'] ) ? (int) $field['number_of_stars'] : 5 );
                }

                if ( $std_type === 'date_time_picker' ) {
                    $datetime_format = $field['datetime_format'] ?? 'date';
                    $date_fns        = $this->php_to_date_fns_format( $field['date_format'] ?? 'Y-m-d' );
                    $time_fns        = $this->php_to_date_fns_format( $field['time_format'] ?? 'g:i A' );

                    if ( 'time' === $datetime_format ) {
                        $picker_type  = 'time';
                        $final_format = $time_fns ?: 'HH:mm';
                    } elseif ( 'date-time' === $datetime_format ) {
                        $picker_type  = 'both';
                        $final_format = trim( $date_fns . ' ' . $time_fns );
                    } else {
                        $picker_type  = 'date';
                        $final_format = $date_fns ?: 'YYYY-MM-DD';
                    }

                    $fdto->set_picker_type( $picker_type )->set_date_format( $final_format );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
