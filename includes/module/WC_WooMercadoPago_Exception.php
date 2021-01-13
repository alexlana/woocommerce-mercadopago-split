<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_WooMercadoPago_Exception
 */
class WC_WooMercadoPago_Exception extends Exception {

    /**
     * WC_WooMercadoPago_Exception constructor.
     * @param $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct( $message, $code = 500, Exception $previous = null ) {
        parent::__construct( $message, $code, $previous );
    }
}