<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Abandoned_Cart_LifterLMS extends WPF_Abandoned_Cart_Integrations_Base {

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
		add_action( 'lifterlms_pre_checkout_form', array( $this, 'pre_checkout_form' ) );

		// Remove the tags after checkout complete
		add_action( 'lifterlms_access_plan_purchased', array( $this, 'checkout_complete' ), 10, 2 );

		// Meta Box
		add_action( 'llms_access_plan_mb_after_row_five', array( $this, 'access_plan_settings' ), 10, 3 );
		add_action( 'llms_access_plan_saved', array( $this, 'save_plan' ), 10, 3 );

	}

	/**
	 * Enqueue scripts on checkout page
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_scripts() {

		if ( is_llms_checkout() && ! wpf_is_user_logged_in() ) {
			wp_enqueue_script( 'wpf-abandoned-cart', WPF_ABANDONED_CART_DIR_URL . 'assets/wpf-abandoned-cart.js', array( 'jquery' ), WPF_ABANDONED_CART_VERSION, true );
			wp_localize_script( 'wpf-abandoned-cart', 'wpf_ac_ajaxurl', admin_url( 'admin-ajax.php' ) );
		}

	}

	/**
	 * Start action for logged-in users
	 *
	 * @access public
	 * @return void
	 */

	public function pre_checkout_form() {

		if ( ! wpf_is_user_logged_in() ) {
			return;
		}

		$contact_id = wp_fusion()->user->get_contact_id();

		$apply_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags', array() );

		$user                    = wp_get_current_user();
		$user_data['user_email'] = $user->user_email;
		$user_data['first_name'] = $user->first_name;
		$user_data['last_name']  = $user->last_name;

		$this->checkout_begin( $contact_id, $apply_tags, $user_data );

	}

	/**
	 * Apply abandoned cart tags
	 *
	 * @access public
	 * @return void
	 */

	public function checkout_begin( $contact_id = false, $apply_tags = array(), $user_data = array(), $source = 'lifterlms' ) {

		// Don't run on other abandoned cart hooks
		if ( 'lifterlms' !== $source ) {
			return;
		}

		if ( isset( $_GET['plan'] ) ) {

			$plan_id = $_GET['plan'];

		} elseif ( isset( $_SERVER['HTTP_REFERER'] ) ) {

			// Get the plan ID in an AJAX request (not pretty, I know)
			$checkout_url = $_SERVER['HTTP_REFERER'];
			$parts        = parse_url( $checkout_url );
			parse_str( $parts['query'], $query );

			if ( isset( $query['plan'] ) ) {
				$plan_id = $query['plan'];
			}
		}

		// If we can't find the access plan, quit
		if ( empty( $plan_id ) || 'llms_access_plan' !== get_post_type( $plan_id ) ) {
			return;
		}

		$settings = get_post_meta( $plan_id, '_wpf_abandoned_cart', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_abandoned'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_abandoned'] );
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

	}

	/**
	 * Remove abandoned cart tags
	 *
	 * @access public
	 * @return void
	 */

	public function checkout_complete( $user_id, $plan_id ) {

		// Get tags to be removed
		$remove_tags = wp_fusion()->settings->get( 'abandoned_cart_apply_tags' );

		if ( empty( $remove_tags ) ) {
			$remove_tags = array();
		}

		$settings = get_post_meta( $plan_id, '_wpf_abandoned_cart', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_abandoned'] ) ) {
			$remove_tags = array_merge( $remove_tags, $settings['apply_tags_abandoned'] );
		}

		if ( ! empty( $remove_tags ) ) {
			wp_fusion()->user->remove_tags( $remove_tags, $user_id );
		}

	}


	/*
	 * Adds WPF settings to LLMS access plan meta box
	 *
	 * @access  public
	 * @return  mixed Access Plan Settings
	 */

	public function access_plan_settings( $plan, $id, $order ) {

		if ( empty( $plan ) ) {
			return;
		}

		?>
		<div class="llms-metabox-field d-1of3">

			<label><?php _e( 'Apply Tags - Abandoned Cart', 'wp-fusion-abandoned-cart' ); ?></label>
			<?php

			$settings = get_post_meta( $plan->id, '_wpf_abandoned_cart', true );

			if ( empty( $settings ) ) {
				$settings = array( 'apply_tags_abandoned' => array() );
			}

			$args = array(
				'setting'      => $settings['apply_tags_abandoned'],
				'meta_name'    => "_llms_plans[{$order}][apply_tags_abandoned]",
			);

			wpf_render_tag_multiselect( $args );

			?>
		</div>

		<?php

	}

	/**
	 * Save access plan
	 *
	 * @access  public
	 * @return  void
	 */

	public function save_plan( $plan, $raw_plan_data, $metabox ) {

		if ( ! empty( $raw_plan_data['apply_tags_abandoned'] ) ) {

			update_post_meta( $raw_plan_data['id'], '_wpf_abandoned_cart', array( 'apply_tags_abandoned' => $raw_plan_data['apply_tags_abandoned'] ) );

		} else {

			delete_post_meta( $raw_plan_data['id'], '_wpf_abandoned_cart' );

		}

	}


}

new WPF_Abandoned_Cart_LifterLMS();
