<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_WooMercadoPagoSplit_Exception
 */
class WC_WooMercadoPagoSplit_Exception extends Exception {

    /**
     * WC_WooMercadoPagoSplit_Exception constructor.
     * @param $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct( $message, $code = 500, Exception $previous = null ) {
        parent::__construct( $message, $code, $previous );
    }
}