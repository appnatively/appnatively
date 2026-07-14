<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\EverestForms;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableEverestForms extends EverestForms {
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

class EverestFormTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        if ( ! post_type_exists( 'everest_form' ) ) {
            register_post_type( 'everest_form' );
        }

        $fields = [
            '0gsjC51pek-3'  => [
                'type'     => 'text',
                'label'    => 'Single Line Text',
                'meta-key' => 'single_line_text_5165',
            ],
            'eX3iOwO3vg-13' => [
                'type'     => 'text',
                'label'    => 'Single Line Text two',
                'meta-key' => 'single_line_text_5565',
                'required' => '1',
            ],
            'gl1gQOZ2dd-4'  => [
                'type'     => 'select',
                'label'    => 'Dropdown',
                'meta-key' => 'dropdown_9842',
                'choices'  => [
                    '1' => [ 'label' => 'Option 1', 'value' => '' ],
                    '2' => [ 'label' => 'Option 2', 'value' => '' ],
                    '3' => [ 'label' => 'Option 3', 'value' => '' ],
                ],
            ],
            'mQbOUy0F6w-14' => [
                'type'                           => 'select',
                'label'                          => 'Dropdown two',
                'meta-key'                       => 'dropdown_5971',
                'required'                       => '1',
                'required_field_message_setting' => 'individual',
                'required-field-message'         => '',
                'choices'                        => [
                    '1' => [ 'label' => 'Option 1', 'value' => '' ],
                    '2' => [ 'label' => 'Option 2', 'value' => '' ],
                    '3' => [ 'label' => 'Option 3', 'value' => '' ],
                ],
            ],
            'UST3f9qmZk-6'  => [
                'type'     => 'checkbox',
                'label'    => 'Checkboxes',
                'meta-key' => 'checkboxes_1335',
                'choices'  => [
                    '1' => [ 'label' => 'First Choice', 'value' => '' ],
                    '2' => [ 'label' => 'Second Choice', 'value' => '' ],
                    '3' => [ 'label' => 'Third Choice', 'value' => '' ],
                ],
            ],
            'v1rWdbPGpV-15' => [
                'type'     => 'checkbox',
                'label'    => 'Checkboxes two',
                'meta-key' => 'checkboxes_8940',
                'required' => '1',
                'choices'  => [
                    '1' => [ 'label' => 'First Choice', 'value' => '' ],
                    '2' => [ 'label' => 'Second Choice', 'value' => '' ],
                    '3' => [ 'label' => 'Third Choice', 'value' => '' ],
                ],
            ],
            'FHUAMGRzaT-5'  => [
                'type'     => 'radio',
                'label'    => 'Multiple Choice',
                'meta-key' => 'multiple_choice_2954',
                'choices'  => [
                    '1' => [ 'label' => 'First Choice', 'value' => '' ],
                    '2' => [ 'label' => 'Second Choice', 'value' => '' ],
                    '3' => [ 'label' => 'Third Choice', 'value' => '' ],
                ],
            ],
            'XmIh8nzwe4-16' => [
                'type'     => 'radio',
                'label'    => 'Multiple Choice two',
                'meta-key' => 'multiple_choice_1576',
                'required' => '1',
                'choices'  => [
                    '1' => [ 'label' => 'First Choice', 'value' => '' ],
                    '2' => [ 'label' => 'Second Choice', 'value' => '' ],
                    '3' => [ 'label' => 'Third Choice', 'value' => '' ],
                ],
            ],
            'WiZGBhNssO-7'  => [
                'type'      => 'number',
                'label'     => 'Number',
                'meta-key'  => 'number_1071',
                'min_value' => '',
                'max_value' => '',
            ],
            'YnteIVe13J-17' => [
                'type'      => 'number',
                'label'     => 'Number',
                'meta-key'  => 'number_8777',
                'required'  => '1',
                'min_value' => '',
                'max_value' => '',
            ],
            'CeHcz7jOas-8'  => [
                'type'     => 'email',
                'label'    => 'Email',
                'meta-key' => 'email_6772',
                'required' => '1',
            ],
            '1KWUlhxh9H-18' => [
                'type'     => 'email',
                'label'    => 'Email',
                'meta-key' => 'email_6885',
            ],
            '6Fzj4kTYzw-9'  => [
                'type'     => 'url',
                'label'    => 'Website / URL',
                'meta-key' => 'website__url_5834',
            ],
            'kTb0IUqnKv-19' => [
                'type'     => 'url',
                'label'    => 'Website / URL',
                'meta-key' => 'website__url_2074',
                'required' => '1',
            ],
            'Qo2xfJg9ph-10' => [
                'type'            => 'date-time',
                'label'           => 'Date',
                'meta-key'        => 'date__time_7826',
                'datetime_format' => 'date',
            ],
            'fH9DjoVkli-20' => [
                'type'            => 'date-time',
                'label'           => 'Date two',
                'meta-key'        => 'date__time_5758',
                'required'        => '1',
                'datetime_format' => 'date',
            ],
            'xNu1qlS13M-12' => [
                'type'            => 'date-time',
                'label'           => 'Time',
                'meta-key'        => 'date__time_2340',
                'datetime_format' => 'time',
            ],
            '7b4xw1QrFk-21' => [
                'type'            => 'date-time',
                'label'           => 'Time two',
                'meta-key'        => 'date__time_4908',
                'required'        => '1',
                'datetime_format' => 'time',
            ],
            'lW5oxHEnfK-11' => [
                'type'            => 'rating',
                'label'           => 'Rating',
                'meta-key'        => 'rating_7868',
                'number_of_stars' => '5',
            ],
            'Wrvcw6q9J7-22' => [
                'type'            => 'rating',
                'label'           => 'Rating two',
                'meta-key'        => 'rating_3805',
                'required'        => '1',
                'number_of_stars' => '5',
            ],
        ];

        $form_data = [
            'form_fields' => $fields,
            'settings'    => [
                'form_title' => 'Test Everest Form',
            ],
        ];

        $this->form_id = wp_insert_post(
            [
                'post_title'   => 'Test Everest Form',
                'post_type'    => 'everest_form',
                'post_status'  => 'publish',
                'post_content' => wp_json_encode( $form_data ),
            ]
        );
    }

    public function tearDown(): void {
        global $wpdb;

        $entry_table = $wpdb->prefix . 'evf_entries';
        $meta_table  = $wpdb->prefix . 'evf_entrymeta';

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

    private function get_integration_instance(): TestableEverestForms {
        return new TestableEverestForms( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $everest_forms = new EverestForms( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'everest-forms', $everest_forms->get_key() );
    }

    public function test_get_form() {
        $everest_forms = $this->get_integration_instance();
        $form          = $everest_forms->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
    }

    public function test_get_validation_rules() {
        $everest_forms = $this->get_integration_instance();
        $form          = $everest_forms->expose_get_form( $this->form_id );
        $rules         = $everest_forms->expose_get_validation_rules( $form );

        $expected_fields = [
            '0gsjC51pek-3', 'eX3iOwO3vg-13',
            'gl1gQOZ2dd-4', 'mQbOUy0F6w-14',
            'UST3f9qmZk-6', 'v1rWdbPGpV-15',
            'FHUAMGRzaT-5', 'XmIh8nzwe4-16',
            'WiZGBhNssO-7', 'YnteIVe13J-17',
            'CeHcz7jOas-8', '1KWUlhxh9H-18',
            '6Fzj4kTYzw-9', 'kTb0IUqnKv-19',
            'Qo2xfJg9ph-10', 'fH9DjoVkli-20',
            'xNu1qlS13M-12', '7b4xw1QrFk-21',
            'lW5oxHEnfK-11', 'Wrvcw6q9J7-22',
        ];

        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules );
        }

        $this->assertArrayNotHasKey( 'unsupported', $rules );

        // text, optional → string
        $text_opt_rules = explode( '|', $rules['0gsjC51pek-3'] );
        $this->assertEquals( [ 'string' ], $text_opt_rules );

        // text, required → string|required
        $text_req_rules = explode( '|', $rules['eX3iOwO3vg-13'] );
        $this->assertContains( 'string', $text_req_rules );
        $this->assertContains( 'required', $text_req_rules );
        $this->assertCount( 2, $text_req_rules );

        // email, required → string|email|required
        $email_req_rules = explode( '|', $rules['CeHcz7jOas-8'] );
        $this->assertContains( 'string', $email_req_rules );
        $this->assertContains( 'email', $email_req_rules );
        $this->assertContains( 'required', $email_req_rules );
        $this->assertCount( 3, $email_req_rules );

        // email, optional → string|email
        $email_opt_rules = explode( '|', $rules['1KWUlhxh9H-18'] );
        $this->assertContains( 'string', $email_opt_rules );
        $this->assertContains( 'email', $email_opt_rules );
        $this->assertCount( 2, $email_opt_rules );

        // url, required → string|url|required
        $url_req_rules = explode( '|', $rules['kTb0IUqnKv-19'] );
        $this->assertContains( 'string', $url_req_rules );
        $this->assertContains( 'url', $url_req_rules );
        $this->assertContains( 'required', $url_req_rules );
        $this->assertCount( 3, $url_req_rules );

        // number, required (no min/max) → numeric|required
        $num_req_rules = explode( '|', $rules['YnteIVe13J-17'] );
        $this->assertContains( 'numeric', $num_req_rules );
        $this->assertContains( 'required', $num_req_rules );
        $this->assertCount( 2, $num_req_rules );

        // number, optional (no min/max) → numeric
        $num_opt_rules = explode( '|', $rules['WiZGBhNssO-7'] );
        $this->assertEquals( [ 'numeric' ], $num_opt_rules );

        // radio, required → string|required
        $radio_req_rules = explode( '|', $rules['XmIh8nzwe4-16'] );
        $this->assertContains( 'string', $radio_req_rules );
        $this->assertContains( 'required', $radio_req_rules );
        $this->assertCount( 2, $radio_req_rules );

        // checkbox, required → array|required
        $checkbox_req_rules = explode( '|', $rules['v1rWdbPGpV-15'] );
        $this->assertContains( 'array', $checkbox_req_rules );
        $this->assertContains( 'required', $checkbox_req_rules );
        $this->assertCount( 2, $checkbox_req_rules );

        // select, optional → string
        $select_opt_rules = explode( '|', $rules['gl1gQOZ2dd-4'] );
        $this->assertEquals( [ 'string' ], $select_opt_rules );

        // date-time (date), required → string|required
        $date_req_rules = explode( '|', $rules['fH9DjoVkli-20'] );
        $this->assertContains( 'string', $date_req_rules );
        $this->assertContains( 'required', $date_req_rules );
        $this->assertCount( 2, $date_req_rules );

        // date-time (time), optional → string
        $time_opt_rules = explode( '|', $rules['xNu1qlS13M-12'] );
        $this->assertEquals( [ 'string' ], $time_opt_rules );

        // rating, required, number_of_stars=5 → integer|max:5|required
        $rating_req_rules = explode( '|', $rules['Wrvcw6q9J7-22'] );
        $this->assertContains( 'integer', $rating_req_rules );
        $this->assertContains( 'max:5', $rating_req_rules );
        $this->assertContains( 'required', $rating_req_rules );
        $this->assertCount( 3, $rating_req_rules );

        // rating, optional, number_of_stars=5 → integer|max:5
        $rating_opt_rules = explode( '|', $rules['lW5oxHEnfK-11'] );
        $this->assertContains( 'integer', $rating_opt_rules );
        $this->assertContains( 'max:5', $rating_opt_rules );
        $this->assertCount( 2, $rating_opt_rules );
    }

    public function test_get_validation_messages() {
        $everest_forms = $this->get_integration_instance();
        $form          = $everest_forms->expose_get_form( $this->form_id );
        $messages      = $everest_forms->expose_get_validation_messages( $form );

        // Required messages — all use global default option.
        $this->assertArrayHasKey( 'eX3iOwO3vg-13.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['eX3iOwO3vg-13.required'] );

        $this->assertArrayHasKey( 'mQbOUy0F6w-14.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['mQbOUy0F6w-14.required'] );

        $this->assertArrayHasKey( 'v1rWdbPGpV-15.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['v1rWdbPGpV-15.required'] );

        $this->assertArrayHasKey( 'XmIh8nzwe4-16.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['XmIh8nzwe4-16.required'] );

        $this->assertArrayHasKey( 'YnteIVe13J-17.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['YnteIVe13J-17.required'] );

        $this->assertArrayHasKey( 'CeHcz7jOas-8.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['CeHcz7jOas-8.required'] );

        $this->assertArrayHasKey( 'kTb0IUqnKv-19.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['kTb0IUqnKv-19.required'] );

        $this->assertArrayHasKey( 'fH9DjoVkli-20.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['fH9DjoVkli-20.required'] );

        $this->assertArrayHasKey( '7b4xw1QrFk-21.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['7b4xw1QrFk-21.required'] );

        $this->assertArrayHasKey( 'Wrvcw6q9J7-22.required', $messages );
        $this->assertEquals( 'This field is required.', $messages['Wrvcw6q9J7-22.required'] );

        // Non-required fields should NOT have required message.
        $this->assertArrayNotHasKey( '0gsjC51pek-3.required', $messages );
        $this->assertArrayNotHasKey( 'gl1gQOZ2dd-4.required', $messages );
        $this->assertArrayNotHasKey( 'UST3f9qmZk-6.required', $messages );
        $this->assertArrayNotHasKey( 'FHUAMGRzaT-5.required', $messages );
        $this->assertArrayNotHasKey( 'WiZGBhNssO-7.required', $messages );
        $this->assertArrayNotHasKey( '1KWUlhxh9H-18.required', $messages );
        $this->assertArrayNotHasKey( '6Fzj4kTYzw-9.required', $messages );
        $this->assertArrayNotHasKey( 'Qo2xfJg9ph-10.required', $messages );
        $this->assertArrayNotHasKey( 'xNu1qlS13M-12.required', $messages );
        $this->assertArrayNotHasKey( 'lW5oxHEnfK-11.required', $messages );

        // Email format message.
        $this->assertArrayHasKey( 'CeHcz7jOas-8.email', $messages );
        $this->assertEquals( 'Please enter a valid email address.', $messages['CeHcz7jOas-8.email'] );

        $this->assertArrayHasKey( '1KWUlhxh9H-18.email', $messages );
        $this->assertEquals( 'Please enter a valid email address.', $messages['1KWUlhxh9H-18.email'] );

        // URL format message.
        $this->assertArrayHasKey( '6Fzj4kTYzw-9.url', $messages );
        $this->assertEquals( 'Please enter a valid URL.', $messages['6Fzj4kTYzw-9.url'] );

        $this->assertArrayHasKey( 'kTb0IUqnKv-19.url', $messages );
        $this->assertEquals( 'Please enter a valid URL.', $messages['kTb0IUqnKv-19.url'] );

        // Numeric message for number fields.
        $this->assertArrayHasKey( 'WiZGBhNssO-7.numeric', $messages );
        $this->assertEquals( 'Please enter a valid number.', $messages['WiZGBhNssO-7.numeric'] );

        $this->assertArrayHasKey( 'YnteIVe13J-17.numeric', $messages );
        $this->assertEquals( 'Please enter a valid number.', $messages['YnteIVe13J-17.numeric'] );

        // No min/max messages since min_value/max_value are empty.

        // Integer message for rating fields.
        $this->assertArrayHasKey( 'lW5oxHEnfK-11.integer', $messages );
        $this->assertEquals( 'Please enter a valid number.', $messages['lW5oxHEnfK-11.integer'] );

        $this->assertArrayHasKey( 'Wrvcw6q9J7-22.integer', $messages );
        $this->assertEquals( 'Please enter a valid number.', $messages['Wrvcw6q9J7-22.integer'] );

        // Rating max messages.
        $this->assertArrayHasKey( 'lW5oxHEnfK-11.max', $messages );
        $this->assertEquals( 'Please select a value up to :max.', $messages['lW5oxHEnfK-11.max'] );

        $this->assertArrayHasKey( 'Wrvcw6q9J7-22.max', $messages );
        $this->assertEquals( 'Please select a value up to :max.', $messages['Wrvcw6q9J7-22.max'] );
    }

    public function test_submit() {
        global $wpdb;

        $everest_forms = $this->get_integration_instance();
        $form          = $everest_forms->expose_get_form( $this->form_id );

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'everest-forms' );
        $wp_request->set_param( 'eX3iOwO3vg-13', 'John' );
        $wp_request->set_param( 'CeHcz7jOas-8', 'john@example.com' );
        $wp_request->set_param( 'kTb0IUqnKv-19', 'https://example.com' );
        $wp_request->set_param( 'YnteIVe13J-17', '25' );
        $wp_request->set_param( 'XmIh8nzwe4-16', 'First Choice' );
        $wp_request->set_param( 'v1rWdbPGpV-15', [ 'First Choice', 'Second Choice' ] );
        $wp_request->set_param( 'mQbOUy0F6w-14', 'Option 1' );
        $wp_request->set_param( 'fH9DjoVkli-20', '2024-01-15' );
        $wp_request->set_param( 'Wrvcw6q9J7-22', '4' );
        $wp_request->set_param( 'unsupported', 'should be skipped' );

        $request = new Request( $wp_request );

        $captured_fields = null;

        add_filter(
            'everest_forms_entry_email',
            function ( $send, $fields, $entry, $form_data ) use ( &$captured_fields ) {
                $captured_fields = $fields;
                return false;
            },
            10,
            4
        );

        $everest_forms->expose_submit( $request, $form );

        // Verify the email filter was triggered with correct data.
        $this->assertNotNull( $captured_fields, 'Email notification was not triggered.' );

        $this->assertSame( 'John', $captured_fields['eX3iOwO3vg-13']['value'] );
        $this->assertSame( 'john@example.com', $captured_fields['CeHcz7jOas-8']['value'] );
        $this->assertSame( 'https://example.com', $captured_fields['kTb0IUqnKv-19']['value'] );
        $this->assertSame( '25', $captured_fields['YnteIVe13J-17']['value'] );
        $this->assertSame( 'First Choice', $captured_fields['XmIh8nzwe4-16']['value'] );
        $this->assertSame( 'Option 1', $captured_fields['mQbOUy0F6w-14']['value'] );
        $this->assertSame( '2024-01-15', $captured_fields['fH9DjoVkli-20']['value'] );
        $this->assertEqualsCanonicalizing( [ 'First Choice', 'Second Choice' ], $captured_fields['v1rWdbPGpV-15']['value'] );

        // Rating is stored as an array.
        $this->assertIsArray( $captured_fields['Wrvcw6q9J7-22']['value'] );
        $this->assertSame( 4, $captured_fields['Wrvcw6q9J7-22']['value']['value'] );
        $this->assertSame( 'rating', $captured_fields['Wrvcw6q9J7-22']['value']['type'] );
        $this->assertSame( 5, $captured_fields['Wrvcw6q9J7-22']['value']['number_of_rating'] );
        $this->assertSame( 'star', $captured_fields['Wrvcw6q9J7-22']['value']['icon'] );

        // Verify entries in the database.
        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}evf_entries WHERE form_id = %d",
                $this->form_id
            ),
            ARRAY_A
        );

        $this->assertCount( 1, $entries );
        $entry_id = $entries[0]['entry_id'];

        // Verify fields stored as JSON in the `fields` column.
        $stored_fields = json_decode( $entries[0]['fields'], true );
        $this->assertNotNull( $stored_fields );
        $this->assertSame( 'John', $stored_fields['eX3iOwO3vg-13']['value'] );
        $this->assertSame( 'john@example.com', $stored_fields['CeHcz7jOas-8']['value'] );

        // Verify meta table entries (only fields with meta_key set).
        $meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}evf_entrymeta WHERE entry_id = %d",
                $entry_id
            ),
            ARRAY_A
        );

        $this->assertCount( 9, $meta );

        $meta_keys = wp_list_pluck( $meta, 'meta_key' );
        $this->assertContains( 'single_line_text_5565', $meta_keys );
        $this->assertContains( 'email_6772', $meta_keys );
        $this->assertContains( 'website__url_2074', $meta_keys );
        $this->assertContains( 'number_8777', $meta_keys );
        $this->assertContains( 'multiple_choice_1576', $meta_keys );
        $this->assertContains( 'checkboxes_8940', $meta_keys );
        $this->assertContains( 'dropdown_5971', $meta_keys );
        $this->assertContains( 'date__time_5758', $meta_keys );
        $this->assertContains( 'rating_3805', $meta_keys );
        $this->assertNotContains( 'unsupported', $meta_keys );

        // Verify specific meta values.
        $meta_values = wp_list_pluck( $meta, 'meta_value', 'meta_key' );
        $this->assertSame( 'John', $meta_values['single_line_text_5565'] );
        $this->assertSame( 'john@example.com', $meta_values['email_6772'] );
        $this->assertSame( 'https://example.com', $meta_values['website__url_2074'] );
        $this->assertSame( '25', $meta_values['number_8777'] );
        $this->assertSame( 'First Choice', $meta_values['multiple_choice_1576'] );
        $this->assertSame( 'Option 1', $meta_values['dropdown_5971'] );
        $this->assertSame( '2024-01-15', $meta_values['date__time_5758'] );
    }
}
