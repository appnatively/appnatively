<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\App\DTO\Forms\FormFieldDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class NinjaForms extends Form {
    public function get_key(): string {
        return 'ninjaforms';
    }

    public function boot(): void {
        if ( ! function_exists( 'Ninja_Forms' ) ) {
            return;
        }
        parent::boot();
    }

    protected function get_form( int $id ) {
        $form = Ninja_Forms()->form( $id )->get();

        if ( ! $form || ! $form->get_id() ) {
            return [];
        }

        return [
            'id'    => $form->get_id(),
            'title' => $form->get_setting( 'title' ),
        ];
    }

    public function get_forms(): array {
        $forms = Ninja_Forms()->form()->get_forms();

        if ( empty( $forms ) ) {
            return [];
        }

        $result = [];

        foreach ( $forms as $form ) {
            $result[] = ( new FormDTO() )->set_id( $form->get_id() )
                ->set_title( $form->get_setting( 'title' ) )
                ->set_exclude_to_array( ['fields'] );
        }

        return $result;
    }

    /**
     * @return \NF_Database_Models_Field[] field id => field model
     */
    private function get_fields( array $form ): array {
        if ( empty( $form['id'] ) ) {
            return [];
        }

        return Ninja_Forms()->form( $form['id'] )->get_fields();
    }

    protected function get_standardized_type( string $native_type ): ?string {
        $map = [
            'textbox'         => 'text',
            'textarea'        => 'text',
            'phone'           => 'text',
            'color'           => 'text',
            'email'           => 'email',
            'number'          => 'number',
            'date'            => 'date_time_picker',
            'password'        => 'password',
            'listradio'       => 'radio',
            'listcheckbox'    => 'checkbox',
            'listselect'      => 'single_select',
            'listmultiselect' => 'single_select',
            'starrating'      => 'rating',
        ];

        return $map[$native_type] ?? null;
    }

    private function get_type_rules( string $std_type, $field ): array {
        switch ( $std_type ) {
            case 'text':
                return ['string'];
            case 'number':
                $rules = ['numeric'];
                $min   = $field->get_setting( 'num_min' );
                $max   = $field->get_setting( 'num_max' );
                if ( is_numeric( $min ) ) {
                    $rules[] = 'min:' . (float) $min;
                }
                if ( is_numeric( $max ) ) {
                    $rules[] = 'max:' . (float) $max;
                }
                return $rules;
            case 'email':
                return ['string', 'email'];
            case 'password':
                return ['string'];
            case 'radio':
            case 'single_select':
                return ['string', 'max:255'];
            case 'checkbox':
                return ['array'];
            case 'date_time_picker':
                return ['string'];
            case 'rating':
                $rules     = ['integer'];
                $max_stars = $field->get_setting( 'number_of_stars' );
                if ( is_numeric( $max_stars ) && $max_stars > 0 ) {
                    $rules[] = 'max:' . absint( $max_stars );
                }
                return $rules;
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

        foreach ( $fields as $field_id => $field ) {
            $native_type = (string) $field->get_setting( 'type' );
            $std_type    = $this->get_standardized_type( $native_type );
            if ( ! $std_type ) {
                continue;
            }

            $field_rules = $this->get_type_rules( $std_type, $field );

            if ( $field->get_setting( 'required' ) ) {
                $field_rules[] = 'required';
            }

            if ( ! empty( $field_rules ) ) {
                $rules[(string) $field_id] = implode( '|', array_unique( $field_rules ) );
            }
        }

        return $rules;
    }

    protected function submit( Request $request, array $form ) {
        if ( empty( $form['id'] ) ) {
            return;
        }

        $sub = Ninja_Forms()->form( $form['id'] )->sub()->get();

        foreach ( $this->get_fields( $form ) as $field_id => $field ) {
            $native_type = (string) $field->get_setting( 'type' );
            if ( ! $this->get_standardized_type( $native_type ) ) {
                continue;
            }

            $value = $request->get_param( (string) $field_id );
            if ( null !== $value ) {
                $sub->update_field_value( $field_id, $value );
            }
        }

        // Mirrors NF_Actions_Save::process() so notification add-ons hooked to these events still fire.
        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- firing Ninja Forms' own hooks, not defining hooks of our own.
        do_action( 'nf_before_save_sub', $sub->get_id() );

        $sub->save();

        $sub_id = $sub->get_id();
        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- firing Ninja Forms' own hooks, not defining hooks of our own.
        do_action( 'nf_save_sub', $sub_id );
        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action( 'nf_create_sub', $sub_id );
        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action( 'ninja_forms_save_sub', $sub_id );
    }

    private function get_field_options( $field ): array {
        $options = $field->get_setting( 'options' );
        if ( ! is_array( $options ) ) {
            return [];
        }

        $items = [];

        foreach ( $options as $key => $option ) {
            $option  = (array) $option;
            $items[] = [
                'id'    => isset( $option['value'] ) ? (string) $option['value'] : (string) $key,
                'label' => $option['label'] ?? '',
                'value' => $option['value'] ?? ( $option['label'] ?? '' ),
            ];
        }

        return $items;
    }

    protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO {
        $dto = new FormDTO();

        if ( in_array( 'id', $fields, true ) ) {
            $dto->set_id( (int) ( $raw_form['id'] ?? 0 ) );
        }

        if ( in_array( 'title', $fields, true ) ) {
            $dto->set_title( $raw_form['title'] ?? '' );
        }

        if ( in_array( 'fields', $fields, true ) ) {
            $field_dtos = [];

            foreach ( $this->get_fields( $raw_form ) as $field_id => $field ) {
                $native_type = (string) $field->get_setting( 'type' );
                $std_type    = $this->get_standardized_type( $native_type );

                if ( ! $std_type ) {
                    continue;
                }

                $fdto = new FormFieldDTO();
                $fdto->set_id( (string) $field_id )
                    ->set_type( $std_type )
                    ->set_required( (bool) $field->get_setting( 'required' ) )
                    ->set_label( (string) $field->get_setting( 'label' ) )
                    ->set_placeholder( (string) $field->get_setting( 'placeholder' ) )
                    ->set_field_name( (string) $field_id );

                if ( in_array( $std_type, ['radio', 'checkbox', 'single_select'], true ) ) {
                    $items = $this->get_field_options( $field );
                    if ( $items ) {
                        $fdto->set_items( $items );
                    }
                }

                if ( 'rating' === $std_type ) {
                    $stars = $field->get_setting( 'number_of_stars' );
                    $fdto->set_rating_max( is_numeric( $stars ) && $stars > 0 ? (int) $stars : 5 );
                }

                if ( 'date_time_picker' === $std_type ) {
                    $fdto->set_picker_type( 'date' );
                }

                $field_dtos[] = $fdto;
            }

            $dto->set_fields( $field_dtos );
        }

        return $dto;
    }
}
