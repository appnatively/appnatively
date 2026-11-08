<?php

namespace Crafium\AppNatively\Tests\Integrations;

require_once __DIR__ . '/DirectoryIntegrationTestCase.php';

/** Native aDirectory source records, including its dynamic directory post type. */
class ADirectoryTest extends DirectoryIntegrationTestCase {
    public function test_adirectory_reads_native_directory_records(): void {
        $this->assertTrue( function_exists( 'adqs_get_directory_post_types' ), 'Pinned aDirectory package must be loaded.' );
        $type = 'adqs_test_listing';
        $this->ensure_post_type( $type );
        $this->ensure_taxonomy( 'adqs_listing_types', $type );
        $directory = $this->create_term( 'adqs_listing_types', 'Isolated Directory' );
        update_term_meta( $directory, 'adqs_directory_post_type', $type );
        $this->ensure_taxonomy( 'adqs_category', $type );
        $this->ensure_taxonomy( 'adqs_tags', $type, false );
        $this->ensure_taxonomy( 'adqs_location', $type );
        $category = $this->create_term( 'adqs_category', 'Directory category' );
        $listing  = $this->create_listing(
            $type, 'Directory listing', 'publish', [
                '_is_featured' => 'yes',
                '_price'       => '75'
            ]
        );
        $this->assign_terms( $listing, 'adqs_category', [ $category ] );
        $this->assertContains( $type, adqs_get_directory_post_types() );
        $this->assert_provider_filter_surface( 'adirectory' );
        $this->assert_listing_collection( 'adirectory', $listing, $category );
        $this->assert_featured_listing( 'adirectory', $listing );
        $this->assert_single_listing( 'adirectory', $listing, 'Directory listing' );
    }
}
