<?php

namespace Crafium\AppNatively\Tests\Integrations;

require_once __DIR__ . "/DirectoryIntegrationTestCase.php";

class BusinessDirectoryPluginTest extends DirectoryIntegrationTestCase {
    private string $integration = "business-directory-plugin";

    private string $post_type = "wpbdp_listing";

    private string $category_taxonomy = "wpbdp_category";

    private string $tag_taxonomy = "wpbdp_tag";

    public function setUp(): void {
        parent::setUp();

        $this->post_type         = defined( "WPBDP_POST_TYPE" ) ? WPBDP_POST_TYPE : $this->post_type;
        $this->category_taxonomy = defined( "WPBDP_CATEGORY_TAX" ) ? WPBDP_CATEGORY_TAX : $this->category_taxonomy;
        $this->tag_taxonomy      = defined( "WPBDP_TAGS_TAX" ) ? WPBDP_TAGS_TAX : $this->tag_taxonomy;

        $this->ensure_post_type( $this->post_type );
        $this->ensure_taxonomy( $this->category_taxonomy, $this->post_type );
        $this->ensure_taxonomy( $this->tag_taxonomy, $this->post_type, false );
    }

    public function test_business_directory_plugin_adapter_uses_real_directory_data(): void {
        $category_id = $this->create_term( $this->category_taxonomy, "Business Directory Category" );
        $tag_id      = $this->create_term( $this->tag_taxonomy, "business-directory-tag" );
        $source_id   = $this->create_listing(
            $this->post_type,
            "Business Directory Source",
            "publish",
            [
                "address"        => "Business Directory address",
                "phone"          => "123",
                "email"          => "business-directory@example.com",
                "website"        => "https://example.com",
                "price"          => "300",
                "latitude"       => "23.7808875",
                "longitude"      => "90.2792371",
            ]
        );
        $related_id  = $this->create_listing( $this->post_type, "Business Directory Related" );

        // Sticky (featured) listings are kept in the plugin's own table.
        global $wpdb;
        $wpdb->replace( "{$wpdb->prefix}wpbdp_listings", [ "listing_id" => $source_id, "is_sticky" => 1, "listing_status" => "complete" ] );

        $this->assign_terms( $source_id, $this->category_taxonomy, [$category_id] );
        $this->assign_terms( $source_id, $this->tag_taxonomy, [$tag_id] );
        $this->assign_terms( $related_id, $this->category_taxonomy, [$category_id] );

        $this->assert_provider_filter_surface( $this->integration );
        $this->assert_listing_collection( $this->integration, $source_id, $category_id );
        $this->assert_featured_listing( $this->integration, $source_id );
        $this->assert_single_listing( $this->integration, $source_id, "Business Directory Source" );
        $this->assert_related_listings( $this->integration, $source_id, $related_id );
        $this->assert_categories( $this->integration, $category_id );
        $this->assert_tag_collection( $this->integration );
        $this->assert_review_payload( $this->integration, $source_id );
    }
}
