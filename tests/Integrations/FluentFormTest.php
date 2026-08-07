<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\FluentForm;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableFluentForm extends FluentForm
{
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

class FluentFormTest extends \WP_UnitTestCase
{
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        global $wpdb;

        // $form_fields = [
        //     'fields' => [
        //         [
        //             'element'    => 'input_text',
        //             'attributes' => [ 'name' => 'first_name' ],
        //             'settings'   => [
        //                 'validation_rules' => [
        //                     'required' => [ 'value' => true ]
        //                 ]
        //             ]
        //         ],
        //         [
        //             'element'    => 'input_email',
        //             'attributes' => [ 'name' => 'email' ],
        //             'settings'   => [
        //                 'validation_rules' => [
        //                     'required' => [ 'value' => true ],
        //                     'email'    => [ 'value' => true ]
        //                 ]
        //             ]
        //         ],
        //         [
        //             'element'    => 'custom_html',
        //             'attributes' => [ 'name' => 'unsupported' ],
        //             'settings'   => []
        //         ]
        //     ]
        // ];
        $form_fields = [
            'fields' => [
                [
                    'element'    => 'input_text',
                    'attributes' => ['name' => 'full_name'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => false]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_text',
                    'attributes' => ['name' => 'full_name_1'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_number',
                    'attributes' => ['name' => 'number'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => false],
                            'numeric'  => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_number',
                    'attributes' => ['name' => 'number_1'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => true],
                            'numeric'  => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_email',
                    'attributes' => ['name' => 'email'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => false],
                            'email'    => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_email',
                    'attributes' => ['name' => 'email_1'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => true],
                            'email'    => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_url',
                    'attributes' => ['name' => 'website'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => false],
                            'url'      => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_url',
                    'attributes' => ['name' => 'website_1'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => true],
                            'url'      => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_radio',
                    'attributes' => ['name' => 'radio'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => false]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_radio',
                    'attributes' => ['name' => 'radio_1'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_checkbox',
                    'attributes' => ['name' => 'multiple_choice'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => false]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_checkbox',
                    'attributes' => ['name' => 'multiple_choice_1'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'select',
                    'attributes' => ['name' => 'single_select'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => false]
                        ]
                    ]
                ],
                [
                    'element'    => 'select',
                    'attributes' => ['name' => 'single_select_1'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_password',
                    'attributes' => ['name' => 'password'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => false]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_password',
                    'attributes' => ['name' => 'password_1'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => true]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_date',
                    'attributes' => ['name' => 'datetime'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => false]
                        ]
                    ]
                ],
                [
                    'element'    => 'input_date',
                    'attributes' => ['name' => 'datetime_1'],
                    'settings'   => [
                        'validation_rules' => [
                            'required' => ['value' => true]
                        ]
                    ]
                ],
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
        $wpdb->delete( $wpdb->prefix . 'fluentform_forms', ['id' => $this->form_id] );
        $wpdb->delete( $wpdb->prefix . 'fluentform_submissions', ['form_id' => $this->form_id] );
        $wpdb->delete( $wpdb->prefix . 'fluentform_entry_details', ['form_id' => $this->form_id] );
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableFluentForm {
        return new TestableFluentForm( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $fluent_form = new FluentForm( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'fluentform', $fluent_form->get_key() );
    }

    public function test_get_form() {
        $fluent_form = $this->get_integration_instance();
        $form        = $fluent_form->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
    }

    public function test_get_form_excludes_unpublished_form() {
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'fluentform_forms', [ 'status' => 'draft' ], [ 'id' => $this->form_id ] );

        $fluent_form = $this->get_integration_instance();
        $form        = $fluent_form->expose_get_form( $this->form_id );
        $this->assertEmpty( $form );
    }

    public function test_get_validation_rules() {
        $fluent_form = $this->get_integration_instance();
        $form        = $fluent_form->expose_get_form( $this->form_id );
        $rules       = $fluent_form->expose_get_validation_rules( $form );

        $this->assertArrayHasKey( 'full_name_1', $rules );
        $this->assertArrayHasKey( 'email', $rules );
        $this->assertArrayNotHasKey( 'unsupported', $rules );

        $this->assertEquals( 'required', $rules['full_name_1'] );

        $email_rules = explode( '|', $rules['email'] );
        $this->assertContains( 'email', $email_rules );
        $this->assertCount( 1, $email_rules );

        $this->assertEquals( 'required|email', $rules['email_1'] );
        $this->assertEquals( 'numeric', $rules['number'] );
        $this->assertEquals( 'required|numeric', $rules['number_1'] );
        $this->assertEquals( 'url', $rules['website'] );
        $this->assertEquals( 'required|url', $rules['website_1'] );
        $this->assertEquals( 'required', $rules['radio_1'] );
        $this->assertEquals( 'required', $rules['multiple_choice_1'] );
        $this->assertEquals( 'required', $rules['single_select_1'] );
        $this->assertEquals( 'required', $rules['password_1'] );
        $this->assertEquals( 'required', $rules['datetime_1'] );
    }

    public function test_submit() {
        global $wpdb;

        $fluent_form = $this->get_integration_instance();
        $form        = $fluent_form->expose_get_form( $this->form_id );

        // Mock request object
        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'fluentform' );
        $wp_request->set_param( 'full_name', 'John' );
        $wp_request->set_param( 'email', 'john@example.com' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );
        $wp_request->set_param( 'full_name_1', 'Jane' );
        $wp_request->set_param( 'number', '42' );
        $wp_request->set_param( 'website', 'https://example.com' );
        $wp_request->set_param( 'radio', 'option_1' );
        $wp_request->set_param( 'multiple_choice', ['option_1'] );
        $wp_request->set_param( 'single_select', 'option_1' );
        $wp_request->set_param( 'password', 'secret123' );
        $wp_request->set_param( 'datetime', '2024-01-15' );
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
        $this->assertEquals( 'John', $response['full_name'] );
        $this->assertEquals( 'john@example.com', $response['email'] );
        $this->assertArrayNotHasKey( 'unsupported', $response );
        $this->assertEquals( 'Jane', $response['full_name_1'] );
        $this->assertEquals( '42', $response['number'] );
        $this->assertEquals( 'https://example.com', $response['website'] );
        $this->assertEquals( 'option_1', $response['radio'] );
        $this->assertEquals( ['option_1'], $response['multiple_choice'] );
        $this->assertEquals( 'option_1', $response['single_select'] );
        $this->assertEquals( '2024-01-15', $response['datetime'] );

        // Check wp_fluentform_entry_details
        $details = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fluentform_entry_details WHERE form_id = %d",
                $this->form_id
            ),
            ARRAY_A
        );

        $this->assertCount( 9, $details ); // all string values stored; checkbox array skipped
        $keys = wp_list_pluck( $details, 'field_name' );
        $this->assertContains( 'full_name', $keys );
        $this->assertContains( 'email', $keys );
        $this->assertNotContains( 'unsupported', $keys );
    }
}
