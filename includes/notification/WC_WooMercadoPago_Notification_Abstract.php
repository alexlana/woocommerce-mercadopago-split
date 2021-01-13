<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_WooMercadoPago_Notification_Abstract
 */
abstract class WC_WooMercadoPago_Notification_Abstract
{
    public $mp;
    public $sandbox;
    public $log;
    public $payment;

    /**
     * WC_WooMercadoPago_Notification_Abstract constructor.
     * @param $payment
     */
    public function __construct($payment)
    {
        $this->payment = $payment;
        $this->mp = $payment->mp;
        $this->log = $payment->log;
        $this->sandbox = $payment->sandbox;
        $this->payment = $payment;

        add_action('woocommerce_api_' . strtolower(get_class($payment)), array($this, 'check_ipn_response'));
        add_action('valid_mercadopago_ipn_request', array($this, 'successful_request'));
        add_action('woocommerce_order_status_cancelled', array($this, 'process_cancel_order_meta_box_actions'), 10, 1);
    }

    /**
     * @param $mp_status
     * @return mixed
     */
    public static function get_wc_status_for_mp_status($mp_status)
    {
        $defaults = array(
            'pending' => 'pending',
            'approved' => 'processing',
            'inprocess' => 'on_hold',
            'inmediation' => 'on_hold',
            'rejected' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'chargedback' => 'refunded'
        );
        $status = $defaults[$mp_status];
        return str_replace('_', '-', $status);
    }

    /**
     *
     */
    public function check_ipn_response()
    {
        @ob_clean();
        $this->log->write_log(__FUNCTION__, 'received _get content: ' . json_encode($_GET, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param $data
     * @return bool|WC_Order|WC_Order_Refund
     */
    public function successful_request($data)
    {
        $this->log->write_log(__FUNCTION__, 'starting to process  update...');
        $order_key = $data['external_reference'];

        if (empty($order_key)) {
            $this->log->write_log(__FUNCTION__, 'External Reference not found');
            $this->setResponse(422, null, "External Reference not found");
        }
        $invoice_prefix = get_option('_mp_store_identificator', 'WC-');
        $id = (int)str_replace($invoice_prefix, '', $order_key);
        $order = wc_get_order($id);
        if (!$order) {
            $this->log->write_log(__FUNCTION__, 'Order is invalid');
            $this->setResponse(422, null, "Order is invalid");
        }

        $order_id = (method_exists($order, 'get_id') ? $order->get_id() : $order->get_id());
        if ($order_id !== $id) {
            $this->log->write_log(__FUNCTION__, 'Order error');
            $this->setResponse(422, null, "Order error");
        }

        $this->log->write_log(__FUNCTION__, 'updating metadata and status with data: ' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $order;
    }

    /**
     * @param $processed_status
     * @param $data
     * @param $order
     * @throws WC_WooMercadoPago_Exception
     */
    public function proccessStatus($processed_status, $data, $order)
    {
        $used_gateway = get_class($this->payment);

        switch ($processed_status) {
            case 'approved':
                $this->mp_rule_approved($data, $order, $used_gateway);
                break;
            case 'pending':
                $this->mp_rule_pending($data, $order, $used_gateway);
                break;
            case 'in_process':
                $this->mp_rule_in_process($data, $order);
                break;
            case 'rejected':
                $this->mp_rule_rejected($data, $order);
                break;
            case 'refunded':
                $this->mp_rule_refunded($order);
                break;
            case 'cancelled':
                $this->mp_rule_cancelled($data, $order);
                break;
            case 'in_mediation':
                $this->mp_rule_in_mediation($order);
                break;
            case 'charged_back':
                $this->mp_rule_charged_back($order);
                break;
            default:
                throw new WC_WooMercadoPago_Exception('Process Status - Invalid Status: ' . $processed_status);
        }
    }

    /**
     * @param $data
     * @param $order
     * @param $used_gateway
     */
    public function mp_rule_approved($data, $order, $used_gateway)
    {
        $order->add_order_note('Mercado Pago: ' . __('Payment approved.', 'woocommerce-mercadopago'));

        $payment_completed_status = apply_filters(
            'woocommerce_payment_complete_order_status',
            $order->needs_processing() ? 'processing' : 'completed',
            $order->get_id(),
            $order
        );

        if (method_exists($order, 'get_status') && $order->get_status() !== 'completed') {
            switch ($used_gateway) {
                case 'WC_WooMercadoPago_CustomGateway':
                    $order->payment_complete();
                    if ($payment_completed_status !== 'completed') {
                        $order->update_status(self::get_wc_status_for_mp_status('approved'));
                    }
                    break;
                case 'WC_WooMercadoPago_TicketGateway':
                    if (get_option('stock_reduce_mode', 'no') == 'no') {
                        $order->payment_complete();
                        if ($payment_completed_status !== 'completed') {
                            $order->update_status(self::get_wc_status_for_mp_status('approved'));
                        }
                    }
                    break;
                case 'WC_WooMercadoPago_BasicGateway':
                    $order->payment_complete();
                    if ($payment_completed_status !== 'completed') {
                        $order->update_status(self::get_wc_status_for_mp_status('approved'));
                    }
                    break;
            }
        }
    }

    /**
     * @param $order
     * @param $usedGateway
     */
    public function mp_rule_pending($data, $order, $used_gateway)
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(self::get_wc_status_for_mp_status('pending'));
            switch ($used_gateway) {
                case 'WC_WooMercadoPago_TicketGateway':
                    $notes = $order->get_customer_order_notes();
                    $has_note = false;
                    if (sizeof($notes) > 1) {
                        $has_note = true;
                        break;
                    }
                    if (!$has_note) {
                        $order->add_order_note(
                            'Mercado Pago: ' . __('Waiting for the ticket payment.', 'woocommerce-mercadopago')
                        );
                        $order->add_order_note(
                            'Mercado Pago: ' . __('Waiting for the ticket payment.', 'woocommerce-mercadopago'),
                            1, false
                        );
                    }
                    break;
                default:
                    $order->add_order_note(
                        'Mercado Pago: ' . __('The customer has not made the payment yet.', 'woocommerce-mercadopago')
                    );
                    break;
            }
        } else {
            $this->validateOrderNoteType($data, $order, 'pending');
        }

        return;
    }

    /**
     * @param $order
     */
    public function mp_rule_in_process($data, $order)
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(
                self::get_wc_status_for_mp_status('inprocess'),
                'Mercado Pago: ' . __('Payment is pending review.', 'woocommerce-mercadopago')
            );
        } else {
            $this->validateOrderNoteType($data, $order, 'in_process');
        }

        return;
    }

    /**
     * @param $order
     */
    public function mp_rule_rejected($data, $order)
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(
                self::get_wc_status_for_mp_status('rejected'),
                'Mercado Pago: ' . __('Payment was declined. The customer can try again.', 'woocommerce-mercadopago')
            );
        } else {
            $this->validateOrderNoteType($data, $order, 'rejected');
        }

        return;
    }

    /**
     * @param $order
     */
    public function mp_rule_refunded($order)
    {
        $order->update_status(
            self::get_wc_status_for_mp_status('refunded'),
            'Mercado Pago: ' . __('Payment was returned to the customer.', 'woocommerce-mercadopago')
        );
        return;
    }

    /**
     * @param $order
     */
    public function mp_rule_cancelled($data, $order)
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(
                self::get_wc_status_for_mp_status('cancelled'),
                'Mercado Pago: ' . __('Payment was canceled.', 'woocommerce-mercadopago')
            );
        } else {
            $this->validateOrderNoteType($data, $order, 'cancelled');
        }

        return;
    }

    /**
     * @param $order
     */
    public function mp_rule_in_mediation($order)
    {
        $order->update_status(self::get_wc_status_for_mp_status('inmediation'));
        $order->add_order_note(
            'Mercado Pago: ' . __('The payment is in mediation or the purchase was unknown by the customer.', 'woocommerce-mercadopago')
        );
        return;
    }

    /**
     * @param $order
     */
    public function mp_rule_charged_back($order)
    {
        $order->update_status(self::get_wc_status_for_mp_status('chargedback'));
        $order->add_order_note(
            'Mercado Pago: ' . __('The payment is in mediation or the purchase was unknown by the customer.',
            'woocommerce-mercadopago')
        );
        return;
    }

    /**
     * @param $order
     */
    public function process_cancel_order_meta_box_actions($order)
    {
        $order_payment = wc_get_order($order);
        $used_gateway = (method_exists($order_payment, 'get_meta')) ? $order_payment->get_meta('_used_gateway') : get_post_meta($order_payment->id, '_used_gateway', true);
        $payments = (method_exists($order_payment, 'get_meta')) ? $order_payment->get_meta('_Mercado_Pago_Payment_IDs') : get_post_meta($order_payment->id, '_Mercado_Pago_Payment_IDs', true);

        if ($used_gateway == 'WC_WooMercadoPago_CustomGateway') {
            return;
        }
        $this->log->write_log(__FUNCTION__, 'cancelling payments for ' . $payments);
        // Canceling the order and all of its payments.
        if ($this->mp != null && !empty($payments)) {
            $payment_ids = explode(', ', $payments);
            foreach ($payment_ids as $p_id) {
                $response = $this->mp->cancel_payment($p_id);
                $status = $response['status'];
                $this->log->write_log(__FUNCTION__, 'cancel payment of id ' . $p_id . ' => ' . ($status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $response['response']['message']));
            }
        } else {
            $this->log->write_log(__FUNCTION__, 'no payments or credentials invalid');
        }
    }

    /**
     * @param $checkout_info
     */
    public function check_and_save_customer_card($checkout_info)
    {
        $this->log->write_log(__FUNCTION__, 'checking info to create card: ' . json_encode($checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $custId = null;
        $token = null;
        $issuer_id = null;
        $payment_method_id = null;
        if (isset($checkout_info['payer']['id']) && !empty($checkout_info['payer']['id'])) {
            $custId = $checkout_info['payer']['id'];
        } else {
            return;
        }
        if (isset($checkout_info['metadata']['token']) && !empty($checkout_info['metadata']['token'])) {
            $token = $checkout_info['metadata']['token'];
        } else {
            return;
        }
        if (isset($checkout_info['issuer_id']) && !empty($checkout_info['issuer_id'])) {
            $issuer_id = (integer)($checkout_info['issuer_id']);
        }
        if (isset($checkout_info['payment_method_id']) && !empty($checkout_info['payment_method_id'])) {
            $payment_method_id = $checkout_info['payment_method_id'];
        }
        try {
            $this->mp->create_card_in_customer($custId, $token, $payment_method_id, $issuer_id);
        } catch (WC_WooMercadoPago_Exception $ex) {
            $this->log->write_log(__FUNCTION__, 'card creation failed: ' . json_encode($ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    protected function canUpdateOrderStatus($order) {
        return method_exists($order, 'get_status') && $order->get_status() !== 'completed' && $order->get_status() !== 'processing';
    }

    /**
     * @param $data
     * @param $order
     * @param $status
     * @return void
     */
    protected function validateOrderNoteType($data, $order, $status)
    {
        $paymentId = $data['id'];

        if(isset($data['ipn_type']) && $data['ipn_type'] === 'merchant_order') {
            $payments = [];
            foreach($data['payments'] as $payment) {
                $payments[] = $payment['id'];
            }

            $paymentId = implode(',', $payments);
        }

        $order->add_order_note(
            sprintf(
                __('Mercado Pago: The payment %s was notified by Mercado Pago with status %s.', 'woocommerce-mercadopago'),
                $paymentId,
                $status
            )
        );
    }

    /**
     * @param $code
     * @param $code_message
     * @param $body
     */
    public function setResponse($code, $code_message, $body)
    {
        status_header($code, $code_message);
        die($body);
    }
}
