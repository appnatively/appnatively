<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Forms\Formidable;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class TestableFormidable extends Formidable {
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

class FormidableTest extends \WP_UnitTestCase {
    private function get_integration_instance(): TestableFormidable {
        return new TestableFormidable( \Crafium\AppNatively\WpMVC\App::instance() );
    }

    public function test_get_key() {
        $formidable = new Formidable( \Crafium\AppNatively\WpMVC\App::instance() );
        $this->assertEquals( 'formidable', $formidable->get_key() );
    }

    public function test_get_validation_rules() {
        $formidable = $this->get_integration_instance();

        $form = [
            'id'     => 1,
            'fields' => [
                [
                    'id'        => 25,
                    'type'      => 'text',
                    'field_key' => 'full_name',
                    'required'  => false,
                ],
                [
                    'id'        => 26,
                    'type'      => 'text',
                    'field_key' => 'company',
                    'required'  => true,
                ],
                [
                    'id'        => 27,
                    'type'      => 'email',
                    'field_key' => 'email',
                    'required'  => true,
                ],
                [
                    'id'        => 28,
                    'type'      => 'email',
                    'field_key' => 'secondary_email',
                    'required'  => false,
                ],
                [
                    'id'        => 29,
                    'type'      => 'url',
                    'field_key' => 'website',
                    'required'  => true,
                ],
                [
                    'id'        => 30,
                    'type'      => 'number',
                    'field_key' => 'age',
                    'required'  => true,
                ],
                [
                    'id'        => 31,
                    'type'      => 'number',
                    'field_key' => 'score',
                    'required'  => false,
                ],
                [
                    'id'        => 32,
                    'type'      => 'radio',
                    'field_key' => 'gender',
                    'required'  => true,
                ],
                [
                    'id'        => 33,
                    'type'      => 'checkbox',
                    'field_key' => 'hobbies',
                    'required'  => true,
                ],
                [
                    'id'        => 34,
                    'type'      => 'select',
                    'field_key' => 'country',
                    'required'  => true,
                ],
                [
                    'id'        => 35,
                    'type'      => 'select',
                    'field_key' => 'city',
                    'required'  => false,
                ],
                [
                    'id'        => 36,
                    'type'      => 'textarea',
                    'field_key' => 'bio',
                    'required'  => false,
                ],
                [
                    'id'        => 37,
                    'type'      => 'gdpr',
                    'field_key' => 'gdpr_consent',
                    'required'  => true,
                ],
            ],
        ];

        $rules = $formidable->expose_get_validation_rules( $form );

        $expected_fields = [
            'full_name', 'company', 'email', 'secondary_email', 'website',
            'age', 'score', 'gender', 'hobbies', 'country', 'city',
            'bio', 'gdpr_consent',
        ];
        foreach ( $expected_fields as $field ) {
            $this->assertArrayHasKey( $field, $rules );
        }

        // full_name: text, no required → string
        $full_name_rules = explode( '|', $rules['full_name'] );
        $this->assertEquals( [ 'string' ], $full_name_rules );

        // bio: textarea standardizes to text, no required → string
        $bio_rules = explode( '|', $rules['bio'] );
        $this->assertEquals( [ 'string' ], $bio_rules );

        // company: text, required → string|required
        $company_rules = explode( '|', $rules['company'] );
        $this->assertContains( 'string', $company_rules );
        $this->assertContains( 'required', $company_rules );
        $this->assertCount( 2, $company_rules );

        // email: email, required → string|email|required
        $email_rules = explode( '|', $rules['email'] );
        $this->assertContains( 'string', $email_rules );
        $this->assertContains( 'email', $email_rules );
        $this->assertContains( 'required', $email_rules );
        $this->assertCount( 3, $email_rules );

        // secondary_email: email, no required → string|email
        $secondary_email_rules = explode( '|', $rules['secondary_email'] );
        $this->assertContains( 'string', $secondary_email_rules );
        $this->assertContains( 'email', $secondary_email_rules );
        $this->assertCount( 2, $secondary_email_rules );

        // website: url, required → string|url|required
        $website_rules = explode( '|', $rules['website'] );
        $this->assertContains( 'string', $website_rules );
        $this->assertContains( 'url', $website_rules );
        $this->assertContains( 'required', $website_rules );
        $this->assertCount( 3, $website_rules );

        // age: number, required → numeric|required
        $age_rules = explode( '|', $rules['age'] );
        $this->assertContains( 'numeric', $age_rules );
        $this->assertContains( 'required', $age_rules );
        $this->assertCount( 2, $age_rules );

        // hobbies: checkbox, required → array|required
        $hobbies_rules = explode( '|', $rules['hobbies'] );
        $this->assertContains( 'array', $hobbies_rules );
        $this->assertContains( 'required', $hobbies_rules );
        $this->assertCount( 2, $hobbies_rules );

        // gdpr_consent: gdpr consent must always be affirmatively given → integer|in:1|required
        $gdpr_rules = explode( '|', $rules['gdpr_consent'] );
        $this->assertContains( 'integer', $gdpr_rules );
        $this->assertContains( 'in:1', $gdpr_rules );
        $this->assertContains( 'required', $gdpr_rules );
        $this->assertCount( 3, $gdpr_rules );
    }

    public function test_validation_rules_with_empty_form() {
        $formidable = $this->get_integration_instance();
        $rules      = $formidable->expose_get_validation_rules( [] );
        $this->assertEmpty( $rules );
    }

    public function test_validation_rules_with_missing_fields() {
        $formidable = $this->get_integration_instance();
        $rules      = $formidable->expose_get_validation_rules( [ 'id' => 1 ] );
        $this->assertEmpty( $rules );
    }
}
