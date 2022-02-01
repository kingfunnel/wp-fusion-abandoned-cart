<?php

class WPF_Abandoned_Cart_Settings {

	/**
	 * Get things started
	 *
	 * @since 1.0
	 * @return void
	 */

	public function __construct() {

		add_filter( 'wpf_configure_sections', array( $this, 'configure_sections' ), 10, 2 );
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

		if ( class_exists( 'WooCommerce' ) || class_exists( 'Easy_Digital_Downloads' ) ) {
			add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
			add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );
		}

		add_filter( 'option_wpf_options', array( $this, 'maybe_upgrade_options' ) );
		add_filter( 'wpf_initialize_options', array( $this, 'maybe_upgrade_options' ) );

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

		$settings['abandoned_cart_header'] = array(
			'title'   => __( 'Abandoned Cart Tracking', 'wp-fusion-abandoned-cart' ),
			'desc'    => __( '<a href="https://wpfusion.com/documentation/abandoned-cart-tracking/abandoned-cart-overview/" target="_blank">Read our documentation</a> for more information on abandoned cart tracking with WP Fusion.', 'wp-fusion-abandoned-cart' ),
			'type'    => 'heading',
			'section' => 'addons',
		);

		if ( class_exists( 'WooCommerce' ) || class_exists( 'Easy_Digital_Downloads' ) ) {
			$settings['abandoned_cart_header']['desc'] .= ' ' . sprintf( __( '%1$sClick here%2$s to view saved carts.', 'wp-fusion' ), '<a href="' . admin_url( 'edit.php?post_type=wpf_cart' ) . '">', '</a>' );
		}

		if ( isset( wp_fusion_abandoned_cart()->crm ) && in_array( 'add_cart', wp_fusion_abandoned_cart()->crm->supports ) ) {

			$settings['abandoned_cart_sync_carts'] = array(
				'title'   => __( 'Sync Carts', 'wp-fusion-abandoned-cart' ),
				'desc'    => sprintf( __( 'Sync cart contents over the %s Abandoned Cart API.', 'wp-fusion-abandoned-cart' ), wp_fusion()->crm->name ),
				'type'    => 'checkbox',
				'section' => 'addons',
				'unlock'  => array( 'abandoned_cart_image_size', 'abandoned_cart_categories' ),
			);

			// Warning if Sync Carts is enabled in addition to the custom fields

			if ( true == wp_fusion()->settings->get( 'abandoned_cart_sync_carts' ) ) {

				$tracked_fields = $this->prepare_meta_fields();

				foreach ( $tracked_fields as $key => $field ) {
					if ( wpf_is_field_active( $key ) ) {
						$settings['abandoned_cart_header']['desc'] .= '<br /><br /><div class="alert alert-danger">';
						$settings['abandoned_cart_header']['desc'] .= __( '<strong>Note:</strong> You are currently syncing the cart details to custom contact fields (configured on the Contact Fields tab). It\'s not necessary to enable <strong>Sync Carts</strong> at the same time. This will result in duplicate API calls and slower performance.', 'wp-fusion' );
						$settings['abandoned_cart_header']['desc'] .= '</div>';
						break;
					}
				}
			}

			if ( class_exists( 'WooCommerce' ) || class_exists( 'Easy_Digital_Downloads' ) ) {

				$choices = array();

				$sizes = get_intermediate_image_sizes();

				foreach ( $sizes as $size ) {
					$choices[ $size ] = $size;
				}

				asort( $choices );

				$settings['abandoned_cart_image_size'] = array(
					'title'   => __( 'Cart Items Image Size', 'wp-fusion-abandoned-cart' ),
					'desc'    => sprintf( __( 'Select an image size for product thumbnails sent to %s.', 'wp-fusion-abandoned-cart' ), wp_fusion()->crm->name ),
					'std'     => 'medium',
					'type'    => 'select',
					'choices' => $choices,
					'section' => 'addons',
				);

			}

			if ( class_exists( 'WooCommerce' ) ) {

				$settings['abandoned_cart_categories'] = array(
					'title'   => __( 'Product Categories', 'wp-fusion-abandoned-cart' ),
					'std'     => 'categories',
					'type'    => 'radio',
					'choices' => array(
						'categories' => __( 'Sync the categories from the product as categories', 'wp-fusion-abandoned-cart' ),
						'attributes' => __( 'Sync the selected attributes of the cart item as categories', 'wp-fusion-abandoned-cart' ),
					),
					'section' => 'addons',
				);

			}
		}

		if ( class_exists( 'WooCommerce' ) || class_exists( 'Easy_Digital_Downloads' ) ) {

			$settings['abandoned_cart_recovery_url_destination'] = array(
				'title'   => __( 'Recovery URL', 'wp-fusion-abandoned-cart' ),
				'std'     => 'checkout',
				'type'    => 'radio',
				'choices' => array(
					'checkout' => __( 'Checkout', 'wp-fusion-abandoned-cart' ),
					'cart'     => __( 'Cart', 'wp-fusion-abandoned-cart' ),
					'current'  => __( 'Current Page', 'wp-fusion-abandoned-cart' ),
				),
				'section' => 'addons',
				'tooltip' => __( 'Current Page works best with plugins like CartFlows, WooFunnels, or LaunchFlows where different products have different checkout pages.', 'wp-fusion' ),
			);

		}

		$settings['abandoned_cart_apply_tags'] = array(
			'title'   => __( 'Apply Tags', 'wp-fusion-abandoned-cart' ),
			'desc'    => __( 'Apply these tags when a user begins checkout. Read <a href="https://wpfusionplugin.com/documentation/#abandoned-cart-tracking" target="_blank">our documentation</a> for strategies for tracking abandoned carts.', 'wp-fusion-abandoned-cart' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'addons',
		);

		if ( class_exists( 'WooCommerce' ) || class_exists( 'Easy_Digital_Downloads' ) ) {

			$settings['abandoned_cart_add_to_cart'] = array(
				'title'   => __( 'Trigger on Add to Cart', 'wp-fusion-abandoned-cart' ),
				'desc'    => __( 'Trigger abandoned cart actions when a product is added to the cart for logged in users (instead of at checkout).', 'wp-fusion-abandoned-cart' ),
				'type'    => 'checkbox',
				'section' => 'addons',
			);

		}

		return $settings;

	}



	/**
	 * Register the meta field group on the Contact Fields list.
	 *
	 * @since  1.6.7
	 *
	 * @param  array $field_groups The field groups.
	 * @return array The field groups.
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['abandoned_cart'] = array(
			'title'  => 'WP Fusion Abandoned Cart',
			'url'    => 'https://wpfusion.com/documentation/abandoned-cart-tracking/abandoned-cart-overview/#syncing-cart-fields',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Add abandoned cart fields to the Contact Fields list.
	 *
	 * @since 1.6.7
	 *
	 * @param array $meta_fields The meta fields.
	 * @return array The meta fields.
	 */
	public function prepare_meta_fields( $meta_fields = array() ) {

		$meta_fields['cart_recovery_url'] = array(
			'label'  => 'Recovery URL',
			'group'  => 'abandoned_cart',
			'pseudo' => true,
		);

		$meta_fields['cart_value'] = array(
			'label'  => 'Cart Value',
			'type'   => 'int',
			'group'  => 'abandoned_cart',
			'pseudo' => true,
		);

		$meta_fields['cart_discount_code'] = array(
			'label'  => 'Cart Discount Code',
			'group'  => 'abandoned_cart',
			'pseudo' => true,
		);

		$meta_fields['cart_discount_amount'] = array(
			'label'  => 'Cart Discount Amount',
			'group'  => 'abandoned_cart',
			'pseudo' => true,
		);

		return $meta_fields;

	}

	/**
	 * Move the Cart Value and Recovery URL settings from the Addons tab to the
	 * contact fields list.
	 *
	 * @since  1.6.7
	 *
	 * @param  array $options The options.
	 * @return array The options.
	 */
	public function maybe_upgrade_options( $options ) {

		if ( ! empty( $options['abandoned_cart_value_field'] ) ) {

			$options['contact_fields']['cart_value'] = array(
				'crm_field' => $options['abandoned_cart_value_field']['crm_field'],
				'active'    => true,
			);

			unset( $options['abandoned_cart_value_field'] );

		}

		if ( ! empty( $options['abandoned_cart_recovery_url'] ) ) {

			$options['contact_fields']['cart_recovery_url'] = array(
				'crm_field' => $options['abandoned_cart_recovery_url']['crm_field'],
				'active'    => true,
			);

			unset( $options['abandoned_cart_recovery_url'] );

		}

		return $options;

	}

}
