<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\WPForms;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

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

    public function expose_get_validation_messages( array $form ) {
        return $this->get_validation_messages( $form );
    }
}

class WPFormsTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        $form_data = [
            'field_id' => 16,
            'fields'   => [
                '1'  => [
                    'id'            => '1',
                    'type'          => 'text',
                    'label'         => 'single text',
                    'description'   => '',
                    'required'      => '1',
                    'size'          => 'medium',
                    'placeholder'   => '',
                    'limit_count'   => '1',
                    'limit_mode'    => 'characters',
                    'default_value' => '',
                    'input_mask'    => '',
                    'css'           => '',
                ],
                '10' => [
                    'id'            => '10',
                    'type'          => 'text',
                    'label'         => 'single text two',
                    'description'   => '',
                    'size'          => 'medium',
                    'placeholder'   => '',
                    'limit_count'   => '1',
                    'limit_mode'    => 'characters',
                    'default_value' => '',
                    'input_mask'    => '',
                    'css'           => '',
                ],
                '7'  => [
                    'id'                       => '7',
                    'type'                     => 'email',
                    'label'                    => 'Email',
                    'description'              => '',
                    'required'                 => '1',
                    'size'                     => 'medium',
                    'placeholder'              => '',
                    'confirmation_placeholder' => '',
                    'default_value'            => false,
                    'filter_type'              => '',
                    'allowlist'                => '',
                    'denylist'                 => '',
                    'css'                      => '',
                ],
                '11' => [
                    'id'                       => '11',
                    'type'                     => 'email',
                    'label'                    => 'Email two',
                    'description'              => '',
                    'size'                     => 'medium',
                    'placeholder'              => '',
                    'confirmation_placeholder' => '',
                    'default_value'            => false,
                    'filter_type'              => '',
                    'allowlist'                => '',
                    'denylist'                 => '',
                    'css'                      => '',
                ],
                '2'  => [
                    'id'              => '2',
                    'type'            => 'select',
                    'label'           => 'Dropdown',
                    'choices'         => [
                        '1' => [ 'label' => 'First Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                        '2' => [ 'label' => 'Second Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                        '3' => [ 'label' => 'Third Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                    ],
                    'description'     => '',
                    'required'        => '1',
                    'style'           => 'classic',
                    'size'            => 'medium',
                    'placeholder'     => '--- Select Choice ---',
                    'dynamic_choices' => '',
                    'css'             => '',
                ],
                '12' => [
                    'id'              => '12',
                    'type'            => 'select',
                    'label'           => 'Dropdown two',
                    'choices'         => [
                        '1' => [ 'label' => 'First Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                        '2' => [ 'label' => 'Second Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                        '3' => [ 'label' => 'Third Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                    ],
                    'description'     => '',
                    'style'           => 'classic',
                    'size'            => 'medium',
                    'placeholder'     => '--- Select Choice ---',
                    'dynamic_choices' => '',
                    'css'             => '',
                ],
                '4'  => [
                    'id'                   => '4',
                    'type'                 => 'checkbox',
                    'label'                => 'Checkboxes',
                    'choices'              => [
                        '1' => [ 'label' => 'First Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                        '2' => [ 'label' => 'Second Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                        '3' => [ 'label' => 'Third Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                    ],
                    'choices_images_style' => 'modern',
                    'choices_icons_color'  => '#066aab',
                    'choices_icons_size'   => 'large',
                    'choices_icons_style'  => 'default',
                    'description'          => '',
                    'required'             => '1',
                    'input_columns'        => '',
                    'choice_limit'         => '',
                    'dynamic_choices'      => '',
                    'css'                  => '',
                ],
                '13' => [
                    'id'                   => '13',
                    'type'                 => 'checkbox',
                    'label'                => 'Checkboxes two',
                    'choices'              => [
                        '1' => [ 'label' => 'First Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                        '2' => [ 'label' => 'Second Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                        '3' => [ 'label' => 'Third Choice', 'value' => '', 'image' => '', 'icon' => 'face-smile', 'icon_style' => 'regular' ],
                    ],
                    'choices_images_style' => 'modern',
                    'choices_icons_color'  => '#066aab',
                    'choices_icons_size'   => 'large',
                    'choices_icons_style'  => 'default',
                    'description'          => '',
                    'input_columns'        => '',
                    'choice_limit'         => '',
                    'dynamic_choices'      => '',
                    'css'                  => '',
                ],
                '5'  => [
                    'id'            => '5',
                    'type'          => 'number',
                    'label'         => 'Numbers',
                    'description'   => '',
                    'required'      => '1',
                    'size'          => 'medium',
                    'placeholder'   => '',
                    'min'           => '',
                    'max'           => '',
                    'default_value' => '',
                    'css'           => '',
                ],
                '14' => [
                    'id'            => '14',
                    'type'          => 'number',
                    'label'         => 'Numbers two',
                    'description'   => '',
                    'size'          => 'medium',
                    'placeholder'   => '',
                    'min'           => '',
                    'max'           => '',
                    'default_value' => '',
                    'css'           => '',
                ],
                '6'  => [
                    'id'            => '6',
                    'type'          => 'number-slider',
                    'label'         => 'Number Slider',
                    'description'   => '',
                    'required'      => '',
                    'min'           => '10',
                    'max'           => '24',
                    'default_value' => '24',
                    'step'          => '1',
                    'size'          => 'medium',
                    'value_display' => 'Selected Value: {value}',
                    'css'           => '',
                ],
                '15' => [
                    'id'            => '15',
                    'type'          => 'number-slider',
                    'label'         => 'Number Slider two',
                    'description'   => '',
                    'required'      => '',
                    'min'           => '10',
                    'max'           => '24',
                    'default_value' => '24',
                    'step'          => '1',
                    'size'          => 'medium',
                    'value_display' => 'Selected Value: {value}',
                    'css'           => '',
                ],
            ],
            'settings' => [
                'form_title'             => 'Blank Form',
                'form_desc'              => '',
                'submit_text'            => 'Submit',
                'submit_text_processing' => 'Sending...',
                'form_class'             => '',
                'submit_class'           => '',
                'ajax_submit'            => '1',
                'notification_enable'    => '1',
                'notifications'          => [
                    '1' => [
                        'email'          => '{admin_email}',
                        'subject'        => 'New Blank Form Entry',
                        'sender_name'    => 'app',
                        'sender_address' => '{admin_email}',
                        'replyto'        => '',
                        'message'        => '{all_fields}',
                        'template'       => '',
                    ],
                ],
                'confirmations'          => [
                    '1' => [
                        'type'                => 'message',
                        'message'             => '<p>Thanks for contacting us! We will be in touch with you shortly.</p>',
                        'message_scroll'      => '1',
                        'page'                => 'previous_page',
                        'page_url_parameters' => '',
                        'redirect'            => '',
                    ],
                ],
                'antispam_v3'            => '1',
                'store_spam_entries'     => '0',
                'form_tags'              => [],
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
        return new TestableWPForms( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $wpforms = new WPForms( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'wpforms', $wpforms->get_key() );
    }

    public function test_get_form() {
        $wpforms = $this->get_integration_instance();
        $form    = $wpforms->expose_get_form( $this->form_id );

        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
        $this->assertNotEmpty( $form['fields'] );
        $this->assertCount( 12, $form['fields'] );
    }

    public function test_get_validation_rules() {
        $wpforms = $this->get_integration_instance();
        $form    = $wpforms->expose_get_form( $this->form_id );
        $rules   = $wpforms->expose_get_validation_rules( $form );

        $expected_field_ids = [ '1', '10', '7', '11', '2', '12', '4', '13', '5', '14', '6', '15' ];
        foreach ( $expected_field_ids as $id ) {
            $this->assertArrayHasKey( $id, $rules );
        }

        $this->assertEquals( 'string|required', $rules['1'] );
        $this->assertEquals( 'string', $rules['10'] );
        $this->assertEquals( 'string|email|required', $rules['7'] );
        $this->assertEquals( 'string|email', $rules['11'] );
        $this->assertEquals( 'string|max:255|required', $rules['2'] );
        $this->assertEquals( 'string|max:255', $rules['12'] );
        $this->assertEquals( 'array|required', $rules['4'] );
        $this->assertEquals( 'array', $rules['13'] );
        $this->assertEquals( 'numeric|required', $rules['5'] );
        $this->assertEquals( 'numeric', $rules['14'] );
        $this->assertEquals( 'numeric|min:10|max:24', $rules['6'] );
        $this->assertEquals( 'numeric|min:10|max:24', $rules['15'] );
    }

    public function test_get_validation_messages() {
        $wpforms  = $this->get_integration_instance();
        $form     = $wpforms->expose_get_form( $this->form_id );
        $messages = $wpforms->expose_get_validation_messages( $form );

        $this->assertArrayHasKey( '1.required', $messages );
        $this->assertArrayHasKey( '7.required', $messages );
        $this->assertArrayHasKey( '7.email', $messages );
        $this->assertArrayHasKey( '11.email', $messages );
        $this->assertArrayHasKey( '2.required', $messages );
        $this->assertArrayHasKey( '4.required', $messages );
        $this->assertArrayHasKey( '5.required', $messages );
        $this->assertArrayHasKey( '5.numeric', $messages );
        $this->assertArrayHasKey( '14.numeric', $messages );
        $this->assertArrayHasKey( '6.numeric', $messages );
        $this->assertArrayHasKey( '6.min', $messages );
        $this->assertArrayHasKey( '6.max', $messages );
        $this->assertArrayHasKey( '15.numeric', $messages );
        $this->assertArrayHasKey( '15.min', $messages );
        $this->assertArrayHasKey( '15.max', $messages );

        $this->assertStringContainsString( ':min', $messages['6.min'] );
        $this->assertStringContainsString( ':max', $messages['6.max'] );
        $this->assertStringContainsString( ':min', $messages['15.min'] );
        $this->assertStringContainsString( ':max', $messages['15.max'] );

        $this->assertArrayNotHasKey( '10.required', $messages );
        $this->assertArrayNotHasKey( '12.required', $messages );
        $this->assertArrayNotHasKey( '13.required', $messages );
        $this->assertArrayNotHasKey( '14.required', $messages );
    }

    public function test_submit() {
        $wpforms = $this->get_integration_instance();
        $form    = $wpforms->expose_get_form( $this->form_id );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'wpforms' );
        $wp_request->set_param( '1', 'Hello' );
        $wp_request->set_param( '10', 'World' );
        $wp_request->set_param( '7', 'test@example.com' );
        $wp_request->set_param( '11', 'test2@example.com' );
        $wp_request->set_param( '2', 'First Choice' );
        $wp_request->set_param( '12', 'Second Choice' );
        $wp_request->set_param( '4', [ 'First Choice' ] );
        $wp_request->set_param( '13', [ 'Second Choice' ] );
        $wp_request->set_param( '5', '42' );
        $wp_request->set_param( '14', '10' );
        $wp_request->set_param( '6', '20' );
        $wp_request->set_param( '15', '15' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );

        $request = new Request( $wp_request );

        $captured_entry     = null;
        $captured_form_data = null;

        add_action(
            'wpforms_process_before', function ( $entry, $form_data ) use ( &$captured_entry, &$captured_form_data ) {
                $captured_entry     = $entry;
                $captured_form_data = $form_data;
            }, 10, 2 
        );

        $wpforms->expose_submit( $request, $form );

        $this->assertNotNull( $captured_entry, 'wpforms_process_before action was not fired' );

        $process = wpforms()->obj( 'process' );
        $this->assertEmpty( $process->errors, 'WPForms validation errors: ' . print_r( $process->errors, true ) );
        // WPForms Lite does not persist entries, so entry_id is always 0.
        $this->assertEquals( $this->form_id, $captured_entry['id'] );
        $this->assertArrayHasKey( 'fields', $captured_entry );

        $fields = $captured_entry['fields'];
        $this->assertEquals( 'Hello', $fields['1'] );
        $this->assertEquals( 'World', $fields['10'] );
        $this->assertEquals( 'test@example.com', $fields['7'] );
        $this->assertEquals( 'test2@example.com', $fields['11'] );
        $this->assertEquals( 'First Choice', $fields['2'] );
        $this->assertEquals( 'Second Choice', $fields['12'] );
        $this->assertEquals( [ 'First Choice' ], $fields['4'] );
        $this->assertEquals( [ 'Second Choice' ], $fields['13'] );
        $this->assertEquals( '42', $fields['5'] );
        $this->assertEquals( '10', $fields['14'] );
        $this->assertEquals( '20', $fields['6'] );
        $this->assertEquals( '15', $fields['15'] );
        $this->assertArrayNotHasKey( 'unsupported', $fields );
    }
}
