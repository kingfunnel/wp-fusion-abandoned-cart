<?php

class WPF_Abandoned_Cart_Integrations_Base {

	public function __construct() {

		$this->init();

		add_action( 'wp_ajax_nopriv_wpf_abandoned_cart', array( $this, 'save_checkout_data' ) );
		add_action( 'wp_ajax_nopriv_wpf_progressive_update_cart', array( $this, 'progressive_update' ) );

	}

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		// intentionally left blank
	}

	/**
	 * Saves data entered at checkout to CRM
	 *
	 * @access public
	 * @return void
	 */

	public function save_checkout_data() {

		if ( ! isset( $_POST['user_email'] ) ) {

			// Woo formatted data

			$post_data = array();
			parse_str( $_POST['data'], $post_data );

			$post_data['first_name'] = $post_data['billing_first_name'];
			$post_data['last_name'] = $post_data['billing_last_name'];
			$post_data['user_email'] = $post_data['billing_email'];

		} else {

			$post_data = $_POST;

			// Misc form data

			if ( isset( $_POST['data'] ) ) {

				$misc_data = array();
				parse_str( $_POST['data'], $misc_data );

				unset( $post_data['data'] );

				$post_data = array_merge( $post_data, $misc_data );

			}
		}

		$apply_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags' );

		if ( empty( $apply_tags ) ) {
			$apply_tags = array();
		}

		// Check to see if it's an existing registered user
		$user = get_user_by( 'email', $post_data['user_email'] );

		$contact_id = false;

		if ( is_object( $user ) ) {

			$contact_id = wp_fusion()->user->get_contact_id( $user->ID, true );

		}

		if ( empty( $contact_id ) ) {

			// See if contact exists already
			$contact_id = wp_fusion()->crm->get_contact_id( $post_data['user_email'] );

			if ( ! is_wp_error( $contact_id ) && empty( $contact_id ) ) {

				wp_fusion()->logger->handle(
					'info', get_current_user_id(), 'Abandoned cart adding contact:', array(
						'meta_array' => $post_data,
						'source'     => 'wpf-abandoned-cart',
					)
				);

				$contact_id = wp_fusion()->crm->add_contact( $post_data );
			}

			if ( is_wp_error( $contact_id ) ) {

				wp_fusion()->logger->handle( 'error', get_current_user_id(), 'Error adding contact: ' . $contact_id->get_error_message(), array( 'source' => 'wpf-abandoned-cart' ) );
				wp_send_json_error( $contact_id );
				die();

			}

			do_action( 'wpf_guest_contact_created', $contact_id, $post_data['user_email'] );

		}

		do_action( 'wpf_abandoned_cart_start', $contact_id, $apply_tags, $post_data, $_POST['source'] );

		wp_send_json_success( $contact_id );

		die();

	}

	/**
	 * Progressively updates contact as additional fields are filled
	 *
	 * @access public
	 * @return void
	 */

	public function progressive_update() {

		$post_data = array();
		parse_str( $_POST['data'], $post_data );

		$post_data['first_name'] = $post_data['billing_first_name'];
		$post_data['last_name']  = $post_data['billing_last_name'];
		$post_data['user_email'] = $post_data['billing_email'];

		wp_fusion()->crm->update_contact( $_POST['contact_id'], $post_data );

		die();

	}


}
