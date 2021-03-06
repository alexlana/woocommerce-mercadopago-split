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
 * Class MeliRestClientSplit
 */
class MeliRestClientSplit extends AbstractRestClientSplit
{
    const API_MELI_BASE_URL = 'https://api.mercadolibre.com';

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPagoSplit_Exception
     */
    public static function get($request)
    {
        $request['method'] = 'GET';
        return self::execAbs($request, self::API_MELI_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPagoSplit_Exception
     */
    public static function post($request)
    {
        $request['method'] = 'POST';
        return self::execAbs($request, self::API_MELI_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPagoSplit_Exception
     */
    public static function put($request)
    {
        $request['method'] = 'PUT';
        return self::execAbs($request, self::API_MELI_BASE_URL);
    }

    /**
     * @param $request
     * @return array|null
     * @throws WC_WooMercadoPagoSplit_Exception
     */
    public static function delete($request)
    {
        $request['method'] = 'DELETE';
        return self::execAbs($request, self::API_MELI_BASE_URL);
    }
}
