<?php
/**
 * Transact Pro for Prestashop 1.7.x
 *
 * @author    Transact Pro
 * @copyright 2017
 */

class Transact_ProValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @var Transact_pro
     */
    public $module;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;

        if ($cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || !$this->module->active
            || !$this->module->checkSupportedCurrencies()
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check if this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'transact_pro') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = Context::getContext()->currency;
        $total = (float)number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');

        $this->moveForward($cart, $total, $currency);
    }

    /**
     * @param Cart $cart
     * @param float $total
     * @param Currency $currency
     */
    private function moveForward($cart, $total, $currency) {
        $isGatewayPaymentSide = $this->module->getPaymentCapture() === TransactproService::PAYMENT_SIDE_GATEWAY;

        if($isGatewayPaymentSide) {
            $this->moveToGateway($cart, $currency);
        } else {
            $this->validateOrder($cart, $total, $currency);

            $order_id = $this->module->currentOrder;
            $link = new Link();

            Tools::redirect($link->getModuleLink('transact_pro', 'process', array('order_id' => $order_id)));
        }
    }

    /**
     * @param Cart $cart
     * @param Currency $currency
     */
    private function moveToGateway($cart, $currency) {
        $data = DataExtractionHelper::extractPaymentData($cart, $currency);
        $total = $data['amount'];

        $response = $this->module->makeGatewayRequest($data);

        if ($response) {
            if ($response['is_success'] && isset($response['gw']['redirect-url'])) {
                PrestaShopLogger::addLog('Transact Pro: Transaction "'.$response['gw']['gateway-transaction-id']
                    .'" was successful', 1, null, null, null, true);

                $this->validateOrder($cart, $total, $currency);

                $order = new Order($this->module->currentOrder);
                $this->module->savePayment($response, $order->id, $total, $currency->iso_code);
                $this->module->approveOrderPayment($order->id, $response);

                $this->context->cookie->processing_transaction_guid = $response['gw']['gateway-transaction-id'];
                header('Location: '.$response['gw']['redirect-url']);
                exit;
            } else {
                $this->module->savePayment($response, null, $total, $currency->iso_code);
                PrestaShopLogger::addLog('Transact Pro: Transaction "'.$response['gw']['gateway-transaction-id']
                    .'" was failed', 1, null, null, null, true);
            }
        }

        Tools::redirect($this->context->link->getPageLink('order', true, null, array('step' => 3, 'transact_pro_failed' => true)));
    }

    /**
     * @param Cart $cart
     * @param float $total
     * @param Currency $currency
     */
    private function validateOrder($cart, $total, $currency) {
        $this->module->validateOrder($cart->id, Configuration::get(Transact_pro::TRANSACT_PRO_PENDING), $total,
            $this->module->displayName, null, null, $currency->id);
    }
}
