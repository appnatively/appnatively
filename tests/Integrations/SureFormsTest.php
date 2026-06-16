<?php

namespace AppNatively\Tests\Integrations;

use AppNatively\App\Integrations\Forms\SureForms;
use AppNatively\WpMVC\RequestValidator\Request;

class TestableSureForms extends SureForms {
    public function expose_get_validation_rules( array $form ) {
        return $this->get_validation_rules( $form );
    }

    public function expose_submit( Request $request, array $form ) {
        return $this->submit( $request, $form );
    }

    public function expose_get_form( int $id ) {
        return $this->get_form( $id );
    }
}

class SureFormsTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        $blocks = [
            $this->make_block( 'srfm/input', [
                'block_id' => 'blk_001',
                'label'    => 'Single Line Text',
                'slug'     => 'input',
                'required' => false,
                'textLength' => '',
                'placeholder' => '',
                'defaultValue' => '',
            ] ),
            $this->make_block( 'srfm/email', [
                'block_id' => 'blk_002',
                'label'    => 'Email',
                'slug'     => 'email',
                'required' => true,
                'placeholder' => '',
                'defaultValue' => '',
            ] ),
            $this->make_block( 'srfm/number', [
                'block_id'    => 'blk_003',
                'label'       => 'Numbers',
                'slug'        => 'number',
                'required'    => false,
                'minValue'    => '',
                'maxValue'    => '',
                'placeholder' => '',
                'defaultValue' => '',
            ] ),
            $this->make_block( 'srfm/url', [
                'block_id' => 'blk_004',
                'label'    => 'Website',
                'slug'     => 'url',
                'required' => true,
                'placeholder' => '',
                'defaultValue' => '',
            ] ),
            $this->make_block( 'srfm/checkbox', [
                'block_id' => 'blk_005',
                'label'    => 'Checkboxes',
                'slug'     => 'checkbox',
                'required' => false,
            ] ),
            $this->make_block( 'srfm/dropdown', [
                'block_id' => 'blk_006',
                'label'    => 'Dropdown',
                'slug'     => 'dropdown',
                'required' => false,
                'options'  => [
                    [ 'label' => 'First Choice', 'value' => '' ],
                    [ 'label' => 'Second Choice', 'value' => '' ],
                    [ 'label' => 'Third Choice', 'value' => '' ],
                ],
            ] ),
        ];

        $post_content = '';
        foreach ( $blocks as $block ) {
            $post_content .= $block . "\n";
        }

        $this->form_id = wp_insert_post( [
            'post_title'   => 'Test SureForms Form',
            'post_type'    => 'sureforms_form',
            'post_status'  => 'publish',
            'post_content' => $post_content,
        ] );

        update_post_meta( $this->form_id, '_srfm_submit_button_text', 'Submit' );
        update_post_meta( $this->form_id, '_srfm_confirmation_type', 'message' );
        update_post_meta( $this->form_id, '_srfm_confirmation_message', 'Thanks for contacting us!' );
    }

    private function make_block( string $name, array $attrs ): string {
        $json = json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        return '<!-- wp:' . $name . ' ' . $json . ' /-->';
    }

    public function tearDown(): void {
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableSureForms {
        return new TestableSureForms( \AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $sureforms = new SureForms( \AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'sureforms', $sureforms->get_key() );
    }

    public function test_get_form() {
        $wpforms = $this->get_integration_instance();
        $form    = $wpforms->expose_get_form( $this->form_id );

        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
        $this->assertNotEmpty( $form['fields'] );
        $this->assertCount( 6, $form['fields'] );
    }

    public function test_get_validation_rules() {
        $wpforms = $this->get_integration_instance();
        $form    = $wpforms->expose_get_form( $this->form_id );
        $rules   = $wpforms->expose_get_validation_rules( $form );

        $expected_fields = [
            'input',
            'email',
            'number',
            'url',
            'checkbox',
            'dropdown',
        ];

        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules );
        }

        $this->assertEquals( 'string', $rules['input'] );
        $this->assertEquals( 'string|email|required', $rules['email'] );
        $this->assertEquals( 'numeric', $rules['number'] );
        $this->assertEquals( 'string|url|required', $rules['url'] );
        $this->assertEquals( 'array', $rules['checkbox'] );
        $this->assertEquals( 'string|max:255', $rules['dropdown'] );
    }

    public function test_submit_entries_add_directly() {
        if ( ! class_exists( '\SRFM\Inc\Database\Tables\Entries' ) ) {
            $this->markTestSkipped( 'SureForms Entries table class not available' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'srfm_entries';

        $data = [
            'form_id'   => $this->form_id,
            'form_data' => [ 'field' => 'value' ],
            'created_at' => current_time( 'mysql' ),
        ];

        $entry_id = \SRFM\Inc\Database\Tables\Entries::add( $data );
        $this->assertGreaterThan( 0, $entry_id, 'Entries::add() should return a positive ID' );

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", $entry_id ), ARRAY_A );
        $this->assertNotEmpty( $row, "Row not found in {$table} after Entries::add()" );
    }

    public function test_submit() {
        $sureforms = $this->get_integration_instance();
        $form      = $sureforms->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form, 'Form not retrieved' );

        $expected_data = [
            'input'    => 'Hello',
            'email'    => 'test@example.com',
            'number'   => '42',
            'url'      => 'https://example.com',
            'checkbox' => [ 'Option 1' ],
            'dropdown' => 'First Choice',
        ];

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'sureforms' );
        foreach ( $expected_data as $key => $value ) {
            $wp_request->set_param( $key, $value );
        }
        $wp_request->set_param( 'unsupported', 'should be skipped' );

        $request = new Request( $wp_request );

        $passed_form_data = null;
        add_filter( 'srfm_form_submit_data', function ( $form_data ) use ( &$passed_form_data ) {
            $passed_form_data = $form_data;
            return $form_data;
        } );

        $captured_entry_id = null;
        $entry             = null;
        add_action( 'srfm_form_submit', function ( $response ) use ( &$captured_entry_id, &$entry ) {
            if ( ! empty( $response['entry_id'] ) ) {
                $captured_entry_id = $response['entry_id'];
                $entry             = \SRFM\Inc\Database\Tables\Entries::get( $captured_entry_id );
            }
        } );

        $sureforms->expose_submit( $request, $form );

        // Verify the raw slug-value pairs were passed to handle_form_entry()
        $this->assertNotNull( $passed_form_data, 'srfm_form_submit_data was not fired' );
        $this->assertSame( $this->form_id, (int) $passed_form_data['form-id'], 'form-id mismatch' );
        foreach ( $expected_data as $key => $value ) {
            $this->assertArrayHasKey( $key, $passed_form_data, "Missing key: {$key}" );
            $this->assertSame( $value, $passed_form_data[$key], "Value mismatch for: {$key}" );
        }
        $this->assertArrayNotHasKey( 'unsupported', $passed_form_data );

        // Verify the entry was created
        $this->assertNotNull( $captured_entry_id, 'srfm_form_submit action was not fired or entry_id missing' );
        $this->assertGreaterThan( 0, $captured_entry_id, 'Entry ID should be a positive integer' );
        $this->assertNotEmpty( $entry, 'Entry not found via Entries::get() inside srfm_form_submit hook' );
        $this->assertSame( $this->form_id, (int) $entry['form_id'], 'Entry form_id mismatch' );
    }
}
