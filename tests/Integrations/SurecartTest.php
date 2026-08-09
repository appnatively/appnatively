<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Ecommerce\SureCart;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

/**
 * SureCart is a remote-API-backed platform (api.surecart.com) rather than a
 * local-DB plugin like WooCommerce/FluentCart, so these tests can't create
 * "real" product/order fixtures the same way. Instead:
 *
 *  - Product/category reads go through the local sc_product/sc_collection
 *    WP mirror (by design — see ProductRepository), so those are seeded and
 *    asserted exactly like the WooCommerce/FluentCart tests.
 *  - Every test blocks outbound HTTP calls to surecart.com via
 *    pre_http_request, so nothing here depends on (or can accidentally hit)
 *    a live, connected SureCart account. Tests that require the live API
 *    (cart/orders) assert the integration's graceful-degradation behavior
 *    (empty results / a thrown Exception) rather than a successful purchase
 *    flow, which cannot be verified without a real connected account.
 */
class SurecartTest extends \WP_UnitTestCase
{
    private $post_id;

    private $category_id;

    private $user_id;

    private $sc_product_id = 'prod_test123';

    private $sc_price_id = 'price_test123';

    private $sc_variant_id = 'variant_test123';

    public function setUp(): void {
        parent::setUp();

        if ( ! class_exists( '\SureCart' ) || ! post_type_exists( 'sc_product' ) ) {
            $this->markTestSkipped( 'SureCart is not loaded.' );
        }

        $term              = wp_insert_term( 'Test SureCart Category', 'sc_collection' );
        $this->category_id = $term['term_id'];

        $this->post_id = wp_insert_post(
            [
                'post_type'   => 'sc_product',
                'post_title'  => 'Test SureCart Product',
                'post_status' => 'publish',
            ]
        );

        update_post_meta( $this->post_id, 'sc_id', $this->sc_product_id );
        update_post_meta(
            $this->post_id,
            'product',
            [
                'id'             => $this->sc_product_id,
                'name'           => 'Test SureCart Product',
                'description'    => 'A test product description.',
                'in_stock'       => true,
                'created_at'     => '2024-01-01T00:00:00+00:00',
                'updated_at'     => '2024-01-01T00:00:00+00:00',
                'prices'         => [
                    'data' => [
                        [ 'id' => $this->sc_price_id, 'amount' => 1999, 'scratch_amount' => 0, 'currency' => 'usd' ],
                    ],
                ],
                'variants'       => [
                    'data' => [
                        [ 'id' => $this->sc_variant_id, 'sku' => 'TEST-RED', 'name' => 'Red', 'amount' => 2500, 'scratch_amount' => 0, 'available' => true ],
                    ],
                ],
                'product_medias' => [ 'data' => [] ],
            ]
        );

        wp_set_object_terms( $this->post_id, [ $this->category_id ], 'sc_collection' );

        $this->user_id = $this->factory->user->create( [ 'role' => 'customer' ] );

        // Block real network calls to SureCart's live API — tests must be
        // deterministic and must never depend on (or hit) a connected account.
        add_filter( 'pre_http_request', [ $this, 'block_surecart_network' ], 10, 3 );
    }

    public function tearDown(): void {
        remove_filter( 'pre_http_request', [ $this, 'block_surecart_network' ], 10 );

        if ( $this->post_id ) {
            wp_delete_post( $this->post_id, true );
        }
        if ( $this->category_id ) {
            wp_delete_term( $this->category_id, 'sc_collection' );
        }
        parent::tearDown();
    }

    /**
     * @param mixed  $preempt
     * @param array  $args
     * @param string $url
     * @return mixed
     */
    public function block_surecart_network( $preempt, $args, $url ) {
        if ( strpos( $url, 'surecart.com' ) !== false ) {
            return new \WP_Error( 'surecart_test_network_blocked', 'Live SureCart API calls are disabled in tests.' );
        }
        return $preempt;
    }

    private function build_request( array $params = [] ): Request {
        $wp_request = new \WP_REST_Request();
        foreach ( $params as $key => $value ) {
            $wp_request->set_param( $key, $value );
        }
        return new Request( $wp_request );
    }

    public function test_products_returns_seeded_product() {
        $surecart = new SureCart();
        $request  = $this->build_request( [ 'page' => 1, 'per_page' => 10 ] );

        $paginator = $surecart->products( null, $request, [ 'id', 'name', 'price' ] );

        $this->assertGreaterThanOrEqual( 1, $paginator->get_total() );
        $ids = array_map( fn( $dto ) => $dto->get_id(), $paginator->get_items() );
        $this->assertContains( $this->post_id, $ids );
    }

    public function test_products_filters_by_category() {
        $other_id = wp_insert_post(
            [
                'post_type'   => 'sc_product',
                'post_title'  => 'Other SureCart Product',
                'post_status' => 'publish',
            ]
        );
        update_post_meta( $other_id, 'sc_id', 'prod_other' );
        update_post_meta( $other_id, 'product', [ 'id' => 'prod_other', 'name' => 'Other SureCart Product' ] );

        $surecart = new SureCart();
        $request  = $this->build_request( [ 'page' => 1, 'per_page' => 10, 'categoryId' => $this->category_id ] );

        $paginator = $surecart->products( null, $request, [ 'id' ] );
        $ids       = array_map( fn( $dto ) => $dto->get_id(), $paginator->get_items() );

        $this->assertContains( $this->post_id, $ids );
        $this->assertNotContains( $other_id, $ids );

        wp_delete_post( $other_id, true );
    }

    public function test_product_falls_back_to_mirror_when_live_api_unreachable() {
        $surecart = new SureCart();
        $request  = $this->build_request( [ 'id' => $this->post_id ] );

        $dto = $surecart->product( null, $request, [ 'id', 'name', 'price' ] );

        $this->assertNotNull( $dto );
        $this->assertEquals( $this->post_id, $dto->get_id() );
        $this->assertEquals( 'Test SureCart Product', $dto->get_name() );
        $this->assertEquals( '19.99', $dto->get_price() );
    }

    public function test_product_throws_for_missing_id() {
        $surecart = new SureCart();
        $request  = $this->build_request( [ 'id' => 999999999 ] );

        $this->expectException( Exception::class );
        $surecart->product( null, $request, [ 'id' ] );
    }

    public function test_product_includes_variant_from_mirror() {
        $surecart = new SureCart();
        $request  = $this->build_request( [ 'id' => $this->post_id ] );

        $dto = $surecart->product( null, $request, [ 'id', 'variants' ] );

        $this->assertNotNull( $dto );
        $variants = $dto->get_variants();
        $this->assertCount( 1, $variants );
        // .id is a position within the product's variants list, not the real
        // SureCart id — see ProductRepository::map_to_product_dto().
        $this->assertEquals( 0, $variants[0]->get_id() );
        $this->assertEquals( 'Red', $variants[0]->get_name() );
        $this->assertEquals( '25.00', $variants[0]->get_price() );
    }

    public function test_products_filters_returns_reduced_shape() {
        $surecart = new SureCart();
        $filters  = $surecart->products_filters( null, $this->build_request() );

        $this->assertNull( $filters->get_price() );
        $this->assertNull( $filters->get_rating() );
        $this->assertSame( [], $filters->get_attributes() );
        $this->assertSame( [], $filters->get_sort_options() );
    }

    public function test_categories_returns_seeded_category() {
        $surecart = new SureCart();
        $request  = $this->build_request( [ 'page' => 1, 'per_page' => 10 ] );

        $paginator = $surecart->categories( null, $request, [ 'id', 'name' ] );

        $ids = array_map( fn( $dto ) => $dto->get_id(), $paginator->get_items() );
        $this->assertContains( $this->category_id, $ids );
    }

    public function test_category_returns_single_category() {
        $surecart = new SureCart();
        $request  = $this->build_request( [ 'id' => $this->category_id ] );

        $dto = $surecart->category( null, $request, [ 'id', 'name' ] );

        $this->assertNotNull( $dto );
        $this->assertEquals( $this->category_id, $dto->get_id() );
        $this->assertEquals( 'Test SureCart Category', $dto->get_name() );
    }

    public function test_category_throws_for_missing_id() {
        $surecart = new SureCart();
        $request  = $this->build_request( [ 'id' => 999999999 ] );

        $this->expectException( Exception::class );
        $surecart->category( null, $request, [ 'id' ] );
    }

    public function test_cart_get_returns_empty_cart_without_checkout_id() {
        $surecart = new SureCart();

        try {
            $cart = $surecart->cart_get( null, $this->build_request() );
        } catch ( \Throwable $e ) {
            $this->markTestSkipped( 'SureCart account/currency resolution is not safe to exercise without a connected account: ' . $e->getMessage() );
        }

        $this->assertNull( $cart->get_id() );
        $this->assertSame( [], $cart->get_items() );
        $this->assertEquals( 0, $cart->get_item_count() );
    }

    public function test_cart_add_throws_when_live_api_unreachable() {
        $surecart = new SureCart();
        $request  = $this->build_request(
            [ 'items' => [ [ 'productId' => $this->post_id, 'quantity' => 2 ] ] ]
        );

        // Resolving price/variant succeeds locally (from the mirror), but
        // Checkout::create() is network-blocked and must surface as a
        // catchable Exception, not a fatal error.
        $this->expectException( Exception::class );
        $surecart->cart_add( null, $request );
    }

    public function test_orders_get_returns_empty_for_guest() {
        $surecart  = new SureCart();
        $paginator = $surecart->orders_get( null, $this->build_request( [ 'page' => 1, 'per_page' => 10 ] ) );

        $this->assertEquals( 0, $paginator->get_total() );
        $this->assertSame( [], $paginator->get_items() );
    }

    public function test_orders_get_returns_empty_when_no_linked_customer() {
        wp_set_current_user( $this->user_id );
        $surecart = new SureCart();

        $paginator = $surecart->orders_get( null, $this->build_request( [ 'page' => 1, 'per_page' => 10 ] ) );

        $this->assertEquals( 0, $paginator->get_total() );
        $this->assertSame( [], $paginator->get_items() );
    }

    public function test_orders_get_degrades_gracefully_when_live_api_unreachable() {
        wp_set_current_user( $this->user_id );
        update_user_meta( $this->user_id, 'sc_customer_ids', [ 'live' => 'cus_test123' ] );

        $surecart  = new SureCart();
        $paginator = $surecart->orders_get( null, $this->build_request( [ 'page' => 1, 'per_page' => 10 ] ) );

        // The live Order::where()->paginate() call is network-blocked and
        // returns a WP_Error — orders_get() must degrade to an empty
        // paginator rather than throw or fatal.
        $this->assertEquals( 0, $paginator->get_total() );
        $this->assertSame( [], $paginator->get_items() );
    }

    public function test_order_get_returns_null_for_guest() {
        $surecart = new SureCart();
        $result   = $surecart->order_get( null, 'ord_test123', $this->build_request() );

        $this->assertNull( $result );
    }
}
