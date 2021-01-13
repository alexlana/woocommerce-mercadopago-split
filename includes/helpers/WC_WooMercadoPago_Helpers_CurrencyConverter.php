<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_WooMercadoPago_Helpers_CurrencyConverter
 */
class WC_WooMercadoPago_Helpers_CurrencyConverter
{
    const CONFIG_KEY      = 'currency_conversion';
    const DEFAULT_RATIO   = 1;

    /** @var WC_WooMercadoPago_Helpers_CurrencyConverter */
    private static $instance;
    private $msg_description;

    /**
     * @var array
     */
    private $ratios = [];

    /**
     * @var array
     */
    private $cache = [];

    /**
     * @var array
     */
    private $currencyCache = [];

    /**
     * @var
     */
    private $supportedCurrencies;

    /**
     * @var bool
     */
    private $isShowingAlert = false;

    /** @var WC_WooMercadoPago_Log */
    private $log;

    /**
     * Private constructor to make class singleton
     */
    private function __construct()
    {
        $this->msg_description = __('Activate this option so that the value of the currency set in WooCommerce is compatible with the value of the currency you use in Mercado Pago.', 'woocommerce-mercadopago');
        $this->log = new WC_WooMercadoPago_Log();
        return $this;
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return $this
     */
    private function init(WC_WooMercadoPago_PaymentAbstract $method)
    {
        if (!isset($this->ratios[$method->id])) {

            try {
                if (!$this->isEnabled($method)) {
                    $this->setRatio($method->id);
                    return $this;
                }

                $accountCurrency = $this->getAccountCurrency($method);
                $localCurrency = get_woocommerce_currency();

                if (!$accountCurrency || $accountCurrency == $localCurrency) {
                    $this->setRatio($method->id);
                    return $this;
                }

                $this->setRatio($method->id, $this->loadRatio($localCurrency, $accountCurrency, $method));
            } catch (Exception $e) {
                $this->setRatio($method->id);
                throw $e;
            }
        }

        return $this;
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return mixed|null
     */
    private function getAccountCurrency(WC_WooMercadoPago_PaymentAbstract $method)
    {
        $key = $method->id;

        if (isset($this->currencyCache[$key])) {
            return $this->currencyCache[$key];
        }

        $siteId = $this->getSiteId($this->getAccessToken($method));

        if (!$siteId) {
            return null;
        }

        $configs = $this->getCountryConfigs();

        if (!isset($configs[$siteId]) || !isset($configs[$siteId]['currency'])) {
            return null;
        }

        return isset($configs[$siteId]) ? $configs[$siteId]['currency'] : null;
    }

    /**
     * @return array
     */
    private function getCountryConfigs()
    {
        try {
            $configInstance = new WC_WooMercadoPago_Configs();
            return $configInstance->getCountryConfigs();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return mixed
     */
    private function getAccessToken(WC_WooMercadoPago_PaymentAbstract $method)
    {
        $type = $method->getOption('checkout_credential_prod') == 'no'
            ? '_mp_access_token_test'
            : '_mp_access_token_prod';

        return $method->getOption($type);
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return mixed
     */
    public function isEnabled(WC_WooMercadoPago_PaymentAbstract $method)
    {
        return $method->getoption(self::CONFIG_KEY, 'no') == 'yes' ? true : false;
    }

    /**
     * @param $methodId
     * @param int $value
     */
    private function setRatio($methodId, $value = self::DEFAULT_RATIO)
    {
        $this->ratios[$methodId] = $value;
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return int|mixed
     */
    private function getRatio(WC_WooMercadoPago_PaymentAbstract $method)
    {
        $this->init($method);
        return isset($this->ratios[$method->id])
            ? $this->ratios[$method->id]
            : self::DEFAULT_RATIO;
    }

    /**
     * @param $fromCurrency
     * @param $toCurrency
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return int
     */
    public function loadRatio($fromCurrency, $toCurrency, WC_WooMercadoPago_PaymentAbstract $method = null)
    {
        $cacheKey = $fromCurrency . '--' . $toCurrency;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $ratio = self::DEFAULT_RATIO;

        if ($fromCurrency == $toCurrency) {
            $this->cache[$cacheKey] = $ratio;
            return $ratio;
        }

        try {
            $result = MeliRestClient::get(
                array(
                    'uri' => sprintf('/currency_conversions/search?from=%s&to=%s', $fromCurrency, $toCurrency),
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->getAccessToken($method)
                    )
                )
            );

            if ($result['status'] != 200) {
                $this->log->write_log(__FUNCTION__, 'Mercado pago gave error to get currency value, payment creation failed with error: ' . print_r($result, true));
                $ratio = self::DEFAULT_RATIO;
                throw new Exception('Status: ' . $result['status'] . ' Message: ' . $result['response']['message']);
            }

            if (isset($result['response'], $result['response']['ratio'])) {
                $ratio = $result['response']['ratio'] > 0 ? $result['response']['ratio'] : self::DEFAULT_RATIO;
            }
        } catch (Exception $e) {
            $this->log->write_log(
                "WC_WooMercadoPago_Helpers_CurrencyConverter::loadRatio('$fromCurrency', '$toCurrency')",
                $e->__toString()
            );

            throw $e;
        }

        $this->cache[$cacheKey] = $ratio;
        return $ratio;
    }

    /**
     * @param $accessToken
     * @return string | null
     */
    private function getSiteId($accessToken)
    {
        try {
            $mp = new MP($accessToken);
            $result = $mp->get('/users/me', array('Authorization' => 'Bearer ' . $accessToken));
            return isset($result['response'], $result['response']['site_id']) ? $result['response']['site_id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return float
     */
    public function ratio(WC_WooMercadoPago_PaymentAbstract $method)
    {
        $this->init($method);
        return $this->getRatio($method);
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return string|void
     */
    public function getDescription(WC_WooMercadoPago_PaymentAbstract $method)
    {
        return $this->msg_description;
    }

    /**
     * Check if currency is supported in mercado pago API
     * @param $currency
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return bool
     */
    private function isCurrencySupported($currency, WC_WooMercadoPago_PaymentAbstract $method)
    {
        foreach ($this->getSupportedCurrencies($method) as $country) {
            if ($country['id'] == $currency) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get supported currencies from mercado pago API
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return array|bool
     */
    public function getSupportedCurrencies(WC_WooMercadoPago_PaymentAbstract $method)
    {
        if (is_null($this->supportedCurrencies)) {
            try {

                $request = array(
                    'uri' => '/currencies',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->getAccessToken($method)
                    )
                );

                $result = MeliRestClient::get($request);

                if (!isset($result['response'])) {
                    return false;
                }

                $this->supportedCurrencies = $result['response'];
            } catch (Exception $e) {
                $this->supportedCurrencies = [];
            }
        }

        return $this->supportedCurrencies;
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return array
     */
    public function getField(WC_WooMercadoPago_PaymentAbstract $method)
    {
        return array(
            'title'       => __('Convert Currency', 'woocommerce-mercadopago'),
            'type'        => 'select',
            'default'     => 'no',
            'description' => $this->msg_description,
            'options'     => array(
                'no'  => __('No', 'woocommerce-mercadopago'),
                'yes' => __('Yes', 'woocommerce-mercadopago'),
            ),
        );
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @param $oldData
     * @param $newData
     */
    public function scheduleNotice(WC_WooMercadoPago_PaymentAbstract $method, $oldData, $newData)
    {
        if (!isset($oldData[self::CONFIG_KEY]) || !isset($newData[self::CONFIG_KEY])) {
            return;
        }

        if ($oldData[self::CONFIG_KEY] != $newData[self::CONFIG_KEY]) {
            $_SESSION[self::CONFIG_KEY]['notice'] = array(
                'type'   => $newData[self::CONFIG_KEY] == 'yes' ? 'enabled' : 'disabled',
                'method' => $method,
            );
        }
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     */
    public function notices(WC_WooMercadoPago_PaymentAbstract $method)
    {
        $show = isset($_SESSION[self::CONFIG_KEY]) ? $_SESSION[self::CONFIG_KEY] : array();
        $localCurrency = get_woocommerce_currency();

        $accountCurrency = $this->getAccountCurrency($method);

        if ($localCurrency == $accountCurrency || empty($accountCurrency) ) {
            return;
        }

        if (isset($show['notice'])) {
            unset($_SESSION[self::CONFIG_KEY]['notice']);
            if ($show['notice']['type'] == 'enabled') {
                echo $this->noticeEnabled($method);
            } elseif ($show['notice']['type'] == 'disabled') {
                echo $this->noticeDisabled($method);
            }
        }

        if (!$this->isEnabled($method) && !$this->isShowingAlert && $method->isCurrencyConvertable()) {
            echo $this->noticeWarning($method);
        }
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return string
     */
    public function noticeEnabled(WC_WooMercadoPago_PaymentAbstract $method)
    {
        $localCurrency = get_woocommerce_currency();
        $currency = $this->getAccountCurrency($method);

        $type = 'notice-error';
        $message = sprintf(__('Now we convert your currency from %s to %s.', 'woocommerce-mercadopago'), $localCurrency, $currency);

        return WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return string
     */
    public function noticeDisabled(WC_WooMercadoPago_PaymentAbstract $method)
    {
        $localCurrency = get_woocommerce_currency();
        $currency = $this->getAccountCurrency($method);

        $type = 'notice-error';
        $message =  sprintf(__('We no longer convert your currency from %s to %s.', 'woocommerce-mercadopago'), $localCurrency, $currency);

        return WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

    /**
     * @param WC_WooMercadoPago_PaymentAbstract $method
     * @return string
     */
    public function noticeWarning(WC_WooMercadoPago_PaymentAbstract $method)
    {
        global $current_section;

        if (in_array($current_section, array($method->id, sanitize_title(get_class($method))), true)) {
            $this->isShowingAlert = true;

            $type = 'notice-error';
            $message =  __('<b>Attention:</b> The currency settings you have in WooCommerce are not compatible with the currency you use in your Mercado Pago account. Please activate the currency conversion.', 'woocommerce-mercadopago');

            return WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
        }

        return '';
    }

    /**
     * @param $str
     * @param mixed ...$values
     * @return string|void
     */
    private function __($str, ...$values)
    {
        $translated = $str;

        if (!empty($values)) {
            $translated = vsprintf($translated, $values);
        }

        return $translated;
    }
}
