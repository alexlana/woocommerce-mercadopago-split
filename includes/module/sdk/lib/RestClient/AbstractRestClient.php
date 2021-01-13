<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Class AbstractRestClient
 */
class AbstractRestClient
{
    public static $email_admin = '';
    public static $site_locale = '';
    public static $check_loop = 0;

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function execAbs($request, $url)
    {
        try {
            $connect = self::build_request($request, $url);
            return self::execute($request, $connect);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param $request
     * @return false|resource
     * @throws WC_WooMercadoPago_Exception
     */
    public static function build_request($request, $url)
    {
        if (!extension_loaded('curl')) {
            throw new WC_WooMercadoPago_Exception('cURL extension not found. You need to enable cURL in your php.ini or another configuration you have.');
        }

        if (!isset($request['method'])) {
            throw new WC_WooMercadoPago_Exception('No HTTP METHOD specified');
        }

        if (!isset($request['uri'])) {
            throw new WC_WooMercadoPago_Exception('No URI specified');
        }

        $headers = array('accept: application/json');
        if ($request['method'] == 'POST') {
            $headers[] = 'x-product-id:' . (WC_WooMercadoPago_Module::is_mobile() ? WC_WooMercadoPago_Constants::PRODUCT_ID_MOBILE : WC_WooMercadoPago_Constants::PRODUCT_ID_DESKTOP);
            $headers[] = 'x-platform-id:' . WC_WooMercadoPago_Constants::PLATAFORM_ID;
            $headers[] = 'x-integrator-id:' . get_option('_mp_integrator_id', null);
        }

        $json_content = true;
        $form_content = false;
        $default_content_type = true;

        if (isset($request['headers']) && is_array($request['headers'])) {
            foreach ($request['headers'] as $h => $v) {
                if ($h == 'content-type') {
                    $default_content_type = false;
                    $json_content = $v == 'application/json';
                    $form_content = $v == 'application/x-www-form-urlencoded';
                }
                array_push($headers, $h . ': ' . $v);
            }
        }
        if ($default_content_type) {
            array_push($headers, 'content-type: application/json');
        }

        $connect = curl_init();
        curl_setopt($connect, CURLOPT_USERAGENT, 'platform:v1-whitelabel,type:woocommerce,so:' . WC_WooMercadoPago_Constants::VERSION);
        curl_setopt($connect, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($connect, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($connect, CURLOPT_CAINFO, $GLOBALS['LIB_LOCATION'] . '/cacert.pem');
        curl_setopt($connect, CURLOPT_CUSTOMREQUEST, $request['method']);
        curl_setopt($connect, CURLOPT_HTTPHEADER, $headers);

        if (isset($request['params']) && is_array($request['params'])) {
            if (count($request['params']) > 0) {
                $request['uri'] .= (strpos($request['uri'], '?') === false) ? '?' : '&';
                $request['uri'] .= self::build_query($request['params']);
            }
        }

        curl_setopt($connect, CURLOPT_URL, $url . $request['uri']);


        if (isset($request['data'])) {
            if ($json_content) {
                if (gettype($request['data']) == 'string') {
                    json_decode($request['data'], true);
                } else {
                    $request['data'] = json_encode($request['data']);
                }
                if (function_exists('json_last_error')) {
                    $json_error = json_last_error();
                    if ($json_error != JSON_ERROR_NONE) {
                        throw new WC_WooMercadoPago_Exception("JSON Error [{$json_error}] - Data: " . $request['data']);
                    }
                }
            } elseif ($form_content) {
                $request['data'] = self::build_query($request['data']);
            }
            curl_setopt($connect, CURLOPT_POSTFIELDS, $request['data']);
        }

        return $connect;
    }

    /**
     * @param $connect
     * @return array|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function execute($request, $connect)
    {
        $response = null;
        $api_result = curl_exec($connect);
        if (curl_errno($connect)) {
            throw new WC_WooMercadoPago_Exception(curl_error($connect));
        }
        $api_http_code = curl_getinfo($connect, CURLINFO_HTTP_CODE);

        if ($api_http_code != null && $api_result != null) {
            $response = array('status' => $api_http_code, 'response' => json_decode($api_result, true));
        }

        curl_close($connect);
        return $response;
    }

    /**
     * @param $params
     * @return string
     */
    public static function build_query($params)
    {
        if (function_exists('http_build_query')) {
            return http_build_query($params, '', '&');
        } else {
            foreach ($params as $name => $value) {
                $elements[] = "{$name}=" . urlencode($value);
            }
            return implode('&', $elements);
        }
    }

    /**
     * @param $email
     */
    public static function set_email($email)
    {
        self::$email_admin = $email;
    }

    /**
     * @param $country_code
     */
    public static function set_locale($country_code)
    {
        self::$site_locale = $country_code;
    }
}
