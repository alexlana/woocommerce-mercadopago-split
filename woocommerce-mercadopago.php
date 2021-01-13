<?php

/**
 * Plugin Name: Mercado Pago payments for WooCommerce
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: Configure the payment options and accept payments with cards, ticket and money of Mercado Pago account.
 * Version: 4.6.0
 * Author: Mercado Pago
 * Author URI: https://developers.mercadopago.com/
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /i18n/languages/
 * WC requires at least: 3.0.0
 * WC tested up to: 4.7.0
 * @package MercadoPago
 * @category Core
 * @author Mercado Pago
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WC_MERCADOPAGO_BASENAME')) {
    define('WC_MERCADOPAGO_BASENAME', plugin_basename(__FILE__));
}

if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!class_exists('WC_WooMercadoPago_Init')) {
    include_once dirname(__FILE__) . '/includes/module/WC_WooMercadoPago_Init.php';

    register_activation_hook(__FILE__, array('WC_WooMercadoPago_Init', 'mercadopago_plugin_activation'));
    add_action('plugins_loaded', array('WC_WooMercadoPago_Init', 'woocommerce_mercadopago_init'));
}
