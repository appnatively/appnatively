<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
use Crafium\AppNatively\App\Support\Settings;
/** Native WordPress suite; standalone queue tests also run without wp-tests-lib. */
class NotificationEventsTest extends \WP_UnitTestCase
{
    public function setUp(): void {
        parent::setUp();
        Settings::set_api_enabled( true );
        Events::install();
        update_option( 'craf_appna_notification_epoch', 'integration-test' );
        foreach ( [
            'source-a',
            'source-b',
        ] as $source ) {
            $keys = [];
            foreach ( [
                'events',
                'subjects',
                'identity',
            ] as $scope ) {
                $keys[$scope] = [
                    'id'     => $scope,
                    'secret' => str_repeat( 'a', 64 ),
                ];
            }
            $binding = [
                'sourceId'     => $source,
                'appId'        => 'app-' . $source,
                'generation'   => 1,
                'enabled'      => true,
                'provisioning' => 'ready',
                'keys'         => $keys,
                'endpoint'     => 'https://example.com/api/notification-sources',
            ];
            Events::save_binding( $binding, Events::bindings()[$source] ?? null );
            foreach ( [
                'events',
                'subjects',
            ] as $channel ) {
                Events::queue( $source, $channel )->discard();
            }
        }
    }

    public function tearDown(): void {
        foreach ( [
            'source-a',
            'source-b',
        ] as $source ) {
            foreach ( [
                'events',
                'subjects',
            ] as $channel ) {
                Events::queue( $source, $channel )->discard();
            }
        }
        wp_set_current_user( 0 );
        parent::tearDown();
    }

    private function items( string $source, string $channel = 'events' ): array {
        $items = [];
        foreach ( Events::queue( $source, $channel )->get_batches() as $batch ) {
            $items = array_merge( $items, $batch->data );
        }
        foreach ( $items as &$item ) {
            $item['event'] = json_decode( $item['event'], true );
        }
        unset( $item );
        return $items;
    }

    public function test_hook_persists_framework_batches_without_sending_http(): void {
        $calls     = 0;
        $intercept = function () use ( &$calls ) {
            ++$calls;
            return new \WP_Error( 'unexpected_http' );
        };
        add_filter( 'pre_http_request', $intercept );
        $id = Events::emit(
            'custom.created', [], [
                'id' => 'stable-id',
            ]
        );
        remove_filter( 'pre_http_request', $intercept );
        $this->assertSame( 'stable-id', $id );
        $this->assertSame( 0, $calls );
        foreach ( [
            'source-a',
            'source-b',
        ] as $source ) {
            $items = $this->items( $source );
            $this->assertCount( 1, $items );
            $this->assertSame( 'stable-id', $items[0]['event']['id'] );
        }
    }

    public function test_sending_defaults_on_and_requires_the_sending_switch_and_api_access(): void {
        delete_option( 'craf_appna_notification_epoch' );
        delete_option( 'craf_appna_notifications_enabled' );
        $this->assertSame( 'default-on', Events::emit( 'custom.created', [], ['id' => 'default-on'] ) );
        update_option( 'craf_appna_notifications_enabled', 'no' );
        $this->assertNull( Events::emit( 'custom.created', [] ) );
        update_option( 'craf_appna_notifications_enabled', 'yes' );
        Settings::set_api_enabled( false );
        $this->assertNull( Events::emit( 'system.subject.updated', [] ) );
    }

    public function test_disabled_binding_receives_no_security_or_business_work(): void {
        $binding            = Events::bindings()['source-a'];
        $binding['enabled'] = false;
        Events::save_binding( $binding );
        Events::emit(
            'system.subject.deleted', [], [
                'entity' => [
                    'type' => 'user',
                    'id'   => '7',
                ],
            ]
        );
        $this->assertSame( [], $this->items( 'source-a', 'subjects' ) );
        $this->assertCount( 1, $this->items( 'source-b', 'subjects' ) );
    }

    public function test_original_app_auth_works_without_notification_storage(): void {
        $user = self::factory()->user->create();
        delete_option( 'craf_appna_notification_epoch' );
        $token   = Auth::issue( $user );
        $request = new \WP_REST_Request();
        $request->set_header( 'Authorization', 'Bearer ' . $token );
        $this->assertSame( $user, Auth::resolve( $request ) );
        Auth::revoke( $token );
        $this->assertNull( Auth::resolve( $request ) );
    }

    public function test_native_cookie_proof_does_not_write_user_metadata(): void {
        $user = self::factory()->user->create();
        wp_set_current_user( $user );
        $token                     = \WP_Session_Tokens::get_instance( $user )->create( time() + 3600 );
        $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $token );
        $before                    = get_user_meta( $user );
        $session                   = Events::session_metadata( new \WP_REST_Request(), $user );
        $this->assertNotNull( $session );
        $this->assertSame( $before, get_user_meta( $user ) );
        unset( $_COOKIE[LOGGED_IN_COOKIE] );
    }

    public function test_transition_versions_are_plugin_owned_and_never_reused(): void {
        $id = random_int( 100000, 999999999 );
        $this->assertSame( 51, Events::next_version( 'order', $id, 50 ) );
        $this->assertSame( 52, Events::next_version( 'order', $id, 50 ) );
        $this->assertSame( 53, Events::next_version( 'order', $id, 1 ) );
    }
}
