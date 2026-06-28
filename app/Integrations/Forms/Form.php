<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Exception;

abstract class Form extends Provider {
    abstract public function get_key(): string;

    abstract protected function get_form( int $id );

    abstract protected function get_validation_rules( array $form ) : array;

    abstract protected function submit( Request $request, array $form );

    public function boot(): void {
        add_filter( "craf_appna_form_{$this->get_key()}_submit", [$this, "form_submit"], 10, 1 );
    }

    public function form_submit( Request $request ) {
        $form = $this->get_form( $request->get_param( "form_id" ) );
        if ( ! $form ) {
            throw new Exception( __( 'Form not found', 'appnatively' ) );
        }

        $request->validate( $this->get_validation_rules( $form ) );

        $this->submit( $request, $form );
    }
}
