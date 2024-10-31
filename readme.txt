=== Pay with ATH Movil (WooCommerce payment gateway) ===
Contributors: robtorres
Tags: ecommerce, e-commerce, commerce, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, configurable, athmovil
Requires at least: 4.4
Tested up to: 5.8
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Accept ATH Movil payments on your WooCommerce store.

== Description ==

This is a ATH Movil Payment Gateway for WooCommerce.

ATH Movil allows you to securely sell your products online allowing the customer to pay you with their ATH Movil account.

**IMPORTANT**: ATH Movil open a pop window every time the user click the "Pay with ATH móvil" button. Make sure your customers don't use a popup blocker or they won't be able to make payments through this gateway.

== Installation ==

= Minimum Requirements =

* WordPress 4.4 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "Pay with ATH Movil (WooCommerce payment gateway)" and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favorite FTP application. The
WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

If on the off-chance you do encounter issues with the shop/category pages after an update you simply need to flush the permalinks by going to WordPress > Settings > Permalinks and hitting 'save'. That should return things to normal.

== Changelog ==

= 1.2.2 - 2022-09-30 =
* Fix       - Fixed an issue where the default "Place Order" button still show when the ATH Movil payment gateway is selected

= 1.2.1 - 2021-11-08 =
* Fix       - Fixed ATH Movil script references

= 1.2.0 - 2021-10-25 =
* Update    - Start using the ATH Movil API v4. Payments and responses should be more stable.
* New       - Added refund capability
* New       - Added new option to show a popup disclaimer on the checkout page

= 1.0.3 - 2020-05-01 =
* Update    - Added the spanish translation

= 1.0.2 - 2020-04-16 =
* Fix       - Error that was causing problem when opening ATH Movil pop up window on Safari.

= 1.1.0 - 2021-01-18 =
* Update    - Updated the ATH Movil API to v3
* Fix       - Fixed bug that didn't allow updating the shipping cost when the user selected a different shipping method
* Fix       - Fixed bug that was causing the screen "Sorry, this business is unavailable" to show up
* Fix       - Fixed bug that was showing a the error "athm_valid"
* Issue     - There is a known issue that the ATH Movil pop up window don't close when the payment is submitted or cancelled. This is a bug of the ATH Movil API. The workaround is telling the user that the pop up window need to be closed once the payment is submitted.

= 1.1.1 - 2020-01-20 =
* Update    - Integrate the ATH Movil gateway with the Checkout component of Awesome for WooCommerce plugin
