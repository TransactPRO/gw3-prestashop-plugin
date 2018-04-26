<?php
/**
 * Transact Pro for Prestashop 1.7.x
 *
 * @author    Transact Pro
 * @copyright 2018
 */

class Transact_ProProcessModuleFrontController extends ModuleFrontController
{
    /**
     * @var Transact_pro
     */
    public $module;

    public $ssl = true;
    public $display_column_left = false;
    public $display_column_right = false;
    public $errors = array();

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $order_id = (int)Tools::getValue('order_id');
        $order = new Order($order_id);
        $isGatewayPaymentSide = $this->module->getPaymentCapture() === TransactproService::PAYMENT_SIDE_GATEWAY;

        if ($this->module->validateOrderPayment($order) && !$isGatewayPaymentSide) {
            $currency = new Currency($order->id_currency);
            $cart = new Cart($order->id_cart);
            $customer = new Customer($cart->id_customer);

            if (Tools::getValue('make_payment')) {
                $data = $this->extractPaymentData($cart, $currency);
                $total = $data['amount'];
                $response = $this->module->makeGatewayRequest($data);

                if ($response) {
                    $this->module->savePayment($response, $order->id, $total, $currency->iso_code);

                    if ($response['is_success']) {
                        $this->module->approveOrderPayment($order->id, $response);

                        PrestaShopLogger::addLog('Transact Pro: Transaction "'.$response['gw']['gateway-transaction-id']
                            .'" was successful', 1, null, null, null, true);

                        if(isset($response['gw']['redirect-url'])) {
                            $this->context->cookie->processing_transaction_guid = $response['gw']['gateway-transaction-id'];
                            header('Location: '.$response['gw']['redirect-url']);
                            exit;
                        } else {
                            Tools::redirect(
                                'index.php?controller=order-confirmation&id_cart=' . $cart->id .
                                '&id_module=' . $this->module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key
                            );
                        }
                    }

                    PrestaShopLogger::addLog('Transact Pro: Transaction "'.$response['gw']['gateway-transaction-id']
                        .'" was failed', 1, null, null, null, true);

                    if(isset($response['error']['message'])) {
                        $this->errors[] = $this->module->l($response['error']['message'], 'process');
                    } else {
                        $this->errors[] = $this->module->l('Payment failed.', 'process');
                    }
                } else {
                    $this->errors[] = $this->module->l('Unexpected error.', 'process');
                }
            }

            // Render form
            $this->context->smarty->assign(array(
                'currency' => $currency,
                'orderId' => $order->id,
                'total' => $cart->getOrderTotal(true, Cart::BOTH),
                'this_path' => $this->module->getPathUri(),
                'this_path_bw' => $this->module->getPathUri(),
                'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/',
                'errors' => $this->errors,
                'input' => $_POST
            ));

            $this->context->controller->addJS(__PS_BASE_URI__ . 'modules/transact_pro/views/js/jquery/jquery.payment.js');
            $this->context->controller->addJS(__PS_BASE_URI__ . 'modules/transact_pro/views/js/transact-pro.js');

            $this->setTemplate('module:transact_pro/views/templates/front/payment_process.tpl');
        } else {
            $this->setTemplate('module:transact_pro/views/templates/front/payment_process_error.tpl');
        }
    }

    /**
     * @param Cart $cart
     * @param Currency $currency
     * @return array
     */
    private function extractPaymentData($cart, $currency) {
        $total = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
        $data = array(
            'amount' => $total,
            'currency' => $currency->iso_code
        );

        $data['payment_instrument'] = array(
            'pan' => strip_tags(str_replace(' ', '', Tools::getValue('card_pan'))),
            'exp_year' => (int)Tools::getValue('expiration_year'),
            'exp_month' => (int)Tools::getValue('expiration_month'),
            'cvc' => strip_tags(trim(Tools::getValue('cvc'))),
            'holder' => strip_tags(trim(Tools::getValue('card_holder')))
        );

        $data['customer'] = $this->extractCustomerData($cart);

        return $data;
    }

    /**
     * @param Cart $cart
     * @return array
     */
    private function extractCustomerData($cart) 
    {
        $defaultValue = ' ';

        $shippingAddress = new Address(intval($cart->id_address_delivery));
        $shippingCountry = new Country(intval($shippingAddress->id_country));

        $billingAddress = new Address(intval($cart->id_address_invoice));
        $billingCountry = new Country(intval($billingAddress->id_country));

        return array(
            'phone' => $this->sanitize($shippingAddress->phone) ?: $defaultValue,
            'billing_address_country' => $this->sanitize($billingCountry->iso_code),
            'billing_address_city' => $this->sanitize($billingAddress->city) ?: $defaultValue,
            'billing_address_street' => $this->sanitize($billingAddress->address1) ?: $defaultValue,
            'billing_address_house' => $this->sanitize($billingAddress->address2) ?: $defaultValue,
            'billing_address_ZIP' => $this->sanitize($billingAddress->postcode) ?: $defaultValue,
            'shipping_address_country' => $this->sanitize($shippingCountry->iso_code),
            'shipping_address_city' => $this->sanitize($shippingAddress->city) ?: $defaultValue,
            'shipping_address_street' => $this->sanitize($shippingAddress->address1) ?: $defaultValue,
            'shipping_address_house' => $this->sanitize($shippingAddress->address2) ?: $defaultValue,
            'shipping_address_ZIP' => $this->sanitize($shippingAddress->postcode) ?: $defaultValue
        );
    }

    /**
     * @param string $value
     * @return string
     */
    private function sanitize($value) 
    {
        return preg_replace('/[^\w\d\s]/', ' ', (string) $value);
    }
}
