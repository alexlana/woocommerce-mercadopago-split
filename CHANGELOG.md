# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.6.0] - 2020-12-01

### Added
  - Add review rating banner
  - Improve security on checkouts, xss javascript sanitizer
  - Support section block added in checkout settings

### Changed
  - Fixed error that prevents configuring the Mercado Pago plugin

## [4.5.0] - 2020-10-26

### Added
  - Compatibility with WooCommerce v4.6.x
  - Improved security (added access token in the header for all calls to Mercado Livre and Mercado Pago endpoints)
  - Add new endpoint to validate Access Token and Public key to substitute old process to validation
  - Improved performance with CSS minification

### Changed
  - Fixed conflict with wc-api webhook and Mercado Pago webhook/IPN.
  - Fixed alert in currency conversion
  - Fixed tranlate in currency conversion
  - Bug fixed when updating orders that have two or more payments associated.

## [4.4.0] - 2020-09-21

### Added
  - Compatibility with WooCommerce v4.5.x

### Changed
  - Adjusted error when shipping is not used

## [4.3.1] - 2020-09-10

### Changed
  - Adjusted inventory (for canceled orders) on payments made at the personalized offline checkout

## [4.3.0] - 2020-08-31

### Added
  - Improve plugin initialization
  - Compatibility with Wordpress v5.5 and WooCommerce v4.4.x

### Changed
  - Fixed currency conversion API - Alert added at checkout when currency conversion fails
  - Adjusted inventory (for canceled orders) on payments made at the personalized offline checkout
  - Adjusted translation in general
  - Adjusted currency translation alert

## [4.2.2] - 2020-07-27

### Added
  - Added feature: cancelled orders on WooCommerce are automatically cancelled on Mercado Pago
  - Compatibility with Wordpress v5.4 and WooCommerce v4.3.x


### Changed
  - Fixed notification bug - No longer updates completed orders
  - Fixed currency conversion API - No longer allows payments without currency conversion
  - Fixed payment procesisng for virtual products
  - Added ABSPATH in every PHP file
  - Adjusted installments translation
  - Adjusted state names for Transparent Checkout in Brazil
  - Adjusted currency translation translations
  - Removed text in code written in Spanish

## [4.2.1] - 2020-05-18

### Changed
  - Corrected CI document input validation on Uruguay Custom Offline Checkout.

## [4.2.0] - 2020-05-13

### Added
  - Added compatibility with WooCommerce version 4.1.0
  - Added Integrator ID field on checkouts’ configuration screens
  - Added validation for Public Keys
  - Added alert to activate the WooCommerce plugin whenever it is inactive
  - Added alert to install the WooCommerce plugin whenever it is uninstalled
  - Added assets versioning
  - Added minification of JS files
  - Added debug mode for JS in order to use files without minification
  - Added payment flow for WebPay in Chile for Checkout Custom Offline
  - Updated documentation and regionalized links

### Changed
  - Corrected notification status on charged_back
  - Corrected issue when invalid credentials were switched
  - Corrected checkout options for Store Name, Store Category and Store ID
  - Corrected validation on the cardNumber field whenever card number is removed
  - Corrected input masks on CPNJ and CPF; CNPJ validation and translation in Brazil for Custom Checkout Offline;
  - Corrected mercadopago.js loading
  - Corrected processing of payment status notifications
  - Corrected personalized URLs for successful, refused and pending payments on Checkout Mercado Pago
  - Added success and error messages on received payment notifications
  - Added alphabetical order on offline payment methods for Checkout Custom
  - Added CI document input on Custom Checkout OFF in Uruguay
  - Added compatibility with third-party discount plugins which attribute value on order->fees (computation of fees_cost upon purchase)
  - Added validation, focus and error messages on all JS inputs on Checkout Custom Online and Offline
  - Usability improvements for Checkout Custom - Credit Card on mobile devices
  - Adjusted error messages on online Checkout Custom Online
  - Adjusted status updates on Checkout Custom Offline orders
  - Updated documentation and guide links

## [4.1.1] - 2020-01-10

### Added
- [PPP-155] Currency Conversion in Checkout Mercado Pago added

### Changed
- [PPP-154] Currency Conversion for CHO Custom ON and OFF fixed
- [PPP-156] Shipping Cost in the creation of Preferences fixed
- [PPP-156] ME2 shipping mode in the creation of Preferences removed
- [PPP-44] Checkout Mercado Pago class instance fixed when the first configurations are saved


## [4.1.0] - 2020-01-06

### Added
- [PLUG-473] CHANGELOG.md added in repository.
- [PLUG-456] Feature currency conversion returned.
- [PLUG-467] New feature to check if cURL is installed

### Changed
   - Updated plugin name from "WooCommerce Mercado Pago" to "Mercado Pago payments for WooCommerce".
 - [PLUG-459]
   - Fixed credential issue when the plugin is upgraded from version 3.x.x to 4xx. Unable to save empty credential.
   - Fixed issue to validate credential when checkout is active. The same problem occurs when removing the enabled checkout credential.
   - Fixed error: Undefined index: MLA in WC_WooMercadoPago_Credentials.php on line 163.
   - Fixed error: Call to a member function analytics_save_settings() in WC_WooMercadoPago_Hook_Abstract.php on line 68. Has affected users that cleared the credential and filled new credential production.
   - Fixed load of WC_WooMercadoPago_Module.php file.
   - Fixed error Uncaught Error: Call to a member function homologValidate().
   - Fixed error Undefined index: section in WC_WooMercadoPago_PaymentAbstract.php on line 303. Affected users who did not have homologous accounts
   - Fixed issue to validate credential when checkout is active. The same problem occurs when removing the enabled checkout credential.
   - Fixed issue to calculate commission and discount.
   - Fixed issue on methadata.
   - Fixed Layout of checkout custom input.
   - Fixed translation of Modo Producción, Habilitá and definí
- [PLUG-459-2] Refactored Javascript code for custom checkout Debit and credit card. Performance improvement, reduced number of SDK calls. Fixed validation errors. Javascript code refactored to the order review page. Removed select from mexico payment method.
- [PLUG-462]
  - Fixed Uncaught Error call to a member function update_status() in WC_WooMercadoPago_Notification_Abstract.php. Handle Mercado Pago Notification Failures and Exceptions.
  - Fixed Uncaught Error call to a member function update_status() in WC_WooMercadoPago_Notification_Abstract.php. Handle Mercado Pago Notification Failures and Exceptions.
- [PLUG-463]
  - Remove Mercado Creditos from Custom CHO OFF.
  - Fix PT-BR debit card translation on admin.
  - Fix PT-BR debit card translation on checkout.
  - Remove "One Step Checkout" from CHO Custom Off.
- [PLUG-466] Removed feature and support to Mercado Envios shipping. Before install the plugin verify if your store has another method of shipping configured.
- [PLUG-470] Fixed issue to check if WooCommerce plugin is installed
- [PLUG-455] Curl Validation.
- [PLUG-474] Removed mercadoenvios/WC_WooMercadoPago_Product_Recurrent.php file.
