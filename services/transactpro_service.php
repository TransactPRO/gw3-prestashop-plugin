<?php

use TransactPro\Gateway\Gateway;
use TransactPro\Gateway\Operations\Operation;
use TransactPro\Gateway\Operations\Transactions\Credit;
use TransactPro\Gateway\Operations\Transactions\DmsHold;
use TransactPro\Gateway\Operations\Transactions\P2P;
use TransactPro\Gateway\Operations\Transactions\Sms;
use TransactPro\Gateway\DataSets\Customer;
use TransactPro\Gateway\DataSets\Money;
use TransactPro\Gateway\DataSets\Order;
use TransactPro\Gateway\DataSets\PaymentMethod;
use TransactPro\Gateway\DataSets\System;


class TransactproService
{
    private static $instance = null;

    const METHOD_UNKNOWN_NAME = 'UNKNOWN';
    const METHOD_SMS = 1;
    const METHOD_DMS = 2;
    const METHOD_CREDIT = 3;
    const METHOD_P2P = 4;

    const STATUS_UNKNOWN_NAME = 'UNKNOWN';
    const STATUS_INIT = 1;
    const STATUS_SENT_TO_BANK = 2;
    const STATUS_HOLD_OK = 3;
    const STATUS_DMS_HOLD_FAILED = 4;
    const STATUS_SMS_FAILED_SMS = 5;
    const STATUS_DMS_CHARGE_FAILED = 6;
    const STATUS_SUCCESS = 7;
    const STATUS_EXPIRED = 8;
    const STATUS_HOLD_EXPIRED = 9;
    const STATUS_REFUND_FAILED = 11;
    const STATUS_REFUND_PENDING = 12;
    const STATUS_REFUND_SUCCESS = 13;
    const STATUS_DMS_CANCEL_OK = 15;
    const STATUS_DMS_CANCEL_FAILED = 16;
    const STATUS_REVERSED = 17;
    const STATUS_INPUT_VALIDATION_FAILED = 18;
    const STATUS_BR_VALIDATION_FAILED = 19;
    const STATUS_TERMINAL_GROUP_SELECT_FAILED = 20;
    const STATUS_TERMINAL_SELECT_FAILED = 21;
    const STATUS_DECLINED_BY_BR_ACTION = 23;
    const STATUS_WAITING_CARD_FORM_FILL = 25;
    const STATUS_MPI_URL_GENERATED = 26;
    const STATUS_WAITING_MPI = 27;
    const STATUS_MPI_FAILED = 28;
    const STATUS_MPI_NOT_REACHABLE = 29;
    const STATUS_INSIDE_FORM_URL_SENT = 30;
    const STATUS_MPI_AUTH_FAILED = 31;
    const STATUS_ACQUIRER_NOT_REACHABLE = 32;
    const STATUS_REVERSAL_FAILED = 33;
    const STATUS_CREDIT_FAILED = 34;
    const STATUS_P2P_FAILED = 35;

    const PAYMENT_SIDE_MERCHANT = 1;
    const PAYMENT_SIDE_GATEWAY = 2;

    const DEFAULT_CUSTOMER_DATA = array(
        'email' => '',
        'phone' => ' ',
        'birth_date' => ' ',
        'billing_address_country' => ' ',
        'billing_address_state' => ' ',
        'billing_address_city' => ' ',
        'billing_address_street' => ' ',
        'billing_address_house' => ' ',
        'billing_address_flat' => ' ',
        'billing_address_ZIP' => ' ',
        'shipping_address_country' => ' ',
        'shipping_address_state' => ' ',
        'shipping_address_city' => ' ',
        'shipping_address_street' => ' ',
        'shipping_address_house' => ' ',
        'shipping_address_flat' => ' ',
        'shipping_address_ZIP' => ' ',
    );

    const DEFAULT_ORDER_DATA = array(
        'description' => 'Buy Order',
        'merchant_side_url' => _PS_BASE_URL_,
        'recipient_name' => 'PrestaShop'
    );

    const DEFAULT_SYSTEM_DATA = array(
        'user_IP' => '127.0.0.1'
    );

    const DEFAULT_MONEY_DATA = array(
        'amount' => '',
        'currency' => ''
    );

    const DEFAULT_PAYMENT_METHOD_DATA = array(
        'PAN' => '',
        'expire' => '',
        'CVV' => '',
        'card_holder_name' => ''
    );

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        //Do nothing
    }

    /**
     * @param int $transaction_status
     * @return mixed|string
     */
    public function getTransactionStatusName($transaction_status)
    {
        $result = self::STATUS_UNKNOWN_NAME;

        $status_names = array(
            self::STATUS_INIT => 'INIT',
            self::STATUS_SENT_TO_BANK => 'SENT_TO_BANK',
            self::STATUS_HOLD_OK => 'HOLD_OK',
            self::STATUS_DMS_HOLD_FAILED => 'DMS_HOLD_FAILED',
            self::STATUS_SMS_FAILED_SMS => 'SMS_FAILED_SMS',
            self::STATUS_DMS_CHARGE_FAILED => 'DMS_CHARGE_FAILED',
            self::STATUS_SUCCESS => 'SUCCESS',
            self::STATUS_EXPIRED => 'EXPIRED',
            self::STATUS_HOLD_EXPIRED => 'HOLD_EXPIRED',
            self::STATUS_REFUND_FAILED => 'REFUND_FAILED',
            self::STATUS_REFUND_PENDING => 'REFUND_PENDING',
            self::STATUS_REFUND_SUCCESS => 'REFUND_SUCCESS',
            self::STATUS_DMS_CANCEL_OK => 'DMS_CANCEL_OK',
            self::STATUS_DMS_CANCEL_FAILED => 'DMS_CANCEL_FAILED',
            self::STATUS_REVERSED => 'REVERSED',
            self::STATUS_INPUT_VALIDATION_FAILED => 'INPUT_VALIDATION_FAILED',
            self::STATUS_BR_VALIDATION_FAILED => 'BR_VALIDATION_FAILED',
            self::STATUS_TERMINAL_GROUP_SELECT_FAILED => 'TERMINAL_GROUP_SELECT_FAILED',
            self::STATUS_TERMINAL_SELECT_FAILED => 'TERMINAL_SELECT_FAILED',
            self::STATUS_DECLINED_BY_BR_ACTION => 'DECLINED_BY_BR_ACTION',
            self::STATUS_WAITING_CARD_FORM_FILL => 'WAITING_CARD_FORM_FILL',
            self::STATUS_MPI_URL_GENERATED => 'MPI_URL_GENERATED',
            self::STATUS_WAITING_MPI => 'WAITING_MPI',
            self::STATUS_MPI_FAILED => 'MPI_FAILED',
            self::STATUS_MPI_NOT_REACHABLE => 'MPI_NOT_REACHABLE',
            self::STATUS_INSIDE_FORM_URL_SENT => 'INSIDE_FORM_URL_SENT',
            self::STATUS_MPI_AUTH_FAILED => 'MPI_AUTH_FAILED',
            self::STATUS_ACQUIRER_NOT_REACHABLE => 'ACQUIRER_NOT_REACHABLE',
            self::STATUS_REVERSAL_FAILED => 'REVERSAL_FAILED',
            self::STATUS_CREDIT_FAILED => 'CREDIT_FAILED',
            self::STATUS_P2P_FAILED => 'P2P_FAILED'
        );

        if (array_key_exists($transaction_status, $status_names)) {
            $result = $status_names[$transaction_status];
        }

        return $result;
    }

    /**
     * @param int $payment_method
     * @return mixed|string
     */
    public function getPaymentMethodName($payment_method)
    {
        $result = self::METHOD_UNKNOWN_NAME;

        $method_names = array(
            self::METHOD_SMS => 'SMS',
            self::METHOD_DMS => 'DMS',
            self::METHOD_CREDIT => 'CREDIT',
            self::METHOD_P2P => 'P2P',
        );

        if (array_key_exists($payment_method, $method_names)) {
            $result = $method_names[$payment_method];
        }

        return $result;
    }

    /**
     * @param int $transaction_status
     * @return bool
     */
    public function canRefundTransaction($transaction_status)
    {
        return in_array((int)$transaction_status, array(
            self::STATUS_SUCCESS
        ));
    }

    /**
     * @param int $transaction_status
     * @return bool
     */
    public function canChargeTransaction($transaction_status)
    {
        return in_array((int)$transaction_status, array(
            self::STATUS_HOLD_OK
        ));
    }

    /**
     * @param int $payment_method
     * @param int $transaction_status
     * @return bool
     */
    public function canCancelTransaction($payment_method, $transaction_status)
    {
        $payment_method = (int)$payment_method;
        $transaction_status = (int)$transaction_status;

        if((int)$payment_method === self::METHOD_DMS) {
            $result = in_array($transaction_status, array(
                self::STATUS_HOLD_OK
            ));
        } else {
            $result = in_array($transaction_status, array(
                self::STATUS_SUCCESS
            ));
        }

        return $result;
    }

    /**
     * @param int $payment_method
     * @param int $transaction_status
     * @return bool
     */
    public function isSuccessTransaction($payment_method, $transaction_status)
    {
        $payment_method = (int)$payment_method;
        $transaction_status = (int)$transaction_status;

        if (in_array($payment_method, array(self::METHOD_DMS))) {
            $result = in_array($transaction_status, array(
                self::STATUS_INSIDE_FORM_URL_SENT, //gw side form
                self::STATUS_MPI_URL_GENERATED, //3d
                self::STATUS_HOLD_OK,
                self::STATUS_SUCCESS
            ));
        } else {
            $result = in_array($transaction_status, array(
                self::STATUS_INSIDE_FORM_URL_SENT, //gw side form
                self::STATUS_MPI_URL_GENERATED, //3d
                self::STATUS_SUCCESS
            ));
        }

        return $result;
    }

    /**
     * @param float $value
     * @param int $power
     * @return int
     */
    public function lowestDenomination($value, $power = 2)
    {
        $value = (float)$value;
        return (int)($value * pow(10, $power));
    }

    /**
     * @param int $value
     * @param int $power
     * @return float
     */
    public function standardDenomination($value, $power = 2)
    {
        $value = (int) $value;
        return (float) ($value / pow(10, $power));
    }

    /**
     * @param Gateway $gw
     * @param int $payment_method
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createTransaction($gw, $payment_method, $data)
    {
        $endpoint_name = 'create'.$this->getPaymentMethodInternalName($payment_method);

        if (!method_exists($gw, $endpoint_name)) {
            throw new \Exception('Unexpected payment method.', 500);
        }

        /**
         * @var Sms|DmsHold|Credit|P2P $endpoint
         */
        $endpoint = $gw->{$endpoint_name}();

        $this->setCustomerData($endpoint->customer(), $data['customer'] ?? []);
        $this->setOrderData($endpoint->order(), $data['order'] ?? []);
        $this->setSystemData($endpoint->system(), $data['system'] ?? []);
        $this->setMoneyData($endpoint->money(), $data['money'] ?? []);
        $this->setPaymentMethodData($endpoint->paymentMethod(), $data['payment_method'] ?? []);

        return $this->processEndpoint($gw, $endpoint);
    }

    /**
     * @param Gateway $gw
     * @param string $transaction_id
     * @param int $amount
     * @return array
     */
    public function refundTransaction(Gateway $gw, $transaction_id, $amount)
    {
        $refund = $gw->createRefund();
        $refund->command()->setGatewayTransactionID($transaction_id);
        $refund->money()->setAmount($amount);

        return $this->processEndpoint($gw, $refund);
    }

    /**
     * @param Gateway $gw
     * @param string $transaction_id
     * @param int $amount
     * @return array
     */
    public function chargeDmsHoldTransaction(Gateway $gw, $transaction_id, $amount)
    {
        $charge = $gw->createDmsCharge();
        $charge->command()->setGatewayTransactionID($transaction_id);
        $charge->money()->setAmount($amount);

        return $this->processEndpoint($gw, $charge);
    }

    /**
     * @param Gateway $gw
     * @param string $transaction_id
     * @param int $payment_method
     * @return array
     */
    public function cancelTransaction(Gateway $gw, $transaction_id, $payment_method)
    {
        if((int)$payment_method === self::METHOD_DMS) {
            $result = $this->cancelDmsHoldTransaction($gw, $transaction_id);
        } else {
            $result = $this->reverseTransaction($gw, $transaction_id);
        }

        return $result;
    }

    /**
     * @param string $url
     * @return Gateway
     */
    public function createGateway(string $url) : Gateway
    {
        return new Gateway($url);
    }

    /**
     * @param Gateway $gw
     * @param string $account_id
     * @param string $secret_key
     * @return TransactproService
     */
    public function auth(Gateway $gw, string $account_id, string $secret_key): self
    {
        $gw->auth()
            ->setAccountId($account_id)
            ->setSecretKey($secret_key);

        return $this;
    }

    /**
     * @param Gateway $gw
     * @param Operation $endpoint
     * @return array
     * @throws Exception
     */
    private function processEndpoint(Gateway $gw, Operation $endpoint)
    {
        $request = $gw->generateRequest($endpoint);
        $response = $gw->process($request);

        $json = json_decode($response->getBody(), true);
        $json_status = json_last_error();

        if (JSON_ERROR_NONE !== $json_status) {
            throw new \Exception('JSON ' . json_last_error_msg(), $json_status);
        }

        return $json;
    }

    private function cancelDmsHoldTransaction(Gateway $gw, $transaction_id)
    {
        $cancel = $gw->createCancel();
        $cancel->command()->setGatewayTransactionID($transaction_id);

        return $this->processEndpoint($gw, $cancel);
    }

    /**
     * @param Gateway $gw
     * @param $transaction_id
     * @return array
     */
    private function reverseTransaction(Gateway $gw, $transaction_id)
    {
        $reverse = $gw->createReversal();
        $reverse->command()->setGatewayTransactionID($transaction_id);

        return $this->processEndpoint($gw, $reverse);
    }

    /**
     * @param Customer $object
     * @param array $data
     */
    private function setCustomerData($object, $data)
    {
        $this->setTransactionData($object, array_merge(self::DEFAULT_CUSTOMER_DATA, $data));
    }

    /**
     * @param Order $object
     * @param array $data
     */
    private function setOrderData($object, $data)
    {
        $this->setTransactionData($object, array_merge(self::DEFAULT_ORDER_DATA, $data));
    }

    /**
     * @param System $object
     * @param array $data
     */
    private function setSystemData($object, $data)
    {
        $this->setTransactionData($object, array_merge(self::DEFAULT_SYSTEM_DATA, $data));
    }

    /**
     * @param Money $object
     * @param array $data
     */
    private function setMoneyData($object, $data)
    {
        $this->setTransactionData($object, array_merge(self::DEFAULT_MONEY_DATA, $data));
    }

    /**
     * @param PaymentMethod $object
     * @param array $data
     */
    private function setPaymentMethodData($object, $data)
    {
        $this->setTransactionData($object, array_merge(self::DEFAULT_PAYMENT_METHOD_DATA, $data));
    }

    /**
     * @param Customer|Order|System|Money|PaymentMethod $object
     * @param array $data
     */
    private function setTransactionData($object, $data)
    {
        foreach ($data as $key => $value) {
            $name = 'set'.str_replace('_', '', ucwords($key, '_'));

            if (method_exists($object, $name)) {
                $object->{$name}($value);
            }
        }
    }

    /**
     * @param int $payment_method
     * @return mixed|string
     */
    private function getPaymentMethodInternalName($payment_method)
    {
        $result = self::METHOD_UNKNOWN_NAME;

        $method_names = array(
            self::METHOD_SMS => 'Sms',
            self::METHOD_DMS => 'DmsHold',
            self::METHOD_CREDIT => 'Credit',
            self::METHOD_P2P => 'P2P',
        );

        if (array_key_exists($payment_method, $method_names)) {
            $result = $method_names[$payment_method];
        }

        return $result;
    }
}