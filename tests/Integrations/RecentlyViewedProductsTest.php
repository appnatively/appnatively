<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\DTO\Ecommerce\ProductDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductPaginatorDTO;
use Crafium\AppNatively\App\Support\Settings;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

class RecentlyViewedProductsTest extends \WP_UnitTestCase {
    private const HOOK = 'craf_appna_ecommerce_woocommerce_recently_viewed_products';

    private $saved_hook;

    private $requested_ids = [];

    public function setUp(): void {
        parent::setUp();
        wp_set_current_user( 0 );
        Settings::set_api_enabled( true );
        rest_get_server();
        $this->saved_hook = $GLOBALS['wp_filter'][ self::HOOK ] ?? null;
        remove_all_filters( self::HOOK );
        add_filter( self::HOOK, [ $this, 'resolve' ], 10, 3 );
    }

    public function tearDown(): void {
        remove_all_filters( self::HOOK );
        if ( $this->saved_hook ) {
            $GLOBALS['wp_filter'][ self::HOOK ] = $this->saved_hook;
        }
        Settings::set_api_enabled( true );
        parent::tearDown();
    }

    public function resolve( $data, $request, $fields ) {
        $this->requested_ids = $request->get_param( 'ids' );
        $this->assertContains( 'average_rating', $fields );
        $this->assertContains( 'rating_count', $fields );
        $items = [];
        // Deliberately unordered and includes an unsolicited product.
        foreach ( [ 18, 999, 42 ] as $id ) {
            $items[] = ( new ProductDTO() )->set_id( $id )->set_name( 'Product ' . $id );
        }
        return new ProductPaginatorDTO( 9, 100, 999, 10, $items );
    }

    private function request( array $params = [] ) {
        $request = new \WP_REST_Request( 'GET', '/craf_appna/v1/ecommerce/recently-viewed' );
        $request->set_query_params( array_merge( [ 'integration' => 'woocommerce' ], $params ) );
        return rest_do_request( $request );
    }

    public function test_guest_request_normalizes_orders_deduplicates_and_counts_resolved_cards(): void {
        $response = $this->request( [ 'ids' => [ '00042', '73', 18, '42' ] ] );
        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( [ 42, 73, 18 ], $this->requested_ids );
        $data = json_decode( wp_json_encode( $response->get_data() ), true )['data'];
        $this->assertSame( [ 42, 18 ], array_column( $data['items'], 'id' ) );
        $this->assertSame( 2, $data['total'] );
        $this->assertSame( 2, $data['per_page'] );
        $this->assertSame( 1, $data['current_page'] );
        $this->assertSame( 1, $data['last_page'] );
    }

    public function test_empty_and_missing_ids_do_not_query_the_provider(): void {
        foreach ( [ [], [ 'ids' => [] ] ] as $params ) {
            $response = $this->request( $params );
            $this->assertSame( 200, $response->get_status() );
            $data = json_decode( wp_json_encode( $response->get_data() ), true )['data'];
            $this->assertSame( [], $data['items'] );
            $this->assertSame( 0, $data['total'] );
            $this->assertSame( 0, $data['per_page'] );
        }
        $this->assertSame( [], $this->requested_ids );
    }

    public function test_accepts_thirty_ids(): void {
        $this->assertSame( 200, $this->request( [ 'ids' => range( 1, 30 ) ] )->get_status() );
        $this->assertSame( range( 1, 30 ), $this->requested_ids );
    }

    public function invalid_params(): array {
        $cases = [
            [ 'ids' => range( 1, 31 ) ], [ 'ids' => array_fill( 0, 31, 1 ) ],
            [ 'ids' => '42' ], [ 'ids' => null ], [ 'integration' => 'invalid' ], [ 'integration' => null ],
        ];
        foreach ( [ 0, -1, true, false, 1.5, 1.0, '1.5', '1e2', '-1', '+1', ' 1', '1abc', '', '000', [], null, (string) PHP_INT_MAX . '0', 'prod_test123' ] as $id ) {
            $cases[] = [ 'ids' => [ $id ] ];
        }
        return array_map( static fn( $params ) => [ $params ], $cases );
    }

    /** @dataProvider invalid_params */
    public function test_rejects_invalid_requests_before_lookup( array $params ): void {
        $this->assertSame( 422, $this->request( $params )->get_status() );
        $this->assertSame( [], $this->requested_ids );
    }

    public function test_inactive_integration_fails_even_with_empty_history(): void {
        remove_all_filters( self::HOOK );
        $this->assertSame( 503, $this->request()->get_status() );
    }

    public function test_lookup_failure_is_not_an_empty_success(): void {
        remove_all_filters( self::HOOK );
        add_filter(
            self::HOOK, static function() {
                throw new Exception( 'Temporary failure', 503 );
            }
        );
        $this->assertSame( 503, $this->request( [ 'ids' => [ 42 ] ] )->get_status() );
    }

    public function test_invalid_provider_result_fails(): void {
        remove_all_filters( self::HOOK );
        add_filter( self::HOOK, '__return_null' );
        $this->assertSame( 503, $this->request( [ 'ids' => [ 42 ] ] )->get_status() );
    }

    public function test_api_switch_blocks_requests(): void {
        Settings::set_api_enabled( false );
        $this->assertSame( 503, $this->request( [ 'ids' => [ 42 ] ] )->get_status() );
        $this->assertSame( [], $this->requested_ids );
    }
}
