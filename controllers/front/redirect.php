<?php
/**
 * Transact Pro for Prestashop 1.7.x
 *
 * @author    Transact Pro
 * @copyright 2018
 */

class Transact_ProRedirectModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        if(isset($this->context->cookie->processing_transaction_guid) && $this->context->cookie->processing_transaction_guid) {
            $transaction = TransactionRepository::getTransaction($this->context->cookie->processing_transaction_guid);
            $this->context->cookie->processing_transaction_guid = null;

            if($transaction && TransactproService::getInstance()
                ->isSuccessTransaction($transaction['payment_method'], $transaction['transaction_status'])) {

                $orderId = $transaction['order_id'];
                $order = new Order($orderId);
                $cart = new Cart($order->id_cart);
                $customer = new Customer($cart->id_customer);

                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart=' . $cart->id .
                    '&id_module=' . $this->module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key
                );
            }
        }

        $this->setTemplate('module:transact_pro/views/templates/front/payment_redirect_error.tpl');
    }
}
