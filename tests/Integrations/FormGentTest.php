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
<!-- wp:formgent/text {"id":"X6iDs9UygXoi","name":"full_name"} /-->

<!-- wp:formgent/text {"id":"f1PR7nP-Dq3F","name":"full_name-1","required":true} /-->

<!-- wp:formgent/email {"id":"eat_0vnQlL4y"} /-->

<!-- wp:formgent/email {"id":"PFCDBQYJJYpX","name":"email-1","required":true} /-->

<!-- wp:formgent/number {"id":"QA1J8l_hryr7"} /-->

<!-- wp:formgent/number {"id":"JKyYwgCc7MVc","name":"number-1","required":true} /-->

<!-- wp:formgent/website {"id":"Ejh4esjqMNGx"} /-->

<!-- wp:formgent/website {"id":"-IMAkkAeOYKC","name":"website-1","required":true} /-->

<!-- wp:formgent/single-choice {"id":"L1AvyM6ciPpo","name":"radio"} /-->

<!-- wp:formgent/single-choice {"id":"MmAqLwz-q-K3","name":"radio-1","required":true} /-->

<!-- wp:formgent/dropdown {"id":"pVq-X9chFLyx","name":"single_select","options":[{"id":"YB5SxjY63LyupxDhGv3pa","label":"New Option","value":"xLofrTuvFjdoEZCqwQ6Zq"}]} /-->

<!-- wp:formgent/dropdown {"id":"RuvfyUHrzqV-","name":"single_select-1","options":[{"id":"YB5SxjY63LyupxDhGv3pa","label":"New Option","value":"xLofrTuvFjdoEZCqwQ6Zq"}],"required":true} /-->

<!-- wp:formgent/multiple-choice {"id":"dxeVhqg5Q8C-","name":"multiple_choice","options":[{"id":"yToh1u7tCszcZ6U-uyOFm","label":"New Option","value":"new_option_1780941272219"},{"label":"New Item 2","id":1,"collapsed":true,"is_default":false,"value":"new-item-2"}]} /-->

<!-- wp:formgent/multiple-choice {"id":"diY5DWX-qPIY","name":"multiple_choice-1","options":[{"id":"yToh1u7tCszcZ6U-uyOFm","label":"New Option","value":"new_option_1780941272219"},{"label":"New Item 2","id":1,"collapsed":true,"is_default":false,"value":"new-item-2"}],"required":true} /-->

<!-- wp:formgent/range-slider {"id":"qnZ3HYgCIjEq","name":"rangeslider"} /-->

<!-- wp:formgent/range-slider {"id":"8jIh6bR6PBVZ","name":"rangeslider-1","required":true} /-->

<!-- wp:formgent/rating {"id":"ZOHkZmb3-2J5","label_alignment":"justify"} /-->

<!-- wp:formgent/rating {"id":"edclJJAbyyCk","name":"rating-1","label_alignment":"justify","required":true} /-->

<!-- wp:formgent/date-picker {"id":"cOXv5HpiOMgu","name":"datetime"} /-->

<!-- wp:formgent/date-picker {"id":"Qk0SOdiv9oif","name":"datetime-1","required":true} /-->
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

        $expected_fields = [
            'full_name', 'full_name-1', 'email', 'email-1', 'number', 'number-1',
            'website', 'website-1', 'radio', 'radio-1', 'single_select', 'single_select-1',
            'multiple_choice', 'multiple_choice-1', 'rangeslider', 'rangeslider-1',
            'rating', 'rating-1', 'datetime', 'datetime-1',
        ];
        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules );
        }
        $this->assertArrayNotHasKey( 'unsupported', $rules );

        // full_name: text, no required, no limit → string
        $full_name_rules = explode( '|', $rules['full_name'] );
        $this->assertEquals( [ 'string' ], $full_name_rules );

        // full_name-1: text, required → string|required
        $full_name_1_rules = explode( '|', $rules['full_name-1'] );
        $this->assertContains( 'string', $full_name_1_rules );
        $this->assertContains( 'required', $full_name_1_rules );
        $this->assertCount( 2, $full_name_1_rules );

        // email: default name, no required → string|email
        $email_rules = explode( '|', $rules['email'] );
        $this->assertContains( 'string', $email_rules );
        $this->assertContains( 'email', $email_rules );
        $this->assertCount( 2, $email_rules );

        // email-1: required → string|email|required
        $email_1_rules = explode( '|', $rules['email-1'] );
        $this->assertContains( 'string', $email_1_rules );
        $this->assertContains( 'email', $email_1_rules );
        $this->assertContains( 'required', $email_1_rules );
        $this->assertCount( 3, $email_1_rules );
    }

    public function test_submit() {
        global $wpdb;

        $formgent = $this->get_integration_instance();
        $form     = $formgent->expose_get_form( $this->form_id );

        // Mock request object
        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'formgent' );
        $wp_request->set_param( 'full_name', 'Jane' );
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
        $this->assertContains( 'full_name', $names );
        $this->assertContains( 'email', $names );
        $this->assertNotContains( 'unsupported', $names );

        $values = wp_list_pluck( $answers, 'value' );
        $this->assertContains( 'Jane', $values );
        $this->assertContains( 'jane@example.com', $values );
    }
}
