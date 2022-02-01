<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Abandoned_Cart_Memberpress extends WPF_Abandoned_Cart_Integrations_Base {

	/**
	 * Get things started
	 *
	 * @access public
	 * @return void
	 */

	public function init() {

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Abandoned cart tracking
		add_action( 'wpf_abandoned_cart_start', array( $this, 'checkout_begin' ), 10, 4 );
		add_action( 'mepr-txn-status-pending', array( $this, 'checkout_begin_loggedin' ) );

		// After checkout complete (MemberPress has a ton of hooks for this and it makes no sense)
		add_action( 'mepr-event-transaction-completed', array( $this, 'checkout_complete' ) );
		add_action( 'mepr-txn-status-complete', array( $this, 'checkout_complete' ) );
		add_action( 'mepr-signup', array( $this, 'checkout_complete' ) );
		add_action( 'mepr-event-subscription-created', array( $this, 'checkout_complete' ) );

		add_action( 'wpf_memberpress_meta_box', array( $this, 'meta_box' ), 10, 2 );

	}

	/**
	 * Enqueue scripts on checkout page
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_scripts() {

		if ( MeprUtils::is_product_page() && ! wpf_is_user_logged_in() ) {
			wp_enqueue_script( 'wpf-abandoned-cart', WPF_ABANDONED_CART_DIR_URL . 'assets/wpf-abandoned-cart.js', array( 'jquery' ), WPF_ABANDONED_CART_VERSION, true );
			wp_localize_script( 'wpf-abandoned-cart', 'wpf_ac_ajaxurl', admin_url( 'admin-ajax.php' ) );
		}

	}

	/**
	 * Maybe apply abandoned cart tags for registered users when a pending transaction is created
	 *
	 * @access public
	 * @return void
	 */

	public function checkout_begin_loggedin( $txn ) {

		$apply_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags' );

		if ( empty( $apply_tags ) ) {
			$apply_tags = array();
		}

		$this->checkout_begin( false, $apply_tags );

	}

	/**
	 * Apply abandoned cart tags
	 *
	 * @access public
	 * @return void
	 */

	public function checkout_begin( $contact_id = false, $apply_tags = array(), $user_data = array(), $source = 'memberpress' ) {

		// Don't run on initial checkout load for guests
		if ( ! wpf_is_user_logged_in() && false == $contact_id ) {
			return;
		}

		if ( empty( $user_data ) && wpf_is_user_logged_in() ) {

			$user                    = wp_get_current_user();
			$user_data['user_email'] = $user->user_email;
			$user_data['first_name'] = $user->first_name;
			$user_data['last_name']  = $user->last_name;

		}

		$membership_url = $_SERVER['HTTP_REFERER'];

		$membership_id = url_to_postid( $membership_url );

		// If we can't find the membership, quit
		if ( empty( $membership_id ) || 'memberpressproduct' !== get_post_type( $membership_id ) ) {
			return;
		}

		$settings = get_post_meta( $membership_id, 'wpf-settings-memberpress', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_abandoned_cart'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_abandoned_cart'] );
		}

		if ( ! empty( $apply_tags ) ) {

			// Apply the tags
			if ( wpf_is_user_logged_in() ) {

				wp_fusion()->user->apply_tags( $apply_tags );

			} elseif ( false !== $contact_id ) {

				wp_fusion()->logger->handle(
					'info', get_current_user_id(), 'Applying abandoned cart tags:', array(
						'tag_array' => $apply_tags,
						'source'    => 'wpf-abandoned-cart',
					)
				);

				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}
		}

		$items = array(
			array(
				'product_id'  => $membership_id,
				'name'        => get_post_field('post_title', $membership_id, 'raw' ),
				'quantity'    => 1,
				'price'       => get_post_meta( $membership_id, '_mepr_product_price', true ),
				'product_url' => $membership_url,
			),
		);

		$size = wp_fusion()->settings->get( 'abandoned_cart_image_size', 'medium' );

		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $membership_id ), $size );

		if ( ! empty( $image ) ) {
			$items[0]['image_url'] = $image[0];
		}

		$mepr_options = MeprOptions::fetch();

		$args = array(
			'cart_id'      => rand(),
			'recovery_url' => $membership_url,
			'items'        => $items,
			'user_email'   => $user_data['user_email'],
			'provider'     => 'MemberPress',
			'update'       => false,
			'currency'     => $mepr_options->currency_code,
		);

		do_action( 'wpf_abandoned_cart_created', $contact_id, $args );

	}

	/**
	 * Remove abandoned cart tags
	 *
	 * @access public
	 * @return void
	 */

	public function checkout_complete( $event ) {

		// The mepr-signup hook passes a transaction already
		if ( is_a( $event, 'MeprTransaction' ) ) {
			$txn = $event;
		} else {
			$txn = $event->get_data();
		}

		if ( 'active' == $txn->status || 'complete' == $txn->status ) {

			// Get tags to be removed
			$remove_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags' );

			if ( empty( $remove_tags ) ) {
				$remove_tags = array();
			}

			$settings = get_post_meta( $txn->product_id, 'wpf-settings-memberpress', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_abandoned_cart'] ) ) {
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_abandoned_cart'] );
			}

			if ( ! empty( $remove_tags ) ) {

				wp_fusion()->user->remove_tags( $remove_tags, $txn->user_id );

			}
		}

	}

	/**
	 * Add settings to MemberPress meta box
	 *
	 * @access public
	 * @return mixed HTML output
	 */

	public function meta_box( $settings, $product ) {

		$defaults = array(
			'apply_tags_abandoned_cart' => array()
		);

		$settings = array_merge( $defaults, $settings );

		echo '<br /><br /><label><strong>' . __( 'Apply Tags - Abandoned Cart', 'wp-fusion-abandoned-cart' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_abandoned_cart'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_abandoned_cart',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when someone begins to check out with this product. Tags will be removed after successful payment.', 'wp-fusion-abandoned-cart' ) . '</small></span>';


	}


}

new WPF_Abandoned_Cart_Memberpress();
