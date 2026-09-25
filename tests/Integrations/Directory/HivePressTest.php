<?php

namespace Crafium\AppNatively\Tests\Integrations;

require_once __DIR__ . "/DirectoryIntegrationTestCase.php";

class HivePressTest extends DirectoryIntegrationTestCase {
    private string $integration = "hivepress";

    private string $post_type = "hp_listing";

    private string $category_taxonomy = "hp_listing_category";

    public function setUp(): void {
        parent::setUp();

        $this->ensure_post_type( $this->post_type );
        $this->ensure_taxonomy( $this->category_taxonomy, $this->post_type );
    }

    public function test_hivepress_adapter_uses_real_directory_data(): void {
        $category_id = $this->create_term( $this->category_taxonomy, "HivePress Category" );
        $source_id   = $this->create_listing(
            $this->post_type,
            "HivePress Source",
            "publish",
            [
                "hp_address"   => "HivePress address",
                "hp_phone"     => "123",
                "hp_email"     => "hivepress@example.com",
                "hp_website"   => "https://example.com",
                "hp_featured"  => "1",
                "hp_price"     => "200",
                "hp_latitude"  => "23.7808875",
                "hp_longitude" => "90.2792371",
            ]
        );
        $related_id  = $this->create_listing( $this->post_type, "HivePress Related" );

        $this->assign_terms( $source_id, $this->category_taxonomy, [$category_id] );
        $this->assign_terms( $related_id, $this->category_taxonomy, [$category_id] );

        $this->assert_provider_filter_surface( $this->integration );
        $this->assert_listing_collection( $this->integration, $source_id, $category_id );
        $this->assert_featured_listing( $this->integration, $source_id );
        $this->assert_single_listing( $this->integration, $source_id, "HivePress Source" );
        $this->assert_related_listings( $this->integration, $source_id, $related_id );
        $this->assert_categories( $this->integration, $category_id );
        $this->assert_tag_collection( $this->integration );
        $this->assert_review_payload( $this->integration, $source_id );
    }
}
