<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MPRestClientSplit
 */
class MPRestClientSplit extends AbstractRestClientSplit
{
    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPagoSplit_Exception
     */
    public static function get($request)
    {
        $request['method'] = 'GET';
        return self::execAbs($request, WC_WooMercadoPagoSplit_Constants::API_MP_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPagoSplit_Exception
     */
    public static function post($request)
    {
        $request['method'] = 'POST';
        return self::execAbs($request, WC_WooMercadoPagoSplit_Constants::API_MP_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPagoSplit_Exception
     */
    public static function put($request)
    {
        $request['method'] = 'PUT';
        return self::execAbs($request, WC_WooMercadoPagoSplit_Constants::API_MP_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPagoSplit_Exception
     */
    public static function delete($request)
    {
        $request['method'] = 'DELETE';
        return self::execAbs($request, WC_WooMercadoPagoSplit_Constants::API_MP_BASE_URL);
    }

}
