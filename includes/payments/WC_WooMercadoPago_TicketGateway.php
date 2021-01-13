<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_WooMercadoPago_TicketGateway
 */
class WC_WooMercadoPago_TicketGateway extends WC_WooMercadoPago_PaymentAbstract
{
    CONST ID = 'woo-mercado-pago-ticket';

    /**
     * WC_WooMercadoPago_TicketGateway constructor.
     * @throws WC_WooMercadoPago_Exception
     */
    public function __construct()
    {
        $this->id = self::ID;

        if (!$this->validateSection()) {
            return;
        }

        $this->description = __('Accept cash payments within the custom checkout and expand your customers purchase options.', 'woocommerce-mercadopago');
        $this->form_fields = array();
        $this->method_title = __('Mercado Pago - Custom Checkout', 'woocommerce-mercadopago');
        $this->title = __('Pay with cash', 'woocommerce-mercadopago');
        $this->method_description = $this->getMethodDescription($this->description);
        $this->coupon_mode = $this->getOption('coupon_mode', 'no');
        $this->stock_reduce_mode = $this->getOption('stock_reduce_mode', 'no');
        $this->date_expiration = $this->getOption('date_expiration', 3);
        $this->type_payments = $this->getOption('type_payments', 'no');
        $this->payment_type = "ticket";
        $this->checkout_type = "custom";
        $this->activated_payment = $this->get_activated_payment();
        $this->field_forms_order = $this->get_fields_sequence();
        parent::__construct();
        $this->form_fields = $this->getFormFields('Ticket');
        $this->hook = new WC_WooMercadoPago_Hook_Ticket($this);
        $this->notification = new WC_WooMercadoPago_Notification_Webhook($this);
        $this->currency_convertion = true;
    }

    /**
     * @param $label
     * @return array
     */
    public function getFormFields($label)
    {
        if (is_admin() && $this->isManageSection()) {
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            wp_enqueue_script(
                'woocommerce-mercadopago-ticket-config-script',
                plugins_url('../assets/js/ticket_config_mercadopago' . $suffix . '.js', plugin_dir_path(__FILE__)),
                array(),
                WC_WooMercadoPago_Constants::VERSION
            );
        }

        if (empty($this->checkout_country)) {
            $this->field_forms_order = array_slice($this->field_forms_order, 0, 7);
        }

        if (!empty($this->checkout_country) && empty($this->getAccessToken()) && empty($this->getPublicKey())) {
            $this->field_forms_order = array_slice($this->field_forms_order, 0, 22);
        }

        $form_fields = array();
        $form_fields['checkout_ticket_header'] = $this->field_checkout_ticket_header();
        if (!empty($this->checkout_country) && !empty($this->getAccessToken()) && !empty($this->getPublicKey())) {
            $form_fields['checkout_ticket_options_title'] = $this->field_checkout_ticket_options_title();
            $form_fields['checkout_ticket_payments_title'] = $this->field_checkout_ticket_payments_title();
            $form_fields['checkout_ticket_payments_description'] = $this->field_checkout_ticket_options_description();
            $form_fields['checkout_ticket_payments_advanced_title'] = $this->field_checkout_ticket_payments_advanced_title();
            $form_fields['coupon_mode'] = $this->field_coupon_mode();
            $form_fields['stock_reduce_mode'] = $this->field_stock_reduce_mode();
            $form_fields['date_expiration'] = $this->field_date_expiration();
            foreach ($this->field_ticket_payments() as $key => $value) {
                $form_fields[$key] = $value;
            }
        }

        $form_fields_abs = parent::getFormFields($label);
        if (count($form_fields_abs) == 1) {
            return $form_fields_abs;
        }
        $form_fields_merge = array_merge($form_fields_abs, $form_fields);
        $fields = $this->sortFormFields($form_fields_merge, $this->field_forms_order);

        return $fields;
    }

    /**
     * get_fields_sequence
     * @return array
     */
    public function get_fields_sequence()
    {
        return [
            // Necessary to run
            'title',
            'description',
            // Checkout de pagos con dinero en efectivo<br> Aceptá pagos al instante y maximizá la conversión de tu negocio
            'checkout_ticket_header',
            'checkout_steps',
            // ¿En qué país vas a activar tu tienda?
            'checkout_country_title',
            'checkout_country',
            'checkout_btn_save',
            // Carga tus credenciales
            'checkout_credential_title',
            'checkout_credential_mod_test_title',
            'checkout_credential_mod_test_description',
            'checkout_credential_mod_prod_title',
            'checkout_credential_mod_prod_description',
            'checkout_credential_prod',
            'checkout_credential_link',
            'checkout_credential_title_test',
            'checkout_credential_description_test',
            '_mp_public_key_test',
            '_mp_access_token_test',
            'checkout_credential_title_prod',
            'checkout_credential_description_prod',
            '_mp_public_key_prod',
            '_mp_access_token_prod',
            // No olvides de homologar tu cuenta
            'checkout_homolog_title',
            'checkout_homolog_subtitle',
            'checkout_homolog_link',
            // Set up the payment experience in your store
            'checkout_ticket_options_title',
            'mp_statement_descriptor',
            '_mp_category_id',
            '_mp_store_identificator',
            '_mp_integrator_id',
            // Advanced settings
            'checkout_advanced_settings',
            '_mp_debug_mode',
            '_mp_custom_domain',
            // Configure the personalized payment experience in your store
            'checkout_ticket_payments_title',
            'checkout_payments_subtitle',
            'checkout_ticket_payments_description',
            'enabled',
            WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY,
            'field_ticket_payments',
            'date_expiration',
            // Advanced configuration of the personalized payment experience"
            'checkout_ticket_payments_advanced_title',
            'checkout_payments_advanced_description',
            'coupon_mode',
            'stock_reduce_mode',
            'gateway_discount',
            'commission',
            // Support session
            'checkout_support_title',
            'checkout_support_description',
            'checkout_support_description_link',
            'checkout_support_problem',
            // Everything ready for the takeoff of your sales?
            'checkout_ready_title',
            'checkout_ready_description',
            'checkout_ready_description_link'
        ];
    }

    /**
     * @return array
     */
    public static function get_activated_payment()
    {
        $activated_payment = array();
        $get_payment_methods_ticket = get_option('_all_payment_methods_ticket', '');

        if (!empty($get_payment_methods_ticket)) {
            $saved_optons = get_option('woocommerce_woo-mercado-pago-ticket_settings', '');

            if (!is_array($get_payment_methods_ticket)) {
                $get_payment_methods_ticket = json_decode($get_payment_methods_ticket, true);
            }

            foreach ($get_payment_methods_ticket as $payment_methods_ticket) {
                if (!isset($saved_optons['ticket_payment_' . $payment_methods_ticket['id']]) || $saved_optons['ticket_payment_' . $payment_methods_ticket['id']] == 'yes') {
                    array_push($activated_payment, $payment_methods_ticket);
                }
            }
        }
        return $activated_payment;
    }

    /**
     * @return array
     */
    public function field_checkout_ticket_header()
    {
        $checkout_ticket_header = array(
            'title' => sprintf(
                __('Checkout of payments with cash %s', 'woocommerce-mercadopago'),
                '<div class="mp-row">
                <div class="mp-col-md-12 mp_subtitle_header">
                ' . __('Accept face-to-face payments, do not leave anyone out!', 'woocommerce-mercadopago') . '
                 </div>
              <div class="mp-col-md-12">
                <p class="mp-text-checkout-body mp-mb-0">
                  ' . __('Include this preferred purchase option by some customers.', 'woocommerce-mercadopago') . '
                </p>
              </div>
            </div>'
            ),
            'type' => 'title',
            'class' => 'mp_title_header'
        );
        return $checkout_ticket_header;
    }

    /**
     * @return array
     */
    public function field_checkout_ticket_options_title()
    {
        $checkout_options_title = array(
            'title' => __('Configure Mercado Pago for WooCommerce', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_title_bd'
        );
        return $checkout_options_title;
    }

    /**
     * @return array
     */
    public function field_checkout_ticket_options_description()
    {
        $checkout_options_description = array(
            'title' => __('Enable Mercado Pago for cash payments in your store and <br> select the options available to your customers.', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_small_text'
        );
        return $checkout_options_description;
    }

    /**
     * @return array
     */
    public function field_checkout_ticket_payments_title()
    {
        $checkout_payments_title = array(
            'title' => __('Set payment preferences with cash', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_title_bd'
        );
        return $checkout_payments_title;
    }

    /**
     * @return array
     */
    public function field_checkout_ticket_payments_advanced_title()
    {
        $checkout_ticket_payments_advanced_title = array(
            'title' => __('Advanced configuration of the cash payment experience', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle_bd'
        );
        return $checkout_ticket_payments_advanced_title;
    }

    /**
     * @return array
     */
    public function field_stock_reduce_mode()
    {
        return array(
            'title' => __('Reduce inventory', 'woocommerce-mercadopago'),
            'type' => 'select',
            'default' => 'no',
            'description' => __('Activates inventory reduction during the creation of an order, whether or not the final payment is credited. Disable this option to reduce it only when payments are approved.', 'woocommerce-mercadopago'),
            'options' => array(
                'no' => __('No', 'woocommerce-mercadopago'),
                'yes' => __('Yes', 'woocommerce-mercadopago')
            )
        );
    }

    /**
     * @return array
     */
    public function field_date_expiration()
    {
        return array(
            'title' => __('Payment Due', 'woocommerce-mercadopago'),
            'type' => 'number',
            'description' => __('In how many days will cash payments expire.', 'woocommerce-mercadopago'),
            'default' => ''
        );
    }

    /**
     * @return array
     */
    public function field_ticket_payments()
    {
        $ticket_payments = array();
        $ticket_payments_sort = array();

        $get_payment_methods_ticket = get_option('_all_payment_methods_ticket', '[]');

        $count_payment = 0;

        if (!is_array($get_payment_methods_ticket)) {
            $get_payment_methods_ticket = json_decode($get_payment_methods_ticket, true);
        }

        foreach ($get_payment_methods_ticket as $payment_method_ticket) {
            $element = array(
                'label' => $payment_method_ticket['name'],
                'id' => 'woocommerce_mercadopago_' . $payment_method_ticket['id'],
                'default' => 'yes',
                'type' => 'checkbox',
                'class' => 'ticket_payment_method_select',
                'custom_attributes' => array(
                    'data-translate' => __('All payment methods', 'woocommerce-mercadopago')
                ),
            );

            $count_payment++;

            if ($count_payment == 1) {
                $element['title'] = __('Payment methods', 'woocommerce-mercadopago');
                $element['desc_tip'] = __('Choose the available payment methods in your store.', 'woocommerce-mercadopago');
            }
            if ($count_payment == count($get_payment_methods_ticket)) {
                $element['description'] = __('Activate the available payment methods to your clients.', 'woocommerce-mercadopago');
            }

            $ticket_payments["ticket_payment_" . $payment_method_ticket['id']] = $element;
            $ticket_payments_sort[] = "ticket_payment_" . $payment_method_ticket['id'];
        }

        $index = 0;
        foreach ($this->field_forms_order as $k => $field) {
            $index++;
            if ($field == 'field_ticket_payments') {
                unset($this->field_forms_order[$k]);
                array_splice($this->field_forms_order, $index, 0, $ticket_payments_sort);
                break;
            }
        }

        return $ticket_payments;
    }

    /**
     *
     */
    public function payment_fields()
    {
        //add css
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_style(
            'woocommerce-mercadopago-basic-checkout-styles',
            plugins_url('../assets/css/basic_checkout_mercadopago' . $suffix . '.css', plugin_dir_path(__FILE__))
        );

        $amount = $this->get_order_total();
        $discount = $amount * ($this->gateway_discount / 100);
        $comission = $amount * ($this->commission / 100);
        $amount = $amount - $discount + $comission;

        $logged_user_email = (wp_get_current_user()->ID != 0) ? wp_get_current_user()->user_email : null;
        $address = get_user_meta(wp_get_current_user()->ID, 'billing_address_1', true);
        $address_2 = get_user_meta(wp_get_current_user()->ID, 'billing_address_2', true);
        $address .= (!empty($address_2) ? ' - ' . $address_2 : '');
        $country = get_user_meta(wp_get_current_user()->ID, 'billing_country', true);
        $address .= (!empty($country) ? ' - ' . $country : '');

        try {
            $currency_ratio = WC_WooMercadoPago_Helpers_CurrencyConverter::getInstance()->ratio($this);
        } catch (Exception $e) {
            $currency_ratio = WC_WooMercadoPago_Helpers_CurrencyConverter::DEFAULT_RATIO;
        }

        $parameters = array(
            'amount' => $amount,
            'payment_methods' => $this->activated_payment,
            'site_id' => $this->getOption('_site_id_v1'),
            'coupon_mode' => isset($logged_user_email) ? $this->coupon_mode : 'no',
            'discount_action_url' => $this->discount_action_url,
            'payer_email' => esc_js($logged_user_email),
            'currency_ratio' => $currency_ratio,
            'woocommerce_currency' => get_woocommerce_currency(),
            'account_currency' => $this->site_data['currency'],
            'febraban' => (wp_get_current_user()->ID != 0) ?
                array(
                    'firstname' => esc_js(wp_get_current_user()->user_firstname),
                    'lastname' => esc_js(wp_get_current_user()->user_lastname),
                    'docNumber' => '',
                    'address' => esc_js($address),
                    'number' => '',
                    'city' => esc_js(get_user_meta(wp_get_current_user()->ID, 'billing_city', true)),
                    'state' => esc_js(get_user_meta(wp_get_current_user()->ID, 'billing_state', true)),
                    'zipcode' => esc_js(get_user_meta(wp_get_current_user()->ID, 'billing_postcode', true))
                ) :
                array(
                    'firstname' => '',
                    'lastname' => '',
                    'docNumber' => '',
                    'address' => '',
                    'number' => '',
                    'city' => '',
                    'state' => '',
                    'zipcode' => '',
                ),
            'images_path' => plugins_url('../assets/images/', plugin_dir_path(__FILE__)),
        );

        wc_get_template('checkout/ticket_checkout.php', $parameters, 'woo/mercado/pago/module/', WC_WooMercadoPago_Module::get_templates_path());
    }

    /**
     * @param $order_id
     * @return array|void
     */
    public function process_payment($order_id)
    {
        $ticket_checkout = apply_filters('wc_mercadopagoticket_ticket_checkout', $_POST['mercadopago_ticket']);
        $this->log->write_log(__FUNCTION__, 'Ticket POST: ' . json_encode($ticket_checkout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $order = wc_get_order($order_id);
        $amount = $this->get_order_total();
        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data('_used_gateway', get_class($this));
            if (!empty($this->gateway_discount)) {
                $discount = $amount * ($this->gateway_discount / 100);
                $order->update_meta_data('Mercado Pago: discount', __('discount of', 'woocommerce-mercadopago') . ' ' . $this->gateway_discount . '% / ' . __('discount of', 'woocommerce-mercadopago') . ' = ' . $discount);
            }

            if (!empty($this->commission)) {
                $comission = $amount * ($this->commission / 100);
                $order->update_meta_data('Mercado Pago: comission', __('fee of', 'woocommerce-mercadopago') . ' ' . $this->commission . '% / ' . __('fee of', 'woocommerce-mercadopago') . ' = ' . $comission);
            }
            $order->save();
        } else {
            update_post_meta($order_id, '_used_gateway', get_class($this));

            if (!empty($this->gateway_discount)) {
                $discount = $amount * ($this->gateway_discount / 100);
                update_post_meta($order_id, 'Mercado Pago: discount', __('discount of', 'woocommerce-mercadopago') . ' ' . $this->gateway_discount . '% / ' . __('discount of', 'woocommerce-mercadopago') . ' = ' . $discount);
            }

            if (!empty($this->commission)) {
                $comission = $amount * ($this->commission / 100);
                update_post_meta($order_id, 'Mercado Pago: comission', __('fee of', 'woocommerce-mercadopago') . ' ' . $this->commission . '% / ' . __('fee of', 'woocommerce-mercadopago') . ' = ' . $comission);
            }
        }

        // Check for brazilian FEBRABAN rules.
        if ($this->getOption('_site_id_v1') == 'MLB') {
            if (!isset($ticket_checkout['firstname']) || empty($ticket_checkout['firstname']) ||
                !isset($ticket_checkout['lastname']) || empty($ticket_checkout['lastname']) ||
                !isset($ticket_checkout['docNumber']) || empty($ticket_checkout['docNumber']) ||
                (strlen($ticket_checkout['docNumber']) != 14 && strlen($ticket_checkout['docNumber']) != 18) ||
                !isset($ticket_checkout['address']) || empty($ticket_checkout['address']) ||
                !isset($ticket_checkout['number']) || empty($ticket_checkout['number']) ||
                !isset($ticket_checkout['city']) || empty($ticket_checkout['city']) ||
                !isset($ticket_checkout['state']) || empty($ticket_checkout['state']) ||
                !isset($ticket_checkout['zipcode']) || empty($ticket_checkout['zipcode'])) {
                wc_add_notice(
                    '<p>' .
                    __('There was a problem processing your payment. Are you sure you have correctly filled out all the information on the payment form?', 'woocommerce-mercadopago') .
                    '</p>',
                    'error'
                );
                return array(
                    'result' => 'fail',
                    'redirect' => '',
                );
            }
        }

        if ($this->getOption('_site_id_v1') == 'MLU') {
            if (
                !isset($ticket_checkout['docNumber']) || empty($ticket_checkout['docNumber']) ||
                !isset($ticket_checkout['docType']) || empty($ticket_checkout['docType'])
            ) {
                wc_add_notice(
                    '<p>' .
                    __('There was a problem processing your payment. Are you sure you have correctly filled out all the information on the payment form?', 'woocommerce-mercadopago') .
                    '</p>',
                    'error'
                );
                return array(
                    'result' => 'fail',
                    'redirect' => '',
                );
            }
        }

        if (isset($ticket_checkout['amount']) && !empty($ticket_checkout['amount']) &&
            isset($ticket_checkout['paymentMethodId']) && !empty($ticket_checkout['paymentMethodId'])) {
            $response = $this->create_preference($order, $ticket_checkout);

            if (is_array($response) && array_key_exists('status', $response)) {
                if ($response['status'] == 'pending') {
                    if ($response['status_detail'] == 'pending_waiting_payment' || $response['status_detail'] == 'pending_waiting_transfer') {
                        WC()->cart->empty_cart();
                        if ($this->stock_reduce_mode == 'yes') {
                            wc_reduce_stock_levels( $order_id );
                        }
                        // WooCommerce 3.0 or later.
                        if (method_exists($order, 'update_meta_data')) {
                            $order->update_meta_data('_transaction_details_ticket', $response['transaction_details']['external_resource_url']);
                            $order->save();
                        } else {
                            update_post_meta($order->get_id(), '_transaction_details_ticket', $response['transaction_details']['external_resource_url']);
                        }
                        // Shows some info in checkout page.
                        $order->add_order_note(
                            'Mercado Pago: ' .
                            __('The customer has not paid yet.', 'woocommerce-mercadopago')
                        );
                        if ($response['payment_type_id'] != 'bank_transfer') {
                            $order->add_order_note(
                                'Mercado Pago: ' .
                                __('To print the ticket again click', 'woocommerce-mercadopago') .
                                ' <a target="_blank" href="' .
                                $response['transaction_details']['external_resource_url'] . '">' .
                                __('here', 'woocommerce-mercadopago') .
                                '</a>', 1, false
                            );
                        }

                        return array(
                            'result' => 'success',
                            'redirect' => $order->get_checkout_order_received_url()
                        );
                    }
                }
            } else {
                // Process when fields are imcomplete.
                wc_add_notice(
                    '<p>' .
                    __('A problem occurred when processing your payment. Are you sure you have correctly filled in all the information on the checkout form?', 'woocommerce-mercadopago') . ' MERCADO PAGO: ' .
                    WC_WooMercadoPago_Module::get_common_error_messages($response) .
                    '</p>',
                    'error'
                );
                return array(
                    'result' => 'fail',
                    'redirect' => '',
                );
            }
        } else {
            // Process when fields are imcomplete.
            wc_add_notice(
                '<p>' .
                __('A problem occurred when processing your payment. Please try again.', 'woocommerce-mercadopago') .
                '</p>',
                'error'
            );
            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * @param $order
     * @param $ticket_checkout
     * @return string|array
     */
    public function create_preference($order, $ticket_checkout)
    {
        $preferencesTicket = new WC_WooMercadoPago_PreferenceTicket($this, $order, $ticket_checkout);
        $preferences = $preferencesTicket->get_preference();
        try {
            $checkout_info = $this->mp->post('/v1/payments', json_encode($preferences));
            $this->log->write_log(__FUNCTION__, 'Created Preference: ' . json_encode($checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if ($checkout_info['status'] < 200 || $checkout_info['status'] >= 300) {
                $this->log->write_log(__FUNCTION__, 'mercado pago gave error, payment creation failed with error: ' . $checkout_info['response']['message']);
                return $checkout_info['response']['message'];
            } elseif (is_wp_error($checkout_info)) {
                $this->log->write_log(__FUNCTION__, 'wordpress gave error, payment creation failed with error: ' . $checkout_info['response']['message']);
                return $checkout_info['response']['message'];
            } else {
                $this->log->write_log(__FUNCTION__, 'payment link generated with success from mercado pago, with structure as follow: ' . json_encode($checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return $checkout_info['response'];
            }
        } catch (WC_WooMercadoPago_Exception $ex) {
            $this->log->write_log(__FUNCTION__, 'payment creation failed with exception: ' . json_encode($ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $ex->getMessage();
        }
    }

    /**
     * @return bool
     */
    public function is_available()
    {
        if (!parent::is_available()) {
            return false;
        }

        $payment_methods = $this->activated_payment;
        if (count($payment_methods) == 0) {
            $this->log->write_log(__FUNCTION__, 'Ticket unavailable, no active payment methods. ');
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public static function getId()
    {
        return WC_WooMercadoPago_TicketGateway::ID;
    }
}
