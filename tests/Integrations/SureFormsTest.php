<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\SureForms;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableSureForms extends SureForms {
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

class SureFormsTest extends \WP_UnitTestCase {
    private $form_id;

    public function setUp(): void {
        parent::setUp();

        $blocks = [
            $this->make_block(
                'srfm/input', [
                    'block_id'     => '5f233a44',
                    'label'        => 'Single Line Text',
                    'slug'         => 'text-field',
                    'required'     => false,
                    'textLength'   => '',
                    'placeholder'  => '',
                    'defaultValue' => '',
                ] 
            ),
            $this->make_block(
                'srfm/email', [
                    'block_id'     => '846eafba',
                    'label'        => 'Email',
                    'slug'         => 'email',
                    'required'     => true,
                    'placeholder'  => '',
                    'defaultValue' => '',
                ] 
            ),
            $this->make_block(
                'srfm/number', [
                    'block_id'     => 'fe3d1169',
                    'label'        => 'Numbers',
                    'slug'         => 'number',
                    'required'     => false,
                    'minValue'     => '',
                    'maxValue'     => '',
                    'placeholder'  => '',
                    'defaultValue' => '',
                ] 
            ),
            $this->make_block(
                'srfm/url', [
                    'block_id'     => '766ee324',
                    'label'        => 'Website',
                    'slug'         => 'url',
                    'required'     => true,
                    'placeholder'  => '',
                    'defaultValue' => '',
                ] 
            ),
            $this->make_block(
                'srfm/checkbox', [
                    'block_id' => 'da780916',
                    'label'    => 'Checkboxes',
                    'slug'     => 'checkbox',
                    'required' => false,
                ] 
            ),
            $this->make_block(
                'srfm/gdpr', [
                    'block_id' => '6ec80c0e',
                    'label'    => 'I consent',
                    'slug'     => 'consent',
                    'required' => true,
                ] 
            ),
            $this->make_block(
                'srfm/dropdown', [
                    'block_id' => 'd1a53b24',
                    'label'    => 'Dropdown',
                    'slug'     => 'dropdown',
                    'required' => false,
                    'options'  => [
                        [ 'label' => 'First Choice', 'value' => '' ],
                        [ 'label' => 'Second Choice', 'value' => '' ],
                        [ 'label' => 'Third Choice', 'value' => '' ],
                    ],
                ] 
            ),
        ];

        $post_content = '';
        foreach ( $blocks as $block ) {
            $post_content .= $block . "\n";
        }

        $this->form_id = wp_insert_post(
            [
                'post_title'   => 'Test SureForms Form',
                'post_type'    => 'sureforms_form',
                'post_status'  => 'publish',
                'post_content' => $post_content,
            ] 
        );

        update_post_meta( $this->form_id, '_srfm_submit_button_text', 'Submit' );
        update_post_meta( $this->form_id, '_srfm_confirmation_type', 'message' );
        update_post_meta( $this->form_id, '_srfm_confirmation_message', 'Thanks for contacting us!' );
    }

    private function make_block( string $name, array $attrs ): string {
        $json = json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        return '<!-- wp:' . $name . ' ' . $json . ' /-->';
    }

    public function tearDown(): void {
        wp_delete_post( $this->form_id, true );
        parent::tearDown();
    }

    private function get_integration_instance(): TestableSureForms {
        return new TestableSureForms( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    private function build_expected_field_name( array $field, int &$dropdown_counter ): string {
        $type = $field['type'];
        if ( $type === 'dropdown' ) {
            $dropdown_counter++;
            $type_part = "dropdown-{$dropdown_counter}";
        } else {
            $type_part = $type;
        }
        $label        = ! empty( $field['label'] ) ? $field['label'] : $type;
        $base64_label = \SRFM\Inc\Helper::encrypt( $label );
        return "srfm-{$type_part}-{$field['block_id']}-lbl-{$base64_label}-{$field['slug']}";
    }

    public function test_get_key() {
        $sureforms = new SureForms( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'sureforms', $sureforms->get_key() );
    }

    public function test_get_form() {
        $sureforms = $this->get_integration_instance();
        $form      = $sureforms->expose_get_form( $this->form_id );

        $this->assertNotEmpty( $form );
        $this->assertEquals( $this->form_id, $form['id'] );
        $this->assertNotEmpty( $form['fields'] );
        $this->assertCount( 7, $form['fields'] );
    }

    public function test_get_validation_rules() {
        $sureforms = $this->get_integration_instance();
        $form      = $sureforms->expose_get_form( $this->form_id );
        $rules     = $sureforms->expose_get_validation_rules( $form );

        $expected_slugs = [
            'text-field',
            'email',
            'number',
            'url',
            'checkbox',
            'consent',
            'dropdown',
        ];

        foreach ( $expected_slugs as $slug ) {
            $this->assertArrayHasKey( $slug, $rules, "Missing rule for slug: {$slug}" );
        }

        $this->assertEquals( 'string', $rules['text-field'] );
        $this->assertEquals( 'string|email|required', $rules['email'] );
        $this->assertEquals( 'numeric', $rules['number'] );
        $this->assertEquals( 'string|url|required', $rules['url'] );
        $this->assertEquals( 'string', $rules['checkbox'] );
        $this->assertEquals( 'string|required', $rules['consent'] );
        $this->assertEquals( 'string|max:255', $rules['dropdown'] );
    }

    public function test_get_validation_messages() {
        $sureforms = $this->get_integration_instance();
        $form      = $sureforms->expose_get_form( $this->form_id );
        $messages  = $sureforms->expose_get_validation_messages( $form );

        // Required fields (email, url, consent are required in the fixture).
        $this->assertArrayHasKey( 'email.required', $messages );
        $this->assertArrayHasKey( 'url.required', $messages );
        $this->assertArrayHasKey( 'consent.required', $messages );

        // Non-required fields have no required message.
        $this->assertArrayNotHasKey( 'text-field.required', $messages );
        $this->assertArrayNotHasKey( 'number.required', $messages );
        $this->assertArrayNotHasKey( 'checkbox.required', $messages );
        $this->assertArrayNotHasKey( 'dropdown.required', $messages );

        // Type-rule messages for email and url fields.
        $this->assertArrayHasKey( 'email.email', $messages );
        $this->assertArrayHasKey( 'url.url', $messages );

        // The fixture's number field has no min/max, so no bound messages.
        $this->assertArrayNotHasKey( 'number.min', $messages );
        $this->assertArrayNotHasKey( 'number.max', $messages );
    }

    public function test_get_validation_messages_number_min_max_placeholders() {
        $sureforms = $this->get_integration_instance();

        $form = [
            'fields' => [
                [
                    'type'     => 'number',
                    'slug'     => 'qty',
                    'required' => true,
                    'min'      => 5,
                    'max'      => 20,
                ],
            ],
        ];

        $messages = $sureforms->expose_get_validation_messages( $form );

        $this->assertArrayHasKey( 'qty.required', $messages );
        $this->assertArrayHasKey( 'qty.min', $messages );
        $this->assertArrayHasKey( 'qty.max', $messages );

        // SureForms stores "%s" placeholders; they must be converted to :min/:max
        // so the WpMVC validator can substitute the bound value.
        $this->assertStringContainsString( ':min', $messages['qty.min'] );
        $this->assertStringContainsString( ':max', $messages['qty.max'] );
        $this->assertStringNotContainsString( '%s', $messages['qty.min'] );
        $this->assertStringNotContainsString( '%s', $messages['qty.max'] );
    }

    public function test_get_validation_messages_reads_settings_option() {
        update_option(
            'srfm_default_dynamic_block_option', [
                'srfm_valid_email' => 'CUSTOM_EMAIL_MSG',
            ] 
        );

        $sureforms = $this->get_integration_instance();
        $form      = $sureforms->expose_get_form( $this->form_id );
        $messages  = $sureforms->expose_get_validation_messages( $form );

        // Proves the message is read from the SureForms settings option, not hardcoded.
        $this->assertSame( 'CUSTOM_EMAIL_MSG', $messages['email.email'] );

        delete_option( 'srfm_default_dynamic_block_option' );
    }

    public function test_submit_entries_add_directly() {
        if ( ! class_exists( '\SRFM\Inc\Database\Tables\Entries' ) ) {
            $this->markTestSkipped( 'SureForms Entries table class not available' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'srfm_entries';

        $data = [
            'form_id'    => $this->form_id,
            'form_data'  => [ 'field' => 'value' ],
            'created_at' => current_time( 'mysql' ),
        ];

        $entry_id = \SRFM\Inc\Database\Tables\Entries::add( $data );
        $this->assertGreaterThan( 0, $entry_id, 'Entries::add() should return a positive ID' );

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE ID = %d", $table, $entry_id ), ARRAY_A );
        $this->assertNotEmpty( $row, "Row not found in {$table} after Entries::add()" );
    }

    public function test_submit() {
        $sureforms = $this->get_integration_instance();
        $form      = $sureforms->expose_get_form( $this->form_id );
        $this->assertNotEmpty( $form, 'Form not retrieved' );

        $slug_to_value = [
            'text-field' => 'Hello',
            'email'      => 'test@example.com',
            'number'     => '42',
            'url'        => 'https://example.com',
            'checkbox'   => 'on',
            'consent'    => 'on',
            'dropdown'   => 'First Choice',
        ];

        $wp_request = new \WP_REST_Request();
        $wp_request->set_param( 'form_id', $this->form_id );
        $wp_request->set_param( 'integration', 'sureforms' );
        foreach ( $slug_to_value as $slug => $value ) {
            $wp_request->set_param( $slug, $value );
        }
        $wp_request->set_param( 'unsupported', 'should be skipped' );

        $request = new Request( $wp_request );

        $passed_form_data = null;
        add_filter(
            'srfm_form_submit_data', function ( $form_data ) use ( &$passed_form_data ) {
                $passed_form_data = $form_data;
                return $form_data;
            } 
        );

        $captured_entry_id = null;
        $entry             = null;
        add_action(
            'srfm_form_submit', function ( $response ) use ( &$captured_entry_id, &$entry ) {
                if ( ! empty( $response['entry_id'] ) ) {
                    $captured_entry_id = $response['entry_id'];
                    $entry             = \SRFM\Inc\Database\Tables\Entries::get( $captured_entry_id );
                }
            } 
        );

        $sureforms->expose_submit( $request, $form );

        // Verify the raw slug-value pairs were passed to handle_form_entry()
        $this->assertNotNull( $passed_form_data, 'srfm_form_submit_data was not fired' );
        $this->assertSame( $this->form_id, (int) $passed_form_data['form-id'], 'form-id mismatch' );

        // Verify the full SureForms field names are used as keys
        $dropdown_counter = 0;
        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['slug'] ) || empty( $field['type'] ) ) {
                continue;
            }
            $slug = $field['slug'];
            if ( ! isset( $slug_to_value[$slug] ) ) {
                continue;
            }
            $expected_field_name = $this->build_expected_field_name( $field, $dropdown_counter );
            $this->assertArrayHasKey( $expected_field_name, $passed_form_data, "Missing field name: {$expected_field_name}" );
            $this->assertSame( $slug_to_value[$slug], $passed_form_data[$expected_field_name], "Value mismatch for: {$expected_field_name}" );
        }
        $this->assertArrayNotHasKey( 'unsupported', $passed_form_data );

        // Verify the entry was created
        $this->assertNotNull( $captured_entry_id, 'srfm_form_submit action was not fired or entry_id missing' );
        $this->assertGreaterThan( 0, $captured_entry_id, 'Entry ID should be a positive integer' );
        $this->assertNotEmpty( $entry, 'Entry not found via Entries::get() inside srfm_form_submit hook' );
        $this->assertSame( $this->form_id, (int) $entry['form_id'], 'Entry form_id mismatch' );
    }
}
