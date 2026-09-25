<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\DTO\PostType\PostDTO;
use Crafium\AppNatively\App\DTO\PostType\PostPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\PostType\TermController;
use Crafium\AppNatively\App\Http\Controllers\PostType\PostController;
use Crafium\AppNatively\App\Http\Controllers\PostType\PostTypeController;
use Crafium\AppNatively\App\Support\CustomFields;
use Crafium\AppNatively\App\Support\ContentTypes;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

/**
 * Custom post types through the blog endpoints: what may be served, and that
 * `post` keeps working with nothing configured.
 */
class ContentTypesTest extends \WP_UnitTestCase {
    private int $term_id;

    public function setUp(): void {
        parent::setUp();

        register_post_type( "event", [ "public" => true, "label" => "Events", "supports" => [ "title", "editor", "thumbnail" ] ] );
        register_post_type( "internal", [ "public" => false, "publicly_queryable" => false ] );
        register_taxonomy( "event_category", "event", [ "public" => true, "hierarchical" => true ] );
        register_post_meta( "event", "capacity", [ "type" => "integer", "single" => true, "show_in_rest" => true ] );
        register_post_meta( "event", "_secret", [ "type" => "string", "single" => true, "show_in_rest" => true ] );
        register_post_meta( "event", "unlisted", [ "type" => "string", "single" => true ] );

        $this->term_id = (int) wp_insert_term( "Jazz", "event_category" )["term_id"];

        delete_option( ContentTypes::OPTION );
        ContentTypes::flush();
    }

    public function tearDown(): void {
        delete_option( ContentTypes::OPTION );
        ContentTypes::flush();
        unregister_post_type( "event" );
        unregister_post_type( "internal" );
        unregister_taxonomy( "event_category" );
        parent::tearDown();
    }

    private function request( array $params = [] ): Request {
        $wp_request = new \WP_REST_Request();

        foreach ( $params as $key => $value ) {
            $wp_request->set_param( $key, $value );
        }

        return new Request( $wp_request );
    }

    private function event( array $args = [] ): int {
        $id = self::factory()->post->create( array_merge( [ "post_type" => "event", "post_status" => "publish" ], $args ) );
        wp_set_object_terms( $id, [ $this->term_id ], "event_category" );
        update_post_meta( $id, "capacity", 200 );
        update_post_meta( $id, "_secret", "hush" );
        update_post_meta( $id, "unlisted", "nope" );
        return $id;
    }

    private function expose_events( string $app_id = "app1", array $fields = [ "capacity" ] ): array {
        return ContentTypes::save_for_app( $app_id, [ [ "postType" => "event", "taxonomies" => [ "event_category" ], "fields" => $fields ] ] );
    }

    private function assert_not_found( callable $call ): void {
        try {
            $call();
            $this->fail( "Expected a not-found refusal." );
        } catch ( Exception $e ) {
            $this->assertSame( 404, $e->getCode() );
        }
    }

    public function test_discovery_offers_viewable_types_only(): void {
        $types = array_column( ContentTypes::discover(), "postType" );

        $this->assertContains( "post", $types );
        $this->assertContains( "event", $types );
        $this->assertNotContains( "internal", $types );
        $this->assertNotContains( "attachment", $types );
    }

    public function test_discovery_offers_rest_registered_public_meta_only(): void {
        $keys = array_column( ContentTypes::describe( "event" )["fields"], "key" );

        $this->assertContains( "capacity", $keys );
        $this->assertNotContains( "_secret", $keys );
        $this->assertNotContains( "unlisted", $keys );
    }

    public function test_save_drops_what_the_site_does_not_offer(): void {
        $saved = ContentTypes::save_for_app(
            "app1",
            [
                [ "postType" => "event", "taxonomies" => [ "event_category", "category" ], "fields" => [ "capacity", "_secret", "_edit_lock", "unlisted" ] ],
                [ "postType" => "product", "taxonomies" => [], "fields" => [] ],
                [ "postType" => "internal", "taxonomies" => [], "fields" => [] ],
            ]
        );

        $this->assertSame( [ "event" ], array_column( $saved, "postType" ) );
        $this->assertSame( [ "event_category" ], array_column( $saved[0]["taxonomies"], "taxonomy" ) );
        $this->assertSame( [ "capacity" ], array_column( $saved[0]["fields"], "key" ) );
    }

    public function test_posts_are_served_with_nothing_configured(): void {
        $post_id = self::factory()->post->create( [ "post_status" => "publish" ] );

        $response = ( new PostController() )->index( $this->request() );

        $this->assertInstanceOf( PostPaginatorDTO::class, $response["data"]["data"] );
        $this->assertContains( $post_id, array_map( fn( PostDTO $post ) => $post->get_id(), $response["data"]["data"]->get_items() ) );
    }

    public function test_unconfigured_post_type_is_not_found(): void {
        $this->event();

        $this->assert_not_found( fn() => ( new PostController() )->index( $this->request( [ "post_type" => "event" ] ) ) );
        $this->assert_not_found( fn() => ( new PostController() )->index( $this->request( [ "post_type" => "internal" ] ) ) );
    }

    public function test_configured_post_type_is_listed_and_filtered_by_term(): void {
        $this->expose_events();
        $event_id = $this->event();
        $other_id = self::factory()->post->create( [ "post_type" => "event", "post_status" => "publish" ] );

        $response = ( new PostController() )->index( $this->request( [ "post_type" => "event", "term_id" => $this->term_id ] ) );
        $ids      = array_map( fn( PostDTO $post ) => $post->get_id(), $response["data"]["data"]->get_items() );

        $this->assertSame( [ $event_id ], $ids );
        $this->assertNotContains( $other_id, $ids );
    }

    public function test_taxonomy_not_exposed_for_the_type_is_refused(): void {
        $this->expose_events();

        $this->assert_not_found( fn() => ( new PostController() )->index( $this->request( [ "post_type" => "event", "taxonomy" => "category", "term_id" => 1 ] ) ) );
        $this->assert_not_found( fn() => ( new TermController() )->index( $this->request( [ "post_type" => "event", "taxonomy" => "post_tag" ] ) ) );
    }

    public function test_show_returns_only_exposed_fields(): void {
        $this->expose_events();
        $event_id = $this->event();

        $post  = ( new PostController() )->show( $this->request( [ "id" => $event_id ] ) )["data"]["data"];
        $array = json_decode( wp_json_encode( $post ), true );

        $this->assertSame( "event", $array["post_type"] );
        $this->assertSame( [ "capacity" => [ "type" => "number", "value" => 200 ] ], $array["custom_fields"] );
        $this->assertSame( $this->term_id, $array["terms"]["event_category"][0]["id"] );
    }

    public function test_list_serves_requested_exposed_fields_only(): void {
        $this->expose_events();
        $this->event();

        $response = ( new PostController() )->index( $this->request( [ "post_type" => "event", "custom_fields" => "capacity,_secret,unlisted" ] ) );
        $array    = json_decode( wp_json_encode( $response["data"]["data"]->get_items()[0] ), true );

        $this->assertSame( [ "capacity" ], array_keys( $array["custom_fields"] ) );
    }

    public function test_show_refuses_wrong_type_protected_and_unexposed_posts(): void {
        $event_id  = $this->event();
        $locked_id = $this->event( [ "post_password" => "pw" ] );

        // Not exposed yet.
        $this->assert_not_found( fn() => ( new PostController() )->show( $this->request( [ "id" => $event_id ] ) ) );

        $this->expose_events();

        $this->assert_not_found( fn() => ( new PostController() )->show( $this->request( [ "id" => $event_id, "post_type" => "post" ] ) ) );
        $this->assert_not_found( fn() => ( new PostController() )->show( $this->request( [ "id" => $locked_id ] ) ) );
    }

    public function test_reads_serve_the_union_of_every_apps_selection(): void {
        $this->expose_events( "app1", [] );
        $this->expose_events( "app2", [ "capacity" ] );

        $this->assertSame( [ "capacity" ], array_keys( ContentTypes::allowed_fields( "event" ) ) );

        ContentTypes::save_for_app( "app2", [] );

        $this->assertTrue( ContentTypes::is_allowed_post_type( "event" ) );
        $this->assertSame( [], ContentTypes::allowed_fields( "event" ) );
    }

    public function test_stored_selection_is_revalidated_when_the_site_changes(): void {
        $this->expose_events();
        unregister_post_type( "event" );
        ContentTypes::flush();

        $this->assertFalse( ContentTypes::is_allowed_post_type( "event" ) );
    }

    private function ids( array $response ): array {
        return array_map( fn( PostDTO $post ) => $post->get_id(), $response["data"]["data"]->get_items() );
    }

    public function test_sticky_and_password_protected_posts_are_never_listed(): void {
        $sticky_locked = self::factory()->post->create( [ "post_status" => "publish", "post_password" => "secret" ] );
        $sticky_open   = self::factory()->post->create( [ "post_status" => "publish" ] );
        stick_post( $sticky_locked );
        stick_post( $sticky_open );

        $ids = $this->ids( ( new PostController() )->index( $this->request() ) );

        $this->assertContains( $sticky_open, $ids );
        $this->assertNotContains( $sticky_locked, $ids );
    }

    public function test_related_is_empty_when_the_post_shares_no_term(): void {
        $this->expose_events();
        $lonely = self::factory()->post->create( [ "post_type" => "event", "post_status" => "publish" ] );
        $this->event();

        $response = ( new PostController() )->related( $this->request( [ "id" => $lonely, "post_type" => "event" ] ) );

        $this->assertSame( [], $this->ids( $response ) );
    }

    public function test_a_single_post_honors_the_requested_custom_fields(): void {
        $this->expose_events( "app1", [ "capacity" ] );
        $id = $this->event();

        $all  = ( new PostController() )->show( $this->request( [ "id" => $id ] ) )["data"];
        $none = ( new PostController() )->show( $this->request( [ "id" => $id, "custom_fields" => "not_a_field" ] ) )["data"];

        $this->assertArrayHasKey( "capacity", $all->get_custom_fields() );
        $this->assertSame( [], $none->get_custom_fields() ?? [] );
    }

    public function test_attachments_of_unpublished_parents_are_not_public(): void {
        $draft  = self::factory()->post->create( [ "post_status" => "draft" ] );
        $public = self::factory()->post->create( [ "post_status" => "publish" ] );

        $hidden  = self::factory()->attachment->create_object( "hidden.jpg", $draft, [ "post_mime_type" => "image/jpeg" ] );
        $visible = self::factory()->attachment->create_object( "shown.jpg", $public, [ "post_mime_type" => "image/jpeg" ] );
        $loose   = self::factory()->attachment->create_object( "loose.jpg", 0, [ "post_mime_type" => "image/jpeg" ] );

        $this->assertFalse( CustomFields::is_public_attachment( $hidden ) );
        $this->assertTrue( CustomFields::is_public_attachment( $visible ) );
        $this->assertTrue( CustomFields::is_public_attachment( $loose ) );
        $this->assertFalse( CustomFields::is_public_attachment( $public ) );
    }

    public function test_an_app_id_of_only_symbols_is_refused(): void {
        try {
            ( new PostTypeController() )->store( $this->request( [ "app_id" => "!!!", "contentTypes" => [] ] ) );
            $this->fail( "Expected the empty app id to be refused." );
        } catch ( Exception $e ) {
            $this->assertSame( 422, $e->getCode() );
        }

        $this->assertArrayNotHasKey( "", get_option( ContentTypes::OPTION, [] ) );
    }
}
