<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\WeForms;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableWeForms extends WeForms {
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

class WeFormsTest extends \WP_UnitTestCase {
    private $form_id;

    private $field_ids = [];

    public function setUp(): void {
        parent::setUp();

        if ( ! post_type_exists( 'wpuf_contact_form' ) ) {
            register_post_type( 'wpuf_contact_form' );
        }

        if ( ! post_type_exists( 'wpuf_input' ) ) {
            register_post_type( 'wpuf_input' );
        }

        $this->form_id = wp_insert_post(
            [
                'post_title'  => 'Test weForms Form',
                'post_type'   => 'wpuf_contact_form',
                'post_status' => 'publish',
            ]
        );

        add_post_meta( $this->form_id, 'wpuf_form_settings', [] );

        $this->create_field(
            [
                'template' => 'text_field',
                'name'     => 'full_name',
                'label'    => 'Full Name',
                'required' => 'no',
            ]
        );

        $this->create_field(
            [
                'template' => 'text_field',
                'name'     => 'company',
                'label'    => 'Company',
                'required' => 'yes',
            ]
        );

        $this->create_field(
            [
                'template' => 'email_address',
                'name'     => 'email',
                'label'    => 'Email',
                'required' => 'no',
            ]
        );

        $this->create_field(
            [
                'template' => 'email_address',
                'name'     => 'secondary_email',
                'label'    => 'Secondary Email',
                'required' => 'yes',
            ]
        );

        $this->create_field(
            [
                'template' => 'dropdown_field',
                'name'     => 'country',
                'label'    => 'Country',
                'required' => 'yes',
                'options'  => [
                    'us' => 'United States',
                    'ca' => 'Canada',
                ],
            ]
        );

        $this->create_field(
            [
                'template' => 'radio_field',
                'name'     => 'gender',
                'label'    => 'Gender',
                'required' => 'yes',
                'options'  => [
                    'male'   => 'Male',
                    'female' => 'Female',
                ],
            ]
        );

        $this->create_field(
            [
                'template' => 'checkbox_field',
                'name'     => 'hobbies',
                'label'    => 'Hobbies',
                'required' => 'yes',
                'options'  => [
                    'reading' => 'Reading',
                    'sports'  => 'Sports',
                ],
            ]
        );

        $this->create_field(
            [
                'template' => 'website_url',
                'name'     => 'website',
                'label'    => 'Website',
                'required' => 'yes',
            ]
        );

        $this->create_field(
            [
                'template' => 'date_field',
                'name'     => 'birthdate',
                'label'    => 'Birthdate',
                'required' => 'yes',
            ]
        );
    }

    private function create_field( array $field_data ) {
        $defaults = [
            'template'    => '',
            'name'        => '',
            'label'       => '',
            'required'    => 'no',
            'id'          => 0,
            'width'       => 'large',
            'css'         => '',
            'placeholder' => '',
            'default'     => '',
            'size'        => 40,
            'help'        => '',
            'is_meta'     => 'yes',
            'is_new'      => true,
            'wpuf_cond'   => [],
        ];

        $field_data = array_merge( $defaults, $field_data );

        $field_id = wp_insert_post(
            [
                'post_title'   => $field_data['label'],
                'post_type'    => 'wpuf_input',
                'post_parent'  => $this->form_id,
                'post_status'  => 'publish',
                'post_content' => maybe_serialize( $field_data ),
                'menu_order'   => count( $this->field_ids ),
            ]
        );

        $this->field_ids[] = $field_id;
    }

    public function tearDown(): void {
        foreach ( $this->field_ids as $field_id ) {
            wp_delete_post( $field_id, true );
        }
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableWeForms {
        return new TestableWeForms( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $weforms = new WeForms( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'weforms', $weforms->get_key() );
    }

    public function test_get_form() {
        $weforms = $this->get_integration_instance();
        $form    = $weforms->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
        $this->assertArrayHasKey( 'fields', $form );
        $this->assertNotEmpty( $form['fields'] );
    }

    public function test_get_validation_rules() {
        $weforms = $this->get_integration_instance();
        $form    = $weforms->expose_get_form( $this->form_id );
        $rules   = $weforms->expose_get_validation_rules( $form );

        $expected_fields = [
            'full_name', 'company', 'email', 'secondary_email',
            'country', 'gender', 'hobbies', 'website', 'birthdate',
        ];
        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules );
        }
        $this->assertArrayNotHasKey( 'unsupported', $rules );

        // full_name: text_field, no required → string
        $full_name_rules = explode( '|', $rules['full_name'] );
        $this->assertEquals( [ 'string' ], $full_name_rules );

        // company: text_field, required → string|required
        $company_rules = explode( '|', $rules['company'] );
        $this->assertContains( 'string', $company_rules );
        $this->assertContains( 'required', $company_rules );
        $this->assertCount( 2, $company_rules );

        // email: email_address, no required → string|email
        $email_rules = explode( '|', $rules['email'] );
        $this->assertContains( 'string', $email_rules );
        $this->assertContains( 'email', $email_rules );
        $this->assertCount( 2, $email_rules );

        // secondary_email: email_address, required → string|email|required
        $secondary_email_rules = explode( '|', $rules['secondary_email'] );
        $this->assertContains( 'string', $secondary_email_rules );
        $this->assertContains( 'email', $secondary_email_rules );
        $this->assertContains( 'required', $secondary_email_rules );
        $this->assertCount( 3, $secondary_email_rules );

        // website: website_url, required → string|url|required
        $website_rules = explode( '|', $rules['website'] );
        $this->assertContains( 'string', $website_rules );
        $this->assertContains( 'url', $website_rules );
        $this->assertContains( 'required', $website_rules );
        $this->assertCount( 3, $website_rules );

        // hobbies: checkbox_field, required → array|required
        $hobbies_rules = explode( '|', $rules['hobbies'] );
        $this->assertContains( 'array', $hobbies_rules );
        $this->assertContains( 'required', $hobbies_rules );
        $this->assertCount( 2, $hobbies_rules );
    }

    public function test_validation_rules_with_empty_form() {
        $weforms = $this->get_integration_instance();
        $rules   = $weforms->expose_get_validation_rules( [] );
        $this->assertEmpty( $rules );
    }

    public function test_validation_rules_with_missing_fields() {
        $weforms = $this->get_integration_instance();
        $rules   = $weforms->expose_get_validation_rules( [ 'id' => 1 ] );
        $this->assertEmpty( $rules );
    }

    public function test_submit() {
        global $wpdb;

        $weforms = $this->get_integration_instance();
        $form    = $weforms->expose_get_form( $this->form_id );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'weforms' );
        $wp_request->set_param( 'full_name', 'Jane' );
        $wp_request->set_param( 'email', 'jane@example.com' );
        $wp_request->set_param( 'website', 'https://example.com' );
        $wp_request->set_param( 'country', 'us' );
        $wp_request->set_param( 'gender', 'female' );
        $wp_request->set_param( 'hobbies', [ 'reading', 'sports' ] );
        $wp_request->set_param( 'birthdate', '2024-01-15' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );
        $request = new Request( $wp_request );

        $weforms->expose_submit( $request, $form );

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}weforms_entries WHERE form_id = %d",
                $this->form_id
            ),
            ARRAY_A
        );

        $this->assertCount( 1, $entries );
        $entry_id = $entries[0]['id'];

        $metas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}weforms_entrymeta WHERE weforms_entry_id = %d",
                $entry_id
            ),
            ARRAY_A
        );

        $this->assertCount( 7, $metas );
        $meta_keys = wp_list_pluck( $metas, 'meta_key' );
        $this->assertContains( 'full_name', $meta_keys );
        $this->assertContains( 'email', $meta_keys );
        $this->assertContains( 'website', $meta_keys );
        $this->assertContains( 'country', $meta_keys );
        $this->assertContains( 'gender', $meta_keys );
        $this->assertContains( 'hobbies', $meta_keys );
        $this->assertContains( 'birthdate', $meta_keys );
        $this->assertNotContains( 'unsupported', $meta_keys );

        $meta_values = wp_list_pluck( $metas, 'meta_value', 'meta_key' );
        $this->assertEquals( 'Jane', $meta_values['full_name'] );
        $this->assertEquals( 'jane@example.com', $meta_values['email'] );
        $this->assertEquals( 'https://example.com', $meta_values['website'] );
        $this->assertEquals( 'us', $meta_values['country'] );
        $this->assertEquals( 'female', $meta_values['gender'] );
        $this->assertEquals( '2024-01-15', $meta_values['birthdate'] );
    }
}
