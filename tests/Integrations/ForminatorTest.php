<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\Forminator;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableForminator extends Forminator {
    public function expose_get_validation_rules( array $form ) {
        return $this->get_validation_rules( $form );
    }

    public function expose_get_validation_messages( array $form ) {
        return $this->get_validation_messages( $form );
    }

    public function expose_submit( Request $request, array $form ) {
        return $this->submit( $request, $form );
    }

    public function expose_get_form( int $id ) {
        return $this->get_form( $id );
    }
}

class ForminatorTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        if ( ! post_type_exists( 'forminator_forms' ) ) {
            register_post_type( 'forminator_forms' );
        }

        $this->form_id = wp_insert_post(
            [
                'post_title'  => 'Test Forminator Form',
                'post_type'   => 'forminator_forms',
                'post_status' => 'publish',
            ]
        );

        $fields = [
            [
                'id'               => 'text-1',
                'element_id'       => 'text-1',
                'type'             => 'text',
                'field_label'      => 'Full Name',
                'required'         => 'true',
                'required_message' => 'Please enter your full name.',
                'wrapper_id'       => 'wrapper-1',
                'placeholder'      => '',
            ],
            [
                'id'          => 'text-2',
                'element_id'  => 'text-2',
                'type'        => 'text',
                'field_label' => 'Company',
                'required'    => 'false',
                'wrapper_id'  => 'wrapper-2',
                'placeholder' => '',
            ],
            [
                'id'                 => 'email-1',
                'element_id'         => 'email-1',
                'type'               => 'email',
                'field_label'        => 'Email',
                'required'           => 'true',
                'required_message'   => 'Please enter your email address.',
                'validation_message' => 'Please provide a valid email.',
                'wrapper_id'         => 'wrapper-3',
                'placeholder'        => '',
            ],
            [
                'id'          => 'email-2',
                'element_id'  => 'email-2',
                'type'        => 'email',
                'field_label' => 'Secondary Email',
                'required'    => 'false',
                'wrapper_id'  => 'wrapper-4',
                'placeholder' => '',
            ],
            [
                'id'                 => 'url-1',
                'element_id'         => 'url-1',
                'type'               => 'url',
                'field_label'        => 'Website',
                'required'           => 'true',
                'required_message'   => 'Please enter your website URL.',
                'validation_message' => 'Please provide a valid URL.',
                'wrapper_id'         => 'wrapper-5',
                'placeholder'        => '',
            ],
            [
                'id'          => 'url-2',
                'element_id'  => 'url-2',
                'type'        => 'url',
                'field_label' => 'Portfolio',
                'required'    => 'false',
                'wrapper_id'  => 'wrapper-6',
                'placeholder' => '',
            ],
            [
                'id'                => 'number-1',
                'element_id'        => 'number-1',
                'type'              => 'number',
                'field_label'       => 'Age',
                'required'          => 'true',
                'required_message'  => 'Please enter your age.',
                'min'               => '1',
                'max'               => '150',
                'limit_min_message' => 'Minimum value is {0}.',
                'limit_max_message' => 'Maximum value is {0}.',
                'wrapper_id'        => 'wrapper-7',
            ],
            [
                'id'          => 'number-2',
                'element_id'  => 'number-2',
                'type'        => 'number',
                'field_label' => 'Score',
                'required'    => 'false',
                'min'         => '0',
                'max'         => '100',
                'wrapper_id'  => 'wrapper-8',
            ],
            [
                'id'          => 'radio-1',
                'element_id'  => 'radio-1',
                'type'        => 'radio',
                'field_label' => 'Gender',
                'required'    => 'true',
                'wrapper_id'  => 'wrapper-9',
                'options'     => [
                    [
                        'label' => 'Male',
                        'value' => 'male',
                    ],
                    [
                        'label' => 'Female',
                        'value' => 'female',
                    ],
                ],
            ],
            [
                'id'          => 'radio-2',
                'element_id'  => 'radio-2',
                'type'        => 'radio',
                'field_label' => 'Preference',
                'required'    => 'false',
                'wrapper_id'  => 'wrapper-10',
                'options'     => [
                    [
                        'label' => 'Option A',
                        'value' => 'a',
                    ],
                    [
                        'label' => 'Option B',
                        'value' => 'b',
                    ],
                ],
            ],
            [
                'id'          => 'checkbox-1',
                'element_id'  => 'checkbox-1',
                'type'        => 'checkbox',
                'field_label' => 'Hobbies',
                'required'    => 'true',
                'wrapper_id'  => 'wrapper-11',
                'options'     => [
                    [
                        'label' => 'Reading',
                        'value' => 'reading',
                    ],
                    [
                        'label' => 'Music',
                        'value' => 'music',
                    ],
                ],
            ],
            [
                'id'          => 'checkbox-2',
                'element_id'  => 'checkbox-2',
                'type'        => 'checkbox',
                'field_label' => 'Interests',
                'required'    => 'false',
                'wrapper_id'  => 'wrapper-12',
                'options'     => [
                    [
                        'label' => 'Tech',
                        'value' => 'tech',
                    ],
                    [
                        'label' => 'Sports',
                        'value' => 'sports',
                    ],
                ],
            ],
            [
                'id'          => 'select-1',
                'element_id'  => 'select-1',
                'type'        => 'select',
                'field_label' => 'Country',
                'required'    => 'true',
                'wrapper_id'  => 'wrapper-13',
                'options'     => [
                    [
                        'label' => 'USA',
                        'value' => 'us',
                    ],
                    [
                        'label' => 'UK',
                        'value' => 'uk',
                    ],
                ],
            ],
            [
                'id'          => 'select-2',
                'element_id'  => 'select-2',
                'type'        => 'select',
                'field_label' => 'City',
                'required'    => 'false',
                'wrapper_id'  => 'wrapper-14',
                'options'     => [
                    [
                        'label' => 'NYC',
                        'value' => 'nyc',
                    ],
                    [
                        'label' => 'London',
                        'value' => 'london',
                    ],
                ],
            ],
            [
                'id'          => 'date-1',
                'element_id'  => 'date-1',
                'type'        => 'date',
                'field_label' => 'Birth Date',
                'required'    => 'true',
                'wrapper_id'  => 'wrapper-15',
            ],
            [
                'id'          => 'date-2',
                'element_id'  => 'date-2',
                'type'        => 'date',
                'field_label' => 'Start Date',
                'required'    => 'false',
                'wrapper_id'  => 'wrapper-16',
            ],
            [
                'id'          => 'rating-1',
                'element_id'  => 'rating-1',
                'type'        => 'rating',
                'field_label' => 'Satisfaction',
                'required'    => 'true',
                'max_rating'  => '5',
                'wrapper_id'  => 'wrapper-17',
            ],
            [
                'id'          => 'rating-2',
                'element_id'  => 'rating-2',
                'type'        => 'rating',
                'field_label' => 'Quality',
                'required'    => 'false',
                'max_rating'  => '10',
                'wrapper_id'  => 'wrapper-18',
            ],
            [
                'id'                => 'slider-1',
                'element_id'        => 'slider-1',
                'type'              => 'slider',
                'field_label'       => 'Budget',
                'required'          => 'true',
                'min'               => '0',
                'max'               => '10000',
                'limit_min_message' => 'Budget must be at least {0}.',
                'limit_max_message' => 'Budget must not exceed {0}.',
                'wrapper_id'        => 'wrapper-19',
            ],
            [
                'id'          => 'textarea-1',
                'element_id'  => 'textarea-1',
                'type'        => 'textarea',
                'field_label' => 'Bio',
                'required'    => 'false',
                'wrapper_id'  => 'wrapper-20',
            ],
        ];

        update_post_meta(
            $this->form_id,
            'forminator_form_meta',
            [
                'fields'   => $fields,
                'settings' => [
                    'form_id' => (string) $this->form_id,
                ],
            ]
        );
    }

    public function tearDown(): void {
        global $wpdb;
        $entry_table = $wpdb->prefix . 'frmt_form_entry';
        $meta_table  = $wpdb->prefix . 'frmt_form_entry_meta';

        $entry_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT entry_id FROM {$entry_table} WHERE form_id = %d", $this->form_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        if ( ! empty( $entry_ids ) ) {
            $ids_placeholder = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare( "DELETE FROM {$meta_table} WHERE entry_id IN ({$ids_placeholder})", $entry_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            );
        }

        $wpdb->delete( $entry_table, [ 'form_id' => $this->form_id ] );
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableForminator {
        return new TestableForminator( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $forminator = new Forminator( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'forminator', $forminator->get_key() );
    }

    public function test_get_form() {
        $forminator = $this->get_integration_instance();
        $form       = $forminator->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
    }

    public function test_get_form_excludes_unpublished_form() {
        wp_update_post( [ 'ID' => $this->form_id, 'post_status' => 'draft' ] );

        $forminator = $this->get_integration_instance();
        $form       = $forminator->expose_get_form( $this->form_id );
        $this->assertEmpty( $form );
    }

    public function test_get_validation_rules() {
        $forminator = $this->get_integration_instance();
        $form       = $forminator->expose_get_form( $this->form_id );
        $rules      = $forminator->expose_get_validation_rules( $form );

        $expected_fields = [
            'text-1',
            'text-2',
            'email-1',
            'email-2',
            'url-1',
            'url-2',
            'number-1',
            'number-2',
            'radio-1',
            'radio-2',
            'checkbox-1',
            'checkbox-2',
            'select-1',
            'select-2',
            'date-1',
            'date-2',
            'rating-1',
            'rating-2',
            'slider-1',
            'textarea-1',
        ];

        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules );
        }

        $this->assertArrayNotHasKey( 'unsupported', $rules );

        // text-1: text, required → string|required
        $text_1_rules = explode( '|', $rules['text-1'] );
        $this->assertContains( 'string', $text_1_rules );
        $this->assertContains( 'required', $text_1_rules );
        $this->assertCount( 2, $text_1_rules );

        // text-2: text, not required → string
        $text_2_rules = explode( '|', $rules['text-2'] );
        $this->assertEquals( [ 'string' ], $text_2_rules );

        // textarea-1: textarea standardizes to text, not required → string
        $textarea_1_rules = explode( '|', $rules['textarea-1'] );
        $this->assertEquals( [ 'string' ], $textarea_1_rules );

        // email-1: email, required → string|email|required
        $email_1_rules = explode( '|', $rules['email-1'] );
        $this->assertContains( 'string', $email_1_rules );
        $this->assertContains( 'email', $email_1_rules );
        $this->assertContains( 'required', $email_1_rules );
        $this->assertCount( 3, $email_1_rules );

        // email-2: email, not required → string|email
        $email_2_rules = explode( '|', $rules['email-2'] );
        $this->assertContains( 'string', $email_2_rules );
        $this->assertContains( 'email', $email_2_rules );
        $this->assertCount( 2, $email_2_rules );

        // url-1: url, required → string|url|required
        $url_1_rules = explode( '|', $rules['url-1'] );
        $this->assertContains( 'string', $url_1_rules );
        $this->assertContains( 'url', $url_1_rules );
        $this->assertContains( 'required', $url_1_rules );
        $this->assertCount( 3, $url_1_rules );

        // number-1: number, required, min=1, max=150 → numeric|min:1|max:150|required
        $number_1_rules = explode( '|', $rules['number-1'] );
        $this->assertContains( 'numeric', $number_1_rules );
        $this->assertContains( 'min:1', $number_1_rules );
        $this->assertContains( 'max:150', $number_1_rules );
        $this->assertContains( 'required', $number_1_rules );
        $this->assertCount( 4, $number_1_rules );

        // checkbox-1: checkbox, required → array|required
        $checkbox_1_rules = explode( '|', $rules['checkbox-1'] );
        $this->assertContains( 'array', $checkbox_1_rules );
        $this->assertContains( 'required', $checkbox_1_rules );
        $this->assertCount( 2, $checkbox_1_rules );

        // rating-1: rating, required, max_rating=5 → integer|max:5|required
        $rating_1_rules = explode( '|', $rules['rating-1'] );
        $this->assertContains( 'integer', $rating_1_rules );
        $this->assertContains( 'max:5', $rating_1_rules );
        $this->assertContains( 'required', $rating_1_rules );
        $this->assertCount( 3, $rating_1_rules );

        // slider-1: slider, required, min=0, max=10000 → numeric|min:0|max:10000|required
        $slider_1_rules = explode( '|', $rules['slider-1'] );
        $this->assertContains( 'numeric', $slider_1_rules );
        $this->assertContains( 'min:0', $slider_1_rules );
        $this->assertContains( 'max:10000', $slider_1_rules );
        $this->assertContains( 'required', $slider_1_rules );
        $this->assertCount( 4, $slider_1_rules );
    }

    public function test_get_validation_messages() {
        $forminator = $this->get_integration_instance();
        $form       = $forminator->expose_get_form( $this->form_id );
        $messages   = $forminator->expose_get_validation_messages( $form );

        // Custom required_message from field settings.
        $this->assertArrayHasKey( 'text-1.required', $messages );
        $this->assertEquals( 'Please enter your full name.', $messages['text-1.required'] );

        $this->assertArrayHasKey( 'email-1.required', $messages );
        $this->assertEquals( 'Please enter your email address.', $messages['email-1.required'] );

        $this->assertArrayHasKey( 'url-1.required', $messages );
        $this->assertEquals( 'Please enter your website URL.', $messages['url-1.required'] );

        $this->assertArrayHasKey( 'number-1.required', $messages );
        $this->assertEquals( 'Please enter your age.', $messages['number-1.required'] );

        // Default required_message per type (no custom required_message set).
        $this->assertArrayHasKey( 'radio-1.required', $messages );
        $this->assertEquals( 'This field is required. Please select a value.', $messages['radio-1.required'] );

        $this->assertArrayHasKey( 'checkbox-1.required', $messages );
        $this->assertEquals( 'This field is required. Please select a value.', $messages['checkbox-1.required'] );

        $this->assertArrayHasKey( 'select-1.required', $messages );
        $this->assertEquals( 'This field is required. Please select a value.', $messages['select-1.required'] );

        $this->assertArrayHasKey( 'date-1.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['date-1.required'] );

        $this->assertArrayHasKey( 'rating-1.required', $messages );
        $this->assertEquals( 'This field is required. Please select a rating.', $messages['rating-1.required'] );

        $this->assertArrayHasKey( 'slider-1.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['slider-1.required'] );

        // Non-required fields should NOT have required message.
        $this->assertArrayNotHasKey( 'text-2.required', $messages );
        $this->assertArrayNotHasKey( 'email-2.required', $messages );
        $this->assertArrayNotHasKey( 'url-2.required', $messages );
        $this->assertArrayNotHasKey( 'number-2.required', $messages );
        $this->assertArrayNotHasKey( 'radio-2.required', $messages );
        $this->assertArrayNotHasKey( 'checkbox-2.required', $messages );
        $this->assertArrayNotHasKey( 'select-2.required', $messages );
        $this->assertArrayNotHasKey( 'date-2.required', $messages );
        $this->assertArrayNotHasKey( 'rating-2.required', $messages );

        // Email format message — custom.
        $this->assertArrayHasKey( 'email-1.email', $messages );
        $this->assertEquals( 'Please provide a valid email.', $messages['email-1.email'] );

        // Email format message — default (no validation_message set).
        $this->assertArrayHasKey( 'email-2.email', $messages );
        $this->assertEquals( 'This is not a valid email.', $messages['email-2.email'] );

        // URL format message — custom.
        $this->assertArrayHasKey( 'url-1.url', $messages );
        $this->assertEquals( 'Please provide a valid URL.', $messages['url-1.url'] );

        // URL format message — default.
        $this->assertArrayHasKey( 'url-2.url', $messages );
        $this->assertEquals( 'Please enter a valid Website URL (e.g. https://wpmudev.com/).', $messages['url-2.url'] );

        // Numeric message for number, rating, slider.
        $this->assertArrayHasKey( 'number-1.numeric', $messages );
        $this->assertEquals( 'This is not a valid number.', $messages['number-1.numeric'] );

        $this->assertArrayHasKey( 'rating-1.numeric', $messages );
        $this->assertEquals( 'This is not a valid number.', $messages['rating-1.numeric'] );

        $this->assertArrayHasKey( 'slider-1.numeric', $messages );
        $this->assertEquals( 'This is not a valid number.', $messages['slider-1.numeric'] );

        // Date message.
        $this->assertArrayHasKey( 'date-1.date', $messages );
        $this->assertEquals( 'Please enter a valid date.', $messages['date-1.date'] );

        // Min/max messages — custom, with {0} converted to :min/:max.
        $this->assertArrayHasKey( 'number-1.min', $messages );
        $this->assertEquals( 'Minimum value is :min.', $messages['number-1.min'] );

        $this->assertArrayHasKey( 'number-1.max', $messages );
        $this->assertEquals( 'Maximum value is :max.', $messages['number-1.max'] );

        $this->assertArrayHasKey( 'slider-1.min', $messages );
        $this->assertEquals( 'Budget must be at least :min.', $messages['slider-1.min'] );

        $this->assertArrayHasKey( 'slider-1.max', $messages );
        $this->assertEquals( 'Budget must not exceed :max.', $messages['slider-1.max'] );

        // textarea-1 is not required, so no required message is generated.
        $this->assertArrayNotHasKey( 'textarea-1.required', $messages );
    }

    public function test_submit() {
        global $wpdb;

        $forminator = $this->get_integration_instance();
        $form       = $forminator->expose_get_form( $this->form_id );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'forminator' );
        $wp_request->set_param( 'text-1', 'John' );
        $wp_request->set_param( 'email-1', 'john@example.com' );
        $wp_request->set_param( 'url-1', 'https://example.com' );
        $wp_request->set_param( 'number-1', '25' );
        $wp_request->set_param( 'radio-1', 'male' );
        $wp_request->set_param( 'checkbox-1', [ 'reading', 'music' ] );
        $wp_request->set_param( 'select-1', 'us' );
        $wp_request->set_param( 'date-1', '2024-01-15' );
        $wp_request->set_param( 'rating-1', '4' );
        $wp_request->set_param( 'slider-1', '5000' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );
        $request = new Request( $wp_request );

        $captured_mail_data = null;
        $captured_entry     = null;

        add_action(
            'forminator_custom_form_mail_before_send_mail',
            function ( $mail, $custom_form, $data, $entry ) use ( &$captured_mail_data, &$captured_entry ) {
                $captured_mail_data = $data;
                $captured_entry     = $entry;
            },
            10,
            4
        );

        $forminator->expose_submit( $request, $form );

        // Verify the mail sender was triggered with correct data.
        $this->assertNotNull( $captured_mail_data, 'Email notification was not triggered.' );
        $this->assertSame( 'John', $captured_mail_data['text-1'] );
        $this->assertSame( 'john@example.com', $captured_mail_data['email-1'] );
        $this->assertSame( 'https://example.com', $captured_mail_data['url-1'] );
        $this->assertSame( '25', $captured_mail_data['number-1'] );
        $this->assertSame( 'male', $captured_mail_data['radio-1'] );
        $this->assertSame( 'us', $captured_mail_data['select-1'] );
        $this->assertSame( '2024-01-15', $captured_mail_data['date-1'] );
        $this->assertSame( '4', $captured_mail_data['rating-1'] );
        $this->assertSame( '5000', $captured_mail_data['slider-1'] );
        $this->assertEqualsCanonicalizing( [ 'reading', 'music' ], $captured_mail_data['checkbox-1'] );

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}frmt_form_entry WHERE form_id = %d",
                $this->form_id
            ),
            ARRAY_A
        );

        $this->assertCount( 1, $entries );
        $entry_id = $entries[0]['entry_id'];

        $meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}frmt_form_entry_meta WHERE entry_id = %d",
                $entry_id
            ),
            ARRAY_A
        );

        $this->assertCount( 10, $meta );
        $names = wp_list_pluck( $meta, 'meta_key' );
        $this->assertContains( 'text-1', $names );
        $this->assertContains( 'email-1', $names );
        $this->assertContains( 'url-1', $names );
        $this->assertContains( 'number-1', $names );
        $this->assertContains( 'radio-1', $names );
        $this->assertContains( 'checkbox-1', $names );
        $this->assertContains( 'select-1', $names );
        $this->assertContains( 'date-1', $names );
        $this->assertContains( 'rating-1', $names );
        $this->assertContains( 'slider-1', $names );
        $this->assertNotContains( 'unsupported', $names );
        $this->assertNotContains( 'textarea-1', $names );

        $values = wp_list_pluck( $meta, 'meta_value' );
        $this->assertContains( 'John', $values );
        $this->assertContains( 'john@example.com', $values );
        $this->assertContains( 'https://example.com', $values );
        $this->assertContains( '25', $values );
        $this->assertContains( 'male', $values );
        $this->assertContains( 'us', $values );
        $this->assertContains( '2024-01-15', $values );
        $this->assertContains( '4', $values );
        $this->assertContains( '5000', $values );
    }
}
