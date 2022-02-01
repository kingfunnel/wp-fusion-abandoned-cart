<?php

class WPF_Abandoned_Cart_Drip {

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

		$this->supports = array( 'add_cart', 'update_cart' );

		add_action( 'wpf_abandoned_cart_created', array( $this, 'cart_created' ), 10, 2 );

	}

	/**
	 * Adds the cart to Drip
	 *
	 * @access public
	 * @return void
	 */

	public function cart_created( $contact_id, $args ) {

		if ( wp_fusion()->settings->get( 'abandoned_cart_sync_carts' ) != true ) {
			return;
		}

		if ( empty( $contact_id ) ) {
			return;
		}

		$order_date  = current_time( 'timestamp' );

		// Convert date
		$offset      = get_option( 'gmt_offset' );
		$order_date -= $offset * 60 * 60;

		$order_date = new DateTime( date( 'c', $order_date ) );

		// DateTimeZone throws an error with 0 as the timezone
		if ( $offset >= 0 ) {
			$offset = '+' . $offset;
		}

		$order_date->setTimezone( new DateTimeZone( $offset ) );

		// Formatting stuff

		foreach( $args['items'] as $i => $item ) {
			$args['items'][ $i ]['product_id'] = (string) $item['product_id'];
			$args['items'][ $i ]['total']      = round( $item['total'], 2 );
			$args['items'][ $i ]['price']      = round( $item['price'], 2 );
			$args['items'][ $i ]['quantity']   = intval( $item['quantity'] );
		}

		$data = (object) array(
			'provider'    => $args['provider'],
			'person_id'   => $contact_id,
			'occurred_at' => $order_date->format( 'c' ),
			'grand_total' => 0,
			'cart_url'    => $args['recovery_url'],
			'items'       => array(),
			'cart_id'     => $contact_id,
		);

		if ( false === $args['update'] ) {
			$data->action = 'created';
		} else {
			$data->action = 'updated';
		}

		foreach ( $args['items'] as $item ) {

			if ( isset( $item['product_variant_id'] ) ) {
				$item['product_variant_id'] = (string) $item['product_variant_id'];
			}

			$data->items[] = (object) $item;

			if ( $item['total'] > 0 ) {
				$data->grand_total += $item['total'];
			}
		}

		$api_token  = wp_fusion()->settings->get( 'drip_token' );
		$account_id = wp_fusion()->settings->get( 'drip_account' );

		$request = 'https://api.getdrip.com/v3/' . $account_id . '/shopper_activity/cart';

		$params = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $api_token ),
				'Content-Type'  => 'application/json',
			),
			'body'    => json_encode( $data ),
		);

		wp_fusion()->logger->handle(
			'info', get_current_user_id(), 'Syncing ' . $args['provider'] . ' cart to Drip:', array(
				'meta_array_nofilter' => $data,
				'source'              => 'wpf-abandoned-cart',
			)
		);

		$response = wp_remote_post( $request, $params );

		if ( is_wp_error( $response ) ) {

			wp_fusion()->logger->handle( $response->get_error_code(), get_current_user_id(), 'Error adding cart: ' . $response->get_error_message(), array( 'source' => 'wpf-abandoned-cart' ) );
			return $response;

		} else {

			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( isset( $body->error ) ) {

				wp_fusion()->logger->handle( 'error', get_current_user_id(), 'Error adding cart: ' . $body->error->message, array( 'source' => 'wpf-abandoned-cart' ) );

				return new WP_Error( 'error', $body->error->message );
			}
		}
	}
}
