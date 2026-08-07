<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\BitForm;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableBitForm extends BitForm {
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

class BitFormTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        if ( ! class_exists( '\BitCode\BitForm\Core\Database\FormModel' ) ) {
            $this->markTestSkipped( 'Bit Form plugin is not loaded.' );
        }

        global $wpdb;

        $form_content = wp_json_encode(
            [
                'fields' => [
                    'fld_full_name' => [
                        'typ'       => 'text',
                        'lbl'       => 'Full Name',
                        'fieldName' => 'full_name',
                        'valid'     => [ 'req' => false ],
                    ],
                    'fld_full_name_req' => [
                        'typ'       => 'text',
                        'lbl'       => 'Full Name Required',
                        'fieldName' => 'full_name_req',
                        'valid'     => [ 'req' => true, 'maxlength' => 50 ],
                    ],
                    'fld_email' => [
                        'typ'       => 'email',
                        'lbl'       => 'Email',
                        'fieldName' => 'email',
                        'valid'     => [ 'req' => true ],
                    ],
                    'fld_gender' => [
                        'typ'       => 'radio',
                        'lbl'       => 'Gender',
                        'fieldName' => 'gender',
                        'valid'     => [ 'req' => false ],
                        'opt'       => [
                            [ 'lbl' => 'Male', 'val' => 'male' ],
                            [ 'lbl' => 'Female', 'val' => 'female' ],
                        ],
                    ],
                    'fld_gdpr' => [
                        'typ' => 'gdpr',
                        'lbl' => 'I agree to the terms',
                    ],
                    'fld_html' => [
                        'typ' => 'html',
                    ],
                ],
            ]
        );

        $wpdb->insert(
            "{$wpdb->prefix}bitforms_form",
            [
                'form_name'    => 'Test Bit Form',
                'form_content' => $form_content,
                'status'       => 1,
                'created_at'   => current_time( 'mysql' ),
            ]
        );

        $this->form_id = (int) $wpdb->insert_id;
    }

    public function tearDown(): void {
        global $wpdb;

        if ( $this->form_id ) {
            $wpdb->delete( "{$wpdb->prefix}bitforms_form", [ 'id' => $this->form_id ] );

            $entries = $wpdb->get_col(
                $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}bitforms_form_entries WHERE form_id = %d", $this->form_id )
            );
            foreach ( $entries as $entry_id ) {
                $wpdb->delete( "{$wpdb->prefix}bitforms_form_entrymeta", [ 'bitforms_form_entry_id' => $entry_id ] );
            }
            $wpdb->delete( "{$wpdb->prefix}bitforms_form_entries", [ 'form_id' => $this->form_id ] );
            $wpdb->delete( "{$wpdb->prefix}bitforms_form_entry_log", [ 'form_id' => $this->form_id ] );
        }

        parent::tearDown();
    }

    private function get_integration_instance(): TestableBitForm {
        return new TestableBitForm( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $bitform = new BitForm( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'bitform', $bitform->get_key() );
    }

    public function test_get_form() {
        $bitform = $this->get_integration_instance();
        $form    = $bitform->expose_get_form( $this->form_id );

        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, (int) $form['id'] );
    }

    public function test_get_validation_rules() {
        $bitform = $this->get_integration_instance();
        $form    = $bitform->expose_get_form( $this->form_id );
        $rules   = $bitform->expose_get_validation_rules( $form );

        $expected_fields = [ 'fld_full_name', 'fld_full_name_req', 'fld_email', 'fld_gender', 'fld_gdpr' ];
        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules );
        }
        $this->assertArrayNotHasKey( 'fld_html', $rules );

        // fld_full_name: text, not required → string
        $full_name_rules = explode( '|', $rules['fld_full_name'] );
        $this->assertEquals( [ 'string' ], $full_name_rules );

        // fld_full_name_req: text, required, maxlength 50 → string|max:50|required
        $full_name_req_rules = explode( '|', $rules['fld_full_name_req'] );
        $this->assertContains( 'string', $full_name_req_rules );
        $this->assertContains( 'max:50', $full_name_req_rules );
        $this->assertContains( 'required', $full_name_req_rules );

        // fld_email: email, required → string|email|required
        $email_rules = explode( '|', $rules['fld_email'] );
        $this->assertContains( 'string', $email_rules );
        $this->assertContains( 'email', $email_rules );
        $this->assertContains( 'required', $email_rules );

        // fld_gender: radio, not required → string|max:255
        $gender_rules = explode( '|', $rules['fld_gender'] );
        $this->assertContains( 'string', $gender_rules );
        $this->assertContains( 'max:255', $gender_rules );
        $this->assertNotContains( 'required', $gender_rules );

        // fld_gdpr: always required regardless of its own "req" setting → integer|in:1|required
        $gdpr_rules = explode( '|', $rules['fld_gdpr'] );
        $this->assertContains( 'integer', $gdpr_rules );
        $this->assertContains( 'in:1', $gdpr_rules );
        $this->assertContains( 'required', $gdpr_rules );
    }

    public function test_submit() {
        global $wpdb;

        // Bit Form's own FormManager::submisionLog() passes a raw PHP array as the
        // `content` column value straight to $wpdb->insert() without serializing it
        // first, on every submission. That's a pre-existing bug in the plugin itself,
        // not something under our control.
        $this->setExpectedIncorrectUsage( 'wpdb::prepare' );

        $bitform = $this->get_integration_instance();
        $form    = $bitform->expose_get_form( $this->form_id );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'bitform' );
        $wp_request->set_param( 'fld_full_name', 'Jane' );
        $wp_request->set_param( 'fld_email', 'jane@example.com' );
        $wp_request->set_param( 'fld_gender', 'female' );
        $wp_request->set_param( 'fld_gdpr', 1 );
        $request = new Request( $wp_request );

        $bitform->expose_submit( $request, $form );

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bitforms_form_entries WHERE form_id = %d",
                $this->form_id
            ),
            ARRAY_A
        );

        $this->assertCount( 1, $entries );
        $entry_id = $entries[0]['id'];

        $meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bitforms_form_entrymeta WHERE bitforms_form_entry_id = %d",
                $entry_id
            ),
            ARRAY_A
        );

        $meta_values = wp_list_pluck( $meta, 'meta_value', 'meta_key' );
        $this->assertArrayHasKey( 'fld_full_name', $meta_values );
        $this->assertArrayHasKey( 'fld_email', $meta_values );
        $this->assertEquals( 'Jane', $meta_values['fld_full_name'] );
        $this->assertEquals( 'jane@example.com', $meta_values['fld_email'] );
    }
}
