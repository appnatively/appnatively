<?php

namespace Crafium\AppNatively\App\Integrations\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Forms\FormsDTO;
use Crafium\AppNatively\App\DTO\Forms\FormDTO;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\Helpers\Helpers;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

abstract class Form extends Provider {
    abstract public function get_key(): string;

    abstract protected function get_form( int $id );

    abstract public function get_forms(): array;

    abstract protected function get_standardized_type( string $native_type ): ?string;

    abstract protected function map_form_to_dto( array $raw_form, array $fields ): FormDTO;

    abstract protected function get_validation_rules( array $form ) : array;

    abstract protected function submit( Request $request, array $form );

    /**
     * Per-field custom validation messages, keyed "{field}.{rule}". Optional hook —
     * integrations that don't need custom copy can leave the default empty array.
     */
    protected function get_validation_messages( array $form ): array {
        return [];
    }

    /**
     * Optional hook to coerce/normalize request params (checkbox arrays, gdpr
     * ints, range/slider payloads, ...) before validation rules are applied.
     */
    protected function prepare_request_for_validation( Request $request, array $form ): void {}

    /**
     * Throttle submissions per (IP, integration, form) so the public submit
     * endpoint can't be flooded.
     *
     * The address comes from Helpers::get_user_ip_address(), which reads
     * HTTP_CLIENT_IP and X-Forwarded-For before falling back to REMOTE_ADDR —
     * both are headers the caller can set, so this is a best-effort bucket,
     * not a hard guarantee against rotating them for a fresh one.
     *
     * If no address can be determined at all, callers share one bucket rather
     * than skipping the check.
     */
    private function check_rate_limit( int $form_id ): void {
        $ip = Helpers::get_user_ip_address() ?? 'unknown';

        $max    = (int) apply_filters( 'craf_appna_form_rate_limit_max', 5 );
        $window = (int) apply_filters( 'craf_appna_form_rate_limit_window', MINUTE_IN_SECONDS );

        $key   = 'craf_appna_frl_' . md5( $ip . '|' . $this->get_key() . '|' . $form_id );
        $count = (int) get_transient( $key );

        if ( $count >= $max ) {
            throw new Exception( esc_html__( 'Too many submissions. Please wait a moment and try again.', 'appnatively' ), 429 );
        }

        set_transient( $key, $count + 1, $window );
    }

    public function boot(): void {
        add_filter( "craf_appna_form_{$this->get_key()}_submit", [$this, "form_submit"], 10, 1 );
        add_filter( "craf_appna_form_{$this->get_key()}_forms", [$this, "forms"] );
        add_filter( "craf_appna_form_{$this->get_key()}_form", [$this, "form"], 10, 2 );
    }

    public function form_submit( Request $request ) {
        $this->check_rate_limit( (int) $request->get_param( "form_id" ) );

        $form = $this->get_form( $request->get_param( "form_id" ) );
        if ( ! $form ) {
            throw new Exception( esc_html__( 'Form not found', 'appnatively' ) );
        }

        $this->prepare_request_for_validation( $request, $form );

        $validation = $request->make( $request, $this->get_validation_rules( $form ), $this->get_validation_messages( $form ) );
        $validation->throw_if_fails();
        $request->errors = $validation->errors();

        $this->submit( $request, $form );
    }

    public function forms() {
        return ( new FormsDTO() )->set_items( $this->get_forms() );
    }

    public function form( $value, Request $request ): ?FormDTO {
        $id       = (int) craf_appna_route_param( $request, "id" );
        $raw_form = $this->get_form( $id );

        if ( empty( $raw_form ) ) {
            return null;
        }

        $fields_param = $request->get_param( 'fields' );
        $fields       = $fields_param ? explode( ',', $fields_param ) : ['id', 'fields'];

        return $this->map_form_to_dto( $raw_form, $fields );
    }
}
