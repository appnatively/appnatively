<?php

namespace AppNatively\Tests\Integrations;

use AppNatively\App\Integrations\Forms\FluentForm;
use AppNatively\WpMVC\RequestValidator\Request;

class TestableFluentForm extends FluentForm {
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

class FluentFormTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        global $wpdb;

        $form_fields = [
            'fields' => [
                [
                    'element'    => 'input_text',
                    'attributes' => [ 'name' => 'first_name' ],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => [ 'value' => true ]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_email',
                    'attributes' => [ 'name' => 'email' ],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => [ 'value' => true ],
                            'email'    => [ 'value' => true ]
                        ]
                    ]
                ],
                [
                    'element'    => 'custom_html',
                    'attributes' => [ 'name' => 'unsupported' ],
                    'settings'   => []
                ]
            ]
        ];

        // Insert mock form post
        $this->form_id = wp_insert_post(
            [
                'post_title'  => 'Test Fluent Form',
                'post_type'   => 'fluentform_form',
                'post_status' => 'publish',
            ]
        );

        // Insert into fluentform custom table
        $wpdb->insert(
            $wpdb->prefix . 'fluentform_forms',
            [
                'id'          => $this->form_id,
                'title'       => 'Test Fluent Form',
                'form_fields' => wp_json_encode( $form_fields ),
                'status'      => 'published',
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ]
        );
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'fluentform_forms', [ 'id' => $this->form_id ] );
        $wpdb->delete( $wpdb->prefix . 'fluentform_submissions', [ 'form_id' => $this->form_id ] );
        $wpdb->delete( $wpdb->prefix . 'fluentform_entry_details', [ 'form_id' => $this->form_id ] );
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableFluentForm {
        return new TestableFluentForm( \AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $fluent_form = new FluentForm( \AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'fluentform', $fluent_form->get_key() );
    }

    public function test_get_form() {
        $fluent_form = $this->get_integration_instance();
        $form        = $fluent_form->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
    }

    public function test_get_validation_rules() {
        $fluent_form = $this->get_integration_instance();
        $form        = $fluent_form->expose_get_form( $this->form_id );
        $rules       = $fluent_form->expose_get_validation_rules( $form );

        $this->assertArrayHasKey( 'first_name', $rules );
        $this->assertArrayHasKey( 'email', $rules );
        $this->assertArrayNotHasKey( 'unsupported', $rules );

        $this->assertEquals( 'required', $rules['first_name'] );
        
        $email_rules = explode( '|', $rules['email'] );
        $this->assertContains( 'required', $email_rules );
        $this->assertContains( 'email', $email_rules );
        $this->assertCount( 2, $email_rules );
    }

    public function test_submit() {
        global $wpdb;

        $fluent_form = $this->get_integration_instance();
        $form        = $fluent_form->expose_get_form( $this->form_id );

        // Mock request object
        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'fluentform' );
        $wp_request->set_param( 'first_name', 'John' );
        $wp_request->set_param( 'email', 'john@example.com' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );
        $request = new Request( $wp_request );

        // Perform submit
        $fluent_form->expose_submit( $request, $form );

        // Check wp_fluentform_submissions
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fluentform_submissions WHERE form_id = %d",
                $this->form_id
            ),
            ARRAY_A
        );

        $this->assertCount( 1, $submissions );
        $response = json_decode( $submissions[0]['response'], true );
        $this->assertEquals( 'John', $response['first_name'] );
        $this->assertEquals( 'john@example.com', $response['email'] );
        $this->assertArrayNotHasKey( 'unsupported', $response );

        // Check wp_fluentform_entry_details
        $details = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fluentform_entry_details WHERE form_id = %d",
                $this->form_id
            ),
            ARRAY_A
        );

        $this->assertCount( 2, $details ); // only first_name and email
        $keys = wp_list_pluck( $details, 'field_name' );
        $this->assertContains( 'first_name', $keys );
        $this->assertContains( 'email', $keys );
        $this->assertNotContains( 'unsupported', $keys );
    }
}
