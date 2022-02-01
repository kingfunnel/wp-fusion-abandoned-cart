<?php

/**
 * Installs WP Fusion Abandoned Cart Addon.
 *
 * @since 1.7.0
 */

function wp_fusion_abandoned_cart_install() {

	if ( class_exists( 'WooCommerce' ) || class_exists( 'Easy_Digital_Downloads' ) ) {

		if ( ! wp_next_scheduled( 'wp_fusion_abandoned_cart_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_fusion_abandoned_cart_cleanup' );
		}
	}
}

register_activation_hook( WPF_ABANDONED_CART_PLUGIN_FILE, 'wp_fusion_abandoned_cart_install' );


/**
 * Remove the scheduled action on deactivation.
 *
 * @since 1.7.0
 */
function wp_fusion_abandoned_cart_deactivate() {
	wp_clear_scheduled_hook( 'wp_fusion_abandoned_cart_cleanup' );
}

register_deactivation_hook( WPF_ABANDONED_CART_PLUGIN_FILE, 'wp_fusion_abandoned_cart_deactivate' );
