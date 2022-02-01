=== WP Fusion - Abandoned Cart Addon ===
Contributors: verygoodplugins
Tags: wp fusion, abandoned cart, woocommerce, easy digital downloads
Requires at least: 4.6
Tested up to: 5.8.2
Stable tag: 1.7.1

Tracks abandoned carts and adds customer data to your CRM before checkout is complete.

== Description ==

Tracks abandoned carts and adds customer data to your CRM before checkout is complete.

== Changelog ==

= 1.7.1 - 1/6/2022 =
* Improved - Will now try to remove malformed utm_varables that may have gotten appended to the cart recovery link by the ESP, and are preventing the contact ID from being read
* Fixed cached carts being publicly queryable on the frontend
* Fixed fatal error resulting from unhandled WP_Error when the initial contact creation failed
* Tested for WooCommerce 6.0.0

= 1.7.0 - 7/2/2021 =
* Moved cart storage out of transients and into a custom post type
* Added support for auto-applied discounts with EDD when using cart recovery URLs
* Improved - Abandoned cart tracking at checkout will also identify the visitor to site tracking scripts with supported CRMs
* Improved - Cart recovery links with WooCommerce and EDD will identify the visitor to site tracking scripts with supported CRMs
* Fixed Sendinblue contacts with + symbols in their email address not working with cart recovery URLs
* Fixed errors recovering carts when both WooCommerce and Easy Digital Downloads are active at the same time

= 1.6.8 - 5/28/2021 =
* Fixed cart recovery not working with WooCommerce 5.2.x
* Fixed errors when WP Fusion wasn't connected to a CRM

= 1.6.7 - 3/10/2021 =
* Added support for syncing cart discounts to ActiveCampaign (Deep Data) from Easy Digital Downloads and WooCommerce
* Added Cart Discount Code and Cart Discount Amount fields for sync
* Added wpf_abandoned_cart_apply_tags filter
* Added wpf_abandoned_cart_data filter
* Improved - Cart fields have been moved to the Contact Fields settings tab
* EDD cart recovery will now restore cart discounts, fees, and the selected payment gateway
* EDD cart recovery will now pre-fill customer information

= 1.6.6 - 2/22/2021 =
* Improved - customer_id created by an ActiveCampaign cart will now be passed to the Enhanced Ecommerce addon at checkout
* Fixed error adding products to cart with ActiveCampaign and Deep Data categories being generated from product attributes
* Fixed Easy Digital Downloads cart tracking not working when only one payment gateway was active
* Fixed fatal error activating the Abandoned Cart addon with WP Fusion Lite

= 1.6.5 - 1/8/2021 =
* Added download image URL to cart data with Easy Digital Downloads
* Added product description to cart data with Easy Digital Downloads and WooCommerce

= 1.6.4 - 9/28/2020 =
* Improved pre-filling the checkout fields when using the recovery link
* Fixed compatibility bug with WP Multilang
* Fixed some bugs when syncing a cart during an active auto-login session
* Updated for tags select compatibility with WP Fusion 3.35

= 1.6.3 - 8/11/2020 =
* Fixed abandoned cart actions not running when EDD payment gateway was changed
* Added .pot file

= 1.6.2 - 5/11/2020 =
* Fixed fatal error with EDD getting cart recovery URL

= 1.6.1 - 4/28/2020 =
* Fixed cart recovery URL not working with CRMs without cart support

= 1.6 - 4/22/2020 =
* Added LifterLMS abandoned cart tracking
* Added Sync Carts support for Easy Digital Downloads
* ActiveCampaign integration will now update an existing cart if one exists instead of creating a new one
* Fixed MemberPress trying to sync carts during WooCommerce checkout
* Fixed MemberPress assigning an empty tag if no abandoned cart tag was specified

= 1.5.4 - 2/24/2020 =
* Added support for currencies other than USD with ActiveCampaign Deep Data
* Fixed cart recovery links not restoring variation IDs

= 1.5.3 - 2/17/2020 =
* Added per-product abandoned cart tagging to MemberPress
* Added option to sync selected product attributes to categories in Drip
* WooCommerce integration will now inherit store settings regarding product prices being inclusive vs exclusive of tax
* Fixed MemberPress abandoned cart tags not being applied to logged in users

= 1.5.2 - 1/23/2020 =
* Added Deep Data / Shopper Activity support for MemberPress abandoned cart tracking
* Fixed not detecting email field properly on some MemberPress checkouts

= 1.5.1 - 11/20/2019 =
* Fixed time zone calculation in ActiveCampaign cart data

= 1.5 - 11/11/2019 =
* Added MemberPress integration
* Added support for WooCommerce product variations in cart data with Drip and ActiveCampaign
* Added option to send prices tax-inclusive with WooCommerce
* Fixed time zone calculation for occurred_at with Drip
* Fixed tags not applying for guest checkouts with EDD

= 1.4.1 - 9/19/2019 =
* Added option to select cart image size for Drip and ActiveCampaign
* Added progressive updates for checkout form data

= 1.4 - 6/12/2019 =
* Added Deep Data Abandoned Cart support for ActiveCampaign
* Added support for auto-applied coupons during cart recovery
* Recovered abandoned carts will now pre-fill the name and email address at checkout
* Fixed Drip Shopper Activity cart recovery URL not syncing if URL was also being sent to a custom field

= 1.3.1 - 6/4/2019 =
* Fixed product variations breaking Shopper Activity abandoned carts with Drip

= 1.3 - 4/22/2019 =
* Added Shopper Activity API support for Drip
* Added option to change the cart recovery URL destination

= 1.2 - 4/16/2019 =
* Added option for syncing total cart value to a custom field

= 1.1 - 3/18/2019 =
* Fixes for tags sometimes not applying when "On Add to Cart" setting was enabled
* Abandoned cart async actions will only run once per checkout form

= 1.0 - 1/25/2019 =
* Fallback for when product ID isn't present on variation cart items
* Fix for sending cart recovery URL for logged in users

= 0.9 - 9/23/2018 =
* Bugfixes

= 0.8 - 9/22/2018 =
* Updated WooCommerce settings storage

= 0.7 - 8/16/2018 =
* Added cart recovery URL for WooCommerce

= 0.6 - 12/22/2017 =
* Added abandoned cart tags for Woo / EDD product variations

= 0.5 =
* Fixed issues where sometimes duplicate contacts would be created

= 0.4 =
* Compatibility updates for WPF v3.3
* WooCommerce 3.0 fixes

= 0.3 =
* Fixed checkout errors

= 0.2 =
* Added support for per-product tagging
* Added option to apply tags on Add To Cart for logged in users

= 0.1 =
* Initial release

= 1449 =