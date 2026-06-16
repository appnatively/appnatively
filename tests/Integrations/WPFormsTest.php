<?php

namespace AppNatively\Tests\Integrations;

use AppNatively\App\Integrations\Forms\WPForms;
use AppNatively\WpMVC\RequestValidator\Request;

class TestableWPForms extends WPForms {
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

class WPFormsTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        $form_data = [
            'field_id' => 10,
            'fields'   => [
                '1' => [
                    'id'          => '1',
                    'type'        => 'text',
                    'label'       => 'Single Line Text',
                    'size'        => 'medium',
                    'limit_count' => '1',
                    'limit_mode'  => 'characters',
                ],
                '7' => [
                    'id'       => '7',
                    'type'     => 'email',
                    'label'    => 'Email',
                    'required' => '1',
                    'size'     => 'medium',
                ],
                '2' => [
                    'id'      => '2',
                    'type'    => 'select',
                    'label'   => 'Dropdown',
                    'choices' => [
                        '1' => [ 'label' => 'First Choice', 'value' => '' ],
                        '2' => [ 'label' => 'Second Choice', 'value' => '' ],
                        '3' => [ 'label' => 'Third Choice', 'value' => '' ],
                    ],
                ],
                '4' => [
                    'id'      => '4',
                    'type'    => 'checkbox',
                    'label'   => 'Checkboxes',
                    'choices' => [
                        '1' => [ 'label' => 'First Choice', 'value' => '' ],
                        '2' => [ 'label' => 'Second Choice', 'value' => '' ],
                        '3' => [ 'label' => 'Third Choice', 'value' => '' ],
                    ],
                ],
                '5' => [
                    'id'    => '5',
                    'type'  => 'number',
                    'label' => 'Numbers',
                    'size'  => 'medium',
                ],
                '6' => [
                    'id'            => '6',
                    'type'          => 'number-slider',
                    'label'         => 'Number Slider',
                    'min'           => '10',
                    'max'           => '24',
                    'default_value' => '24',
                    'step'          => '1',
                    'value_display' => 'Selected Value: {value}',
                    'size'          => 'medium',
                ],
            ],
            'settings' => [
                'form_title'             => 'Test WPForms Form',
                'form_desc'              => '',
                'submit_text'            => 'Submit',
                'submit_text_processing' => 'Sending...',
                'notification_enable'    => '0',
                'notifications'          => [
                    '1' => [
                        'email'          => '{admin_email}',
                        'subject'        => 'New Entry: Test WPForms Form',
                        'sender_name'    => get_bloginfo( 'name' ),
                        'sender_address' => '{admin_email}',
                        'message'        => '{all_fields}',
                    ],
                ],
                'confirmations' => [
                    '1' => [
                        'type'           => 'message',
                        'message'        => 'Thanks for contacting us!',
                        'message_scroll' => '1',
                    ],
                ],
            ],
        ];

        $encoded_content = wpforms_encode( $form_data );

        $this->form_id = wp_insert_post(
            [
                'post_title'   => 'Test WPForms Form',
                'post_type'    => 'wpforms',
                'post_status'  => 'publish',
                'post_content' => $encoded_content,
            ]
        );

        // Re-save form data with the form ID included, as WPForms normally does.
        $form_data['id'] = $this->form_id;
        wp_update_post(
            [
                'ID'           => $this->form_id,
                'post_content' => wpforms_encode( $form_data ),
            ]
        );
    }

    public function tearDown(): void {
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableWPForms {
        return new TestableWPForms( \AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $wpforms = new WPForms( \AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'wpforms', $wpforms->get_key() );
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

        $expected_field_ids = [ '1', '7', '2', '4', '5', '6' ];
        foreach ( $expected_field_ids as $id ) {
            $this->assertArrayHasKey( $id, $rules );
        }

        $this->assertEquals( 'string', $rules['1'] );
        $this->assertEquals( 'string|email|required', $rules['7'] );
        $this->assertEquals( 'string|max:255', $rules['2'] );
        $this->assertEquals( 'array', $rules['4'] );
        $this->assertEquals( 'numeric', $rules['5'] );
        $this->assertEquals( 'numeric|min:10|max:24', $rules['6'] );
    }

    public function test_submit() {
        $wpforms = $this->get_integration_instance();
        $form    = $wpforms->expose_get_form( $this->form_id );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'wpforms' );
        $wp_request->set_param( '1', 'Hello' );
        $wp_request->set_param( '7', 'test@example.com' );
        $wp_request->set_param( '2', 'First Choice' );
        $wp_request->set_param( '4', [ 'First Choice' ] );
        $wp_request->set_param( '5', '42' );
        $wp_request->set_param( '6', '20' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );

        $request = new Request( $wp_request );

        $captured_entry = null;
        $captured_form_data = null;

        add_action( 'wpforms_process_before', function ( $entry, $form_data ) use ( &$captured_entry, &$captured_form_data ) {
            $captured_entry     = $entry;
            $captured_form_data = $form_data;
        }, 10, 2 );

        $wpforms->expose_submit( $request, $form );

        $this->assertNotNull( $captured_entry, 'wpforms_process_before action was not fired' );

        $process = wpforms()->obj( 'process' );
        $this->assertEmpty( $process->errors, 'WPForms validation errors: ' . print_r( $process->errors, true ) );
        // WPForms Lite does not persist entries, so entry_id is always 0.
        $this->assertEquals( $this->form_id, $captured_entry['id'] );
        $this->assertArrayHasKey( 'fields', $captured_entry );

        $fields = $captured_entry['fields'];
        $this->assertEquals( 'Hello', $fields['1'] );
        $this->assertEquals( 'test@example.com', $fields['7'] );
        $this->assertEquals( 'First Choice', $fields['2'] );
        $this->assertEquals( [ 'First Choice' ], $fields['4'] );
        $this->assertEquals( '42', $fields['5'] );
        $this->assertEquals( '20', $fields['6'] );
        $this->assertArrayNotHasKey( '10', $fields );
    }
}
