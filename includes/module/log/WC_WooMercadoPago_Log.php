<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_WooMercadoPago_Log
 */
class WC_WooMercadoPago_Log
{
    public $log;
    public $id;
    public $debugMode;

    /**
     * WC_WooMercadoPago_Log constructor.
     * @param null $payment
     * @param bool $debugMode
     */
    public function __construct($payment = null, $debugMode = false)
    {
        $this->getDebugMode($payment, $debugMode);
        if(!empty($payment)){
            $this->id = get_class($payment);
        }
        return $this->initLog();
    }

    /**
     * @param $payment
     * @param $debugMode
     */
    public function getDebugMode($payment, $debugMode)
    {
        if (!empty($payment)) {
            $debugMode = $payment->debug_mode;
            if($debugMode == 'no'){
                $debugMode = false;
            }else{
                $debugMode = true;
            }
        }

        if (empty($payment) && empty($debugMode)) {
            $debugMode = true;
        }

        $this->debugMode = $debugMode;
    }

    /**
     * @return WC_Logger|null
     */
    public function initLog()
    {
        if (!empty($this->debugMode)) {
            if (class_exists('WC_Logger')) {
                $this->log = new WC_Logger();
            } else {
                $this->log = WC_WooMercadoPago_Module::woocommerce_instance()->logger();
            }
            return $this->log;
        }
        return null;
    }

    /**
     * @param null $id
     * @return WC_WooMercadoPago_Log|null
     */
    public static function init_mercado_pago_log($id = null)
    {
        $log = new self(null, true);
        if (!empty($log) && !empty($id)) {
            $log->setId($id);
        }
        return $log;
    }

    /**
     * @param $function
     * @param $message
     */
    public function write_log($function, $message)
    {
        if (!empty ($this->debugMode)) {
            $this->log->add($this->id, '[' . $function . ']: ' . $message);
        }
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}