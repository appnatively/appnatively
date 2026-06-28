<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\ContactForm7;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableContactForm7 extends ContactForm7 {
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

class ContactForm7Test extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        $form_content = '
[text* full_name autocomplete:name]
[text full_name_two autocomplete:name]
[email* email autocomplete:email]
[email email_two autocomplete:email]
[url website]
[url* website_two]
[number number]
[number* number_two]
[date* datetime]
[date datetime_two]
[select* single_select "Option 1" "Option 2" "Option 3"]
[select single_select_two "Option 1" "Option 2" "Option 3"]
[checkbox* multiple_choice use_label_element "Option 1" "Option 2" "Option 3"]
[checkbox multiple_choice_two use_label_element "Option 1" "Option 2" "Option 3"]
[radio radio use_label_element "Option 1" "Option 2" "Option 3"]
[radio radio_two use_label_element "Option 1" "Option 2" "Option 3"]
';

        $cf7_form = \WPCF7_ContactForm::get_template();
        $cf7_form->set_title( 'Test CF7 Form' );
        $cf7_form->set_properties( [ 'form' => $form_content ] );
        $cf7_form->save();
        $this->form_id = $cf7_form->id();
    }

    public function tearDown(): void {
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableContactForm7 {
        return new TestableContactForm7( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $cf7 = new ContactForm7( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'contact-form-7', $cf7->get_key() );
    }

    public function test_get_form() {
        $cf7  = $this->get_integration_instance();
        $form = $cf7->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
        $this->assertEquals( 'Test CF7 Form', $form['title'] );
    }

    public function test_get_validation_rules() {
        $cf7   = $this->get_integration_instance();
        $form  = $cf7->expose_get_form( $this->form_id );
        $rules = $cf7->expose_get_validation_rules( $form );

        $expected_fields = [
            'full_name', 'full_name_two', 'email', 'email_two',
            'website', 'website_two', 'number', 'number_two',
            'datetime', 'datetime_two', 'single_select', 'single_select_two',
            'multiple_choice', 'multiple_choice_two', 'radio', 'radio_two',
        ];
        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules );
        }
        $this->assertArrayNotHasKey( 'unsupported', $rules );

        // full_name: text, required, no maxlength
        $full_name_rules = explode( '|', $rules['full_name'] );
        $this->assertContains( 'string', $full_name_rules );
        $this->assertContains( 'required', $full_name_rules );
        $this->assertCount( 2, $full_name_rules );

        // email: email, required
        $email_rules = explode( '|', $rules['email'] );
        $this->assertContains( 'string', $email_rules );
        $this->assertContains( 'email', $email_rules );
        $this->assertContains( 'required', $email_rules );
        $this->assertCount( 3, $email_rules );

        // website: url, not required
        $website_rules = explode( '|', $rules['website'] );
        $this->assertContains( 'string', $website_rules );
        $this->assertContains( 'url', $website_rules );
        $this->assertCount( 2, $website_rules );

        // number: not required
        $this->assertEquals( 'numeric', $rules['number'] );
    }

    public function test_submit() {
        $cf7  = $this->get_integration_instance();
        $form = $cf7->expose_get_form( $this->form_id );

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 TestAgent';

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'contact-form-7' );
        $wp_request->set_param( 'full_name', 'Jane' );
        $wp_request->set_param( 'email', 'jane@example.com' );
        $wp_request->set_param( 'website_two', 'https://example.com' );
        $wp_request->set_param( 'number_two', '42' );
        $wp_request->set_param( 'datetime', '2024-01-01' );
        $wp_request->set_param( 'single_select', 'Option 1' );
        $wp_request->set_param( 'multiple_choice', ['Option 1'] );
        $wp_request->set_param( 'radio', 'Option 1' );
        $wp_request->set_param( 'radio_two', 'Option 1' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );
        $request = new Request( $wp_request );

        $submission_data = [];
        add_action(
            'wpcf7_submit', function ( $cf7_form, $result ) use ( &$submission_data ) {
                $submission      = \WPCF7_Submission::get_instance();
                $submission_data = [
                    'form_id'     => $cf7_form->id(),
                    'status'      => $result['status'],
                    'posted_data' => $submission ? $submission->get_posted_data() : [],
                ];
            }, 10, 2 
        );

        $cf7->expose_submit( $request, $form );

        $this->assertNotEmpty( $submission_data, 'wpcf7_submit action was not fired' );
        $this->assertEquals( $this->form_id, $submission_data['form_id'] );
        $this->assertEquals( 'mail_sent', $submission_data['status'] );

        $posted_data = $submission_data['posted_data'];
        $this->assertEquals( 'Jane', $posted_data['full_name'] );
        $this->assertEquals( 'jane@example.com', $posted_data['email'] );
        $this->assertEquals( 'https://example.com', $posted_data['website_two'] );
        $this->assertEquals( '42', $posted_data['number_two'] );
        $this->assertEquals( ['Option 1'], $posted_data['single_select'] );
        $this->assertArrayNotHasKey( 'unsupported', $posted_data );
    }
}
