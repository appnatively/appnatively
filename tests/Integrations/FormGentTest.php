<?php

namespace AppNatively\Tests\Integrations;

use AppNatively\App\Integrations\Forms\FormGent;
use AppNatively\WpMVC\RequestValidator\Request;

class TestableFormGent extends FormGent {
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

class FormGentTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        if ( ! post_type_exists( 'formgent_form' ) ) {
            register_post_type( 'formgent_form' );
        }

        $post_content = '
<!-- wp:formgent/text {"name":"first_name","required":true,"character_limit":true,"limit":50} /-->
<!-- wp:formgent/email {"name":"email","required":true} /-->
<!-- wp:formgent/file-upload {"name":"unsupported"} /-->
';

        $this->form_id = wp_insert_post(
            [
                'post_title'   => 'Test FormGent Form',
                'post_type'    => 'formgent_form',
                'post_status'  => 'publish',
                'post_content' => $post_content,
            ]
        );

        update_post_meta( $this->form_id, '_formgent_type', 'classic' );
    }

    public function tearDown(): void {
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableFormGent {
        return new TestableFormGent( \AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $formgent = new FormGent( \AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'formgent', $formgent->get_key() );
    }

    public function test_get_form() {
        $formgent = $this->get_integration_instance();
        $form     = $formgent->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form );
        $form_id = isset( $form['id'] ) ? $form['id'] : ( isset( $form['ID'] ) ? $form['ID'] : 0 );
        $this->assertEquals( $this->form_id, $form_id );
    }

    public function test_get_validation_rules() {
        $formgent = $this->get_integration_instance();
        $form     = $formgent->expose_get_form( $this->form_id );
        $rules    = $formgent->expose_get_validation_rules( $form );

        $this->assertArrayHasKey( 'first_name', $rules );
        $this->assertArrayHasKey( 'email', $rules );
        $this->assertArrayNotHasKey( 'unsupported', $rules );

        $first_name_rules = explode( '|', $rules['first_name'] );
        $this->assertContains( 'string', $first_name_rules );
        $this->assertContains( 'max:50', $first_name_rules );
        $this->assertContains( 'required', $first_name_rules );
        $this->assertCount( 3, $first_name_rules );

        $email_rules = explode( '|', $rules['email'] );
        $this->assertContains( 'string', $email_rules );
        $this->assertContains( 'email', $email_rules );
        $this->assertContains( 'required', $email_rules );
        $this->assertCount( 3, $email_rules );
    }

    public function test_submit() {
        global $wpdb;

        $formgent = $this->get_integration_instance();
        $form     = $formgent->expose_get_form( $this->form_id );

        // Mock request object
        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'formgent' );
        $wp_request->set_param( 'first_name', 'Jane' );
        $wp_request->set_param( 'email', 'jane@example.com' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );
        $request = new Request( $wp_request );

        // Perform submit
        $formgent->expose_submit( $request, $form );

        // Check formgent_responses table
        $responses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}formgent_responses WHERE form_id = %d",
                $this->form_id
            ),
            ARRAY_A
        );

        $this->assertCount( 1, $responses );
        $response_id = $responses[0]['id'];

        // Check formgent_answers table
        $answers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}formgent_answers WHERE response_id = %d",
                $response_id
            ),
            ARRAY_A
        );

        $this->assertCount( 2, $answers );
        $names = wp_list_pluck( $answers, 'field_name' );
        $this->assertContains( 'first_name', $names );
        $this->assertContains( 'email', $names );
        $this->assertNotContains( 'unsupported', $names );

        $values = wp_list_pluck( $answers, 'value' );
        $this->assertContains( 'Jane', $values );
        $this->assertContains( 'jane@example.com', $values );
    }
}
