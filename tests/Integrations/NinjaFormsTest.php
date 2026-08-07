<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\NinjaForms;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableNinjaForms extends NinjaForms {
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

class NinjaFormsTest extends \WP_UnitTestCase {
    private $form_id;

    private $field_ids = [];

    public function setUp(): void {
        parent::setUp();

        if ( ! function_exists( 'Ninja_Forms' ) ) {
            $this->markTestSkipped( 'Ninja Forms plugin is not loaded.' );
        }

        // Ninja_Forms()->form() (no id) caches its factory in a method-local static
        // keyed by the empty-string id, so it's reused across every test in the
        // process. Instantiate the model directly instead of going through the
        // factory to get a genuinely new, uncached form.
        global $wpdb;
        $form = new \NF_Database_Models_Form( $wpdb, '' );
        $form->update_setting( 'title', 'Test Ninja Form' );
        $form->save();
        $this->form_id = $form->get_id();

        $this->field_ids['full_name'] = $this->create_field(
            'textbox', [
                'label'    => 'Full Name',
                'required' => false,
            ]
        );

        $this->field_ids['full_name_req'] = $this->create_field(
            'textbox', [
                'label'    => 'Full Name Required',
                'required' => true,
            ]
        );

        $this->field_ids['email'] = $this->create_field(
            'email', [
                'label'    => 'Email',
                'required' => true,
            ]
        );

        $this->field_ids['gender'] = $this->create_field(
            'listradio', [
                'label'    => 'Gender',
                'required' => false,
                'options'  => [
                    [ 'label' => 'Male', 'value' => 'male' ],
                    [ 'label' => 'Female', 'value' => 'female' ],
                ],
            ]
        );

        $this->field_ids['html'] = $this->create_field(
            'html', [
                'label' => 'Some HTML',
            ]
        );
    }

    private function create_field( string $type, array $settings ) {
        $field = Ninja_Forms()->form( $this->form_id )->field()->get();
        $field->update_settings( array_merge( [ 'type' => $type ], $settings ) );
        $field->save();

        return $field->get_id();
    }

    public function tearDown(): void {
        if ( $this->form_id ) {
            Ninja_Forms()->form( $this->form_id )->get()->delete();
        }
        parent::tearDown();
    }

    private function get_integration_instance(): TestableNinjaForms {
        return new TestableNinjaForms( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $ninjaforms = new NinjaForms( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'ninjaforms', $ninjaforms->get_key() );
    }

    public function test_get_form() {
        $ninjaforms = $this->get_integration_instance();
        $form       = $ninjaforms->expose_get_form( $this->form_id );

        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
        $this->assertEquals( 'Test Ninja Form', $form['title'] );
    }

    public function test_get_validation_rules() {
        $ninjaforms = $this->get_integration_instance();
        $form       = $ninjaforms->expose_get_form( $this->form_id );
        $rules      = $ninjaforms->expose_get_validation_rules( $form );

        $full_name_key     = (string) $this->field_ids['full_name'];
        $full_name_req_key = (string) $this->field_ids['full_name_req'];
        $email_key         = (string) $this->field_ids['email'];
        $gender_key        = (string) $this->field_ids['gender'];
        $html_key          = (string) $this->field_ids['html'];

        $this->assertArrayHasKey( $full_name_key, $rules );
        $this->assertArrayHasKey( $full_name_req_key, $rules );
        $this->assertArrayHasKey( $email_key, $rules );
        $this->assertArrayHasKey( $gender_key, $rules );
        $this->assertArrayNotHasKey( $html_key, $rules );

        // full_name: textbox, not required → string
        $full_name_rules = explode( '|', $rules[ $full_name_key ] );
        $this->assertEquals( [ 'string' ], $full_name_rules );

        // full_name_req: textbox, required → string|required
        $full_name_req_rules = explode( '|', $rules[ $full_name_req_key ] );
        $this->assertContains( 'string', $full_name_req_rules );
        $this->assertContains( 'required', $full_name_req_rules );

        // email: email, required → string|email|required
        $email_rules = explode( '|', $rules[ $email_key ] );
        $this->assertContains( 'string', $email_rules );
        $this->assertContains( 'email', $email_rules );
        $this->assertContains( 'required', $email_rules );

        // gender: listradio, not required → string|max:255
        $gender_rules = explode( '|', $rules[ $gender_key ] );
        $this->assertContains( 'string', $gender_rules );
        $this->assertContains( 'max:255', $gender_rules );
        $this->assertNotContains( 'required', $gender_rules );
    }

    public function test_submit() {
        $ninjaforms = $this->get_integration_instance();
        $form       = $ninjaforms->expose_get_form( $this->form_id );

        $full_name_key = (string) $this->field_ids['full_name'];
        $email_key     = (string) $this->field_ids['email'];
        $gender_key    = (string) $this->field_ids['gender'];

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'ninjaforms' );
        $wp_request->set_param( $full_name_key, 'Jane' );
        $wp_request->set_param( $email_key, 'jane@example.com' );
        $wp_request->set_param( $gender_key, 'female' );
        $request = new Request( $wp_request );

        $ninjaforms->expose_submit( $request, $form );

        $subs = Ninja_Forms()->form( $this->form_id )->get_subs( [], true );
        $this->assertCount( 1, $subs );

        $sub = reset( $subs );
        $this->assertEquals( 'Jane', $sub->get_field_value( $full_name_key ) );
        $this->assertEquals( 'jane@example.com', $sub->get_field_value( $email_key ) );
        $this->assertEquals( 'female', $sub->get_field_value( $gender_key ) );
    }
}
