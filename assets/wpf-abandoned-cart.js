jQuery(document).ready(function($){

	// Only do it once

	var didSend = false;

	// For verifying email addresses

	var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;

	// WooCommerce

	$( '.woocommerce input#billing_email' ).blur(function() {

		// Validate email
		var email = $('input#billing_email').val();

  		if(regex.test(email) == true && didSend == false) {

  			formData = $('form.checkout').serialize();

			var data = {
				'action' : 'wpf_abandoned_cart',
				'data'   : formData,
				'source' : 'woocommerce',
			}

			$.post(wpf_ac_ajaxurl, data, function( response ) {

				$('input#billing_last_name, input#billing_phone').blur(function( event ) {

					formData = $('form.checkout').serialize();

					var data = {
						'action'     : 'wpf_progressive_update_cart',
						'data'       : formData,
						'contact_id' : response.data,
						'source'     : 'woocommerce',
					}

					$.post(wpf_ac_ajaxurl, data);

				});

			} );

			didSend = true;

		}

	});

	// LifterLMS

	$( '.llms-checkout input' ).blur(function() {

		// Validate email
		var email = $('input#email_address').val();

  		if( didSend == false && regex.test( email ) == true && $('input#first_name').val() && $('input#last_name').val() ) {

  			didSend = true;

			var data = {
				'action'     : 'wpf_abandoned_cart',
				'first_name' : $('input#first_name').val(),
				'last_name'  : $('input#last_name').val(),
				'user_email' : $('input#email_address').val(),
				'source'     : 'lifterlms',
			}

			$.post(wpf_ac_ajaxurl, data);

		}

	});

	// Easy Digital Downloads

	function EDDAbandonedCart()  {

		$( '#edd_checkout_user_info input' ).blur(function() {

			// Validate email
			var email = $('input#edd-email').val();
			var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;

	  		if( didSend == false && regex.test( email ) == true && $('input#edd-first').val() && $('input#edd-last').val() ) {

	  			didSend = true;

				var data = {
					'action'     : 'wpf_abandoned_cart',
					'first_name' : $('input#edd-first').val(),
					'last_name'  : $('input#edd-last').val(),
					'user_email' : $('input#edd-email').val(),
					'source'     : 'edd',
				}

				$.post(wpf_ac_ajaxurl, data);

			}

		});

	}

	EDDAbandonedCart();

	$('body').on('edd_gateway_loaded', function( event, payment_mode ) {

		// Cases with multiple gateways
		EDDAbandonedCart();

	});

	// MemberPress

	$( '.mepr-signup-form input[name="user_email"]' ).blur(function() {

		// Validate email
		var email = $('input[name="user_email"]').val();

  		if( regex.test(email) == true && didSend == false ) {

			var data = {
				'action'     : 'wpf_abandoned_cart',
				'first_name' : $('input[name="user_first_name"]').val(),
				'last_name'  : $('input[name="user_last_name"').val(),
				'user_email' : $('input[name="user_email"]').val(),
				'data'       : $('form.mepr-signup-form').serialize(),
				'source'     : 'lifterlms',
			}

			$.post(wpf_ac_ajaxurl, data);

			didSend = true;

		}

	});

});