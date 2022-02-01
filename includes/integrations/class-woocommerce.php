<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Abandoned_Cart_Woocommerce extends WPF_Abandoned_Cart_Integrations_Base {

	/**
	 * Get things started
	 *
	 * @access public
	 * @return void
	 */

	public function init() {

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_filter( 'wpf_configure_sections', array( $this, 'configure_sections' ), 10, 2 );
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 20, 2 );

		// Product settings
		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ) );

		// Variations fields
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variable_fields' ), 15, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variable_fields' ), 10, 2 );

		// Abandoned cart tracking
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart' ), 10, 6 );
		add_action( 'wpf_abandoned_cart_start', array( $this, 'checkout_begin' ), 30, 4 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'before_checkout_form' ) );

		// Cart recovery URL
		add_action( 'init', array( $this, 'set_recovered_cart_cookie' ) );
		add_action( 'wp_loaded', array( $this, 'recover_cart' ) );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'pre_fill_checkout_fields' ), 10, 2 );

		// After checkout complete
		add_action( 'wpf_woocommerce_payment_complete', array( $this, 'checkout_complete' ), 20, 2 ); // 20 so we don't delete the saved cart before the Ecom addon runs

	}

	/**
	 * Enqueue scripts on checkout page
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_scripts() {

		// We're not using wpf_is_user_logged_in() here so that the script is still enqueued during an auto login session

		if ( is_checkout() && ! is_user_logged_in() ) {
			wp_enqueue_script( 'wpf-abandoned-cart', WPF_ABANDONED_CART_DIR_URL . 'assets/wpf-abandoned-cart.js', array( 'jquery' ), WPF_ABANDONED_CART_VERSION, true );
			wp_localize_script( 'wpf-abandoned-cart', 'wpf_ac_ajaxurl', admin_url( 'admin-ajax.php' ) );
		}

	}


	/**
	 * Adds Addons tab if not already present
	 *
	 * @access public
	 * @return void
	 */

	public function configure_sections( $page, $options ) {

		if ( ! isset( $page['sections']['addons'] ) ) {
			$page['sections'] = wp_fusion()->settings->insert_setting_before( 'import', $page['sections'], array( 'addons' => __( 'Addons', 'wp-fusion-abandoned-cart' ) ) );
		}

		return $page;

	}


	/**
	 * Add fields to settings page
	 *
	 * @access public
	 * @return array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['ec_woo_header'] = array(
			'title'   => __( 'Misc. Ecommerce Settings', 'wp-fusion-abandoned-cart' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'addons',
		);

		return $settings;

	}

	/**
	 * Display abandoned cart input on WPF / Woo panel
	 *
	 * @access public
	 * @return mixed
	 */

	public function panel_content() {

		global $post;
		$settings = array( 'apply_tags_abandoned' => array() );

		if ( get_post_meta( $post->ID, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group wpf-product">';

		echo '<p class="form-field"><label><strong>' . __( 'Abandoned Cart', 'wp-fusion-abandoned-cart' ) . '</strong></label></p>';

		echo '<p class="form-field"><label for="wpf-apply-tags-woo">' . __( 'Apply tags when cart abandoned', 'wp-fusion-abandoned-cart' ) . ':</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_abandoned'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_abandoned',
			)
		);

		echo '</p>';

		echo '</div>';

	}


	/**
	 * Adds tag multiselect to variation fields
	 *
	 * @access public
	 * @return mixed
	 */

	public function variable_fields( $loop, $variation_data, $variation ) {

		global $post;

		$defaults = array(
			'apply_tags_variation_abandoned' => array( $variation->ID => array() ),
		);

		$settings = get_post_meta( $variation->ID, 'wpf-settings-woo', true );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		$settings = array_merge( $defaults, $settings );

		// If we're using the old variation data store
		$old_settings = get_post_meta( $post->ID, 'wpf-settings-woo', true );

		if ( isset( $old_settings['apply_tags_variation_abandoned'] ) && isset( $old_settings['apply_tags_variation_abandoned'][ $variation->ID ] ) ) {

			$settings['apply_tags_variation_abandoned'][ $variation->ID ] = $old_settings['apply_tags_variation_abandoned'][ $variation->ID ];

		}

		echo '<div><p class="form-row form-row-full"><label for="wpf-apply-tags-woo">' . __( 'Apply tags when cart abandoned', 'wp-fusion-abandoned-cart' ) . ':</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_variation_abandoned'][ $variation->ID ],
				'meta_name' => "wpf-settings-woo-variation[apply_tags_variation_abandoned][{$variation->ID}]",
			)
		);

		echo '</p></div>';

	}


	/**
	 * Saves variable field data to product
	 *
	 * @access public
	 * @return mixed
	 */


	public function save_variable_fields( $variation_id, $i ) {

		// Clean up old data storage
		$post_id = $_POST['product_id'];

		$post_meta = get_post_meta( $post_id, 'wpf-settings-woo', true );

		if ( isset( $post_meta['apply_tags_variation_abandoned'] ) ) {

			unset( $post_meta['apply_tags_variation_abandoned'] );
			update_post_meta( $post_id, 'wpf-settings-woo', $post_meta );

		}

	}

	/**
	 * Triggered when a product is added to the cart for logged in users
	 *
	 * @access public
	 * @return void
	 */

	public function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

		if ( ! wpf_is_user_logged_in() || wp_fusion()->settings->get( 'abandoned_cart_add_to_cart' ) == false ) {
			return;
		}

		$apply_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags', array() );

		$this->checkout_begin( wp_fusion()->user->get_contact_id(), $apply_tags );

	}

	/**
	 * Triggered at start of Woo checkout for logged in users when the add-to-cart trigger is off
	 *
	 * @access public
	 * @return void
	 */

	public function before_checkout_form( $checkout_object ) {

		if ( wpf_is_user_logged_in() && wp_fusion()->settings->get( 'abandoned_cart_add_to_cart' ) == true ) {
			// Already happened during add to cart
			return;
		}

		if ( ! wpf_is_user_logged_in() ) {
			return;
		}

		$apply_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags', array() );

		$this->checkout_begin( wp_fusion()->user->get_contact_id(), $apply_tags );

	}

	/**
	 * Get cart recovery URL
	 *
	 * @access public
	 * @return string Recovery URL
	 */

	public function get_cart_recovery_url( $contact_id, $user_data ) {

		$cart = array(
			'contents' => WC()->cart->get_cart(),
			'user'     => $user_data,
		);

		wp_fusion_abandoned_cart()->carts->create_update_cart( $cart, $contact_id );

		$destination = wp_fusion()->settings->get( 'abandoned_cart_recovery_url_destination', 'checkout' );

		if ( $destination == 'checkout' ) {

			$url = wc_get_checkout_url();

		} elseif ( $destination == 'cart' ) {

			$url = wc_get_cart_url();

		} elseif ( $destination == 'current' ) {

			global $wp;

			if ( ! empty( $wp->request ) ) {

				$url = home_url( $wp->request );

			} else {

				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

					$url = $_SERVER['HTTP_REFERER'];

				} else {

					$url = wc_get_checkout_url();

				}
			}
		}

		$url = add_query_arg( 'wpfrc', urlencode( $contact_id ), trailingslashit( $url ) );

		return $url;

	}

	/**
	 * Get cart ID from WooCommerce session
	 *
	 * @access public
	 * @return bool / int Cart ID
	 */

	public function get_cart_id() {

		// Don't want to use wpf_is_user_logged_in() here otherwise we'll end up with the temporary user ID for the session key

		if ( is_user_logged_in() ) {

			$session_key = get_current_user_id();

		} else {

			$session = new WC_Session_Handler();

			$cookie = $session->get_session_cookie();

			if ( false === $cookie ) {
				return false;
			}

			$session_key = $cookie[0];

		}

		global $wpdb;

		$table = $GLOBALS['wpdb']->prefix . 'woocommerce_sessions';

		$value = $wpdb->get_var( $wpdb->prepare( "SELECT session_id FROM $table WHERE session_key = %s", $session_key ) );

		return $value;

	}

	/**
	 * Applies product specific abandoned cart tags when user data is first captured
	 *
	 * @access public
	 * @return void
	 */

	public function checkout_begin( $contact_id = false, $apply_tags = array(), $user_data = array(), $source = 'woocommerce' ) {

		// Only run on a WC cart
		if ( empty( WC()->cart->get_cart() ) ) {
			return;
		}

		if ( false == $contact_id ) {
			return; // Can't do anything at this point without a contact record
		}

		if ( empty( $user_data ) && wpf_is_user_logged_in() ) {

			// If the user is logged in, get their data from the database. This gets synced to the CRM and also stored in the recovery transient
			$user_data = wp_fusion()->user->get_user_meta( wpf_get_current_user_id() );

		}

		if ( wp_fusion_abandoned_cart()->carts->get_cart( $contact_id ) ) {
			$update = true;
		} else {
			$update = false;
		}

		WC()->cart->calculate_totals(); // Calc totals here so get_cart_contents_total() is accurate

		//
		// Sync update data
		//

		$recovery_url = $this->get_cart_recovery_url( $contact_id, $user_data );

		$update_data = array();

		// Recovery URL

		if ( wpf_is_field_active( 'cart_recovery_url' ) ) {
			$update_data['cart_recovery_url'] = $recovery_url;
		}

		// Cart value

		if ( wpf_is_field_active( 'cart_value' ) ) {
			$update_data['cart_value'] = WC()->cart->get_cart_contents_total();
		}

		// Discounts

		if ( WC()->cart->get_coupons() ) {

			$coupons = WC()->cart->get_coupons();
			$coupon  = reset( $coupons );

			if ( wpf_is_field_active( 'cart_discount_code' ) ) {
				$update_data['cart_discount_code'] = $coupon->get_code();
			}

			if ( wpf_is_field_active( 'cart_discount_amount' ) ) {
				$update_data['cart_discount_amount'] = WC()->cart->get_discount_total();
			}
		}

		$update_data = apply_filters( 'wpf_abandoned_cart_data', $update_data, $contact_id );

		if ( ! empty( $update_data ) ) {

			wp_fusion()->logger->handle(
				'info', wpf_get_current_user_id(), 'Syncing abandoned cart data:', array(
					'meta_array_nofilter' => $update_data,
					'source'              => 'wpf-abandoned-cart',
				)
			);

			wp_fusion()->crm->update_contact( $contact_id, $update_data );

		}

		// Global apply tags

		$apply_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags', array() );

		$items = array();

		foreach ( WC()->cart->get_cart() as $item ) {

			$product_id = $item['product_id'];

			if ( empty( $product_id ) ) {

				// For variations get the setting from the parent
				$product_id = $item['data']->get_parent_id();

			}

			//
			// Put together the item data
			//

			$item_data = array(
				'product_id'  => (string) $product_id,
				// 'name'        => get_post_field('post_title', $product_id, 'raw' ), // can't remmeber why we added this in v1.5...
				'name'        => get_the_title( $product_id ), // Needs to be get_the_title() so it works with WP Multilang
				'quantity'    => $item['quantity'],
				'total'       => round( $item['line_subtotal'], 2 ),
				'product_url' => get_the_permalink( $product_id ),
				'description' => get_the_excerpt( $product_id ),
			);

			$size = wp_fusion()->settings->get( 'abandoned_cart_image_size', 'medium' );

			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), $size );

			if ( ! empty( $image ) ) {
				$item_data['image_url'] = $image[0];
			}

			$item_data['price'] = (float) $item_data['total'] / (float) $item_data['quantity'];

			if ( wc_prices_include_tax() ) {

				$item_data['total'] += $item['line_subtotal_tax'];
				$item_data['price'] += round( $item['line_subtotal_tax'] / $item_data['quantity'], 2 );

			}

			if ( ! empty( get_post_meta( $product_id, '_sku', true ) ) ) {
				$item_data['sku'] = get_post_meta( $product_id, '_sku', true );
			}

			//
			// Categories
			//

			if ( wp_fusion()->settings->get( 'abandoned_cart_categories' ) == 'attributes' ) {

				// Attributes as categories

				$attributes = $item['data']->get_attributes();

				if ( ! empty( $attributes ) ) {

					foreach ( $attributes as $name => $value ) {

						if ( is_object( $value ) ) {
							continue; // Fixes Recoverable fatal error: Object of class WC_Product_Attribute could not be converted to string
						}

						$taxonomy = wc_attribute_taxonomy_name( str_replace( 'attribute_pa_', '', urldecode( $name ) ) );

						if ( taxonomy_exists( $taxonomy ) ) {

							// If this is a term slug, get the term's nice name.
							$term = get_term_by( 'slug', $value, $taxonomy );

							if ( ! is_wp_error( $term ) && $term && $term->name ) {
								$value = $term->name;
							}
							$label = wc_attribute_label( $taxonomy );

						} else {

							// If this is a custom option slug, get the options name.
							$value = apply_filters( 'woocommerce_variation_option_name', $value, null, $taxonomy, $item['data'] );
							$label = wc_attribute_label( str_replace( 'attribute_', '', $name ), $item['data'] );
						}

						$item_data['categories'][] = $label . ': ' . $value;

					}
				}
			} else {

				// Categories as categories

				$product_terms = get_the_terms( $product_id, 'product_cat' );

				if ( ! empty( $product_terms ) ) {

					$item_data['categories'] = array();

					foreach ( $product_terms as $term ) {

						$item_data['categories'][] = $term->name;

					}
				}
			}

			//
			// Get abandoned cart tags based on the product
			//

			$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

			// Products

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_abandoned'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_abandoned'] );
			}

			// Variations

			if ( isset( $item['variation_id'] ) && $item['variation_id'] != 0 ) {

				// Data

				$item_data['product_variant_id'] = $item['variation_id'];
				$item_data['name']               = get_the_title( $item['variation_id'] );
				//$item_data['name']               = get_post_field( 'post_title', $item['variation_id'], 'raw' );

				$image = wp_get_attachment_image_src( get_post_thumbnail_id( $item['variation_id'] ), $size );

				if ( ! empty( $image ) ) {
					$item_data['image_url'] = $image[0];
				}

				// Tags

				if ( isset( $settings['apply_tags_variation_abandoned'] ) && ! empty( $settings['apply_tags_variation_abandoned'][ $item['variation_id'] ] ) ) {

					// Old method
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_variation_abandoned'][ $item['variation_id'] ] );

				} else {

					// New method
					$variation_settings = get_post_meta( $item['variation_id'], 'wpf-settings-woo', true );

					if ( is_array( $variation_settings ) && isset( $variation_settings['apply_tags_variation_abandoned'][ $item['variation_id'] ] ) ) {

						$apply_tags = array_merge( $apply_tags, $variation_settings['apply_tags_variation_abandoned'][ $item['variation_id'] ] );

					}
				}
			}

			$items[] = $item_data;

		}

		// Discounts

		$discounts = array();

		foreach ( WC()->cart->get_coupons() as $coupon ) {

			$discounts[] = array(
				'name'   => $coupon->get_code(),
				'amount' => WC()->cart->get_discount_total(),
			);

		}

		//
		// Apply the tags
		//

		$apply_tags = apply_filters( 'wpf_abandoned_cart_apply_tags', $apply_tags, $contact_id );

		if ( ! empty( $apply_tags ) ) {

			// Don't apply auto-discounts for abandoned carts
			remove_action( 'wpf_tags_modified', array( wp_fusion()->integrations->woocommerce, 'maybe_apply_coupons' ), 10, 2 );

			// Apply the tags
			if ( wpf_is_user_logged_in() ) {

				wp_fusion()->user->apply_tags( $apply_tags );

			} elseif ( false != $contact_id ) {

				wp_fusion()->logger->handle(
					'info', get_current_user_id(), 'Applying abandoned cart tags to contact #' . $contact_id . ':', array(
						'tag_array' => $apply_tags,
						'source'    => 'wpf-abandoned-cart',
					)
				);

				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}
		}

		//
		// Gets the recovery URL
		//

		$args = array(
			'cart_id'      => $this->get_cart_id(),
			'recovery_url' => $recovery_url,
			'items'        => $items,
			'discounts'    => $discounts,
			'user_email'   => $user_data['user_email'],
			'provider'     => 'WooCommerce',
			'update'       => $update,
			'currency'     => get_woocommerce_currency(),
		);

		do_action( 'wpf_abandoned_cart_created', $contact_id, $args );

	}

	/**
	 * Sets cookie for pre-filling checkout
	 *
	 * @access public
	 * @return void
	 */

	public function set_recovered_cart_cookie() {

		if ( isset( $_GET['wpfrc'] ) ) {

			setcookie( 'wpfrc', $_GET['wpfrc'], time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );

		}

	}

	/**
	 * Recover cart URL processing
	 *
	 * @access public
	 * @return void
	 */

	public function recover_cart() {

		if ( isset( $_GET['wpfrc'] ) ) {

			$contact_id = urldecode( $_GET['wpfrc'] );
			$contact_id = str_replace( ' ', '-', $contact_id ); // + symbols become spaces when URL encoded
			$contact_id = sanitize_text_field( $contact_id );

			// Some ESPs append query vars to the end of the URL (like UTM tracking links). This cleans those up.
			if ( false !== strpos( $contact_id, '?' ) ) {
				$contact_id = explode( '?', $contact_id );
				$contact_id = $contact_id[0];
			}

			// No need to send another abandoned cart
			remove_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart' ), 10, 6 );
			remove_action( 'wpf_abandoned_cart_start', array( $this, 'checkout_begin' ), 30, 3 );

			$cart = wp_fusion_abandoned_cart()->carts->get_cart( $contact_id );

			if ( ! empty( $cart ) ) {

				if ( isset( $cart['source'] ) && 'woocommerce' !== $cart['source'] ) {
					return; // don't recover carts from other sources
				}

				if ( isset( $cart['user'] ) ) {

					// Start tracking session
					do_action( 'wpf_guest_contact_updated', $contact_id, $cart['user']['user_email'] );

				}

				if ( isset( $cart['contents'] ) ) {
					$cart = $cart['contents'];
				}

				WC()->cart->set_cart_contents( $cart );

			} else {
				wpf_log( 'notice', 0, 'A cart recovery link was visited for contact ID ' . $contact_id . ' but no cart was found in the database.' );
			}

			// Auto discounts
			$contact_tags = wp_fusion()->crm->get_tags( $contact_id );

			if ( is_wp_error( $contact_tags ) ) {
				wpf_log( 'error', 0, 'Unable to load tags for contact ID ' . $contact_id . ': ' . $contact_tags->get_error_message() );
			}

			wp_fusion()->integrations->woocommerce->maybe_apply_coupons( null, $contact_tags );

		}

	}

	/**
	 * Pre-fill checkout fields with recovered cart data
	 *
	 * @access public
	 * @return string Value
	 */

	public function pre_fill_checkout_fields( $input, $key ) {

		if ( wpf_is_user_logged_in() ) {
			return $input;
		}

		if ( ! isset( $_COOKIE['wpfrc'] ) && ! isset( $_GET['wpfrc'] ) ) {
			return $input;
		}

		if ( isset( $_GET['wpfrc'] ) ) {
			$contact_id = sanitize_text_field( $_GET['wpfrc'] );
		} else {
			$contact_id = sanitize_text_field( $_COOKIE['wpfrc'] );
		}

		$cart = wp_fusion_abandoned_cart()->carts->get_cart( $contact_id );

		if ( empty( $cart ) ) {
			return $input;
		}

		if ( isset( $cart['user'][ $key ] ) ) {
			return $cart['user'][ $key ];
		} else {
			return $input;
		}

	}

	/**
	 * Remove abandoned cart tags
	 *
	 * @access public
	 * @return void
	 */

	public function checkout_complete( $order_id, $contact_id ) {

		// Get tags to be removed
		$remove_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags', array() );

		if ( empty( $remove_tags ) ) {
			$remove_tags = array();
		}

		$order = new WC_Order( $order_id );

		$products = $order->get_items();

		foreach ( $products as $product ) {

			$settings = get_post_meta( $product['product_id'], 'wpf-settings-woo', true );

			if ( empty( $settings ) ) {
				continue;
			}

			// Products
			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_abandoned'] ) ) {
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_abandoned'] );
			}

			// Variations
			if ( isset( $product['variation_id'] ) && $product['variation_id'] != 0 ) {

				if ( isset( $settings['apply_tags_variation_abandoned'] ) && ! empty( $settings['apply_tags_variation_abandoned'][ $product['variation_id'] ] ) ) {

					// Old method
					$remove_tags = array_merge( $remove_tags, $settings['apply_tags_variation_abandoned'][ $product['variation_id'] ] );

				} else {

					// New method
					$variation_settings = get_post_meta( $product['variation_id'], 'wpf-settings-woo', true );

					if ( is_array( $variation_settings ) && isset( $variation_settings['apply_tags_variation_abandoned'][ $product['variation_id'] ] ) ) {

						$remove_tags = array_merge( $remove_tags, $variation_settings['apply_tags_variation_abandoned'][ $product['variation_id'] ] );

					}
				}
			}
		}

		// Remove tags
		if ( ! empty( $remove_tags ) ) {

			$email   = $order->get_billing_email();
			$user_id = $order->get_user_id();

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->remove_tags( $remove_tags, $user_id );

			} else {

				wp_fusion()->logger->handle(
					'info', get_current_user_id(), 'Removing abandoned cart tags:', array(
						'tag_array' => $remove_tags,
						'source'    => 'wpf-abandoned-cart',
					)
				);

				wp_fusion()->crm->remove_tags( $remove_tags, $contact_id );

			}
		}

		//
		// Reset cart fields
		//

		$update_data = array();

		if ( wpf_is_field_active( 'cart_recovery_url' ) ) {
			$update_data['cart_recovery_url'] = null;
		}

		if ( wpf_is_field_active( 'cart_value' ) ) {
			$update_data['cart_value'] = null;
		}

		if ( ! empty( $update_data ) ) {
			wp_fusion()->crm->update_contact( $contact_id, $update_data );
		}

		// Clear out saved cart

		wp_fusion_abandoned_cart()->carts->delete_cart( $contact_id );

		do_action( 'wpf_abandoned_cart_recovered', $contact_id, $order_id );

	}

}

new WPF_Abandoned_Cart_Woocommerce();
