<?php

namespace Crafium\AppNatively\Tests\Integrations;

require_once __DIR__ . "/DirectoryIntegrationTestCase.php";

class GeoDirectoryTest extends DirectoryIntegrationTestCase {
    private string $integration = "geodirectory";

    private string $post_type = "gd_place";

    private string $category_taxonomy = "gd_placecategory";

    private string $tag_taxonomy = "gd_place_tags";

    public function setUp(): void {
        parent::setUp();

        if ( function_exists( "geodir_get_posttypes" ) ) {
            $post_types = geodir_get_posttypes( "array" );
            if ( is_array( $post_types ) && ! empty( $post_types ) ) {
                $this->post_type = (string) array_key_first( $post_types );
            }
        }

        $this->category_taxonomy = $this->post_type . "category";
        $this->tag_taxonomy      = $this->post_type . "_tags";

        $this->ensure_post_type( $this->post_type );
        $this->ensure_taxonomy( $this->category_taxonomy, $this->post_type );
        $this->ensure_taxonomy( $this->tag_taxonomy, $this->post_type, false );
    }

    public function test_geodirectory_adapter_uses_real_directory_data(): void {
        $category_id = $this->create_term( $this->category_taxonomy, "GeoDirectory Category" );
        $tag_id      = $this->create_term( $this->tag_taxonomy, "geodirectory-tag" );
        $source_id   = $this->create_listing( $this->post_type, "GeoDirectory Source" );
        $related_id  = $this->create_listing( $this->post_type, "GeoDirectory Related" );

        $this->assign_terms( $source_id, $this->category_taxonomy, [$category_id] );
        $this->assign_terms( $source_id, $this->tag_taxonomy, [$tag_id] );
        $this->assign_terms( $related_id, $this->category_taxonomy, [$category_id] );

        $comment_id = wp_insert_comment(
            [
                "comment_post_ID"      => $source_id,
                "comment_author"       => "Geo Reviewer",
                "comment_author_email" => "geo-reviewer@example.com",
                "comment_content"      => "Geo review.",
                "comment_approved"     => 1,
            ]
        );
        $this->create_geodirectory_review_rating( $source_id, (int) $comment_id, 4.0 );

        $this->assert_provider_filter_surface( $this->integration );
        $this->assert_listing_collection( $this->integration, $source_id, $category_id );
        $this->assert_single_listing( $this->integration, $source_id, "GeoDirectory Source" );
        $this->assert_related_listings( $this->integration, $source_id, $related_id );
        $this->assert_categories( $this->integration, $category_id );
        $this->assert_tag_collection( $this->integration );
        $this->assert_review_payload( $this->integration, $source_id );
    }

    public function test_geodirectory_reviews_read_ratings_from_review_table(): void {
        $source_id  = $this->create_listing( $this->post_type, "GeoDirectory Rated Source" );
        $comment_id = wp_insert_comment(
            [
                "comment_post_ID"      => $source_id,
                "comment_author"       => "Geo Reviewer",
                "comment_author_email" => "geo-reviewer@example.com",
                "comment_content"      => "Geo rated review.",
                "comment_approved"     => 1,
            ]
        );

        $this->create_geodirectory_review_rating( $source_id, (int) $comment_id, 5.0 );

        $reviews = apply_filters(
            "craf_appna_directory_{$this->integration}_reviews",
            null,
            $this->create_request(
                [
                    "id"          => $source_id,
                    "page"        => 1,
                    "per_page"    => 10,
                    "integration" => $this->integration,
                ]
            )
        );

        $this->assertIsArray( $reviews );
        $this->assertSame( 5.0, $reviews["items"][0]["rating"] );
        $this->assertSame( 1, $reviews["rating_counts"]["5"] );
    }

    private function create_geodirectory_review_rating( int $post_id, int $comment_id, float $rating ): void {
        global $wpdb;

        if ( ! defined( "GEODIR_REVIEW_TABLE" ) || ! $comment_id ) {
            return;
        }

        $wpdb->replace(
            GEODIR_REVIEW_TABLE,
            [
                "comment_id" => $comment_id,
                "post_id"    => $post_id,
                "user_id"    => 0,
                "rating"     => $rating,
                "ratings"    => "",
                "attachments" => "",
                "post_type"  => $this->post_type,
                "city"       => "",
                "region"     => "",
                "country"    => "",
                "latitude"   => "",
                "longitude"  => "",
            ],
            ["%d", "%d", "%d", "%f", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s"]
        );
    }
}
