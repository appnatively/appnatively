<?php

namespace Crafium\AppNatively\Tests\Integrations;

require_once __DIR__ . "/DirectoryIntegrationTestCase.php";

class DirectoristTest extends DirectoryIntegrationTestCase {
    private string $integration = "directorist";

    private string $post_type = "at_biz_dir";

    private string $category_taxonomy = "at_biz_dir-category";

    private string $tag_taxonomy = "at_biz_dir-tags";

    private string $location_taxonomy = "at_biz_dir-location";

    public function setUp(): void {
        parent::setUp();

        $this->post_type         = defined( "ATBDP_POST_TYPE" ) ? ATBDP_POST_TYPE : $this->post_type;
        $this->category_taxonomy = defined( "ATBDP_CATEGORY" ) ? ATBDP_CATEGORY : $this->category_taxonomy;
        $this->tag_taxonomy      = defined( "ATBDP_TAGS" ) ? ATBDP_TAGS : $this->tag_taxonomy;
        $this->location_taxonomy = defined( "ATBDP_LOCATION" ) ? ATBDP_LOCATION : $this->location_taxonomy;

        $this->ensure_post_type( $this->post_type );
        $this->ensure_taxonomy( $this->category_taxonomy, $this->post_type );
        $this->ensure_taxonomy( $this->tag_taxonomy, $this->post_type, false );
        $this->ensure_taxonomy( $this->location_taxonomy, $this->post_type );
    }

    public function test_directorist_adapter_uses_real_directory_data(): void {
        $category_id = $this->create_term( $this->category_taxonomy, "Directorist Category" );
        $tag_id      = $this->create_term( $this->tag_taxonomy, "directorist-tag" );
        $location_id = $this->create_term( $this->location_taxonomy, "Directorist Location" );
        $source_id   = $this->create_listing(
            $this->post_type,
            "Directorist Source",
            "publish",
            [
                "_address"    => "Directorist address",
                "_manual_lat" => "23.7808875",
                "_manual_lng" => "90.2792371",
                "_phone"      => "123",
                "_email"      => "directorist@example.com",
                "_website"    => "https://example.com",
                "_featured"   => "1",
                "_price"      => "100",
            ]
        );
        $related_id  = $this->create_listing( $this->post_type, "Directorist Related" );

        $this->assign_terms( $source_id, $this->category_taxonomy, [$category_id] );
        $this->assign_terms( $source_id, $this->tag_taxonomy, [$tag_id] );
        $this->assign_terms( $source_id, $this->location_taxonomy, [$location_id] );
        $this->assign_terms( $related_id, $this->category_taxonomy, [$category_id] );

        wp_insert_comment(
            [
                "comment_post_ID"      => $source_id,
                "comment_author"       => "Reviewer",
                "comment_author_email" => "reviewer@example.com",
                "comment_content"      => "Great listing.",
                "comment_approved"     => 1,
                "comment_type"         => "review",
            ]
        );

        $this->assert_provider_filter_surface( $this->integration );
        $this->assert_listing_collection( $this->integration, $source_id, $category_id );
        $this->assert_single_listing( $this->integration, $source_id, "Directorist Source" );
        $this->assert_related_listings( $this->integration, $source_id, $related_id );
        $this->assert_categories( $this->integration, $category_id );
        $this->assert_tag_collection( $this->integration );
        $this->assert_review_payload( $this->integration, $source_id );
    }
}
