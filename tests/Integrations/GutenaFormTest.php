<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\GutenaForms;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableGutenaForms extends GutenaForms {
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

    public function expose_form_submit( Request $request ) {
        return $this->form_submit( $request );
    }
}

class GutenaFormTest extends \WP_UnitTestCase {
    private $form_id;

    private $block_form_id = 'gutena_forms_id_test123';

    private $schema = [
        'form_attrs'   => [
            'formID'            => 'gutena_forms_id_test123',
            'formName'          => 'Test Gutena Form',
            'emailNotifyAdmin'  => true,
            'adminEmails'       => 'admin@example.com',
            'adminEmailSubject' => 'New submission',
            'emailFromName'     => 'Admin',
            'replyToEmail'      => 'email_1',
            'emailFrom'         => 'admin@example.com',
        ],
        'form_fields'  => [],
        'block_markup' => '',
    ];

    private $fields = [
        'text_1'     => [
            'nameAttr'   => 'text_1',
            'fieldName'  => 'Single Line Text',
            'fieldType'  => 'text',
            'isRequired' => false,
        ],
        'text_2'     => [
            'nameAttr'   => 'text_2',
            'fieldName'  => 'Single Line Text two',
            'fieldType'  => 'text',
            'isRequired' => true,
        ],
        'email_1'    => [
            'nameAttr'   => 'email_1',
            'fieldName'  => 'Email',
            'fieldType'  => 'email',
            'isRequired' => true,
        ],
        'email_2'    => [
            'nameAttr'   => 'email_2',
            'fieldName'  => 'Email two',
            'fieldType'  => 'email',
            'isRequired' => false,
        ],
        'radio_1'    => [
            'nameAttr'      => 'radio_1',
            'fieldName'     => 'Multiple Choice',
            'fieldType'     => 'radio',
            'isRequired'    => true,
            'selectOptions' => [
                'First Choice',
                'Second Choice',
                'Third Choice',
            ],
        ],
        'radio_2'    => [
            'nameAttr'      => 'radio_2',
            'fieldName'     => 'Multiple Choice two',
            'fieldType'     => 'radio',
            'isRequired'    => false,
            'selectOptions' => [
                'Option A',
                'Option B',
                'Option C',
            ],
        ],
        'checkbox_1' => [
            'nameAttr'      => 'checkbox_1',
            'fieldName'     => 'Checkboxes',
            'fieldType'     => 'checkbox',
            'isRequired'    => true,
            'selectOptions' => [
                'First Choice',
                'Second Choice',
                'Third Choice',
            ],
        ],
        'checkbox_2' => [
            'nameAttr'      => 'checkbox_2',
            'fieldName'     => 'Checkboxes two',
            'fieldType'     => 'checkbox',
            'isRequired'    => false,
            'selectOptions' => [
                'Option 1',
                'Option 2',
            ],
        ],
        'select_1'   => [
            'nameAttr'      => 'select_1',
            'fieldName'     => 'Dropdown',
            'fieldType'     => 'select',
            'isRequired'    => true,
            'selectOptions' => [
                'First Choice',
                'Second Choice',
                'Third Choice',
            ],
        ],
        'select_2'   => [
            'nameAttr'      => 'select_2',
            'fieldName'     => 'Dropdown two',
            'fieldType'     => 'select',
            'isRequired'    => false,
            'selectOptions' => [
                'Option X',
                'Option Y',
            ],
        ],
        'range_1'    => [
            'nameAttr'   => 'range_1',
            'fieldName'  => 'Range',
            'fieldType'  => 'range',
            'isRequired' => true,
            'minMaxStep' => [
                'min'  => 10,
                'max'  => 100,
                'step' => 1,
            ],
        ],
        'range_2'    => [
            'nameAttr'   => 'range_2',
            'fieldName'  => 'Range two',
            'fieldType'  => 'range',
            'isRequired' => false,
        ],
        'number_1'   => [
            'nameAttr'   => 'number_1',
            'fieldName'  => 'Number',
            'fieldType'  => 'number',
            'isRequired' => true,
        ],
        'number_2'   => [
            'nameAttr'   => 'number_2',
            'fieldName'  => 'Number two',
            'fieldType'  => 'number',
            'isRequired' => false,
        ],
    ];

    public function setUp(): void {
        parent::setUp();

        $this->schema['form_fields'] = $this->fields;

        $this->form_id = wp_insert_post(
            [
                'post_title'  => 'Test Gutena Form',
                'post_type'   => 'gutena_forms',
                'post_status' => 'publish',
            ]
        );

        add_post_meta( $this->form_id, 'gutena_form_id', $this->block_form_id );

        update_option( 'gutena_forms_schema_' . $this->block_form_id, $this->schema );
    }

    public function tearDown(): void {
        wp_delete_post( $this->form_id, true );
        delete_option( 'gutena_forms_schema_' . $this->block_form_id );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableGutenaForms {
        return new TestableGutenaForms( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $gutena = new GutenaForms( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'gutena-forms', $gutena->get_key() );
    }

    public function test_get_form() {
        $gutena = $this->get_integration_instance();
        $form   = $gutena->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->block_form_id, $form['form_id'] );
        $this->assertArrayHasKey( 'fields', $form );
    }

    public function test_get_form_excludes_unpublished_form() {
        wp_update_post( [ 'ID' => $this->form_id, 'post_status' => 'draft' ] );

        $gutena = $this->get_integration_instance();
        $form   = $gutena->expose_get_form( $this->form_id );
        $this->assertEmpty( $form );
    }

    public function test_get_validation_rules() {
        $gutena = $this->get_integration_instance();
        $form   = $gutena->expose_get_form( $this->form_id );
        $rules  = $gutena->expose_get_validation_rules( $form );

        $expected_fields = [
            'text_1', 'text_2',
            'email_1', 'email_2',
            'radio_1', 'radio_2',
            'checkbox_1', 'checkbox_2',
            'select_1', 'select_2',
            'range_1', 'range_2',
            'number_1', 'number_2',
        ];

        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules, "Field {$field} not found in rules" );
        }

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

        // range, required (min:10, max:100) → numeric|required|min:10|max:100
        $range_req_rules = explode( '|', $rules['range_1'] );
        $this->assertContains( 'numeric', $range_req_rules );
        $this->assertContains( 'required', $range_req_rules );
        $this->assertContains( 'min:10', $range_req_rules );
        $this->assertContains( 'max:100', $range_req_rules );
        $this->assertCount( 4, $range_req_rules );

        // range, optional (no min/max) → numeric
        $range_opt_rules = explode( '|', $rules['range_2'] );
        $this->assertEquals( [ 'numeric' ], $range_opt_rules );

        // number, required → numeric|required
        $num_req_rules = explode( '|', $rules['number_1'] );
        $this->assertContains( 'numeric', $num_req_rules );
        $this->assertContains( 'required', $num_req_rules );
        $this->assertCount( 2, $num_req_rules );

        // number, optional → numeric
        $num_opt_rules = explode( '|', $rules['number_2'] );
        $this->assertEquals( [ 'numeric' ], $num_opt_rules );
    }

    public function test_get_validation_messages() {
        $gutena   = $this->get_integration_instance();
        $form     = $gutena->expose_get_form( $this->form_id );
        $messages = $gutena->expose_get_validation_messages( $form );

        $required_fields = [ 'text_2', 'email_1', 'radio_1', 'checkbox_1', 'select_1', 'range_1', 'number_1' ];
        foreach ( $required_fields as $field ) {
            $this->assertArrayHasKey( "{$field}.required", $messages );
        }

        $non_required = [ 'text_1', 'email_2', 'radio_2', 'checkbox_2', 'select_2', 'range_2', 'number_2' ];
        foreach ( $non_required as $field ) {
            $this->assertArrayNotHasKey( "{$field}.required", $messages );
        }

        $this->assertArrayHasKey( 'email_1.email', $messages );
        $this->assertArrayHasKey( 'email_2.email', $messages );

        $this->assertArrayHasKey( 'range_1.numeric', $messages );
        $this->assertArrayHasKey( 'range_2.numeric', $messages );
        $this->assertArrayHasKey( 'number_1.numeric', $messages );
        $this->assertArrayHasKey( 'number_2.numeric', $messages );

        $this->assertArrayHasKey( 'range_1.min', $messages );
        $this->assertArrayHasKey( 'range_1.max', $messages );

        $this->assertArrayNotHasKey( 'range_2.min', $messages );
        $this->assertArrayNotHasKey( 'range_2.max', $messages );

        $this->assertArrayNotHasKey( 'number_1.min', $messages );
        $this->assertArrayNotHasKey( 'number_1.max', $messages );
        $this->assertArrayNotHasKey( 'number_2.min', $messages );
        $this->assertArrayNotHasKey( 'number_2.max', $messages );
    }

    public function test_submit() {
        $gutena = $this->get_integration_instance();
        $form   = $gutena->expose_get_form( $this->form_id );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'gutena-forms' );
        $wp_request->set_param( 'text_2', 'John' );
        $wp_request->set_param( 'email_1', 'john@example.com' );
        $wp_request->set_param( 'radio_1', 'First Choice' );
        $wp_request->set_param( 'checkbox_1', [ 'First Choice', 'Second Choice' ] );
        $wp_request->set_param( 'select_1', 'First Choice' );
        $wp_request->set_param( 'range_1', '25' );
        $wp_request->set_param( 'number_1', '42' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );

        $request = new Request( $wp_request );

        $captured_email = null;

        add_filter(
            'pre_wp_mail',
            function ( $null, $atts ) use ( &$captured_email ) {
                $captured_email = $atts;
                return false;
            },
            10,
            2
        );

        $submitted_data_fired = false;
        $submitted_form_id    = null;

        add_action(
            'gutena_forms_submitted_data',
            function ( $data, $form_id ) use ( &$submitted_data_fired, &$submitted_form_id ) {
                $submitted_data_fired = true;
                $submitted_form_id    = $form_id;
            },
            10,
            2
        );

        $gutena->expose_submit( $request, $form );

        $this->assertTrue( $submitted_data_fired, 'gutena_forms_submitted_data action was not fired' );
        $this->assertEquals( $this->block_form_id, $submitted_form_id );

        $this->assertNotNull( $captured_email, 'Email notification was not triggered.' );

        $this->assertStringContainsString( 'John', $captured_email['message'] );
        $this->assertStringContainsString( 'john@example.com', $captured_email['message'] );
        $this->assertStringContainsString( 'First Choice', $captured_email['message'] );
        $this->assertStringContainsString( '25', $captured_email['message'] );

        $this->assertStringNotContainsString( 'should be skipped', $captured_email['message'] );

        $this->assertStringContainsString( 'admin@example.com', $captured_email['to'][0] );
    }

    public function test_form_submit_normalizes_range_array_value() {
        $gutena = $this->get_integration_instance();

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'gutena-forms' );
        $wp_request->set_param( 'text_2', 'John' );
        $wp_request->set_param( 'email_1', 'john@example.com' );
        $wp_request->set_param( 'radio_1', 'First Choice' );
        $wp_request->set_param( 'checkbox_1', [ 'First Choice', 'Second Choice' ] );
        $wp_request->set_param( 'select_1', 'First Choice' );
        $wp_request->set_param( 'range_1', [ 'min' => '10', 'max' => '25' ] );
        $wp_request->set_param( 'number_1', '42' );

        $request = new Request( $wp_request );

        $captured_email = null;

        add_filter(
            'pre_wp_mail',
            function ( $null, $atts ) use ( &$captured_email ) {
                $captured_email = $atts;
                return false;
            },
            10,
            2
        );

        $gutena->expose_form_submit( $request );

        $this->assertNotNull( $captured_email, 'Email notification was not triggered.' );
        $this->assertStringContainsString( '25', $captured_email['message'] );
        $this->assertStringNotContainsString( '10', $captured_email['message'] );
    }

    public function test_form_submit_sends_email_by_default_when_emailNotifyAdmin_missing() {
        $gutena = $this->get_integration_instance();

        $schema = get_option( 'gutena_forms_schema_' . $this->block_form_id );
        unset( $schema['form_attrs']['emailNotifyAdmin'] );
        update_option( 'gutena_forms_schema_' . $this->block_form_id, $schema );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'gutena-forms' );
        $wp_request->set_param( 'text_2', 'John' );
        $wp_request->set_param( 'email_1', 'john@example.com' );
        $wp_request->set_param( 'radio_1', 'First Choice' );
        $wp_request->set_param( 'checkbox_1', [ 'First Choice', 'Second Choice' ] );
        $wp_request->set_param( 'select_1', 'First Choice' );
        $wp_request->set_param( 'range_1', '25' );
        $wp_request->set_param( 'number_1', '42' );

        $request = new Request( $wp_request );

        $captured_email = null;

        add_filter(
            'pre_wp_mail',
            function ( $null, $atts ) use ( &$captured_email ) {
                $captured_email = $atts;
                return false;
            },
            10,
            2
        );

        $gutena->expose_form_submit( $request );

        $this->assertNotNull( $captured_email, 'Email should be sent when emailNotifyAdmin is missing (default true).' );
    }

    public function test_form_submit_skips_email_when_emailNotifyAdmin_false() {
        $gutena = $this->get_integration_instance();

        $schema                                   = get_option( 'gutena_forms_schema_' . $this->block_form_id );
        $schema['form_attrs']['emailNotifyAdmin'] = false;
        update_option( 'gutena_forms_schema_' . $this->block_form_id, $schema );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'gutena-forms' );
        $wp_request->set_param( 'text_2', 'John' );
        $wp_request->set_param( 'email_1', 'john@example.com' );
        $wp_request->set_param( 'radio_1', 'First Choice' );
        $wp_request->set_param( 'checkbox_1', [ 'First Choice', 'Second Choice' ] );
        $wp_request->set_param( 'select_1', 'First Choice' );
        $wp_request->set_param( 'range_1', '25' );
        $wp_request->set_param( 'number_1', '42' );

        $request = new Request( $wp_request );

        $captured_email = null;

        add_filter(
            'pre_wp_mail',
            function ( $null, $atts ) use ( &$captured_email ) {
                $captured_email = $atts;
                return false;
            },
            10,
            2
        );

        $gutena->expose_form_submit( $request );

        $this->assertNull( $captured_email, 'Email should NOT be sent when emailNotifyAdmin is false.' );
    }

    public function test_form_submit_is_rate_limited_per_ip_and_form() {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';

        add_filter(
            'craf_appna_form_rate_limit_max', function () {
                return 2;
            } 
        );

        $gutena = $this->get_integration_instance();

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'gutena-forms' );
        $request = new Request( $wp_request );

        for ( $i = 0; $i < 2; $i++ ) {
            try {
                $gutena->expose_form_submit( $request );
            } catch ( \Throwable $e ) {
                // Validation failures are expected here since required fields
                // aren't filled in; only the rate-limit exception matters below.
            }
        }

        $this->expectExceptionCode( 429 );
        $gutena->expose_form_submit( $request );
    }
}
