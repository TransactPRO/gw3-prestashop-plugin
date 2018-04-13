<?php
/**
 * Transact Pro for Prestashop 1.7.x
 *
 * @author    Transact Pro
 * @copyright 2018
 */

class Transact_ProCallbackModuleFrontController extends ModuleFrontController
{
    /**
     * @var Transact_pro
     */
    public $module;

    public function initContent() {

        parent::initContent();
        $isSuccess = false;

        if (Tools::isSubmit('json')) {
            $json = json_decode(html_entity_decode(Tools::getValue('json')), true);

            $json_status = json_last_error();
            if (JSON_ERROR_NONE == $json_status && isset($json['result-data']['gw']['gateway-transaction-id'])
                && isset($json['result-data']['gw']['status-code'])) {

                $transaction_guid = $json['result-data']['gw']['gateway-transaction-id'];
                $transaction_status = (int)$json['result-data']['gw']['status-code'];

                $transaction = TransactionRepository::getTransaction($transaction_guid);

                $isSuccess = $this->processTransaction($transaction, $transaction_status);
            }
        }

        if (!$isSuccess) {
            PrestaShopLogger::addLog('Transact pro callback was failed. $_POST='.substr(print_r($_POST, true), 0, 500), 1, 500, null, null, true);
        }

        exit;
    }

    /**
     * @param array $transaction
     * @param int $transaction_status
     * @return bool
     */
    private function processTransaction($transaction, $transaction_status) {
        $result = false;

        if ($transaction) {
            $orderId = $transaction['order_id'];
            $transactionGuid = $transaction['transaction_guid'];
            $paymentMethod = (int)$transaction['payment_method'];
            TransactionRepository::updateTransactionStatus($transaction['id'], $transaction_status);

            $isSuccess = TransactproService::getInstance()->isSuccessTransaction($paymentMethod, $transaction_status);

            if ($paymentMethod !== TransactproService::METHOD_DMS || !$isSuccess) {
                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState((int)Configuration::get($isSuccess ? 'PS_OS_PAYMENT' : 'PS_OS_ERROR'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId,
                ));
            }

            PrestaShopLogger::addLog('Transact pro callback for transaction "'.$transactionGuid.'" was '
                .($isSuccess ? 'successful' : 'failed').'.', 1, 200, null, null, true);

            $result = $isSuccess;
        }

        return $result;
    }
}
