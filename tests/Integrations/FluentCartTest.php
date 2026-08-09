<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Ecommerce\FluentCart;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use FluentCart\App\Models\Customer;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderItem;
use FluentCart\App\Models\Product;
use FluentCart\App\Models\ProductDetail;
use FluentCart\App\Models\ProductVariation;

class FluentCartTest extends \WP_UnitTestCase
{
    private $product_id;

    private $variation_id;

    private $category_id;

    private $user_id;

    public function setUp(): void {
        parent::setUp();

        if ( ! class_exists( '\FluentCart\App\Models\Product' ) ) {
            $this->markTestSkipped( 'FluentCart is not loaded.' );
        }

        $term              = wp_insert_term( 'Test FluentCart Category', 'product-categories' );
        $this->category_id = $term['term_id'];

        $product          = Product::create(
            [
                'post_title'   => 'Test FluentCart Product',
                'post_content' => 'A test product description.',
                'post_excerpt' => 'A short description.',
                'post_status'  => 'publish',
            ]
        );
        $this->product_id = $product->ID;

        wp_set_object_terms( $this->product_id, [ $this->category_id ], 'product-categories' );

        ProductDetail::query()->create(
            [
                'post_id'            => $this->product_id,
                'variation_type'     => 'simple',
                'manage_stock'       => 0,
                'stock_availability' => 'in-stock',
                'other_info'         => [],
            ]
        );

        $variation          = ProductVariation::query()->create(
            [
                'post_id'         => $this->product_id,
                'variation_title' => 'Default',
                'item_price'      => 29.99,
                'compare_price'   => 39.99,
                'manage_stock'    => 0,
                'stock_status'    => 'in-stock',
                'available'       => 10,
                'item_status'     => 'active',
            ]
        );
        $this->variation_id = $variation->id;

        $this->user_id = $this->factory->user->create( [ 'role' => 'customer' ] );
    }

    public function tearDown(): void {
        if ( $this->product_id ) {
            wp_delete_post( $this->product_id, true );
        }
        if ( $this->category_id ) {
            wp_delete_term( $this->category_id, 'product-categories' );
        }
        parent::tearDown();
    }

    private function build_request( array $params = [] ): Request {
        $wp_request = new \WP_REST_Request();
        foreach ( $params as $key => $value ) {
            $wp_request->set_param( $key, $value );
        }
        return new Request( $wp_request );
    }

    public function test_products_returns_seeded_product() {
        $fluent_cart = new FluentCart();
        $request     = new \WP_REST_Request();
        $request->set_param( 'page', 1 );
        $request->set_param( 'per_page', 10 );

        $paginator = $fluent_cart->products( null, $request, [ 'id', 'name', 'price' ] );

        $this->assertGreaterThanOrEqual( 1, $paginator->get_total() );
        $ids = array_map( fn( $dto ) => $dto->get_id(), $paginator->get_items() );
        $this->assertContains( $this->product_id, $ids );
    }

    public function test_product_returns_single_product_with_mapped_fields() {
        $fluent_cart = new FluentCart();
        $request     = new \WP_REST_Request();
        $request->set_param( 'id', $this->product_id );

        $dto = $fluent_cart->product( null, $request, [ 'id', 'name', 'price', 'compare_at_price', 'variants' ] );

        $this->assertNotNull( $dto );
        $this->assertEquals( $this->product_id, $dto->get_id() );
        $this->assertEquals( 'Test FluentCart Product', $dto->get_name() );
        $this->assertEquals( '29.99', $dto->get_price() );
        $this->assertEquals( '29.99', $dto->get_compare_at_price() );

        $variant_names = array_map( fn( $v ) => $v->get_name(), $dto->get_variants() );
        $this->assertContains( 'Default', $variant_names );
    }

    public function test_product_returns_null_for_missing_id() {
        $fluent_cart = new FluentCart();
        $request     = new \WP_REST_Request();
        $request->set_param( 'id', 0 );

        $dto = $fluent_cart->product( null, $request, [ 'id' ] );

        $this->assertNull( $dto );
    }

    public function test_product_throws_for_nonexistent_id() {
        $fluent_cart = new FluentCart();
        $request     = new \WP_REST_Request();
        $request->set_param( 'id', 999999999 );

        $this->expectException( Exception::class );
        $fluent_cart->product( null, $request, [ 'id' ] );
    }

    public function test_categories_returns_seeded_category() {
        $fluent_cart = new FluentCart();
        $request     = new \WP_REST_Request();
        $request->set_param( 'page', 1 );
        $request->set_param( 'per_page', 10 );

        $paginator = $fluent_cart->categories( null, $request, [ 'id', 'name' ] );

        $ids = array_map( fn( $dto ) => $dto->get_id(), $paginator->get_items() );
        $this->assertContains( $this->category_id, $ids );
    }

    public function test_category_returns_single_category() {
        $fluent_cart = new FluentCart();
        $request     = new \WP_REST_Request();
        $request->set_param( 'id', $this->category_id );

        $dto = $fluent_cart->category( null, $request, [ 'id', 'name' ] );

        $this->assertNotNull( $dto );
        $this->assertEquals( $this->category_id, $dto->get_id() );
        $this->assertEquals( 'Test FluentCart Category', $dto->get_name() );
    }

    public function test_category_throws_for_missing_id() {
        $fluent_cart = new FluentCart();
        $request     = new \WP_REST_Request();
        $request->set_param( 'id', 999999999 );

        $this->expectException( Exception::class );
        $fluent_cart->category( null, $request, [ 'id' ] );
    }

    public function test_cart_add_get_update_remove_clear() {
        wp_set_current_user( $this->user_id );
        $fluent_cart = new FluentCart();

        // Add
        $add_request = $this->build_request(
            [ 'items' => [ [ 'variantId' => $this->variation_id, 'quantity' => 2 ] ] ]
        );
        $cart        = $fluent_cart->cart_add( null, $add_request );

        $this->assertEquals( 2, $cart->get_item_count() );
        $items = $cart->get_items();
        $this->assertCount( 1, $items );
        $this->assertEquals( $this->variation_id, $items[0]->get_variation_id() );

        // Get
        $get_cart = $fluent_cart->cart_get( null, $this->build_request() );
        $this->assertEquals( 2, $get_cart->get_item_count() );

        // Update (by_input => absolute quantity)
        $update_request = $this->build_request(
            [ 'items' => [ [ 'itemId' => $this->variation_id, 'quantity' => 5 ] ] ]
        );
        $updated_cart   = $fluent_cart->cart_update( null, $update_request );
        $this->assertEquals( 5, $updated_cart->get_item_count() );

        // Remove
        $remove_request = $this->build_request( [ 'itemIds' => [ $this->variation_id ] ] );
        $removed_cart   = $fluent_cart->cart_remove( null, $remove_request );
        $this->assertEquals( 0, $removed_cart->get_item_count() );

        // Re-add then clear
        $fluent_cart->cart_add( null, $add_request );
        $cleared_cart = $fluent_cart->cart_clear( null, $this->build_request() );
        $this->assertEquals( 0, $cleared_cart->get_item_count() );
    }

    public function test_orders_get_and_order_get() {
        $customer = Customer::create(
            [
                'user_id'    => $this->user_id,
                'email'      => 'fluentcart-test@example.com',
                'first_name' => 'Test',
                'last_name'  => 'Customer',
                'status'     => 'active',
            ]
        );

        $order = Order::create(
            [
                'status'         => 'completed',
                'customer_id'    => $customer->id,
                'payment_status' => 'paid',
                'currency'       => 'USD',
                'subtotal'       => 2999,
                'total_amount'   => 2999,
                'tax_total'      => 0,
                'shipping_total' => 0,
                'mode'           => 'test',
            ]
        );

        OrderItem::create(
            [
                'order_id'   => $order->id,
                'post_id'    => $this->product_id,
                'object_id'  => $this->variation_id,
                'post_title' => 'Test FluentCart Product',
                'title'      => 'Default',
                'quantity'   => 1,
                'unit_price' => 2999,
                'line_total' => 2999,
            ]
        );

        wp_set_current_user( $this->user_id );
        $fluent_cart = new FluentCart();

        $paginator = $fluent_cart->orders_get( null, $this->build_request( [ 'page' => 1, 'per_page' => 10 ] ) );
        $this->assertGreaterThanOrEqual( 1, $paginator->get_total() );

        $order_ids = array_map( fn( $dto ) => $dto->id, $paginator->get_items() );
        $this->assertContains( (string) $order->id, $order_ids );

        $single = $fluent_cart->order_get( null, $order->id, $this->build_request() );
        $this->assertNotNull( $single );
        $this->assertEquals( (string) $order->id, $single->id );
        $this->assertCount( 1, $single->line_items );
        $this->assertEquals( $this->product_id, $single->line_items[0]->product_id );

        $order->delete();
        $customer->delete();
    }

    public function test_order_get_returns_null_for_other_users_order() {
        $other_user_id = $this->factory->user->create( [ 'role' => 'customer' ] );
        $customer      = Customer::create(
            [
                'user_id'    => $other_user_id,
                'email'      => 'fluentcart-other@example.com',
                'first_name' => 'Other',
                'last_name'  => 'Customer',
                'status'     => 'active',
            ]
        );

        $order = Order::create(
            [
                'status'         => 'completed',
                'customer_id'    => $customer->id,
                'payment_status' => 'paid',
                'currency'       => 'USD',
                'subtotal'       => 2999,
                'total_amount'   => 2999,
                'mode'           => 'test',
            ]
        );

        wp_set_current_user( $this->user_id );
        $fluent_cart = new FluentCart();

        $result = $fluent_cart->order_get( null, $order->id, $this->build_request() );
        $this->assertNull( $result );

        $order->delete();
        $customer->delete();
    }
}
