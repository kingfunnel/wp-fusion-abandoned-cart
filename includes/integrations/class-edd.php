<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Abandoned_Cart_EDD extends WPF_Abandoned_Cart_Integrations_Base {

	/**
	 * Get things started
	 *
	 * @access public
	 * @return void
	 */

	public function init() {

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Meta boxes
		add_action( 'wpf_edd_meta_box', array( $this, 'meta_box_content' ), 10, 2 );

		// Variable price column fields
		add_action( 'edd_download_price_table_row', array( $this, 'download_table_price_row' ), 10, 3 );

		// Abandoned cart tracking
		add_action( 'edd_before_checkout_cart', array( $this, 'checkout_begin' ) );
		add_action( 'wpf_abandoned_cart_start', array( $this, 'checkout_begin' ), 30, 4 );

		// After checkout complete
		add_action( 'wpf_edd_payment_complete', array( $this, 'checkout_complete' ), 20, 2 ); // 20 so we don't delete the cart before the Ecom addon runs

		// Cart recovery
		add_action( 'init', array( $this, 'recover_cart' ) );

	}

	/**
	 * Enqueue scripts on checkout page
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_scripts() {

		if ( edd_is_checkout() && ! wpf_is_user_logged_in() ) {
			wp_enqueue_script( 'wpf-abandoned-cart', WPF_ABANDONED_CART_DIR_URL . 'assets/wpf-abandoned-cart.js', array( 'jquery' ), WPF_ABANDONED_CART_VERSION, true );
			wp_localize_script( 'wpf-abandoned-cart', 'wpf_ac_ajaxurl', admin_url( 'admin-ajax.php' ) );
		}

	}

	/**
	 * Additional fields in EDD meta box
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_content( $post, $settings ) {

		if ( empty( $settings['apply_tags_abandoned'] ) ) {
			$settings['apply_tags_abandoned'] = array();
		}

		echo '<table class="form-table"><tbody>';

			echo '<tr>';

				echo '<th scope="row"><label for="apply_tags_abandoned">' . __( 'Apply Tags - Abandoned Cart', 'wp-fusion-abandoned-cart' ) . ':</label></th>';
				echo '<td>';
					wpf_render_tag_multiselect(
						array(
							'setting'   => $settings['apply_tags_abandoned'],
							'meta_name' => 'wpf-settings-edd',
							'field_id'  => 'apply_tags_abandoned',
						)
					);
					echo '<span class="description">' . __( 'Use these tags for abandoned cart tracking', 'wp-fusion-abandoned-cart' ) . '</span>';
				echo '</td>';

			echo '</tr>';

		echo '</tbody></table>';

	}


	/**
	 * Outputs WPF fields to variable price rows
	 *
	 * @access public
	 * @return voic
	 */

	public function download_table_price_row( $post_id, $key, $args ) {

		echo '<div class="edd-custom-price-option-section">';

		echo '<span class="edd-custom-price-option-section-title">' . __( 'WP Fusion - Abandoned Cart Settings', 'wp-fusion-abandoned-cart' ) . '</span>';

		$settings = array(
			'apply_tags_abandoned' => array(),
		);

		if ( get_post_meta( $post_id, 'wpf-settings-edd', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-edd', true ) );
		}

		if ( empty( $settings['apply_tags_abandoned_price'][ $key ] ) ) {
			$settings['apply_tags_abandoned_price'][ $key ] = array();
		}

		echo '<label>' . __( 'Apply tags when cart abandoned', 'wp-fusion-abandoned-cart' ) . ':</label><br />';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_abandoned_price'][ $key ],
				'meta_name' => "wpf-settings-edd[apply_tags_abandoned_price][{$key}]",
			)
		);

		echo '</div>';

	}

	/**
	 * Get cart recovery URL
	 *
	 * @access public
	 * @return string Recovery URL
	 */

	public function get_cart_recovery_url( $contact_id, $user_data ) {

		$session = array(
			'user'      => $user_data,
			'contents'  => EDD()->session->get( 'edd_cart' ),
			'customer'  => EDD()->session->get( 'customer' ),
			'discounts' => EDD()->session->get( 'cart_discounts' ),
			'fees'      => EDD()->session->get( 'edd_cart_fees' ),
			'options'   => array(
				'gateway' => edd_get_chosen_gateway(),
			),
			'source'    => 'edd',
		);

		wp_fusion_abandoned_cart()->carts->create_update_cart( $session, $contact_id );

		$url = add_query_arg( 'wpfrc', urlencode( $contact_id ), edd_get_checkout_uri() );

		return $url;

	}

	/**
	 * Recover a saved cart
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
			remove_action( 'wpf_abandoned_cart_start', array( $this, 'checkout_begin' ), 30, 2 );

			$cart = wp_fusion_abandoned_cart()->carts->get_cart( $contact_id );

			if ( ! empty( $cart ) ) {

				if ( isset( $cart['source'] ) && 'edd' !== $cart['source'] ) {
					return; // don't recover carts from other sources
				}

				EDD()->session->set( 'edd_cart', $cart['contents'] );

				// Set the customer

				$customer_data = array(
					'email'      => $cart['user']['user_email'],
					'first_name' => $cart['user']['first_name'],
					'last_name'  => $cart['user']['last_name'],
				);

				$customer = EDD()->customers->get_customer_by( 'email', $cart['user']['user_email'] );

				if ( $customer ) {
					$customer_data['customer_id'] = $customer->id;
					$customer_data['admin_url']   = admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' . $customer->id );
				}

				EDD()->session->set( 'customer', $customer_data );

				if ( ! empty( $cart['discounts'] ) ) {
					EDD()->session->set( 'cart_discounts', $cart['discounts'] );
				}

				// this check for discounts in the URL takes place before cart recovery,
				// so we need to call it manually here
				edd_listen_for_cart_discount();
				edd_apply_preset_discount();

				// reset has_discounts
				EDD()->cart->has_discounts = null;

				if ( ! empty( $cart['fees'] ) ) {
					EDD()->session->set( 'edd_cart_fees', $cart['fees'] );
				}

				if ( ! empty( $cart['options'] ) ) {
					foreach ( $cart['options'] as $session_key => $session_value ) {
						EDD()->session->set( $session_key, $session_value );
					}
				}

				// if the customer had selected a payment method at checkout, set it
				if ( ! empty( $cart['options']['gateway'] ) ) {

					$gateway = $cart['options']['gateway'];

					if ( edd_is_gateway_active( $gateway ) ) {
						$_REQUEST['payment-method'] = $gateway;
					}
				}

				// refresh the cart data from the session data set in this method
				EDD()->cart->setup_cart();

				// Auto discounts
				$contact_tags = wp_fusion()->crm->get_tags( $contact_id );

				if ( is_wp_error( $contact_tags ) ) {
					wpf_log( 'error', 0, 'Unable to load tags for contact ID ' . $contact_id . ': ' . $contact_tags->get_error_message() );
				}

				wp_fusion()->integrations->edd->maybe_auto_apply_discounts( $contact_tags );

				// Start tracking session
				do_action( 'wpf_guest_contact_updated', $contact_id, $cart['user']['user_email'] );

			}
		}

	}


	/**
	 * Get cart ID from EDD session
	 *
	 * @access public
	 * @return bool / int Cart ID
	 */

	public function get_cart_id() {

		if ( isset( $_COOKIE['edd_cart_token'] ) ) {

			$token = $_COOKIE['edd_cart_token'];

		} else {

			$token = edd_generate_cart_token();

			if ( ! headers_sent() ) {

				setcookie( 'edd_cart_token', $token, time() + 3600 * 24 * 7, COOKIEPATH, COOKIE_DOMAIN );

			}
		}

		return $token;

	}

	/**
	 * Applies product specific abandoned cart tags when user data is first captured
	 *
	 * @access public
	 * @return void
	 */

	public function checkout_begin( $contact_id = false, $apply_tags = array(), $user_data = array(), $source = 'edd' ) {

		// Don't run on initial checkout load for guests
		if ( ! wpf_is_user_logged_in() && $contact_id == false ) {
			return;
		}

		if ( empty( $user_data ) && wpf_is_user_logged_in() ) {

			$user                    = wp_get_current_user();
			$user_data['user_email'] = $user->user_email;
			$user_data['first_name'] = $user->first_name;
			$user_data['last_name']  = $user->last_name;

		}

		if ( false == $contact_id && wpf_is_user_logged_in() ) {
			$contact_id = wpf_get_contact_id( wpf_get_current_user_id() );
		}

		if ( false == $contact_id ) {
			return; // Can't do anything at this point without a contact record
		}

		// Are we creating a new cart or updating an existing one

		if ( wp_fusion_abandoned_cart()->carts->get_cart( $contact_id ) ) {
			$update = true;
		} else {
			$update = false;
		}

		$contents = edd_get_cart_content_details();

		//
		// Get some info about the cart
		//

		$items = array();

		$discounts = array();

		foreach ( $contents as $item ) {

			//
			// Put together the item data
			//

			$item_data = array(
				'product_id'  => $item['id'],
				'name'        => get_post_field( 'post_title', $item['id'], 'raw' ),
				'quantity'    => $item['quantity'],
				'product_url' => get_the_permalink( $item['id'] ),
				'price'       => $item['price'],
				'total'       => $item['subtotal'],
				'description' => get_the_excerpt( $item['id'] ),
			);

			$size = wp_fusion()->settings->get( 'abandoned_cart_image_size', 'medium' );

			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $item['id'] ), $size );

			if ( ! empty( $image ) ) {
				$item_data['image_url'] = $image[0];
			}

			if ( ! empty( $item['discount'] ) ) {

				$discounts[] = array(
					'name'   => EDD()->cart->get_discounts()[0],
					'amount' => $item['discount'],
				);

			}

			$items[] = $item_data;
		}

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
			$update_data['cart_value'] = edd_get_cart_total();
		}

		// Discounts

		if ( ! empty( $discounts ) ) {

			if ( wpf_is_field_active( 'cart_discount_code' ) ) {
				$update_data['cart_discount_code'] = $discounts[0]['name'];
			}

			if ( wpf_is_field_active( 'cart_discount_amount' ) ) {
				$update_data['cart_discount_amount'] = $discounts[0]['amount'];
			}
		}

		// Filter it

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

		//
		// Apply tags
		//

		foreach ( $contents as $item ) {

			$settings = get_post_meta( $item['id'], 'wpf-settings-edd', true );

			if ( ! empty( $settings ) && is_array( $settings ) && ! empty( $settings['apply_tags_abandoned'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_abandoned'] );
			}

			// Variable pricing tags

			if ( isset( $settings['apply_tags_abandoned_price'] ) && isset( $item['options'] ) && isset( $item['options']['price_id'] ) ) {

				if ( ! empty( $settings['apply_tags_abandoned_price'][ $item['options']['price_id'] ] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_abandoned_price'][ $item['options']['price_id'] ] );
				}
			}
		}

		// Apply any tags

		$apply_tags = apply_filters( 'wpf_abandoned_cart_apply_tags', $apply_tags, $contact_id );

		if ( ! empty( $apply_tags ) ) {

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
		// Pass the cart on to Deep Data / Shopper Activity
		//

		$args = array(
			'cart_id'      => $this->get_cart_id(),
			'recovery_url' => $recovery_url,
			'items'        => $items,
			'discounts'    => $discounts,
			'user_email'   => $user_data['user_email'],
			'provider'     => 'Easy Digital Downloads',
			'update'       => $update,
			'currency'     => edd_get_currency(),
		);

		do_action( 'wpf_abandoned_cart_created', $contact_id, $args );

	}

	/**
	 * Remove abandoned cart tags
	 *
	 * @access public
	 * @return void
	 */

	public function checkout_complete( $payment_id, $contact_id ) {

		// Get tags to be removed
		$remove_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags' );

		if ( empty( $remove_tags ) ) {
			$remove_tags = array();
		}

		$cart_items = edd_get_payment_meta_cart_details( $payment_id );

		foreach ( $cart_items as $item ) {

			$settings = get_post_meta( $item['id'], 'wpf-settings-edd', true );

			if ( ! empty( $settings ) && is_array( $settings ) && ! empty( $settings['apply_tags_abandoned'] ) ) {
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_abandoned'] );
			}

			// Variable pricing tags
			if ( isset( $settings['apply_tags_abandoned_price'] ) ) {

				$payment_details = get_post_meta( $payment_id, '_edd_payment_meta', true );

				foreach ( $payment_details['downloads'] as $download ) {

					$price_id = edd_get_cart_item_price_id( $download );

					if ( isset( $settings['apply_tags_abandoned_price'][ $price_id ] ) ) {
						$remove_tags = array_merge( $remove_tags, $settings['apply_tags_abandoned_price'][ $price_id ] );
					}
				}
			}
		}

		if ( ! empty( $remove_tags ) ) {

			$user_id = edd_get_payment_user_id( $payment_id );

			if ( $user_id == '-1' ) {

				// Guest checkout
				wp_fusion()->crm->remove_tags( $remove_tags, $contact_id );

			} else {

				// Logged in users
				wp_fusion()->user->remove_tags( $remove_tags, $user_id );

			}
		}

		// Clear out cart

		wp_fusion_abandoned_cart()->carts->delete_cart( $contact_id );

	}


}

new WPF_Abandoned_Cart_EDD();
