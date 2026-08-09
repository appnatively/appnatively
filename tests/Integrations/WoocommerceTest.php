<?php

namespace Crafium\AppNatively\Tests\Integrations;

use Crafium\AppNatively\App\Integrations\Ecommerce\Woocommerce;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class WoocommerceTest extends \WP_UnitTestCase
{
    private $simple_product_id;

    private $variable_product_id;

    private $variation_id;

    private $category_id;

    private $user_id;

    public function setUp(): void {
        parent::setUp();

        if ( ! class_exists( '\WC_Product_Simple' ) ) {
            $this->markTestSkipped( 'WooCommerce is not loaded.' );
        }

        $term              = wp_insert_term( 'Test Category', 'product_cat' );
        $this->category_id = $term['term_id'];

        $simple = new \WC_Product_Simple();
        $simple->set_name( 'Test Simple Product' );
        $simple->set_regular_price( '19.99' );
        $simple->set_price( '19.99' );
        $simple->set_status( 'publish' );
        $simple->set_stock_status( 'instock' );
        $simple->set_category_ids( [ $this->category_id ] );
        $this->simple_product_id = $simple->save();

        $attribute = new \WC_Product_Attribute();
        $attribute->set_id( 0 );
        $attribute->set_name( 'Color' );
        $attribute->set_options( [ 'Red', 'Blue' ] );
        $attribute->set_visible( true );
        $attribute->set_variation( true );

        $variable = new \WC_Product_Variable();
        $variable->set_name( 'Test Variable Product' );
        $variable->set_status( 'publish' );
        $variable->set_attributes( [ $attribute ] );
        $this->variable_product_id = $variable->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id( $this->variable_product_id );
        $variation->set_attributes( [ 'color' => 'Red' ] );
        $variation->set_regular_price( '25.00' );
        $variation->set_stock_status( 'instock' );
        $this->variation_id = $variation->save();

        $this->user_id = $this->factory->user->create( [ 'role' => 'customer' ] );
    }

    public function tearDown(): void {
        if ( $this->simple_product_id ) {
            wp_delete_post( $this->simple_product_id, true );
        }
        if ( $this->variation_id ) {
            wp_delete_post( $this->variation_id, true );
        }
        if ( $this->variable_product_id ) {
            wp_delete_post( $this->variable_product_id, true );
        }
        if ( $this->category_id ) {
            wp_delete_term( $this->category_id, 'product_cat' );
        }
        if ( function_exists( 'WC' ) && WC()->cart ) {
            WC()->cart->empty_cart();
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
        $woocommerce = new Woocommerce();
        $request     = $this->build_request( [ 'page' => 1, 'per_page' => 10 ] );

        $paginator = $woocommerce->products( null, $request, [ 'id', 'name', 'price' ] );

        $this->assertGreaterThanOrEqual( 1, $paginator->get_total() );
        $ids = array_map( fn( $dto ) => $dto->get_id(), $paginator->get_items() );
        $this->assertContains( $this->simple_product_id, $ids );
    }

    public function test_product_returns_single_product() {
        $woocommerce = new Woocommerce();
        $request     = $this->build_request( [ 'id' => $this->simple_product_id ] );

        $dto = $woocommerce->product( null, $request, [ 'id', 'name', 'price', 'categories' ] );

        $this->assertNotNull( $dto );
        $this->assertEquals( $this->simple_product_id, $dto->get_id() );
        $this->assertEquals( 'Test Simple Product', $dto->get_name() );
        $this->assertEquals( '19.99', $dto->get_price() );
    }

    public function test_product_throws_for_missing_id() {
        $woocommerce = new Woocommerce();
        $request     = $this->build_request( [ 'id' => 999999999 ] );

        $this->expectException( Exception::class );
        $woocommerce->product( null, $request, [ 'id' ] );
    }

    public function test_variable_product_includes_variants() {
        $woocommerce = new Woocommerce();
        $request     = $this->build_request( [ 'id' => $this->variable_product_id ] );

        $dto = $woocommerce->product( null, $request, [ 'id', 'variants' ] );

        $this->assertNotNull( $dto );
        $variant_ids = array_map( fn( $v ) => $v->get_id(), $dto->get_variants() );
        $this->assertContains( $this->variation_id, $variant_ids );
    }

    public function test_categories_returns_seeded_category() {
        $woocommerce = new Woocommerce();
        $request     = $this->build_request( [ 'page' => 1, 'per_page' => 10 ] );

        $paginator = $woocommerce->categories( null, $request, [ 'id', 'name' ] );

        $ids = array_map( fn( $dto ) => $dto->get_id(), $paginator->get_items() );
        $this->assertContains( $this->category_id, $ids );
    }

    public function test_category_returns_single_category() {
        $woocommerce = new Woocommerce();
        $request     = $this->build_request( [ 'id' => $this->category_id ] );

        $dto = $woocommerce->category( null, $request, [ 'id', 'name' ] );

        $this->assertNotNull( $dto );
        $this->assertEquals( $this->category_id, $dto->get_id() );
        $this->assertEquals( 'Test Category', $dto->get_name() );
    }

    public function test_category_throws_for_missing_id() {
        $woocommerce = new Woocommerce();
        $request     = $this->build_request( [ 'id' => 999999999 ] );

        $this->expectException( Exception::class );
        $woocommerce->category( null, $request, [ 'id' ] );
    }

    public function test_cart_add_get_update_remove_clear() {
        wp_set_current_user( $this->user_id );
        $woocommerce = new Woocommerce();

        // Add
        $add_request = $this->build_request(
            [ 'items' => [ [ 'productId' => $this->simple_product_id, 'quantity' => 2 ] ] ]
        );
        $cart        = $woocommerce->cart_add( null, $add_request );

        $this->assertEquals( 2, $cart->get_item_count() );
        $items = $cart->get_items();
        $this->assertCount( 1, $items );
        $item_key = $items[0]->get_key();

        // Get
        $get_cart = $woocommerce->cart_get( null, $this->build_request() );
        $this->assertEquals( 2, $get_cart->get_item_count() );

        // Update
        $update_request = $this->build_request(
            [ 'items' => [ [ 'itemId' => $item_key, 'quantity' => 5 ] ] ]
        );
        $updated_cart   = $woocommerce->cart_update( null, $update_request );
        $this->assertEquals( 5, $updated_cart->get_item_count() );

        // Remove
        $remove_request = $this->build_request( [ 'itemIds' => [ $item_key ] ] );
        $removed_cart   = $woocommerce->cart_remove( null, $remove_request );
        $this->assertEquals( 0, $removed_cart->get_item_count() );

        // Re-add then clear
        $woocommerce->cart_add( null, $add_request );
        $cleared_cart = $woocommerce->cart_clear( null, $this->build_request() );
        $this->assertEquals( 0, $cleared_cart->get_item_count() );
    }

    public function test_orders_get_and_order_get() {
        wp_set_current_user( $this->user_id );
        $woocommerce = new Woocommerce();

        $order = wc_create_order( [ 'customer_id' => $this->user_id ] );
        $order->add_product( wc_get_product( $this->simple_product_id ), 1 );
        $order->set_status( 'completed' );
        $order->calculate_totals();
        $order->save();

        $orders_paginator = $woocommerce->orders_get( null, $this->build_request( [ 'page' => 1, 'per_page' => 10 ] ) );
        $this->assertGreaterThanOrEqual( 1, $orders_paginator->get_total() );

        $order_ids = array_map( fn( $dto ) => $dto->id, $orders_paginator->get_items() );
        $this->assertContains( (string) $order->get_id(), $order_ids );

        $single = $woocommerce->order_get( null, $order->get_id(), $this->build_request() );
        $this->assertNotNull( $single );
        $this->assertEquals( (string) $order->get_id(), $single->id );

        $order->delete( true );
    }

    public function test_order_get_returns_null_for_other_users_order() {
        $other_user_id = $this->factory->user->create( [ 'role' => 'customer' ] );

        $order = wc_create_order( [ 'customer_id' => $other_user_id ] );
        $order->add_product( wc_get_product( $this->simple_product_id ), 1 );
        $order->set_status( 'completed' );
        $order->calculate_totals();
        $order->save();

        wp_set_current_user( $this->user_id );
        $woocommerce = new Woocommerce();

        $result = $woocommerce->order_get( null, $order->get_id(), $this->build_request() );

        $order->delete( true );

        $this->assertNull( $result );
    }
}
