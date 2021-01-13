<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_WooMercadoPagoSplit_PreferenceAnalytics {
    public static $ignoreFields = ['_mp_public_key_prod', '_mp_public_key_test', 'title','description', '_mp_access_token_prod', '_mp_access_token_test'];

    public function getBasicSettings(){
       return $this->getSettings('woocommerce_woo-mercado-pago-split-basic_settings');
    }
    public function getCustomSettings(){
        return $this->getSettings('woocommerce_woo-mercado-pago-split-custom_settings');
    }
    public function getTicketSettings(){
        return $this->getSettings('woocommerce_woo-mercado-pago-split-ticket_settings');
    }

    public function getSettings($option){

    $db_options = get_option($option, []);

    $validValues = array();
    foreach ($db_options as $key => $value) {
        if (!empty($value) && !in_array($key, WC_WooMercadoPagoSplit_PreferenceAnalytics::$ignoreFields)) {
            $validValues[$key] = $value;
        }
    }
    return $validValues;
    }
}
