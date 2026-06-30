<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

use Crafium\AppNatively\App\DTO\Forms\FormsDTO;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Exception;

abstract class Form extends Provider {
    abstract public function get_key(): string;

    abstract protected function get_form( int $id );

    abstract public function get_forms(): array;

    abstract protected function get_standardized_type( string $native_type ): ?string;

    abstract protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO;

    abstract protected function get_validation_rules( array $form ) : array;

    abstract protected function submit( Request $request, array $form );

    public function boot(): void {
        add_filter( "craf_appna_form_{$this->get_key()}_submit", [$this, "form_submit"], 10, 1 );
        add_filter( "craf_appna_form_{$this->get_key()}_forms", [$this, "forms"]);
        add_filter( "craf_appna_form_{$this->get_key()}_form", [$this, "form"], 10, 2 );
    }

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( "form_id" ) );
        if ( ! $form ) {
            throw new Exception( __( 'Form not found', 'appnatively' ) );
        }

        $request->validate( $this->get_validation_rules( $form ) );

        $this->submit( $request, $form );
    }

    public function forms() {
        return (new FormsDTO())->set_items( $this->get_forms() );
    }

    public function form( $value, Request $request ): ?FormDTO {
        $id       = (int) $request->get_param( "id" );
        $raw_form = $this->get_form( $id );

        if ( empty( $raw_form ) ) {
            return null;
        }

        $fields_param = $request->get_param( 'fields' );
        $fields       = $fields_param ? explode( ',', $fields_param ) : ['id', 'fields'];

        return $this->map_form_to_dto( $raw_form, $fields );
    }
}
