<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use BitCode\BitForm\Core\Database\FormModel;
use BitCode\BitForm\Core\Form\FormManager;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

class BitForm extends Form {
    public function get_key(): string {
        return 'bitform';
    }

    public function boot(): void {
        if ( ! defined( 'BITFORMS_VERSION' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $rows = ( new FormModel() )->get( ['id', 'form_name', 'form_content', 'status'], ['id' => $id] );

        if ( is_wp_error( $rows ) || empty( $rows ) ) {
            return [];
        }

        $row = $rows[0];

        if ( '1' !== (string) $row->status ) {
            return [];
        }

        return (array) $row;
    }

    public function get_forms(): array {
        $rows = ( new FormModel() )->get( ['id', 'form_name'], ['status' => 1] );

        if ( is_wp_error( $rows ) || empty( $rows ) ) {
            return [];
        }

        $result = [];

        foreach ( $rows as $row ) {
            $result[] = ( new FormDTO() )->set_id( (int) $row->id )
                ->set_title( $row->form_name )
                ->set_exclude_to_array( ['fields'] );
        }

        return $result;
    }

    /**
     * Reads a (possibly missing/nested-null) property off a decoded field object
     * without emitting "attempt to read property on null" warnings.
     */
    private function field_prop( $field, string $path, $default = null ) {
        $value = $field;

        foreach ( explode( '.', $path ) as $part ) {
            if ( is_object( $value ) && isset( $value->$part ) ) {
                $value = $value->$part;
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Bit Form stores its schema as one JSON blob (form_content) keyed by field key
     * (e.g. "fld_xxx"). That key is also what FormManager::saveFormEntry() expects as
     * the submitted_data array key, so it doubles as our public field_name/param name.
     *
     * @return object[] field key => decoded field object
     */
    private function get_fields( array $form ): array {
        $content = $form['form_content'] ?? '';
        if ( ! is_string( $content ) || '' === $content ) {
            return [];
        }

        $decoded = json_decode( $content );
        if ( ! $decoded || empty( $decoded->fields ) ) {
            return [];
        }

        return (array) $decoded->fields;
    }

    protected function get_standardized_type( string $native_type ): ?string {
        $map = [
            'text'           => 'text',
            'username'       => 'text',
            'color'          => 'text',
            'textarea'       => 'text',
            'number'         => 'number',
            'email'          => 'email',
            'url'            => 'url',
            'password'       => 'password',
            'radio'          => 'radio',
            'check'          => 'checkbox',
            'select'         => 'single_select',
            'html-select'    => 'single_select',
            'date'           => 'date_time_picker',
            'datetime-local' => 'date_time_picker',
            'time'           => 'date_time_picker',
            'month'          => 'date_time_picker',
            'week'           => 'date_time_picker',
            'range'          => 'range',
            'rating'         => 'rating',
            'gdpr'           => 'gdpr',
        ];

        return $map[$native_type] ?? null;
    }

    private function get_type_rules( string $std_type, $field ): array {
        switch ( $std_type ) {
            case 'text':
                $rules     = ['string'];
                $maxlength = $this->field_prop( $field, 'valid.maxlength' );
                if ( is_numeric( $maxlength ) ) {
                    $rules[] = 'max:' . absint( $maxlength );
                }
                $minlength = $this->field_prop( $field, 'valid.minlength' );
                if ( is_numeric( $minlength ) ) {
                    $rules[] = 'min:' . absint( $minlength );
                }
                return $rules;
            case 'number':
            case 'range':
                $rules = ['numeric'];
                if ( isset( $field->mn ) && is_numeric( $field->mn ) ) {
                    $rules[] = 'min:' . (float) $field->mn;
                }
                if ( isset( $field->mx ) && is_numeric( $field->mx ) ) {
                    $rules[] = 'max:' . (float) $field->mx;
                }
                return $rules;
            case 'email':
                return ['string', 'email'];
            case 'url':
                return ['string', 'url'];
            case 'password':
                $rules     = ['string'];
                $minlength = $this->field_prop( $field, 'valid.minlength' );
                if ( is_numeric( $minlength ) ) {
                    $rules[] = 'min:' . absint( $minlength );
                }
                return $rules;
            case 'radio':
            case 'single_select':
                return ['string', 'max:255'];
            case 'checkbox':
                return ['array'];
            case 'date_time_picker':
                return ['string'];
            case 'rating':
                $rules = ['integer'];
                if ( ! empty( $field->opt ) ) {
                    $rules[] = 'max:' . count( (array) $field->opt );
                }
                return $rules;
            case 'gdpr':
                return ['integer', 'in:1'];
            default:
                return [];
        }
    }

    protected function get_validation_rules( array $form ): array {
        $fields = $this->get_fields( $form );
        if ( empty( $fields ) ) {
            return [];
        }

        $rules = [];

        foreach ( $fields as $key => $field ) {
            $native_type = $field->typ ?? '';
            $std_type    = $this->get_standardized_type( $native_type );
            if ( ! $std_type ) {
                continue;
            }

            $field_rules = $this->get_type_rules( $std_type, $field );

            if ( 'gdpr' === $std_type || $this->field_prop( $field, 'valid.req' ) ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[(string) $key] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    protected function prepare_request_for_validation( Request $request, array $form ): void {
        // The "in:1" rule needs a real int; clients may send it as bool/"1"/"true".
        foreach ( $this->get_fields( $form ) as $key => $field ) {
            if ( 'gdpr' === ( $field->typ ?? '' ) ) {
                $value = $request->get_param( (string) $key );
                if ( null !== $value ) {
                    $request->set_param( (string) $key, (int) $value );
                }
            }
        }
    }

    protected function submit( Request $request, array $form ) {
        if ( empty( $form['id'] ) ) {
            return;
        }

        $submitted_data = [];

        foreach ( $this->get_fields( $form ) as $key => $field ) {
            if ( ! $this->get_standardized_type( $field->typ ?? '' ) ) {
                continue;
            }

            $value = $request->get_param( (string) $key );
            if ( null !== $value ) {
                $submitted_data[(string) $key] = $value;
            }
        }

        $result = FormManager::getInstance( (int) $form['id'] )->saveFormEntry( $submitted_data );

        if ( is_wp_error( $result ) ) {
            throw new Exception( esc_html( $result->get_error_message() ) );
        }
    }

    private function get_field_options( $field, string $native_type ): array {
        $items = [];

        if ( in_array( $native_type, ['radio', 'check'], true ) && ! empty( $field->opt ) ) {
            foreach ( (array) $field->opt as $key => $opt ) {
                $items[] = [
                    'id'    => (string) $key,
                    'label' => $opt->lbl ?? '',
                    'value' => $opt->val ?? ( $opt->lbl ?? '' ),
                ];
            }
            // optionsList is Bit Form's own property name, so it stays as-is.
            //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
        } elseif ( in_array( $native_type, ['select', 'html-select'], true ) && ! empty( $field->optionsList ) ) {
            //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
            $first_wrapper = reset( $field->optionsList );
            if ( $first_wrapper ) {
                $inner   = array_values( (array) $first_wrapper );
                $options = $inner[0] ?? [];

                foreach ( (array) $options as $key => $opt ) {
                    $items[] = [
                        'id'    => (string) $key,
                        'label' => $opt->lbl ?? '',
                        'value' => $opt->val ?? ( $opt->lbl ?? '' ),
                    ];
                }
            }
        }

        return $items;
    }

    private function get_date_picker_type( string $native_type ): string {
        if ( 'time' === $native_type ) {
            return 'time';
        }
        if ( 'datetime-local' === $native_type ) {
            return 'both';
        }
        return 'date';
    }

    protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO {
        $dto = new FormDTO();

        if ( in_array( 'id', $fields, true ) ) {
            $dto->set_id( (int) ( $raw_form['id'] ?? 0 ) );
        }

        if ( in_array( 'title', $fields, true ) ) {
            $dto->set_title( $raw_form['form_name'] ?? '' );
        }

        if ( in_array( 'fields', $fields, true ) ) {
            $field_dtos = [];

            foreach ( $this->get_fields( $raw_form ) as $key => $field ) {
                $native_type = $field->typ ?? '';
                $std_type    = $this->get_standardized_type( $native_type );

                if ( ! $std_type ) {
                    continue;
                }

                $fdto = new FormFieldDTO();
                $fdto->set_id( (string) $key )
                    ->set_type( $std_type )
                    ->set_required( 'gdpr' === $std_type ? true : (bool) $this->field_prop( $field, 'valid.req', false ) )
                    ->set_label( $field->lbl ?? '' )
                    ->set_placeholder( $field->ph ?? '' )
                    ->set_field_name( (string) $key );

                if ( in_array( $std_type, ['radio', 'checkbox', 'single_select'], true ) ) {
                    $items = $this->get_field_options( $field, $native_type );
                    if ( $items ) {
                        $fdto->set_items( $items );
                    }
                }

                if ( 'range' === $std_type ) {
                    $fdto->set_min_value( isset( $field->mn ) && is_numeric( $field->mn ) ? (float) $field->mn : null )
                        ->set_max_value( isset( $field->mx ) && is_numeric( $field->mx ) ? (float) $field->mx : null )
                        ->set_range_step( isset( $field->step ) && is_numeric( $field->step ) ? (float) $field->step : null );
                }

                if ( 'rating' === $std_type ) {
                    $fdto->set_rating_max( ! empty( $field->opt ) ? count( (array) $field->opt ) : 5 );
                }

                if ( 'date_time_picker' === $std_type ) {
                    $fdto->set_picker_type( $this->get_date_picker_type( $native_type ) );
                }

                if ( 'text' === $std_type ) {
                    $maxlength = $this->field_prop( $field, 'valid.maxlength' );
                    if ( is_numeric( $maxlength ) ) {
                        $fdto->set_character_limit( (int) $maxlength );
                    }
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
