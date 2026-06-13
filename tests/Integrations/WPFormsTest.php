<?php

namespace AppNatively\Tests\Integrations;

use AppNatively\App\Integrations\Forms\WPForms;
use AppNatively\WpMVC\RequestValidator\Request;

class TestableWPForms extends WPForms {
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

class WPFormsTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        $form_data = [
            'field_id' => '20',
            'fields'   => [
                '0' => [
                    'id'       => '0',
                    'type'     => 'text',
                    'label'    => 'Full Name',
                    'required' => '1',
                    'size'     => 'medium',
                ],
                '1' => [
                    'id'       => '1',
                    'type'     => 'text',
                    'label'    => 'Full Name 2',
                    'required' => '0',
                    'size'     => 'medium',
                ],
                '2' => [
                    'id'       => '2',
                    'type'     => 'number',
                    'label'    => 'Number',
                    'required' => '1',
                    'min'      => '1',
                    'max'      => '100',
                ],
                '3' => [
                    'id'       => '3',
                    'type'     => 'number',
                    'label'    => 'Number 2',
                    'required' => '0',
                ],
                '4' => [
                    'id'       => '4',
                    'type'     => 'email',
                    'label'    => 'Email',
                    'required' => '1',
                ],
                '5' => [
                    'id'       => '5',
                    'type'     => 'email',
                    'label'    => 'Email 2',
                    'required' => '0',
                ],
                '6' => [
                    'id'       => '6',
                    'type'     => 'url',
                    'label'    => 'Website',
                    'required' => '1',
                ],
                '7' => [
                    'id'       => '7',
                    'type'     => 'url',
                    'label'    => 'Website 2',
                    'required' => '0',
                ],
                '8' => [
                    'id'       => '8',
                    'type'     => 'checkbox',
                    'label'    => 'Checkboxes',
                    'required' => '1',
                    'choices'  => [
                        '1' => [ 'label' => 'Choice 1', 'value' => '' ],
                        '2' => [ 'label' => 'Choice 2', 'value' => '' ],
                    ],
                ],
                '9' => [
                    'id'       => '9',
                    'type'     => 'checkbox',
                    'label'    => 'Checkboxes 2',
                    'required' => '0',
                    'choices'  => [
                        '1' => [ 'label' => 'Choice 1', 'value' => '' ],
                    ],
                ],
                '10' => [
                    'id'      => '10',
                    'type'     => 'select',
                    'label'    => 'Dropdown',
                    'required' => '1',
                    'choices'  => [
                        '1' => [ 'label' => 'Option 1', 'value' => '' ],
                        '2' => [ 'label' => 'Option 2', 'value' => '' ],
                        '3' => [ 'label' => 'Option 3', 'value' => '' ],
                    ],
                ],
                '11' => [
                    'id'      => '11',
                    'type'     => 'select',
                    'label'    => 'Dropdown 2',
                    'required' => '0',
                    'choices'  => [
                        '1' => [ 'label' => 'Option 1', 'value' => '' ],
                        '2' => [ 'label' => 'Option 2', 'value' => '' ],
                        '3' => [ 'label' => 'Option 3', 'value' => '' ],
                    ],
                ],
                '12' => [
                    'id'       => '12',
                    'type'     => 'number-slider',
                    'label'    => 'Number Slider',
                    'required' => '1',
                    'min'      => '0',
                    'max'      => '100',
                ],
                '13' => [
                    'id'       => '13',
                    'type'     => 'number-slider',
                    'label'    => 'Number Slider 2',
                    'required' => '0',
                ],
                '14' => [
                    'id'         => '14',
                    'type'       => 'rating',
                    'label'      => 'Rating',
                    'required'   => '1',
                    'max_rating' => '5',
                ],
                '15' => [
                    'id'       => '15',
                    'type'     => 'rating',
                    'label'    => 'Rating 2',
                    'required' => '0',
                ],
                '16' => [
                    'id'       => '16',
                    'type'     => 'date-time',
                    'label'    => 'Date Time',
                    'required' => '1',
                ],
                '17' => [
                    'id'       => '17',
                    'type'     => 'date-time',
                    'label'    => 'Date Time 2',
                    'required' => '0',
                ],
                '18' => [
                    'id'       => '18',
                    'type'     => 'password',
                    'label'    => 'Password',
                    'required' => '1',
                ],
                '19' => [
                    'id'       => '19',
                    'type'     => 'password',
                    'label'    => 'Password 2',
                    'required' => '0',
                ],
                '20' => [
                    'id'    => '20',
                    'type'  => 'internal-information',
                    'label' => 'Internal Info',
                ],
            ],
            'settings' => [
                'form_title'             => 'Test WPForms Form',
                'form_desc'              => '',
                'submit_text'            => 'Submit',
                'submit_text_processing' => 'Sending...',
                'notification_enable'    => '0',
                'notifications'          => [
                    '1' => [
                        'email'          => '{admin_email}',
                        'subject'        => 'New Entry: Test WPForms Form',
                        'sender_name'    => get_bloginfo( 'name' ),
                        'sender_address' => '{admin_email}',
                        'message'        => '{all_fields}',
                    ],
                ],
                'confirmations' => [
                    '1' => [
                        'type'           => 'message',
                        'message'        => 'Thanks for contacting us!',
                        'message_scroll' => '1',
                    ],
                ],
            ],
        ];

        $encoded_content = wpforms_encode( $form_data );

        $this->form_id = wp_insert_post(
            [
                'post_title'   => 'Test WPForms Form',
                'post_type'    => 'wpforms',
                'post_status'  => 'publish',
                'post_content' => $encoded_content,
            ]
        );

        // Re-save form data with the form ID included, as WPForms normally does.
        $form_data['id'] = $this->form_id;
        wp_update_post(
            [
                'ID'           => $this->form_id,
                'post_content' => wpforms_encode( $form_data ),
            ]
        );
    }

    public function tearDown(): void {
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableWPForms {
        return new TestableWPForms( \AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $wpforms = new WPForms( \AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'wpforms', $wpforms->get_key() );
    }

    public function test_get_form() {
        $wpforms = $this->get_integration_instance();
        $form    = $wpforms->expose_get_form( $this->form_id );

        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
        $this->assertNotEmpty( $form['fields'] );
        $this->assertCount( 21, $form['fields'] );
    }

    public function test_get_validation_rules() {
        $wpforms = $this->get_integration_instance();
        $form    = $wpforms->expose_get_form( $this->form_id );
        $rules   = $wpforms->expose_get_validation_rules( $form );

        $expected_fields = [
            'full_name',
            'full_name_2',
            'number',
            'number_2',
            'email',
            'email_2',
            'website',
            'website_2',
            'checkboxes',
            'checkboxes_2',
            'dropdown',
            'dropdown_2',
            'number_slider',
            'number_slider_2',
            'rating',
            'rating_2',
            'date_time',
            'date_time_2',
            'password',
            'password_2',
        ];
        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules );
        }
        $this->assertArrayNotHasKey( 'internal_info', $rules );

        $this->assertEquals( 'string|required', $rules['full_name'] );
        $this->assertEquals( 'string', $rules['full_name_2'] );
        $this->assertEquals( 'numeric|min:1|max:100|required', $rules['number'] );
        $this->assertEquals( 'numeric', $rules['number_2'] );
        $this->assertEquals( 'string|email|required', $rules['email'] );
        $this->assertEquals( 'string|email', $rules['email_2'] );
        $this->assertEquals( 'string|url|required', $rules['website'] );
        $this->assertEquals( 'string|url', $rules['website_2'] );
        $this->assertEquals( 'array|required', $rules['checkboxes'] );
        $this->assertEquals( 'array', $rules['checkboxes_2'] );
        $this->assertEquals( 'string|in:Option 1,Option 2,Option 3|required', $rules['dropdown'] );
        $this->assertEquals( 'string|in:Option 1,Option 2,Option 3', $rules['dropdown_2'] );
        $this->assertEquals( 'numeric|min:0|max:100|required', $rules['number_slider'] );
        $this->assertEquals( 'numeric', $rules['number_slider_2'] );
        $this->assertEquals( 'integer|max:5|required', $rules['rating'] );
        $this->assertEquals( 'integer', $rules['rating_2'] );
        $this->assertEquals( 'string|required', $rules['date_time'] );
        $this->assertEquals( 'string', $rules['date_time_2'] );
        $this->assertEquals( 'string|required', $rules['password'] );
        $this->assertEquals( 'string', $rules['password_2'] );
    }

    public function test_submit() {
        $wpforms = $this->get_integration_instance();
        $form    = $wpforms->expose_get_form( $this->form_id );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'wpforms' );
        $wp_request->set_param( 'full_name', 'John' );
        $wp_request->set_param( 'number', '42' );
        $wp_request->set_param( 'email', 'john@example.com' );
        $wp_request->set_param( 'website', 'https://example.com' );
        $wp_request->set_param( 'checkboxes', [ 'Choice 1' ] );
        $wp_request->set_param( 'dropdown', 'Option 1' );
        $wp_request->set_param( 'number_slider', '75' );
        $wp_request->set_param( 'rating', '5' );
        $wp_request->set_param( 'date_time', '2024-01-15' );
        $wp_request->set_param( 'password', 'secret123' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );

        $request = new Request( $wp_request );

        $captured_entry = null;
        $captured_form_data = null;

        add_action( 'wpforms_process_before', function ( $entry, $form_data ) use ( &$captured_entry, &$captured_form_data ) {
            $captured_entry     = $entry;
            $captured_form_data = $form_data;
        }, 10, 2 );

        $wpforms->expose_submit( $request, $form );

        $this->assertNotNull( $captured_entry, 'wpforms_process_before action was not fired' );
        $this->assertEquals( $this->form_id, $captured_entry['id'] );
        $this->assertArrayHasKey( 'fields', $captured_entry );

        $fields = $captured_entry['fields'];
        $this->assertEquals( 'John', $fields['0'] );
        $this->assertEquals( '42', $fields['2'] );
        $this->assertEquals( 'john@example.com', $fields['4'] );
        $this->assertEquals( 'https://example.com', $fields['6'] );
        $this->assertEquals( [ 'Choice 1' ], $fields['8'] );
        $this->assertEquals( 'Option 1', $fields['10'] );
        $this->assertEquals( '75', $fields['12'] );
        $this->assertEquals( '5', $fields['14'] );
        $this->assertEquals( '2024-01-15', $fields['16'] );
        $this->assertEquals( 'secret123', $fields['18'] );
        $this->assertArrayNotHasKey( '20', $fields );
    }
}
