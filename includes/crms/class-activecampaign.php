<?php

class WPF_Abandoned_Cart_ActiveCampaign {

	/**
	 * Lets the rest of WP Fusion know which features are supported by the CRM
	 */

	public $supports;

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function init() {

		$this->supports = array( 'add_cart' );

		add_action( 'wpf_abandoned_cart_created', array( $this, 'cart_created' ), 10, 2 );

	}

	/**
	 * Adds the cart to ActiveCampaign
	 *
	 * @access public
	 * @return void
	 */

	public function cart_created( $contact_id, $args ) {

		if ( wp_fusion()->settings->get( 'abandoned_cart_sync_carts' ) != true ) {
			return;
		}

		$connection_id = wp_fusion()->crm->get_connection_id();
		$customer_id   = wp_fusion()->crm->get_customer_id( $contact_id, $connection_id );

		$product_objects = array();

		$calc_totals = 0;

		foreach ( $args['items'] as $item ) {

			$product_data = array(
				'externalid' => $item['product_id'],
				'name'       => $item['name'],
				'price'      => floatval( $item['price'] ) * 100,
				'quantity'   => $item['quantity'],
				'productUrl' => $item['product_url'],
			);

			if ( ! empty( $item['image_url'] ) ) {
				$product_data['imageUrl'] = $item['image_url'];
			}

			if ( ! empty( $item['description'] ) ) {
				$product_data['description'] = $item['description'];
			}

			$product_objects[] = (object) $product_data;

			$calc_totals += $item['price'] * $item['quantity'];

		}

		// Convert date
		$order_date  = current_time( 'timestamp' );
		$offset      = get_option( 'gmt_offset' );
		$order_date -= $offset * 60 * 60;

		$order_date = new DateTime( date( 'c', $order_date ) );

		// DateTimeZone throws an error with 0 as the timezone
		if ( $offset >= 0 ) {
			$offset = '+' . $offset;
		}

		$order_date->setTimezone( new DateTimeZone( $offset ) );

		$body = array(
			'ecomOrder' => array(
				'externalcheckoutid'  => $args['cart_id'],
				'source'              => 1,
				'email'               => $args['user_email'],
				'orderProducts'       => $product_objects,
				'orderUrl'            => $args['recovery_url'],
				'abandonedDate'       => $order_date->format( 'c' ),
				'externalCreatedDate' => $order_date->format( 'c' ),
				'totalPrice'          => $calc_totals * 100,
				'currency'            => $args['currency'],
				'connectionid'        => $connection_id,
				'customerid'          => $customer_id,
			),
		);

		// Add in discounts if applicable

		foreach ( $args['discounts'] as $discount ) {

			if ( ! isset( $body['ecomOrder']['orderDiscounts'] ) ) {
				$body['ecomOrder']['orderDiscounts'] = array();
				$body['ecomOrder']['discountAmount'] = 0;
			}

			$body['ecomOrder']['orderDiscounts'][] = array(
				'name'           => $discount['name'],
				'type'           => 'order',
				'discountAmount' => $discount['amount'] * 100,
			);

			$body['ecomOrder']['discountAmount'] += ( $discount['amount'] * 100 );

			if ( 'WooCommerce' == $args['provider'] ) {
				// For now the EDD prices will include the discount (for Impress) and the Woo prices will not
				$body['ecomOrder']['totalPrice'] -= ( $discount['amount'] * 100 );
			}
		}

		$params         = wp_fusion()->crm->get_params();
		$params['body'] = json_encode( $body );

		$api_url = wp_fusion()->settings->get( 'ac_url' );
		$api_key = wp_fusion()->settings->get( 'ac_key' );

		if ( false === $args['update'] ) {

			// Create a new cart

			wp_fusion()->logger->handle(
				'info', wpf_get_current_user_id(), 'Syncing cart to ActiveCampaign:', array(
					'meta_array_nofilter' => $body,
					'source'              => 'wpf-abandoned-cart',
				)
			);

			$request  = $api_url . '/api/3/ecomOrders';
			$response = wp_remote_post( $request, $params );

			if ( is_wp_error( $response ) ) {

				wp_fusion()->logger->handle( $response->get_error_code(), wpf_get_current_user_id(), 'Error adding cart: ' . $response->get_error_message(), array( 'source' => 'wpf-abandoned-cart' ) );
				return $response;

			}

			$response = json_decode( wp_remote_retrieve_body( $response ) );

			$order_id = $response->ecomOrder->id;

			// Update the saved cart
			$cart = array(
				'order_id'    => $order_id,
				'customer_id' => $customer_id,
			);

			wp_fusion_abandoned_cart()->carts->create_update_cart( $cart, $contact_id );

		} else {

			// Update an existing cart

			$cart = wp_fusion_abandoned_cart()->carts->get_cart( $contact_id );

			if ( empty( $cart ) || empty( $cart['order_id'] ) ) {

				wp_fusion()->logger->handle( 'notice', wpf_get_current_user_id(), 'Unable to update cart for contact ID ' . $contact_id . ': couldn\'t retrieve ActiveCampaign order ID.', array( 'source' => 'wpf-abandoned-cart' ) );

				// Try again
				$args['update'] = false;

				return $this->cart_created( $contact_id, $args );

			}

			wp_fusion()->logger->handle(
				'info', wpf_get_current_user_id(), 'Updating cart #' . $cart['order_id'] . ' in ActiveCampaign:', array(
					'meta_array_nofilter' => $body,
					'source'              => 'wpf-abandoned-cart',
				)
			);

			$params['method'] = 'PUT';

			$request  = $api_url . '/api/3/ecomOrders/' . $cart['order_id'];
			$response = wp_remote_request( $request, $params );

			if ( is_wp_error( $response ) ) {

				wp_fusion()->logger->handle( $response->get_error_code(), wpf_get_current_user_id(), 'Error updating cart: ' . $response->get_error_message(), array( 'source' => 'wpf-abandoned-cart' ) );
				return $response;

			}
		}
	}

}
