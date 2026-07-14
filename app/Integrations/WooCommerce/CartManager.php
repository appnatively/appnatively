<?php

namespace Crafium\AppNatively\App\Integrations\WooCommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Ecommerce\CartDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\CartItemDTO;
use Crafium\AppNatively\App\DTO\Ecommerce\ProductImageDTO;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;

class CartManager {
    /**
     * Ensure WooCommerce cart and session are loaded.
     * Also authenticates the user from the Bearer token if present.
     *
     * @param Request|null $request The REST request instance.
     * @return void
     */
    public function ensure_cart_loaded( ?Request $request = null ): void {
        $session_needs_reload = false;

        // 1. Authenticate user from Bearer token if present
        if ( $request && ! get_current_user_id() ) {
            $auth_header = $request->get_header( 'Authorization' );
            if ( $auth_header && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
                $token        = $matches[1];
                $hashed_token = hash( 'sha256', $token );
                $users        = get_users(
                    [
                        //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                        'meta_key'    => 'craf_appna_auth_token',
                        //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                        'meta_value'  => $hashed_token,
                        'number'      => 1,
                        'count_total' => false,
                    ] 
                );

                if ( ! empty( $users ) ) {
                    wp_set_current_user( $users[0]->ID );
                    $session_needs_reload = true;
                }
            }
        }

        // 2. If user is a guest and guest cartId is passed, set session cookie
        if ( ! $session_needs_reload && ! get_current_user_id() ) {
            $cart_id = null;
            if ( $request ) {
                $cart_id = $request->get_header( 'X-WC-Session' ) ?: $request->get_header( 'X-Cart-Id' ) ?: $request->get_param( 'cartId' );
                //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
                if ( ! $cart_id && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
                    $raw_body = file_get_contents( 'php://input' );
                    if ( $raw_body ) {
                        $body_data = json_decode( $raw_body, true );
                        $cart_id   = $body_data['cartId'] ?? null;
                    }
                }
            }

            if ( $cart_id && is_string( $cart_id ) && $cart_id !== 'cart' ) {
                $cookie_name             = 'wp_woocommerce_session_' . COOKIEHASH;
                $_COOKIE[ $cookie_name ] = $cart_id;
                $session_needs_reload    = true;
            }
        }

        // 3. Initialize WooCommerce session/cart objects if not yet created
        if ( is_null( WC()->session ) ) {
            WC()->session = new \WC_Session_Handler();
            WC()->session->init();
            // Since it was just created, it already loaded the correct data, no reload needed
            $session_needs_reload = false;
        }
        if ( is_null( WC()->cart ) ) {
            wc_load_cart();
        }

        // 4. Force reload session and cart contents if requested user or guest cart changed
        if ( $session_needs_reload ) {
            WC()->session->init();
            WC()->cart->get_cart_from_session();
        } elseif ( isset( WC()->cart ) && WC()->cart->is_empty() ) {
            // Fallback load if cart is empty in memory (first time load in REST context)
            WC()->cart->get_cart_from_session();
        }
    }

    /**
     * Save cart to session.
     */
    public function save_cart(): void {
        if ( ! is_null( WC()->cart ) ) {
            WC()->cart->calculate_totals();
        }
        if ( ! is_null( WC()->session ) ) {
            WC()->session->save_data();
        }
    }

    /**
     * Get cart DTO.
     *
     * @param CartDTO|null $cart_dto The cart DTO.
     * @param Request $request The REST request instance.
     * @return CartDTO
     */
    public function cart_get( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        WC()->cart->calculate_totals();

        $dto          = new CartDTO();
        $checkout_url = wc_get_checkout_url();
        $auth_header  = $request->get_header( 'Authorization' );
        if ( $auth_header && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
            $token        = $matches[1];
            $hashed_token = hash( 'sha256', $token );
            $users        = get_users(
                [
                    //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_key'    => 'craf_appna_auth_token',
                    //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                    'meta_value'  => $hashed_token,
                    'number'      => 1,
                    'count_total' => false,
                ] 
            );

            if ( ! empty( $users ) ) {
                $user_id = $users[0]->ID;
                // Generate a one-time-use autologin token
                $autologin_token  = bin2hex( random_bytes( 32 ) );
                $hashed_autologin = hash( 'sha256', $autologin_token );
                // Store as a transient — auto-expires in 5 minutes, one-time use
                set_transient( 'craf_appna_autologin_' . $hashed_autologin, $user_id, 5 * MINUTE_IN_SECONDS );
                
                $checkout_url = add_query_arg( 'craf_appna_token', $autologin_token, $checkout_url );
            }
        }

        $dto->set_subtotal( (string) WC()->cart->get_subtotal() )
            ->set_total( (string) WC()->cart->get_total( 'edit' ) )
            ->set_currency( get_woocommerce_currency() )
            ->set_item_count( WC()->cart->get_cart_contents_count() )
            ->set_checkout_url( $checkout_url );

        // Set the session token/cookie as the Cart ID
        $session_cookie = null;
        if ( isset( WC()->session ) ) {
            $customer_id = WC()->session->get_customer_id();
            if ( $customer_id && ! is_user_logged_in() ) {
                // Generate a signed cookie value for guests
                $session_expiration = time() + ( 3600 * 48 ); // 48 hours
                $session_expiring   = time() + ( 3600 * 47 ); // 47 hours
                $to_hash            = $customer_id . '|' . $session_expiration;
                
                if ( function_exists( 'wp_fast_hash' ) ) {
                    $cookie_hash = wp_fast_hash( $to_hash );
                } else {
                    $cookie_hash = hash_hmac( 'md5', $to_hash, wp_hash( $to_hash ) );
                }
                
                $session_cookie = $customer_id . '|' . $session_expiration . '|' . $session_expiring . '|' . $cookie_hash;
            }
        }
        $dto->set_id( $session_cookie ?: 'cart' );

        $items = [];
        foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
            $item_dto = new CartItemDTO();
            $product  = $cart_item['data'];
            
            $item_dto->set_key( $key )
                ->set_product_id( $cart_item['product_id'] )
                ->set_variation_id( $cart_item['variation_id'] )
                ->set_quantity( $cart_item['quantity'] )
                ->set_name( $product->get_name() )
                ->set_price( (string) $product->get_price() )
                ->set_subtotal( (string) $cart_item['line_subtotal'] )
                ->set_total( (string) $cart_item['line_total'] );

            $image_id = $product->get_image_id();
            
            // Fallback to parent image if variation has no image
            if ( ! $image_id && $product->is_type( 'variation' ) ) {
                $image_id = get_post_thumbnail_id( $product->get_parent_id() );
            }

            if ( $image_id ) {
                $img_url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
                if ( $img_url ) {
                    $img = new ProductImageDTO();
                    $img->set_id( (int) $image_id )
                        ->set_src( $img_url )
                        ->set_alt( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
                    $item_dto->set_image( $img );
                }
            }

            if ( ! empty( $cart_item['variation'] ) ) {
                $item_dto->set_variation( $cart_item['variation'] );
            }

            $items[] = $item_dto;
        }

        $dto->set_items( $items );

        return $dto;
    }

    /**
     * Add item to cart.
     */
    public function cart_add( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        
        $items = $request->get_param( "items" );
        foreach ( (array) $items as $item ) {
            $product_id   = (int) ( $item['productId'] ?? 0 );
            $variation_id = (int) ( $item['variantId'] ?? 0 );
            $quantity     = (int) ( $item['quantity'] ?? 1 );
            $options      = (array) ( $item['options'] ?? [] );
            $variations   = [];

            if ( $product_id && $variation_id ) {
                $product   = wc_get_product( $product_id );
                $variation = wc_get_product( $variation_id );

                if ( $product && $variation instanceof \WC_Product_Variation ) {
                    // Start with the raw attributes of the variation
                    $raw_attributes = $variation->get_variation_attributes();

                    // Map any empty ("Any...") attributes from the options passed by the frontend
                    foreach ( $raw_attributes as $attr_key => $attr_val ) {
                        if ( $attr_val === '' ) {
                            $attribute_name = preg_replace( '/^attribute_/', '', $attr_key );
                            $resolved_label = wc_attribute_label( $attribute_name, $product );

                            foreach ( $options as $opt_label => $opt_val ) {
                                if ( strcasecmp( $opt_label, $resolved_label ) === 0 ) {
                                    $slug = $opt_val;
                                    if ( taxonomy_exists( $attribute_name ) ) {
                                        $term = get_term_by( 'name', $opt_val, $attribute_name );
                                        if ( $term ) {
                                            $slug = $term->slug;
                                        }
                                    }
                                    $raw_attributes[ $attr_key ] = $slug;
                                    break;
                                }
                            }
                        }
                    }
                    $variations = $raw_attributes;
                }
            } elseif ( $product_id && $variation_id && empty( $variations ) ) {
                $variation = wc_get_product( $variation_id );
                if ( $variation instanceof \WC_Product_Variation ) {
                    $variations = $variation->get_variation_attributes();
                }
            }

            if ( $product_id ) {
                // Clear any existing notices before adding to cart
                wc_clear_notices();

                $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
                
                if ( ! $cart_item_key ) {
                    $errors = wc_get_notices( 'error' );
                    wc_clear_notices();
                    $message = '';
                    if ( ! empty( $errors ) ) {
                        $messages = [];
                        foreach ( $errors as $error ) {
                            $messages[] = strip_tags( $error['notice'] );
                        }
                        $message = implode( ' ', $messages );
                    }
                    if ( empty( $message ) ) {
                        $message = esc_html__( "Could not add product to cart.", "appnatively" );
                    }
                    throw new Exception( $message, 400 );
                }
                error_log( "add_to_cart product_id={$product_id}, quantity={$quantity}, variation_id={$variation_id}: SUCCESS key={$cart_item_key}" );
            }
        }

        $this->save_cart();

        return $this->cart_get( null, $request );
    }

    /**
     * Update cart item quantity.
     */
    public function cart_update( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        
        $items = $request->get_param( "items" );
        foreach ( (array) $items as $item ) {
            $key      = sanitize_text_field( $item['itemId'] ?? '' );
            $quantity = (int) ( $item['quantity'] ?? 0 );

            if ( $key ) {
                wc_clear_notices();
                $status = WC()->cart->set_quantity( $key, $quantity );
                
                // set_quantity returns false or a WP_Error on failure, or does nothing if quantity is the same
                if ( is_wp_error( $status ) ) {
                    throw new Exception( strip_tags( $status->get_error_message() ), 400 );
                }

                $errors = wc_get_notices( 'error' );
                if ( ! empty( $errors ) ) {
                    wc_clear_notices();
                    $messages = [];
                    foreach ( $errors as $error ) {
                        $messages[] = strip_tags( $error['notice'] );
                    }
                    throw new Exception( implode( ' ', $messages ), 400 );
                }
            }
        }

        $this->save_cart();

        return $this->cart_get( null, $request );
    }

    /**
     * Remove cart item.
     */
    public function cart_remove( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        
        $item_ids = $request->get_param( "itemIds" );
        foreach ( (array) $item_ids as $key ) {
            WC()->cart->remove_cart_item( sanitize_text_field( $key ) );
        }

        $this->save_cart();

        return $this->cart_get( null, $request );
    }

    /**
     * Clear cart.
     */
    public function cart_clear( ?CartDTO $cart_dto, Request $request ): CartDTO {
        $this->ensure_cart_loaded( $request );
        WC()->cart->empty_cart();

        $this->save_cart();

        return $this->cart_get( null, $request );
    }
}
