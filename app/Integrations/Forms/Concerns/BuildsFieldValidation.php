<?php

namespace Crafium\AppNatively\App\Integrations\Forms\Concerns;

defined( "ABSPATH" ) || exit;
trait BuildsFieldValidation
{
    private function get_email_rules( array $field ): array {
        return [
            'string',
            'email'
        ];
    }

    private function get_url_rules( array $field ): array {
        return [
            'string',
            'url'
        ];
    }

    private function get_number_rules( array $field ): array {
        $rules = ['numeric'];
        if ( isset( $field['min'] ) && $field['min'] !== '' ) {
            $rules[] = 'min:' . floatval( $field['min'] );
        }
        if ( isset( $field['max'] ) && $field['max'] !== '' ) {
            $rules[] = 'max:' . floatval( $field['max'] );
        }
        return $rules;
    }

    private function get_single_select_rules( array $field ): array {
        return ['string'];
    }

    private function get_date_time_picker_rules( array $field ): array {
        return ['string'];
    }

    private function get_checkbox_rules( array $field ): array {
        return ['array'];
    }

    private function get_radio_rules( array $field ): array {
        return ['string'];
    }

    private function get_gdpr_rules( array $field ): array {
        // GDPR consent must always be affirmatively given, regardless of whether the
        // plugin author happened to mark the field "required" in the form builder.
        return [
            'integer',
            'in:1',
            'required'
        ];
    }

    private function get_password_rules( array $field ): array {
        return ['string'];
    }
}
