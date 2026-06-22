<?php

namespace AppNatively\Tests\Integrations;

use AppNatively\App\Integrations\Forms\HappyForms;
use AppNatively\WpMVC\RequestValidator\Request;

class TestableHappyForms extends HappyForms {
    public function expose_get_validation_rules( array $form ) {
        return $this->get_validation_rules( $form );
    }

    public function expose_get_validation_messages( array $form ) {
        return $this->get_validation_messages( $form );
    }

    public function expose_submit( Request $request, array $form ) {
        return $this->submit( $request, $form );
    }

    public function expose_get_form( int $id ) {
        return $this->get_form( $id );
    }
}

class HappyFormTest extends \WP_UnitTestCase {
    private $form_id;

    private $layout = [
        'text_1', 'text_2', 'email_1', 'email_2',
        'radio_1', 'radio_2', 'checkbox_1', 'checkbox_2',
        'select_1', 'select_2', 'number_1', 'number_2',
        'multi_line_text_1',
    ];

    private $parts = [
        'text_1'             => [
            'type'     => 'single_line_text',
            'label'    => 'Single Line Text',
            'required' => 0,
            'id'       => 'text_1',
        ],
        'text_2'             => [
            'type'     => 'single_line_text',
            'label'    => 'Single Line Text two',
            'required' => 1,
            'id'       => 'text_2',
        ],
        'email_1'            => [
            'type'     => 'email',
            'label'    => 'Email',
            'required' => 1,
            'id'       => 'email_1',
        ],
        'email_2'            => [
            'type'     => 'email',
            'label'    => 'Email two',
            'required' => 0,
            'id'       => 'email_2',
        ],
        'radio_1'            => [
            'type'     => 'radio',
            'label'    => 'Multiple Choice',
            'required' => 1,
            'id'       => 'radio_1',
            'options'  => [
                0 => [ 'label' => 'First Choice', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                1 => [ 'label' => 'Second Choice', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                2 => [ 'label' => 'Third Choice', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
            ],
        ],
        'radio_2'            => [
            'type'     => 'radio',
            'label'    => 'Multiple Choice two',
            'required' => 0,
            'id'       => 'radio_2',
            'options'  => [
                0 => [ 'label' => 'Option A', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                1 => [ 'label' => 'Option B', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                2 => [ 'label' => 'Option C', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
            ],
        ],
        'checkbox_1'         => [
            'type'     => 'checkbox',
            'label'    => 'Checkboxes',
            'required' => 1,
            'id'       => 'checkbox_1',
            'options'  => [
                0 => [ 'label' => 'First Choice', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                1 => [ 'label' => 'Second Choice', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                2 => [ 'label' => 'Third Choice', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
            ],
        ],
        'checkbox_2'         => [
            'type'     => 'checkbox',
            'label'    => 'Checkboxes two',
            'required' => 0,
            'id'       => 'checkbox_2',
            'options'  => [
                0 => [ 'label' => 'Option 1', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                1 => [ 'label' => 'Option 2', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
            ],
        ],
        'select_1'           => [
            'type'     => 'select',
            'label'    => 'Dropdown',
            'required' => 1,
            'id'       => 'select_1',
            'options'  => [
                0 => [ 'label' => 'First Choice', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                1 => [ 'label' => 'Second Choice', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                2 => [ 'label' => 'Third Choice', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
            ],
        ],
        'select_2'           => [
            'type'     => 'select',
            'label'    => 'Dropdown two',
            'required' => 0,
            'id'       => 'select_2',
            'options'  => [
                0 => [ 'label' => 'Option X', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
                1 => [ 'label' => 'Option Y', 'is_default' => 0, 'description' => '', 'is_heading' => 0 ],
            ],
        ],
        'number_1'           => [
            'type'      => 'number',
            'label'     => 'Number',
            'required'  => 1,
            'id'        => 'number_1',
            'min_value' => 10,
            'max_value' => 100,
        ],
        'number_2'           => [
            'type'      => 'number',
            'label'     => 'Number two',
            'required'  => 0,
            'id'        => 'number_2',
            'min_value' => '',
            'max_value' => '',
        ],
        'multi_line_text_1' => [
            'type'     => 'multi_line_text',
            'label'    => 'Paragraph Text',
            'required' => 0,
            'id'       => 'multi_line_text_1',
        ],
    ];

    public function setUp(): void {
        parent::setUp();

        if ( ! post_type_exists( 'happyform' ) ) {
            register_post_type( 'happyform' );
        }

        $this->form_id = wp_insert_post(
            [
                'post_title'  => 'Test HappyForm',
                'post_type'   => 'happyform',
                'post_status' => 'publish',
            ]
        );

        add_post_meta( $this->form_id, '_happyforms_layout', $this->layout );

        foreach ( $this->parts as $part_id => $part_data ) {
            add_post_meta( $this->form_id, "_happyforms_{$part_id}", $part_data );
        }

        add_post_meta( $this->form_id, '_happyforms_receive_email_alerts', 1 );
        add_post_meta( $this->form_id, '_happyforms_alert_email_subject', 'New submission' );
        add_post_meta( $this->form_id, '_happyforms_alert_email_from_name', 'Admin' );
        add_post_meta( $this->form_id, '_happyforms_email_recipient', 'admin@example.com' );
        add_post_meta( $this->form_id, '_happyforms_alert_email_from_address', 'admin@example.com' );
    }

    public function tearDown(): void {
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableHappyForms {
        return new TestableHappyForms( \AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $happyforms = new HappyForms( \AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'happyforms', $happyforms->get_key() );
    }

    public function test_get_form() {
        $happyforms = $this->get_integration_instance();
        $form       = $happyforms->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['ID'] );
        $this->assertArrayHasKey( 'parts', $form );
    }

    public function test_get_validation_rules() {
        $happyforms = $this->get_integration_instance();
        $form       = $happyforms->expose_get_form( $this->form_id );
        $rules      = $happyforms->expose_get_validation_rules( $form );

        $expected_fields = [
            'text_1', 'text_2',
            'email_1', 'email_2',
            'radio_1', 'radio_2',
            'checkbox_1', 'checkbox_2',
            'select_1', 'select_2',
            'number_1', 'number_2',
        ];

        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules, "Field {$field} not found in rules" );
        }

        $this->assertArrayNotHasKey( 'multi_line_text_1', $rules );

        // text, optional → string
        $text_opt_rules = explode( '|', $rules['text_1'] );
        $this->assertEquals( [ 'string' ], $text_opt_rules );

        // text, required → string|required
        $text_req_rules = explode( '|', $rules['text_2'] );
        $this->assertContains( 'string', $text_req_rules );
        $this->assertContains( 'required', $text_req_rules );
        $this->assertCount( 2, $text_req_rules );

        // email, required → string|email|required
        $email_req_rules = explode( '|', $rules['email_1'] );
        $this->assertContains( 'string', $email_req_rules );
        $this->assertContains( 'email', $email_req_rules );
        $this->assertContains( 'required', $email_req_rules );
        $this->assertCount( 3, $email_req_rules );

        // email, optional → string|email
        $email_opt_rules = explode( '|', $rules['email_2'] );
        $this->assertContains( 'string', $email_opt_rules );
        $this->assertContains( 'email', $email_opt_rules );
        $this->assertCount( 2, $email_opt_rules );

        // radio, required → string|required
        $radio_req_rules = explode( '|', $rules['radio_1'] );
        $this->assertContains( 'string', $radio_req_rules );
        $this->assertContains( 'required', $radio_req_rules );
        $this->assertCount( 2, $radio_req_rules );

        // checkbox, required → array|required
        $checkbox_req_rules = explode( '|', $rules['checkbox_1'] );
        $this->assertContains( 'array', $checkbox_req_rules );
        $this->assertContains( 'required', $checkbox_req_rules );
        $this->assertCount( 2, $checkbox_req_rules );

        // select, optional → string
        $select_opt_rules = explode( '|', $rules['select_2'] );
        $this->assertEquals( [ 'string' ], $select_opt_rules );

        // number, required (min:10, max:100) → numeric|required|min:10|max:100
        $num_req_rules = explode( '|', $rules['number_1'] );
        $this->assertContains( 'numeric', $num_req_rules );
        $this->assertContains( 'required', $num_req_rules );
        $this->assertContains( 'min:10', $num_req_rules );
        $this->assertContains( 'max:100', $num_req_rules );
        $this->assertCount( 4, $num_req_rules );

        // number, optional (no min/max) → numeric
        $num_opt_rules = explode( '|', $rules['number_2'] );
        $this->assertEquals( [ 'numeric' ], $num_opt_rules );
    }

    public function test_get_validation_messages() {
        $happyforms = $this->get_integration_instance();
        $form       = $happyforms->expose_get_form( $this->form_id );
        $messages   = $happyforms->expose_get_validation_messages( $form );

        $required_fields = [ 'text_2', 'email_1', 'radio_1', 'checkbox_1', 'select_1', 'number_1' ];
        foreach ( $required_fields as $field ) {
            $this->assertArrayHasKey( "{$field}.required", $messages );
            $this->assertEquals( happyforms_get_validation_message( 'field_empty' ), $messages["{$field}.required"] );
        }

        $non_required = [ 'text_1', 'email_2', 'radio_2', 'checkbox_2', 'select_2', 'number_2' ];
        foreach ( $non_required as $field ) {
            $this->assertArrayNotHasKey( "{$field}.required", $messages );
        }

        $this->assertArrayHasKey( 'email_1.email', $messages );
        $this->assertEquals( happyforms_get_validation_message( 'field_invalid' ), $messages['email_1.email'] );

        $this->assertArrayHasKey( 'email_2.email', $messages );
        $this->assertEquals( happyforms_get_validation_message( 'field_invalid' ), $messages['email_2.email'] );

        $this->assertArrayHasKey( 'number_1.numeric', $messages );
        $this->assertEquals( happyforms_get_validation_message( 'field_invalid' ), $messages['number_1.numeric'] );

        $this->assertArrayHasKey( 'number_2.numeric', $messages );
        $this->assertEquals( happyforms_get_validation_message( 'field_invalid' ), $messages['number_2.numeric'] );

        $this->assertArrayHasKey( 'number_1.min', $messages );
        $this->assertEquals( happyforms_get_validation_message( 'number_min_invalid' ), $messages['number_1.min'] );

        $this->assertArrayHasKey( 'number_1.max', $messages );
        $this->assertEquals( happyforms_get_validation_message( 'number_max_invalid' ), $messages['number_1.max'] );

        $this->assertArrayNotHasKey( 'number_2.min', $messages );
        $this->assertArrayNotHasKey( 'number_2.max', $messages );
    }

    public function test_submit() {
        $happyforms = $this->get_integration_instance();
        $form       = $happyforms->expose_get_form( $this->form_id );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'happyforms' );
        $wp_request->set_param( 'text_2', 'John' );
        $wp_request->set_param( 'email_1', 'john@example.com' );
        $wp_request->set_param( 'radio_1', 'First Choice' );
        $wp_request->set_param( 'checkbox_1', [ 'First Choice', 'Second Choice' ] );
        $wp_request->set_param( 'select_1', 'First Choice' );
        $wp_request->set_param( 'number_1', '25' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );

        $request = new Request( $wp_request );

        $captured_email = null;

        add_filter(
            'happyforms_email_alert',
            function ( $email_message ) use ( &$captured_email ) {
                $captured_email = $email_message;
                return $email_message;
            },
            10,
            1
        );

        $submission_success_fired = false;

        add_action(
            'happyforms_submission_success',
            function () use ( &$submission_success_fired ) {
                $submission_success_fired = true;
            }
        );

        $happyforms->expose_submit( $request, $form );

        $this->assertTrue( $submission_success_fired, 'happyforms_submission_success action was not fired' );
        $this->assertNotNull( $captured_email, 'Email notification was not triggered.' );

        $this->assertSame( 'John', $captured_email->message['text_2'] );
        $this->assertSame( 'john@example.com', $captured_email->message['email_1'] );
        $this->assertSame( 'First Choice', $captured_email->message['radio_1'] );
        $this->assertSame( '25', $captured_email->message['number_1'] );
        $this->assertSame( 'First Choice', $captured_email->message['select_1'] );

        $this->assertArrayHasKey( 'checkbox_1', $captured_email->message );
        $this->assertIsArray( $captured_email->message['checkbox_1'] );

        $this->assertArrayNotHasKey( 'unsupported', $captured_email->message );
    }
}
