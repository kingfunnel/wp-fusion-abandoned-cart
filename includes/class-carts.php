<?php

/**
 * Creates the cart post type for use with EDD and WooCommerce and handles
 * saving / loading / deleting carts.
 *
 * @since 1.7.0
 */
class WPF_Abandoned_Cart_Carts {

	/**
	 * Get things started.
	 *
	 * @since 1.7.0
	 */

	public function __construct() {

		if ( ! class_exists( 'WooCommerce' ) && ! class_exists( 'Easy_Digital_Downloads' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'wp_fusion_abandoned_cart_cleanup', array( $this, 'cleanup' ) );

		if ( ! wp_next_scheduled( 'wp_fusion_abandoned_cart_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_fusion_abandoned_cart_cleanup' );
		}

	}

	/**
	 * Register the abandoned cart post type.
	 *
	 * @since 1.7.0
	 */
	public function register_post_type() {

		$args = array(
			'label'               => __( 'Carts', 'wp-fusion-abandoned-cart' ),
			'description'         => __( 'WP Fusion Abandoned Carts', 'wp-fusion-abandoned-cart' ),
			'supports'            => array( 'title', 'editor' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'page',
			'show_in_rest'        => false,
			'rewrite'             => false,
		);

		register_post_type( 'wpf_cart', $args );

	}

	/**
	 * Create or update a cart in the DB.
	 *
	 * @since  1.7.0
	 *
	 * @param  array  $cart_contents The cart contents.
	 * @param  string $contact_id    The contact ID.
	 * @return int|WP_Error The cart ID or an error.
	 */
	public function create_update_cart( $cart_contents, $contact_id ) {

		$contact_id = str_replace( '+', '-', $contact_id ); // Sendinblue uses email addresses as contact IDs
		$contact_id = sanitize_text_field( $contact_id );

		$cart = get_page_by_title( 'wp-fusion-abandoned-cart-' . $contact_id, ARRAY_A, 'wpf_cart' );

		// Merge with the existing data so we don't lose any order IDs

		if ( null !== $cart && ! is_wp_error( $cart ) ) {

			$content              = unserialize( $cart['post_content'] );
			$cart['post_content'] = array_merge( $content, $cart_contents );

		} else {

			$cart = array(
				'post_title'   => 'wp-fusion-abandoned-cart-' . $contact_id,
				'post_status'  => 'publish',
				'post_type'    => 'wpf_cart',
				'post_content' => $cart_contents,
			);

		}

		$cart['post_content'] = serialize( wp_unslash( $cart['post_content'] ) ); // make it saveable

		remove_all_filters( 'content_save_pre' ); // these filters will mess up saving the serialized object

		return wp_insert_post( $cart );

	}

	/**
	 * Get the cart for a contact ID.
	 *
	 * @since  1.7.0
	 *
	 * @param  string     $contact_id The contact ID.
	 * @return array|bool The cart, or false if not found.
	 */
	public function get_cart( $contact_id ) {

		$cart = get_page_by_title( 'wp-fusion-abandoned-cart-' . $contact_id, OBJECT, 'wpf_cart' );

		if ( is_wp_error( $cart ) ) {
			wpf_log( 'notice', 0, 'A cart recovery link was visited for contact ID ' . $contact_id . ' but an error was encountered loading the cart: ' . $cart->get_error_message() );
			return false;
		}

		if ( null !== $cart ) {
			return unserialize( $cart->post_content );
		} else {

			$cart = get_transient( "wpf_abandoned_cart_{$contact_id}" ); // Helps with the transition from pre 1.7 to 1.7+

			if ( ! empty( $cart ) ) {
				return $cart;
			} else {
				return false;
			}
		}

	}

	/**
	 * Delete a cart after checkout.
	 *
	 * @since  1.7.0
	 *
	 * @param  string $contact_id The contact ID.
	 * @return bool   Whether or not the cart was deleted.
	 */
	public function delete_cart( $contact_id ) {

		$cart = get_page_by_title( 'wp-fusion-abandoned-cart-' . $contact_id, OBJECT, 'wpf_cart' );

		if ( null !== $cart ) {
			return wp_delete_post( $cart->ID, true );
		} else {
			return true;
		}

	}

	/**
	 * Auto-delete the cached cart data after 30 days.
	 *
	 * @since 1.7.0
	 */
	public function cleanup() {

		$args = array(
			'fields'         => 'ids',
			'posts_per_page' => 200,
			'post_type'      => 'wpf_cart',
			'date_query'     => array(
				'before' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			),
		);

		$posts = get_posts( $args );

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

	}

}
