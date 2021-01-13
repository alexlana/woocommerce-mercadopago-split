<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_WooMercadoPago_Module
 */
class WC_WooMercadoPago_Module extends WC_WooMercadoPago_Configs
{
    public static $categories = array();
    public static $country_configs = array();
    public static $site_data;
    public static $instance = null;
    public static $mpInstancePayment = array();
    public static $mpInstance = null;
    public static $payments_name = null;
    public static $notices = array();

    /**
     * WC_WooMercadoPago_Module constructor.
     */
    public function __construct()
    {
        try {
            $this->loadHelpers();
            $this->loadConfigs();
            $this->loadLog();
            $this->loadHooks();
            $this->loadPreferences();
            $this->loadPayments();
            $this->loadNotifications();
            $this->loadStockManager();

            add_action('admin_enqueue_scripts', [$this, 'loadAdminCss']);

            add_filter('woocommerce_available_payment_gateways', array($this, 'filterPaymentMethodByShipping'));
            add_filter('plugin_action_links_' . WC_MERCADOPAGO_BASENAME, array($this, 'woomercadopago_settings_link'));
            add_filter('plugin_row_meta', array($this, 'mp_plugin_row_meta'), 10, 2);

            if (is_admin()) {
                //validate credentials
                if (isset($_REQUEST['section'])) {
                    $credentials = new WC_WooMercadoPago_Credentials();
                    if (!$credentials->tokenIsValid()) {
                        add_action('admin_notices', [$this, 'enablePaymentNotice']);
                    }
                }
            }
        } catch (Exception $e) {
            $log = WC_WooMercadoPago_Log::init_mercado_pago_log('WC_WooMercadoPago_Module');
            $log->write_log('__construct: ', $e->getMessage());
        }
    }

    /**
     * @param $payment
     * @return MP
     * @throws WC_WooMercadoPago_Exception
     */
    public static function getMpInstance($payment = null)
    {
        $credentials = new WC_WooMercadoPago_Credentials($payment);
        $validateCredentialsType = $credentials->validateCredentialsType();
        if ($validateCredentialsType == WC_WooMercadoPago_Credentials::TYPE_ACCESS_TOKEN) {
            $mp = new MP($credentials->accessToken);
            $mp->setPaymentClass($payment);
        }
        if ($validateCredentialsType == WC_WooMercadoPago_Credentials::TYPE_ACCESS_CLIENT) {
            $mp = new MP($credentials->clientId, $credentials->clientSecret);
            $mp->setPaymentClass($payment);
            if (!empty($payment)) {
                $payment->sandbox = false;
            }
        }

        if (!isset($mp)) {
            return false;
        }

        $email = (wp_get_current_user()->ID != 0) ? wp_get_current_user()->user_email : null;
        $mp->set_email($email);

        $locale = get_locale();
        $locale = (strpos($locale, '_') !== false && strlen($locale) == 5) ? explode('_', $locale) : array('', '');
        $mp->set_locale($locale[1]);

        return $mp;
    }

    /**
     * @param null $payment
     * @return MP|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function getMpInstanceSingleton($payment = null)
    {
        $mp = null;
        if (!empty($payment)) {
            $class = get_class($payment);
            if (!isset(self::$mpInstancePayment[$class])) {
                self::$mpInstancePayment[$class] = self::getMpInstance($payment);
                $mp = self::$mpInstancePayment[$class];
                if (!empty($mp)) {
                    return $mp;
                }
            }
        }

        if (self::$mpInstance === null || empty($mp)) {
            self::$mpInstance = self::getMpInstance();
        }

        return self::$mpInstance;
    }

    /**
     * @return WC_WooMercadoPago_Module|null
     * Singleton
     */
    public static function init_mercado_pago_class()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Load Config / Categories
     */
    public function loadConfigs()
    {
        self::$country_configs = self::getCountryConfigs();
        $configs = new parent();
        self::$categories = $configs->getCategories();
        self::$site_data = self::get_site_data();
        self::$payments_name = self::setPaymentGateway();
    }

    /**
     *  Load Hooks
     */
    public function loadHooks()
    {
        include_once dirname(__FILE__) . '/../payments/hooks/WC_WooMercadoPago_Hook_Abstract.php';
        include_once dirname(__FILE__) . '/../payments/hooks/WC_WooMercadoPago_Hook_Basic.php';
        include_once dirname(__FILE__) . '/../payments/hooks/WC_WooMercadoPago_Hook_Custom.php';
        include_once dirname(__FILE__) . '/../payments/hooks/WC_WooMercadoPago_Hook_Ticket.php';
    }

    /**
     * Load Helpers
     */
    public function loadHelpers()
    {
        include_once dirname(__FILE__) . '/../helpers/WC_WooMercadoPago_Helpers_CurrencyConverter.php';
    }

    /**
     * Load Preferences Classes
     */
    public function loadPreferences()
    {
        include_once dirname(__FILE__) . '/preference/WC_WooMercadoPago_PreferenceAbstract.php';
        include_once dirname(__FILE__) . '/preference/WC_WooMercadoPago_PreferenceBasic.php';
        include_once dirname(__FILE__) . '/preference/WC_WooMercadoPago_PreferenceCustom.php';
        include_once dirname(__FILE__) . '/preference/WC_WooMercadoPago_PreferenceTicket.php';
        include_once dirname(__FILE__) . '/preference/analytics/WC_WooMercadoPago_PreferenceAnalytics.php';
    }

    /**
     *  Load Payment Classes
     */
    public function loadPayments()
    {
        include_once dirname(__FILE__) . '/../payments/WC_WooMercadoPago_PaymentAbstract.php';
        include_once dirname(__FILE__) . '/../payments/WC_WooMercadoPago_BasicGateway.php';
        include_once dirname(__FILE__) . '/../payments/WC_WooMercadoPago_CustomGateway.php';
        include_once dirname(__FILE__) . '/../payments/WC_WooMercadoPago_TicketGateway.php';
        add_filter('woocommerce_payment_gateways', array($this, 'setPaymentGateway'));
    }

    /**
     *
     */
    public function loadNotifications()
    {
        include_once dirname(__FILE__) . '/../notification/WC_WooMercadoPago_Notification_Abstract.php';
        include_once dirname(__FILE__) . '/../notification/WC_WooMercadoPago_Notification_IPN.php';
        include_once dirname(__FILE__) . '/../notification/WC_WooMercadoPago_Notification_Webhook.php';
    }

    /**
     *
     */
    public function loadLog()
    {
        include_once dirname(__FILE__) . '/log/WC_WooMercadoPago_Log.php';
    }

    /**
     *
     */
    public function loadAdminCss()
    {
        if (is_admin()) {
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

            wp_enqueue_style(
                'woocommerce-mercadopago-basic-config-styles',
                plugins_url('../assets/css/config_mercadopago' . $suffix . '.css', plugin_dir_path(__FILE__))
            );
        }
    }

    /**
     * Stock Manager
     */
    public function loadStockManager()
    {
        include_once dirname(__FILE__) . '/../stock/WC_WooMercadoPago_Stock_Manager.php';
    }

    /**
     * @param $methods
     * @return array
     */
    public function filterPaymentMethodByShipping($methods)
    {
        return $methods;
    }

    /**
     * Enable Payment Notice
     */
    public function enablePaymentNotice()
    {
        $type = 'notice-warning';
        $message = __('Fill in your credentials to enable payment methods.', 'woocommerce-mercadopago');
        echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

    // Add settings link on plugin page.
    public function woomercadopago_settings_link($links)
    {
        $links_mp = $this->define_link_country();
        $plugin_links = array();
        $plugin_links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Set up', 'woocommerce-mercadopago') . '</a>';
        $plugin_links[] = '<a target="_blank" href="' . 'https://wordpress.org/support/plugin/woocommerce-mercadopago/reviews/?rate=5#new-post' . '">' . __('Your opinion helps us get better', 'woocommerce-mercadopago') . '</a>';
        $plugin_links[] = '<br><a target="_blank" href="' . 'https://www.mercadopago.' . $links_mp['sufix_url'] . 'developers/' . $links_mp['translate'] . '/guides/plugins/woocommerce/introduction/' . '">' . __('Guides and Documentation', 'woocommerce-mercadopago') . '</a>';
        $plugin_links[] = '<a target="_blank" href="' . 'https://www.mercadopago.' . $links_mp['sufix_url'] . $links_mp['help'] . '">' . __('Report Problem', 'woocommerce-mercadopago') . '</a>';
        return array_merge($plugin_links, $links);
    }

    /**
     * Construct link
     * @return array
     */
    public static function define_link_country()
    {
        $wc_country = get_option('woocommerce_default_country', '');
        $sufix_country = 'AR';
        $country = array(
            'AR' => array( // Argentinian
                'help' => 'ayuda',
                'sufix_url' => 'com.ar/',
                'translate' => 'es',
            ),
            'BR' => array( // Brazil
                'help' => 'ajuda',
                'sufix_url' => 'com.br/',
                'translate' => 'pt',
            ),
            'CL' => array( // Chile
                'help' => 'ayuda',
                'sufix_url' => 'cl/',
                'translate' => 'es',
            ),
            'CO' => array( // Colombia
                'help' => 'ayuda',
                'sufix_url' => 'com.co/',
                'translate' => 'es',
            ),
            'MX' => array( // Mexico
                'help' => 'ayuda',
                'sufix_url' => 'com.mx/',
                'translate' => 'es',
            ),
            'PE' => array( // Peru
                'help' => 'ayuda',
                'sufix_url' => 'com.pe/',
                'translate' => 'es',
            ),
            'UY' => array( // Uruguay
                'help' => 'ayuda',
                'sufix_url' => 'com.uy/',
                'translate' => 'es',
            ),
        );
        if ($wc_country != '') {

            $sufix_country = strlen($wc_country) > 2 ? substr($wc_country, 0, 2) : $wc_country;
        }

        $sufix_country = strtoupper($sufix_country);
        $links_country = array_key_exists($sufix_country, $country) ? $country[$sufix_country] : $country['AR'];

        return $links_country;
    }

    /**
     * Show row meta on the plugin screen.
     * @param mixed $links Plugin Row Meta.
     * @param mixed $file Plugin Base file.
     * @return array
     */
    public function mp_plugin_row_meta($links, $file)
    {
        if (WC_MERCADOPAGO_BASENAME === $file) {
            $new_link = array();
            $new_link[] = $links[0];
            $new_link[] = esc_html__('By Mercado Pago', 'woocommerce-mercadopago');

            return $new_link;
        }

        return (array)$links;
    }

    // ============================================================

    // Save and valid Sponsor_id if the site_id returned that api is equal site_id costumers
    public static function is_valid_sponsor_id($sponsor_id)
    {
        $access_token = get_option('_mp_access_token_prod', '');
        $site_id = get_option('_site_id_v1', '');

        $varify_sponsor = true;

        if (empty($access_token)) {
            $varify_sponsor = false;
        } elseif ($sponsor_id == '') {
            $varify_sponsor = false;
            update_option('_mp_sponsor_id', $sponsor_id, true);
        } elseif (!is_numeric($sponsor_id)) {
            $varify_sponsor = false;
            echo '<div class="error"><p>' . __('The <strong> Sponsor ID  </strong> must be valid!', 'woocommerce-mercadopago') . '</p></div>';
        } elseif ($sponsor_id != get_option('_mp_sponsor_id', '')) {
            $varify_sponsor = true;
        } elseif ($site_id != get_option('_mp_sponsor_site_id', '')) {
            $varify_sponsor = true;
        } else {
            $varify_sponsor = false;
        }

        if ($varify_sponsor) {
            $mp_sponsor_id = WC_WooMercadoPago_Module::getMpInstanceSingleton();
            $get_sponor_id = $mp_sponsor_id->get('/users/' . $sponsor_id, array('Authorization' => 'Bearer ' . $access_token), false);
            if (!is_wp_error($get_sponor_id) && ($get_sponor_id['status'] == 200 || $get_sponor_id['status'] == 201)) {
                if ($get_sponor_id['response']['site_id'] == $site_id) {
                    update_option('_mp_sponsor_id', $sponsor_id, true);
                    update_option('_mp_sponsor_site_id', $get_sponor_id['response']['site_id'], true);
                } else {
                    echo '<div class="error"><p>' . __('The <strong>Sponsor ID</strong> must be from the same country as the seller!', 'woocommerce-mercadopago') . '</p></div>';
                    update_option('_mp_sponsor_id', '', true);
                }
            } else {
                echo '<div class="error"><p>' . __('The <strong> Sponsor ID  </strong> must be valid!', 'woocommerce-mercadopago') . '</p></div>';
                update_option('_mp_sponsor_id', '', true);
            }
        }
    }

    /**
     * Summary: Check if we have valid credentials for v1.
     * Description: Check if we have valid credentials.
     * @return boolean true/false depending on the validation result.
     */

    // Get WooCommerce instance
    public static function woocommerce_instance()
    {
        if (function_exists('WC')) {
            return WC();
        } else {
            global $woocommerce;
            return $woocommerce;
        }
    }

    // Get common error messages
    public static function get_common_error_messages($key)
    {
        if ($key === 'Invalid payment_method_id') {
            return __('The payment method is not valid or not available.', 'woocommerce-mercadopago');
        }
        if ($key === 'Invalid transaction_amount') {
            return __('The transaction amount cannot be processed by Mercado Pago.', 'woocommerce-mercadopago') . ' ' .
                __('Possible causes: Currency not supported; Amounts below the minimum or above the maximum allowed.', 'woocommerce-mercadopago');
        }
        if ($key === 'Invalid users involved') {
            return __('The users are not valid.', 'woocommerce-mercadopago') . ' ' .
                __('Possible causes: Buyer and seller have the same account in Mercado Pago; The transaction involving production and test users.', 'woocommerce-mercadopago');
        }
        if ($key === 'Unauthorized use of live credentials') {
            return __('Unauthorized use of production credentials.', 'woocommerce-mercadopago') . ' ' .
                __('Possible causes: Use permission in use for the credential of the seller.', 'woocommerce-mercadopago');
        }
        return $key;
    }

    /**
     * Summary: Get the rate of conversion between two currencies.
     * Description: The currencies are the one used in WooCommerce and the one used in $site_id.
     * @return float float that is the rate of conversion.
     */
    public static function get_conversion_rate($used_currency)
    {
        $fromCurrency = get_woocommerce_currency();
        $toCurrency = $used_currency;

        return WC_WooMercadoPago_Helpers_CurrencyConverter::getInstance()->loadRatio($fromCurrency, $toCurrency);
    }

    /**
     * @return array
     */
    public static function get_common_settings()
    {
        $w = WC_WooMercadoPago_Module::woocommerce_instance();
        $infra_data = array(
            'module_version' => WC_WooMercadoPago_Constants::VERSION,
            'platform' => 'WooCommerce',
            'platform_version' => $w->version,
            'code_version' => phpversion(),
            'so_server' => PHP_OS
        );
        return $infra_data;
    }

    /**
     * Summary: Get Sponsor ID to preferences.
     * Description: This function verifies, if the sponsor ID was configured,
     * if NO, return Sponsor ID determined of get_site_data(),
     * if YES return Sponsor ID configured on plugin
     * @return a string.
     */
    public static function get_sponsor_id()
    {
        $site_data = WC_WooMercadoPago_Module::get_site_data();
        return $site_data['sponsor_id'];
    }

    /**
     * Summary: Get information about the used Mercado Pago account based in its site.
     * Description: Get information about the used Mercado Pago account based in its site.
     * @return an array with the information.
     */
    public static function get_site_data()
    {
        $site_id = get_option('_site_id_v1', '');
        if (isset($site_id) && !empty($site_id)) {
            return self::$country_configs[$site_id];
        } else {
            return null;
        }
    }

    // Fix to URL Problem : #038; replaces & and breaks the navigation.
    public static function fix_url_ampersand($link)
    {
        return str_replace('\/', '/', str_replace('&#038;', '&', $link));
    }

    /**
     * Summary: Find template's folder.
     * Description: Find template's folder.
     * @return a string that identifies the path.
     */
    public static function get_templates_path()
    {
        return plugin_dir_path(__FILE__) . '../../templates/';
    }

    /**
     * Summary: Get client id from access token.
     * Description: Get client id from access token.
     * @return the client id.
     */
    public static function get_client_id($at)
    {
        $t = explode('-', $at);
        if (count($t) > 0) {
            return $t[1];
        }
        return '';
    }

    // Check if an order is recurrent.
    public static function is_subscription($items)
    {
        $is_subscription = false;
        if (sizeof($items) == 1) {
            foreach ($items as $cart_item_key => $cart_item) {
                $is_recurrent = (method_exists($cart_item, 'get_meta')) ?
                    $cart_item->get_meta('_used_gateway') : get_post_meta($cart_item['product_id'], '_mp_recurring_is_recurrent', true);
                if ($is_recurrent == 'yes') {
                    $is_subscription = true;
                }
            }
        }
        return $is_subscription;
    }

    // Return boolean indicating if currency is supported.
    public static function is_supported_currency($site_id)
    {
        return get_woocommerce_currency() == WC_WooMercadoPago_Module::$country_configs[$site_id]['currency'];
    }

    public static function build_currency_conversion_err_msg($currency)
    {
        return '<img width="14" height="14" src="' .
            plugins_url('assets/images/error.png', __FILE__) . '"> ' .
            __('ERROR: It was not possible to convert the unsupported currency', 'woocommerce-mercadopago') .
            ' ' . get_woocommerce_currency() . ' ' .
            __('a', 'woocommerce-mercadopago') . ' ' . $currency . '. ' .
            __('Currency conversions should be done outside of this module.', 'woocommerce-mercadopago');
    }

    public static function build_currency_not_converted_msg($currency, $country_name)
    {
        return '<img width="14" height="14" src="' .
            plugins_url('assets/images/warning.png', __FILE__) . '"> ' .
            __('ATTENTION: The currency', 'woocommerce-mercadopago') .
            ' ' . get_woocommerce_currency() . ' ' .
            __('defined in WooCommerce is different from that used by the credentials of your country.<br>The currency for transactions made with this payment method will be', 'woocommerce-mercadopago') .
            ' ' . $currency . ' (' . $country_name . '). ' .
            __('Currency conversions should be done outside of this module.', 'woocommerce-mercadopago');
    }

    public static function build_currency_converted_msg($currency)
    {
        return '<img width="14" height="14" src="' .
            plugins_url('assets/images/check.png', __FILE__) . '"> ' .
            __('CONVERTED CURRENCY: Your store is converting currency of', 'woocommerce-mercadopago') .
            ' ' . get_woocommerce_currency() . ' ' .
            __('for', 'woocommerce-mercadopago') . ' ' . $currency;
    }

    public static function get_country_name($site_id)
    {
        switch ($site_id) {
            case 'MCO':
                return __('Colombia', 'woocommerce-mercadopago');
            case 'MLA':
                return __('Argentina', 'woocommerce-mercadopago');
            case 'MLB':
                return __('Brazil', 'woocommerce-mercadopago');
            case 'MLC':
                return __('Chile', 'woocommerce-mercadopago');
            case 'MLM':
                return __('Mexico', 'woocommerce-mercadopago');
            case 'MLU':
                return __('Uruguay', 'woocommerce-mercadopago');
            case 'MLV':
                return __('Venezuela', 'woocommerce-mercadopago');
            case 'MPE':
                return __('Peru', 'woocommerce-mercadopago');
        }
        return '';
    }

    // Build the string representing the path to the log file.
    public static function build_log_path_string($gateway_id, $gateway_name)
    {
        return '<a href="' . esc_url(admin_url('admin.php?page=wc-status&tab=logs&log_file=' .
            esc_attr($gateway_id) . '-' . sanitize_file_name(wp_hash($gateway_id)) . '.log')) . '">' .
            $gateway_name . '</a>';
    }

    public static function get_map($selector_id)
    {
        $html = '';
        $arr = explode('_', $selector_id);
        $defaults = array(
            'pending' => 'pending',
            'approved' => 'processing',
            'inprocess' => 'on_hold',
            'inmediation' => 'on_hold',
            'rejected' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'chargedback' => 'refunded'
        );
        $selection = get_option('_mp_' . $selector_id, $defaults[$arr[2]]);

        foreach (wc_get_order_statuses() as $slug => $status) {
            $slug = str_replace(array('wc-', '-'), array('', '_'), $slug);
            $html .= sprintf(
                '<option value="%s"%s>%s %s</option>',
                $slug,
                selected($selection, $slug, false),
                __('Update the WooCommerce order to ', 'woocommerce-mercadopago'),
                $status
            );
        }

        return $html;
    }

    public static function generate_refund_cancel_subscription($domain, $success_msg, $fail_msg, $options, $str1, $str2, $str3, $str4)
    {
        $subscription_js = '<script type="text/javascript">
				( function() {
					var MPSubscription = {}
					MPSubscription.callSubscriptionCancel = function () {
						var url = "' . $domain . '";
						url += "&action_mp_payment_id=" + document.getElementById("payment_id").value;
						url += "&action_mp_payment_amount=" + document.getElementById("payment_amount").value;
						url += "&action_mp_payment_action=cancel";
						document.getElementById("sub_pay_cancel_btn").disabled = true;
						MPSubscription.AJAX({
							url: url,
							method : "GET",
							timeout : 5000,
							error: function() {
								document.getElementById("sub_pay_cancel_btn").disabled = false;
								alert("' . $fail_msg . '");
							},
							success : function ( status, data ) {
								document.getElementById("sub_pay_cancel_btn").disabled = false;
								var mp_status = data.status;
								var mp_message = data.message;
								if (data.status == 200) {
									alert("' . $success_msg . '");
								} else {
									alert(mp_message);
								}
							}
						});
					}
					MPSubscription.callSubscriptionRefund = function () {
						var url = "' . $domain . '";
						url += "&action_mp_payment_id=" + document.getElementById("payment_id").value;
						url += "&action_mp_payment_amount=" + document.getElementById("payment_amount").value;
						url += "&action_mp_payment_action=refund";
						document.getElementById("sub_pay_refund_btn").disabled = true;
						MPSubscription.AJAX({
							url: url,
							method : "GET",
							timeout : 5000,
							error: function() {
								document.getElementById("sub_pay_refund_btn").disabled = false;
								alert("' . $fail_msg . '");
							},
							success : function ( status, data ) {
								document.getElementById("sub_pay_refund_btn").disabled = false;
								var mp_status = data.status;
								var mp_message = data.message;
								if (data.status == 200) {
									alert("' . $success_msg . '");
								} else {
									alert(mp_message);
								}
							}
						});
					}
					MPSubscription.AJAX = function( options ) {
						var useXDomain = !!window.XDomainRequest;
						var req = useXDomain ? new XDomainRequest() : new XMLHttpRequest()
						var data;
						options.url += ( options.url.indexOf( "?" ) >= 0 ? "&" : "?" );
						options.requestedMethod = options.method;
						if ( useXDomain && options.method == "PUT" ) {
							options.method = "POST";
							options.url += "&_method=PUT";
						}
						req.open( options.method, options.url, true );
						req.timeout = options.timeout || 1000;
						if ( window.XDomainRequest ) {
							req.onload = function() {
								data = JSON.parse( req.responseText );
								if ( typeof options.success === "function" ) {
									options.success( options.requestedMethod === "POST" ? 201 : 200, data );
								}
							};
							req.onerror = req.ontimeout = function() {
								if ( typeof options.error === "function" ) {
									options.error( 400, {
										user_agent:window.navigator.userAgent, error : "bad_request", cause:[]
									});
								}
							};
							req.onprogress = function() {};
						} else {
							req.setRequestHeader( "Accept", "application/json" );
							if ( options.contentType ) {
								req.setRequestHeader( "Content-Type", options.contentType );
							} else {
								req.setRequestHeader( "Content-Type", "application/json" );
							}
							req.onreadystatechange = function() {
								if ( this.readyState === 4 ) {
									if ( this.status >= 200 && this.status < 400 ) {
										// Success!
										data = JSON.parse( this.responseText );
										if ( typeof options.success === "function" ) {
											options.success( this.status, data );
										}
									} else if ( this.status >= 400 ) {
										data = JSON.parse( this.responseText );
										if ( typeof options.error === "function" ) {
											options.error( this.status, data );
										}
									} else if ( typeof options.error === "function" ) {
										options.error( 503, {} );
									}
								}
							};
						}
						if ( options.method === "GET" || options.data == null || options.data == undefined ) {
							req.send();
						} else {
							req.send( JSON.stringify( options.data ) );
						}
					}
					this.MPSubscription = MPSubscription;
				} ).call();
			</script>';
        $subscription_meta_box = '<table>' .
            '<tr class="total">' .
            '<td><label for="payment_id" style="margin-right:1px;">' .
            $str1 .
            '</label></td>' .
            '<td><select id="payment_id" name="refund_payment_id" style="margin-left:1px;">' .
            $options .
            '</select></td>' .
            '</tr>' .
            '<tr class="total">' .
            '<td><label for="payment_amount" style="margin-right:1px;">' .
            $str2 .
            '</label></td>' .
            '<td><input type="number" class="text amount_input" id="payment_amount" value="0" name="payment_amount"' .
            ' placeholder="Decimal" min="0" step="0.01" value="0.00" style="width:112px; margin-left:1px;"' .
            ' ng-pattern="/^[0-9]+(\.[0-9]{1,2})?$/"/>' .
            '</td>' .
            '</tr>' .
            '<tr class="total">' .
            '<td><input onclick="MPSubscription.callSubscriptionRefund();" type="button"' .
            ' id="sub_pay_refund_btn" class="button button" style="margin-left:1px; margin-top:2px;"' .
            ' name="refund" value="' . $str3 .
            '" style="margin-right:1px;"></td>' .
            '<td><input onclick="MPSubscription.callSubscriptionCancel();" type="button"' .
            ' id="sub_pay_cancel_btn" class="button button" style="margin-right:1px; margin-top:2px;"' .
            ' name="cancel" value="' . $str4 .
            '" style="margin-left:1px;"></td>' .
            '</tr>' .
            '</table>';
        return $subscription_js . $subscription_meta_box;
    }

    /**
     * Check if product dimensions are well defined
     */
    public static function is_product_dimensions_valid($all_product_data)
    {
        if (empty($all_product_data)) {
            return true;
        }
        foreach ($all_product_data as $p) {
            $product = wc_get_product($p->ID);
            if (!$product->is_virtual()) {
                $w = $product->get_weight();
                $dimensions = $product->get_dimensions(false);
                if (empty($w) || !is_numeric($w)) {
                    return false;
                }
                if (!is_numeric($dimensions['height'])) {
                    return false;
                }
                if (!is_numeric($dimensions['width'])) {
                    return false;
                }
                if (!is_numeric($dimensions['length'])) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    public static function isWcNewVersion()
    {
        $wooCommerceVersion = WC()->version;
        if ($wooCommerceVersion <= "4.0.0") {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public static function is_mobile()
    {
        $mobile = false;
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($user_agent, 0, 4))) {
            $mobile = true;
        }
        return $mobile;
    }
}
