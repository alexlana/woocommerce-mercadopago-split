<?php

if (!defined('ABSPATH')) {
    exit;
}

$GLOBALS['LIB_LOCATION'] = dirname(__FILE__);

/**
 * Class MP
 */
class MP
{
    private $client_id;
    private $client_secret;
    private $ll_access_token;
    private $sandbox = FALSE;
    private $accessTokenByClient;
    private $paymentClass;

    /**
     * MP constructor.
     * @throws WC_WooMercadoPago_Exception
     */
    public function __construct()
    {
        $includes_path = dirname(__FILE__);
        require_once($includes_path . '/RestClient/AbstractRestClient.php');
        require_once($includes_path . '/RestClient/MeliRestClient.php');
        require_once($includes_path . '/RestClient/MpRestClient.php');

        $i = func_num_args();
        if ($i > 2 || $i < 1) {
            throw new WC_WooMercadoPago_Exception('Invalid arguments. Use CLIENT_ID and CLIENT SECRET, or ACCESS_TOKEN');
        }

        if ($i == 1) {
            $this->ll_access_token = func_get_arg(0);
        }

        if ($i == 2) {
            $this->client_id = func_get_arg(0);
            $this->client_secret = func_get_arg(1);
        }
    }

    /**
     * @param $email
     */
    public function set_email($email)
    {
        MPRestClient::set_email($email);
        MeliRestClient::set_email($email);
    }

    /**
     * @param $country_code
     */
    public function set_locale($country_code)
    {
        MPRestClient::set_locale($country_code);
        MeliRestClient::set_locale($country_code);
    }

    /**
     * @param null $enable
     * @return bool
     */
    public function sandbox_mode($enable = NULL)
    {
        if (!is_null($enable)) {
            $this->sandbox = $enable === TRUE;
        }
        return $this->sandbox;
    }

    /**
     * @return mixed|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function get_access_token()
    {

        if (isset($this->ll_access_token) && !is_null($this->ll_access_token)) {
            return $this->ll_access_token;
        }

        if (!empty($this->accessTokenByClient)) {
            return $this->accessTokenByClient;
        }

        $app_client_values = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials'
        );

        $access_data = MPRestClient::post(
            array(
                'uri' => '/oauth/token',
                'data' => $app_client_values,
                'headers' => array(
                    'content-type' => 'application/x-www-form-urlencoded'
                )
            ),
            WC_WooMercadoPago_Constants::VERSION
        );

        if ($access_data['status'] != 200) {
            return null;
        }

        $response = $access_data['response'];
        $this->accessTokenByClient = $response['access_token'];

        return $this->accessTokenByClient;
    }

    /**
     * @param $id
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function search_paymentV1($id)
    {

        $request = array(
            'uri' => '/v1/payments/' . $id,
            'params' => array('access_token' => $this->get_access_token())
        );

        $payment = MPRestClient::get($request, WC_WooMercadoPago_Constants::VERSION);
        return $payment;
    }

    //=== CUSTOMER CARDS FUNCTIONS ===

    /**
     * @param $payer_email
     * @return array|mixed|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function get_or_create_customer($payer_email)
    {

        $customer = $this->search_customer($payer_email);

        if ($customer['status'] == 200 && $customer['response']['paging']['total'] > 0) {
            $customer = $customer['response']['results'][0];
        } else {
            $resp = $this->create_customer($payer_email);
            $customer = $resp['response'];
        }

        return $customer;
    }

    /**
     * @param $email
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function create_customer($email)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/v1/customers',
            'data' => array(
                'email' => $email
            )
        );

        $customer = MPRestClient::post($request);
        return $customer;
    }

    /**
     * @param $email
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function search_customer($email)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/v1/customers/search',
            'params' => array(
                'email' => $email
            )
        );

        $customer = MPRestClient::get($request);
        return $customer;
    }

    /**
     * @param $customer_id
     * @param $token
     * @param null $payment_method_id
     * @param null $issuer_id
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function create_card_in_customer(
        $customer_id,
        $token,
        $payment_method_id = null,
        $issuer_id = null
    ) {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/v1/customers/' . $customer_id . '/cards',
            'data' => array(
                'token' => $token,
                'issuer_id' => $issuer_id,
                'payment_method_id' => $payment_method_id
            )
        );

        $card = MPRestClient::post($request);
        return $card;
    }

    /**
     * @param $customer_id
     * @param $token
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function get_all_customer_cards($customer_id, $token)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/v1/customers/' . $customer_id . '/cards',
        );

        $cards = MPRestClient::get($request);
        return $cards;
    }

    //=== COUPOM AND DISCOUNTS FUNCTIONS ===
    /**
     * @param $transaction_amount
     * @param $payer_email
     * @param $coupon_code
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function check_discount_campaigns($transaction_amount, $payer_email, $coupon_code)
    {
        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/discount_campaigns',
            'params' => array(
                'transaction_amount' => $transaction_amount,
                'payer_email' => $payer_email,
                'coupon_code' => $coupon_code
            )
        );
        $discount_info = MPRestClient::get($request);
        return $discount_info;
    }

    //=== CHECKOUT AUXILIARY FUNCTIONS ===

    /**
     * @param $id
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function get_authorized_payment($id)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/authorized_payments/{$id}',
        );

        $authorized_payment_info = MPRestClient::get($request);
        return $authorized_payment_info;
    }

    /**
     * @param $preference
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function create_preference($preference)
    {

        $request = array(
            'uri' => '/checkout/preferences',
            'headers' => array(
                'user-agent' => 'platform:desktop,type:woocommerce,so:' . WC_WooMercadoPago_Constants::VERSION,
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'data' => $preference
        );

        $preference_result = MPRestClient::post($request);
        return $preference_result;
    }

    /**
     * @param $id
     * @param $preference
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function update_preference($id, $preference)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/checkout/preferences/{$id}',
            'data' => $preference
        );

        $preference_result = MPRestClient::put($request);
        return $preference_result;
    }

    /**
     * @param $id
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function get_preference($id)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/checkout/preferences/{$id}',
        );

        $preference_result = MPRestClient::get($request);
        return $preference_result;
    }

    /**
     * @param $preference
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function create_payment($preference)
    {

        $request = array(
            'uri' => '/v1/payments',
            'headers' => array(
                'X-Tracking-Id' => 'platform:v1-whitelabel,type:woocommerce,so:' . WC_WooMercadoPago_Constants::VERSION,
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'data' => $preference
        );

        $payment = MPRestClient::post($request, WC_WooMercadoPago_Constants::VERSION);
        return $payment;
    }

    /**
     * @param $preapproval_payment
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function create_preapproval_payment($preapproval_payment)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/preapproval',
            'data' => $preapproval_payment
        );

        $preapproval_payment_result = MPRestClient::post($request);
        return $preapproval_payment_result;
    }

    /**
     * @param $id
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function get_preapproval_payment($id)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/preapproval/' . $id
        );

        $preapproval_payment_result = MPRestClient::get($request);
        return $preapproval_payment_result;
    }

    /**
     * @param $id
     * @param $preapproval_payment
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function update_preapproval_payment($id, $preapproval_payment)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/preapproval/' . $id,
            'data' => $preapproval_payment
        );

        $preapproval_payment_result = MPRestClient::put($request);
        return $preapproval_payment_result;
    }

    /**
     * @param $id
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function cancel_preapproval_payment($id)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/preapproval/' . $id,
            'data' => array(
                'status' => 'cancelled'
            )
        );

        $response = MPRestClient::put($request);
        return $response;
    }

    //=== REFUND AND CANCELING FLOW FUNCTIONS ===

    /**
     * @param $id
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function refund_payment($id)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/v1/payments/' . $id . '/refunds'
        );

        $response = MPRestClient::post($request);
        return $response;
    }

    /**
     * @param $id
     * @param $amount
     * @param $reason
     * @param $external_reference
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function partial_refund_payment($id, $amount, $reason, $external_reference)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/v1/payments/' . $id . '/refunds',
            'data' => array(
                'amount' => $amount,
                'metadata' => array(
                    'metadata' => $reason,
                    'external_reference' => $external_reference
                )
            )
        );

        $response = MPRestClient::post($request);
        return $response;
    }

    /**
     * @param $id
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function cancel_payment($id)
    {

        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/v1/payments/' . $id,
            'data' => '{"status":"cancelled"}'
        );

        $response = MPRestClient::put($request);
        return $response;
    }

    /**
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function get_payment_methods()
    {
        $request = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token()
            ),
            'uri' => '/v1/payment_methods',
        );

        $response = MPRestClient::get($request);
        asort($result);
        return $response;
    }

    /**
     * Validate if the seller is homologated
     * @param $access_token
     * @param $public_key
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function getCredentialsWrapper($access_token = null, $public_key = null)
    {
        $request = array(
            'uri' => '/plugins-credentials-wrapper/credentials',
        );

        if (!empty($access_token) && empty($public_key)) {
            $request['headers'] = array('Authorization' => 'Bearer ' . $access_token);
        }

        if (empty($access_token) && !empty($public_key)) {
            $request['params'] = array('public_key' => $public_key);
        }

        $response = MPRestClient::get($request);

        if ($response['status'] > 202) {
            $log = WC_WooMercadoPago_Log::init_mercado_pago_log('getCredentialsWrapper');
            $log->write_log('API GET Credentials Wrapper error:', $response['response']['message']);
            return false;
        }

        return $response['response'];
    }

    //=== GENERIC RESOURCE CALL METHODS ===

    /**
     * @param $request
     * @param null $params
     * @param bool $authenticate
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function get($request, $headers = [], $authenticate = true)
    {

        if (is_string($request)) {
            $request = array(
                'headers' => $headers,
                'uri' => $request,
                'authenticate' => $authenticate
            );
        }

        if (!isset($request['authenticate']) || $request['authenticate'] !== false) {
            $access_token = $this->get_access_token();
            if (!empty($access_token)) {
                $request['headers'] = array('Authorization'=> 'Bearer ' . $access_token);
            }
        }

        $result = MPRestClient::get($request);
        return $result;
    }

    /**
     * @param $request
     * @param null $data
     * @param null $params
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function post($request, $data = null, $params = null)
    {

        if (is_string($request)) {
            $request = array(
                'headers' => array('Authorization' => 'Bearer ' . $this->get_access_token()),
                'uri' => $request,
                'data' => $data,
                'params' => $params
            );
        }

        $request['params'] = isset($request['params']) && is_array($request['params']) ?
            $request["params"] :
            array();

        $result = MPRestClient::post($request);
        return $result;
    }

    /**
     * @param $request
     * @param null $data
     * @param null $params
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function put($request, $data = null, $params = null)
    {

        if (is_string($request)) {
            $request = array(
                'headers' => array('Authorization' => 'Bearer ' . $this->get_access_token()),
                'uri' => $request,
                'data' => $data,
                'params' => $params
            );
        }

        $request['params'] = isset($request['params']) && is_array($request['params']) ?
            $request['params'] :
            array();

        $result = MPRestClient::put($request);
        return $result;
    }

    /**
     * @param $request
     * @param null $params
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function delete($request, $params = null)
    {

        if (is_string($request)) {
            $request = array(
                'headers' => array('Authorization' => 'Bearer ' . $this->get_access_token()),
                'uri' => $request,
                'params' => $params
            );
        }

        $request['params'] = isset($request['params']) && is_array($request['params']) ?
            $request['params'] :
            array();

        $result = MPRestClient::delete($request);
        return $result;
    }

    /**
     * @param null $payment
     */
    public function setPaymentClass($payment = null)
    {
        if (!empty($payment)) {
            $this->paymentClass = get_class($payment);
        }
    }

    /**
     * @return mixed
     */
    public function getPaymentClass()
    {
        return $this->paymentClass;
    }

}
