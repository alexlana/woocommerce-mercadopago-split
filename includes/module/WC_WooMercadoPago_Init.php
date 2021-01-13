<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_WooMercadoPago_Init
{
    /**
     * Load plugin text domain.
     *
     * Need to require here before test for PHP version.
     *
     * @since 3.0.1
     */
    public static function woocommerce_mercadopago_load_plugin_textdomain()
    {
        $text_domain = 'woocommerce-mercadopago';
        $locale = apply_filters('plugin_locale', get_locale(), $text_domain);

        $original_language_file = dirname(__FILE__) . '/../../i18n/languages/woocommerce-mercadopago-' . $locale . '.mo';

        // Unload the translation for the text domain of the plugin
        unload_textdomain($text_domain);
        // Load first the override file
        load_textdomain($text_domain, $original_language_file);
    }

    /**
     * Notice about unsupported PHP version.
     *
     * @since 3.0.1
     */
    public static function wc_mercado_pago_unsupported_php_version_notice()
    {
        $type = 'error';
        $message = esc_html__('Mercado Pago payments for WooCommerce requires PHP version 5.6 or later. Please update your PHP version.', 'woocommerce-mercadopago');
        echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

    /**
     * Curl validation
     */
    public static function wc_mercado_pago_notify_curl_error()
    {
        $type = 'error';
        $message = __('Mercado Pago Error: PHP Extension CURL is not installed.', 'woocommerce-mercadopago');
        echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

    /**
     * Summary: Places a warning error to notify user that WooCommerce is missing.
     * Description: Places a warning error to notify user that WooCommerce is missing.
     */
    public static function notify_woocommerce_miss()
    {
        $type = 'error';
        $message = sprintf(
            __('The Mercado Pago module needs an active version of %s in order to work!', 'woocommerce-mercadopago'),
            ' <a href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
        );
        echo WC_WooMercadoPago_Notices::getAlertWocommerceMiss($message, $type);
    }

    public static function add_mp_order_meta_box_actions($actions)
    {
        $actions['cancel_order'] = __('Cancel order', 'woocommerce-mercadopago');
        return $actions;
    }

    /**
     *
     */
    public static function mp_show_admin_notices()
    {
        if (!WC_WooMercadoPago_Module::isWcNewVersion() || (isset($_GET['page']) && $_GET['page'] == "wc-settings") && is_plugin_active('woocommerce-admin/woocommerce-admin.php')) {
            return;
        }

        $noticesArray = WC_WooMercadoPago_Module::$notices;
        $notices = array_unique($noticesArray, SORT_STRING);
        foreach ($notices as $notice) {
            echo $notice;
        }
    }

    /**
     * Activation plugin hook
     */
    public static function mercadopago_plugin_activation()
    {
        $dismissedReview = (int) get_option('_mp_dismiss_review');
        if (!isset($dismissedReview) || $dismissedReview == 1) {
            update_option('_mp_dismiss_review', 0, true);
        }
    }

    /**
     * Init the plugin
     */
    public static function woocommerce_mercadopago_init()
    {
        self::woocommerce_mercadopago_load_plugin_textdomain();
        require_once dirname(__FILE__) . '../../admin/notices/WC_WooMercadoPago_Notices.php';
        WC_WooMercadoPago_Notices::initMercadopagoNotice();

        // Check for PHP version and throw notice.
        if (version_compare(PHP_VERSION, '5.6', '<=')) {
            add_action('admin_notices', array(__CLASS__, 'wc_mercado_pago_unsupported_php_version_notice'));
            return;
        }

        if (!in_array('curl', get_loaded_extensions())) {
            add_action('admin_notices', array(__CLASS__, 'wc_mercado_pago_notify_curl_error'));
            return;
        }

        // Load Mercado Pago SDK
        require_once dirname(__FILE__) . '/sdk/lib/MP.php';

        // Checks with WooCommerce is installed.
        if (class_exists('WC_Payment_Gateway')) {
            require_once dirname(__FILE__) . '/config/WC_WooMercadoPago_Constants.php';
            require_once dirname(__FILE__) . '/WC_WooMercadoPago_Exception.php';
            require_once dirname(__FILE__) . '/WC_WooMercadoPago_Configs.php';
            require_once dirname(__FILE__) . '/log/WC_WooMercadoPago_Log.php';
            require_once dirname(__FILE__) . '/WC_WooMercadoPago_Module.php';
            require_once dirname(__FILE__) . '/WC_WooMercadoPago_Credentials.php';
            require_once dirname(__FILE__) . '../../admin/notices/WC_WooMercadoPago_ReviewNotice.php';

            WC_WooMercadoPago_Module::init_mercado_pago_class();
            WC_WooMercadoPago_ReviewNotice::initMercadopagoReviewNotice();

            add_action('woocommerce_order_actions', array(__CLASS__, 'add_mp_order_meta_box_actions'));
        } else {
            add_action('admin_notices', array(__CLASS__, 'notify_woocommerce_miss'));
        }
        add_action('woocommerce_settings_checkout', array(__CLASS__, 'mp_show_admin_notices'));
    }
}
