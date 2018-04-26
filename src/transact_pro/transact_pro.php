<?php
/**
 * Transact Pro for Prestashop 1.7.x
 *
 * @author    Transact Pro
 * @copyright 2018
 */

include_once(_PS_MODULE_DIR_ . 'transact_pro/libraries/gw3/vendor/autoload.php');
include_once(_PS_MODULE_DIR_ . 'transact_pro/services/transactpro_service.php');
include_once(_PS_MODULE_DIR_ . 'transact_pro/repositories/transactionRepository.php');

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use TransactPro\Gateway\Gateway;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Transact_pro extends PaymentModule
{
    /**
     * @var null|HelperList
     */
    private $helperList = null;
    /**
     * @var null|TransactproService
     */
    private $transactproService = null;

    private $gatewayUrl;
    private $accountId;
    private $secretKey;
    private $paymentMethod;
    private $paymentCapture;

    const SUPPORTED_CURRENCIES = array('EUR');

    const CONFIG_ACCOUNT_ID = 'CONFIG_ACCOUNT_ID';
    const CONFIG_SECRET_KEY = 'CONFIG_SECRET_KEY';
    const CONFIG_GATEWAY_URL = 'CONFIG_GATEWAY_URL';
    const CONFIG_PAYMENT_METHOD = 'CONFIG_PAYMENT_METHOD';
    const CONFIG_PAYMENT_CAPTURE = 'CONFIG_PAYMENT_CAPTURE';

    const TRANSACT_PRO_PENDING = 'TRANSACT_PRO_PENDING';

    public function __construct()
    {
        $this->name = 'transact_pro';
        $this->tab = 'payments_gateways';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->version = '1.0.0';
        $this->author = 'Transact Pro';

        $config = Configuration::getMultiple(array(
            self::CONFIG_GATEWAY_URL,
            self::CONFIG_ACCOUNT_ID,
            self::CONFIG_SECRET_KEY,
            self::CONFIG_PAYMENT_METHOD,
            self::CONFIG_PAYMENT_CAPTURE
        ));

        $this->gatewayUrl = (isset($config[self::CONFIG_GATEWAY_URL])) ? $config[self::CONFIG_GATEWAY_URL] : null;
        $this->accountId = (isset($config[self::CONFIG_ACCOUNT_ID])) ? $config[self::CONFIG_ACCOUNT_ID] : null;
        $this->secretKey = (isset($config[self::CONFIG_SECRET_KEY])) ? $config[self::CONFIG_SECRET_KEY] : null;
        $this->paymentMethod = (isset($config[self::CONFIG_PAYMENT_METHOD])) ? (int)$config[self::CONFIG_PAYMENT_METHOD] : null;
        $this->paymentCapture = (isset($config[self::CONFIG_PAYMENT_CAPTURE])) ? (int)$config[self::CONFIG_PAYMENT_CAPTURE] : null;

        $this->bootstrap = true;
        $this->transactproService = TransactproService::getInstance();

        parent::__construct();

        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Transact Pro');
        $this->description = $this->l('Accept different types of payments on your website with Transact Pro.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency set for this module');
        }
    }

    /**
     * @return bool
     */
    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn') || !$this->registerHook('displayPaymentTop') || !$this->createTable()) {
            return false;
        }

        $order_pending = new OrderState();
        $order_pending->module_name = $this->name;
        foreach (Language::getLanguages() as $language) {
            $order_pending->name[$language['id_lang']] = 'Awaiting Credit Card Payment';
        }
        $order_pending->send_email = 0;
        $order_pending->invoice = 0;
        $order_pending->color = '#4169E1';
        $order_pending->unremovable = false;
        $order_pending->logable = 0;

        $order_pending->add();

        Configuration::updateValue(self::TRANSACT_PRO_PENDING, $order_pending->id);

        return true;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        $order_state_pending = new OrderState(Configuration::get(self::TRANSACT_PRO_PENDING));

        return (
            Configuration::deleteByName(self::CONFIG_GATEWAY_URL)
            && Configuration::deleteByName(self::CONFIG_ACCOUNT_ID)
            && Configuration::deleteByName(self::CONFIG_SECRET_KEY)
            && Configuration::deleteByName(self::CONFIG_PAYMENT_METHOD)
            && Configuration::deleteByName(self::CONFIG_PAYMENT_CAPTURE)
            && Configuration::deleteByName(self::TRANSACT_PRO_PENDING)
            && $order_state_pending->delete()
            && parent::uninstall()
        );
    }

    /**
     * @return string
     */
    public function hookDisplayPaymentTop()
    {
        $result = '';

        if (Tools::getValue('transact_pro_failed')) {
            $result = $this->displayError('Unexpected error.');
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        $output = '';

        switch(Tools::getValue('action')) {
            case 'refund_transaction':
                $output .= $this->postProcessRefundAction();
                break;
            case 'refund_transaction_submit':
                $output .= $this->postProcessRefundSubmitAction();
                break;
            case 'refund_success':
                $output .= $this->displayConfirmation($this->l('Transaction '.Tools::getValue('transaction_guid').' was refunded.'));
                $output .= $this->renderSettingsForm();
                break;
            case 'cancel_transaction':
                $output .= $this->postProcessTransactionCancel();
                break;
            case 'cancel_success':
                $output .= $this->displayConfirmation($this->l('Transaction '.Tools::getValue('transaction_guid').' was cancelled.'));
                $output .= $this->renderSettingsForm();
                break;
            case 'charge_transaction':
                $output .= $this->postProcessTransactionCharge();
                break;
            case 'charge_transaction_success':
                $output .= $this->displayConfirmation($this->l('Transaction '.Tools::getValue('transaction_guid').' was charged.'));
                $output .= $this->renderSettingsForm();
                break;
            default:
                $output .= $this->postProcessDefaultAdminAction();
                break;
        }

        return $output.$this->renderTransactionsList();
    }

    /**
     * @return string
     */
    protected function renderTransactionsList()
    {
        $helper = new HelperList();

        $helper->module = $this;
        $helper->title = $this->l('Transactions');
        $helper->table = $this->name;
        $helper->no_link = true;
        $helper->identifier = 'transaction_guid';
        $helper->actions = array('cancel', 'refund', 'charge');
        $helper->shopLinkType = '';

        $values = $this->getTransactionsListValues();
        $helper->listTotal = count($values['rows']);
        $helper->list_skip_actions = [
            'cancel' => $values['unableToCancel'],
            'refund' => $values['unableToRefund'],
            'charge' => $values['unableToCharge']
        ];

        $helper->tpl_vars = array('show_filters' => false);
        $helper->currentIndex = $this->getModuleConfigUrl();

        $this->helperList = $helper;

        return $helper->generateList($values['rows'], $this->getTransactionsListStructure());
    }

    /**
     * @return array
     */
    public function getTransactionsListStructure()
    {
        return array(
            'id' => array('title' => $this->l('ID'), 'type' => 'text', 'orderby' => false),
            'transaction_guid' => array('title' => $this->l('GUID'), 'type' => 'text', 'orderby' => false),
            'order_id' => array('title' => $this->l('Order ID'), 'type' => 'text', 'orderby' => false),
            'transaction_status' => array('title' => $this->l('Status'), 'type' => 'text', 'orderby' => false),
            'payment_method' => array('title' => $this->l('Method'), 'type' => 'text', 'orderby' => false),
            'transaction_amount' => array('title' => $this->l('Amount'), 'type' => 'text', 'orderby' => false),
            'transaction_currency' => array('title' => $this->l('Currency'), 'type' => 'text', 'orderby' => false),
            'device_ip' => array('title' => $this->l('Customer IP'), 'type' => 'text', 'orderby' => false),
            'is_refunded' => array('title' => $this->l('Is refunded'), 'type' => 'bool', 'align' => 'center', 'orderby' => false),
            'created_at' => array('title' => $this->l('Creation date'), 'type' => 'text', 'orderby' => false),
        );
    }

    /**
     * @return array
     */
    public function getTransactionsListValues()
    {
        $transactions = TransactionRepository::getTransactions();
        $unableToCancel = array();
        $unableToRefund = array();
        $unableToCharge = array();

        foreach ($transactions as $key => &$transaction) {
            if (!$this->transactproService->canCancelTransaction($transaction['payment_method'], $transaction['transaction_status'])) {
                $unableToCancel[] = $transaction['transaction_guid'];
            }

            if (!$this->transactproService->canRefundTransaction($transaction['transaction_status'])) {
                $unableToRefund[] = $transaction['transaction_guid'];
            }

            if (!$this->transactproService->canChargeTransaction($transaction['transaction_status'])) {
                $unableToCharge[] = $transaction['transaction_guid'];
            }

            $transaction['payment_method'] = $this->transactproService->getPaymentMethodName($transaction['payment_method']);
            $transaction['transaction_status'] = $this->transactproService->getTransactionStatusName($transaction['transaction_status']);
            $transaction['created_at'] = date('Y-m-d H:i:s', strtotime($transaction['created_at']));
        }

        return array(
            'rows' => $transactions,
            'unableToCancel' => $unableToCancel,
            'unableToRefund' => $unableToRefund,
            'unableToCharge' => $unableToCharge
        );
    }

    /**
     * @param string|null $token
     * @param string $id
     * @param string|null $name
     * @return string
     */
    public function displayCancelLink($token = null, $id, $name = null)
    {
        $this->smarty->assign(array(
            'href' => $this->helperList->currentIndex.'&action=cancel_transaction&'.$this->helperList->identifier
                .'='.$id,
            'action' => $this->trans('Cancel', array()),
        ));

        return $this->display(__FILE__, 'views/templates/admin/list_action_cancel_transaction.tpl');
    }

    /**
     * Display refund action link
     */
    public function displayRefundLink($token = null, $id, $name = null)
    {
        $this->smarty->assign(array(
            'href' => $this->helperList->currentIndex.'&action=refund_transaction&'.$this->helperList->identifier
                .'='.$id,
            'action' => $this->trans('Refund', array()),
        ));

        return $this->display(__FILE__, 'views/templates/admin/list_action_refund_transaction.tpl');
    }

    /**
     * Display charge action link
     */
    public function displayChargeLink($token = null, $id, $name = null)
    {
        $this->smarty->assign(array(
            'href' => $this->helperList->currentIndex.'&action=charge_transaction&'.$this->helperList->identifier
                .'='.$id,
            'action' => $this->trans('Charge', array()),
        ));

        return $this->display(__FILE__, 'views/templates/admin/list_action_charge_transaction.tpl');
    }

    /* Renders admin module configuration form */
    protected function renderSettingsForm()
    {
        $form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs'
                ),
                'description' => $this->l('Please, enter your Transact Pro credentials.'),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Gateway URL'),
                        'name' => 'gateway_url',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Account ID'),
                        'name' => 'account_id',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Secret Key'),
                        'name' => 'secret_key',
                        'required' => true
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Payment Method'),
                        'name' => 'payment_method',
                        'options' => array(
                            'query' => array(
                                array('id' => TransactproService::METHOD_SMS, 'name' => $this->l('SMS')),
                                array('id' => TransactproService::METHOD_DMS, 'name' => $this->l('DMS')),
                                array('id' => TransactproService::METHOD_CREDIT, 'name' => $this->l('Credit')),
                                array('id' => TransactproService::METHOD_P2P, 'name' => $this->l('P2P')),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        ),
                        'required' => true
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Payment Information Capture'),
                        'name' => 'payment_capture',
                        'options' => array(
                            'query' => array(
                                array('id' => TransactproService::PAYMENT_SIDE_MERCHANT, 'name' => $this->l('Merchant Side')),
                                array('id' => TransactproService::PAYMENT_SIDE_GATEWAY, 'name' => $this->l('Payment Gateway Side')),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        ),
                        'required' => true
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->getModuleConfigUrl();
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($form));
    }

    /**
     * @return string
     */
    protected function renderRefundForm()
    {
        $transaction = $this->getTransaction(Tools::getValue('transaction_guid'));

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Refund Transaction'),
                    'icon' => 'icon-undo'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Refund Amount'),
                        'name' => 'refund_amount',
                        'desc' => $this->l('Full refund is: ').$transaction['transaction_amount'].' '.$transaction['transaction_currency'],
                        'required' => true
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Refund Reason'),
                        'name' => 'refund_reason',
                        'rows' => 5,
                        'required' => false
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Refund'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->getModuleConfigUrl().'&action=refund_transaction_submit&transaction_guid='
            .Tools::getValue('transaction_guid');
        $helper->tpl_vars = array(
            'fields_value' => array(
                'refund_amount' => Tools::getValue('refund_amount'),
                'refund_reason' => Tools::getValue('refund_reason')
            ),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
            'back_url' => $this->getModuleConfigUrl(),
            'show_cancel_button' => true
        );

        return $helper->generateForm(array($fields_form));
    }

    /**
     * @return string
     */
    protected function postProcessDefaultAdminAction() {
        $html = '';

        if (Tools::isSubmit('btnSubmit')) {
            if ($this->validatePostRequest()) {
                $html .= $this->processPostRequest();
            } else {
                $html .= $this->displayError($this->l('All fields are required!'));
            }
        }

        $html .= $this->renderSettingsForm();

        return $html;
    }

    /**
     * @return string
     */
    protected function postProcessTransactionCharge() {
        $transaction = $this->getTransaction(Tools::getValue('transaction_guid'));
        $html = '';

        if ($this->transactproService->canChargeTransaction($transaction['transaction_status'])) {
            $gw = $this->initGateway();
            $response = $this->transactproService->chargeDmsHoldTransaction($gw, $transaction['transaction_guid'], $transaction['payment_method']);

            $transaction_status = $response['gw']['status-code'] ?? null;
            if (TransactproService::STATUS_SUCCESS === $transaction_status) {
                $this->updateTransactionStatus($transaction['id'], $transaction_status);
                $orderId = $transaction['order_id'];

                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId,
                ));

                Tools::redirect($this->getModuleConfigUrl().'&action=charge_transaction_success&transaction_guid='.$transaction['transaction_guid']);
            } else {
                $reason = $response['error']['message'] ?? 'UNKNOWN';
                $html .= $this->displayError($this->l('Transaction charging failed. Reason: '.$reason));
            }
        } else {
            $html .= $this->displayError($this->l('Cannot charge transaction with status "'
                .$this->transactproService->getTransactionStatusName($transaction['transaction_status']).'".'));
        }

        return $html.$this->renderSettingsForm();
    }

    /**
     * @return string
     */
    protected function postProcessTransactionCancel() {
        $transaction = $this->getTransaction(Tools::getValue('transaction_guid'));
        $html = '';

        if ($this->transactproService->canCancelTransaction($transaction['payment_method'], $transaction['transaction_status'])) {
            $gw = $this->initGateway();
            $response = $this->transactproService->cancelTransaction($gw, $transaction['transaction_guid'], $transaction['payment_method']);

            $transaction_status = $response['gw']['status-code'] ?? null;
            if (TransactproService::STATUS_REVERSED == $transaction_status || TransactproService::STATUS_DMS_CANCEL_OK == $transaction_status) {
                $this->updateTransactionStatus($transaction['id'], $transaction_status);
                $orderId = $transaction['order_id'];

                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState(Configuration::get('PS_OS_CANCELED'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId,
                ));

                Tools::redirect($this->getModuleConfigUrl().'&action=cancel_success&transaction_guid='.$transaction['transaction_guid']);
            } else {
                $reason = $response['error']['message'] ?? 'UNKNOWN';
                $html .= $this->displayError($this->l('Transaction cancelling failed. Reason: '.$reason));
            }

        } else {
            $html .= $this->displayError($this->l('Cannot cancel transaction with status "'
                .$this->transactproService->getTransactionStatusName($transaction['transaction_status'])
                .'" and payment method "'.$this->transactproService->getPaymentMethodName($transaction['payment_method']).'".'));
        }

        return $html.$this->renderSettingsForm();
    }

    /**
     * @return string
     */
    protected function postProcessRefundAction() {
        $transaction = $this->getTransaction(Tools::getValue('transaction_guid'));
        $html = '';

        if(!$this->transactproService->canRefundTransaction($transaction['transaction_status'])) {
            $html .= $this->displayError($this->l('Cannot refund transaction with status "'
                .$this->transactproService->getTransactionStatusName($transaction['transaction_status']).'".'));
            $html .= $this->renderSettingsForm();
        } else {
            $html .= $this->renderRefundForm();
        }

        return $html;
    }

    /**
     * @return string
     */
    protected function postProcessRefundSubmitAction() {
        $html = '';
        $amountString = Tools::getValue('refund_amount');

        if($amountString) {
            $amountString = preg_replace('~[^0-9\.\,]~', '', $amountString);
            $gw = $this->initGateway();
            $transaction = $this->getTransaction(Tools::getValue('transaction_guid'));
            $reason = Tools::getValue('refund_reason');

            if (strpos($amountString, ',') !== FALSE && strpos($amountString, '.') !== FALSE) {
                $amount = (float) str_replace(',', '', $amountString);
            } else if (strpos($amountString, ',') !== FALSE && strpos($amountString, '.') === FALSE) {
                $amount = (float) str_replace(',', '.', $amountString);
            } else {
                $amount = (float) $amountString;
            }

            $refund_amount = $this->transactproService->lowestDenomination($amount);
            $response = $this->transactproService->refundTransaction($gw, $transaction['transaction_guid'], $refund_amount);

            $status = $response['gw']['status-code'] ?? null;

            $refunds = array();

            if (! empty($transaction['refunds'])) {
                $refunds = json_decode($transaction['refunds'], true);
            }

            $refunds[] = array(
                'transaction_guid' => $transaction['transaction_guid'],
                'transaction_status' => $status,
                'amount' => $amount,
                'reason' => $reason,
                'created_at' => date('Y-m-d H:i:s')
            );

            $this->updateTransactionRefunds($transaction['id'], $refunds);

            if ($status == TransactproService::STATUS_REFUND_SUCCESS) {
                $orderId = $transaction['order_id'];

                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState(Configuration::get('PS_OS_REFUND'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId,
                ));

                Tools::redirect($this->getModuleConfigUrl().'&action=refund_success&transaction_guid='.$transaction['transaction_guid']);
            } else {
                $reason = $response['error']['message'] ?? 'UNKNOWN';
                $html .= $this->displayError($this->l('Refund failed. Reason: '.$reason));
            }
        } else {
            $html .= $this->displayError($this->l('Please enter refund amount.'));
        }

        $html .= $this->renderRefundForm();

        return $html;
    }

    /**
     * @return array
     */
    public function getConfigFieldsValues()
    {
        return array(
            'gateway_url' => Configuration::get(self::CONFIG_GATEWAY_URL),
            'account_id' => Configuration::get(self::CONFIG_ACCOUNT_ID),
            'secret_key' => Configuration::get(self::CONFIG_SECRET_KEY),
            'payment_method' => Configuration::get(self::CONFIG_PAYMENT_METHOD) ?: TransactproService::METHOD_SMS,
            'payment_capture' => Configuration::get(self::CONFIG_PAYMENT_CAPTURE) ?: TransactproService::PAYMENT_SIDE_MERCHANT
        );
    }

    /**
     * @param int|null $id_shop
     * @return array|false|mysqli_result|null|PDOStatement|resource
     */
    public function getSupportedCurrencies($id_shop = null)
    {
        $id_shop = Context::getContext()->shop->id;

        $sql = 'SELECT c.*
                FROM `' . _DB_PREFIX_ . 'module_currency` mc
                LEFT JOIN `' . _DB_PREFIX_ . 'currency` c ON c.`id_currency` = mc.`id_currency`
                WHERE c.`deleted` = 0
                    AND mc.`id_module` = ' . (int)$this->id . '
                    AND c.`active` = 1
                    AND mc.id_shop = ' . (int)$id_shop . '
                    AND c.`iso_code` IN ("' . implode('", "', self::SUPPORTED_CURRENCIES) . '")
                ORDER BY c.`name` ASC';

        return Db::getInstance()->executeS($sql);
    }

    /**
     * @param string $guid
     * @return array
     */
    public function getTransaction($guid)
    {
        return TransactionRepository::getTransaction($guid);
    }

    /**
     * @return bool
     */
    public function checkSupportedCurrencies()
    {
        return !empty($this->getSupportedCurrencies());
    }

    /**
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function makeGatewayRequest($data)
    {
        $response = [];

        try {
            $response = $this->makeTransaction($data);
            $response['is_success'] = $this->transactproService
                ->isSuccessTransaction($this->paymentMethod, $response['gw']['status-code'] ?? -1);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Transact Pro: '.$e->getMessage(), 1, $e->getCode(), null, null, true);
        }

        return $response;
    }

    /**
     * @param Order $order
     * @return bool
     */
    public function validateOrderPayment($order)
    {
        $state = $order->getCurrentState();

        if ($order->id
            && $order->module == $this->name
            && $this->context->cookie->id_customer == $order->id_customer
            && !$order->valid
            && $state != (int)Configuration::get('PS_OS_CANCELED')
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param array $response
     * @param int $order_id
     * @param float $amount
     * @param string $currency
     * @return bool
     */
    public function savePayment($response, $order_id, $amount, $currency)
    {
        $transaction_guid = $response['gw']['gateway-transaction-id'];
        $transaction_status = (int)$response['gw']['status-code'];
        $payment_method = (int)$this->paymentMethod;
        $user_ip = $_SERVER['REMOTE_ADDR'];

        return TransactionRepository::add($transaction_guid, $order_id, $transaction_status, $payment_method, $amount, $currency, $user_ip);
    }

    /**
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function updateTransactionStatus($id, $status)
    {
        return TransactionRepository::updateTransactionStatus($id, $status);
    }

    /**
     * @param int $id
     * @param array $refunds
     * @return bool
     */
    public function updateTransactionRefunds($id, $refunds)
    {
        return TransactionRepository::updateTransactionRefunds($id, $refunds);
    }

    /**
     * @param int $orderId
     * @param array $response
     */
    public function approveOrderPayment($orderId, $response)
    {
        $transaction_status = (int)$response['gw']['status-code'];
        $isPayed = !in_array((int)$transaction_status, array(TransactproService::STATUS_MPI_URL_GENERATED, TransactproService::STATUS_INSIDE_FORM_URL_SENT))
            && (int)$this->paymentMethod !== TransactproService::METHOD_DMS;
        $status = $isPayed ? 'PS_OS_PAYMENT' : 'PS_OS_PREPARATION';

        $history = new OrderHistory();
        $history->id_order = $orderId;
        $history->changeIdOrderState((int)Configuration::get($status), $orderId);
        $history->addWithemail(true, array(
            'order_name' => $orderId,
        ));
    }

    /**
     * @param array $params
     * @return array|void
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkSupportedCurrencies()) {
            return;
        }

        $this->context->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://')
                . htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8') . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ));

        $payment_options = array(
            $this->getEmbeddedPaymentOption(),
        );

        return $payment_options;
    }

    /**
     * @return PaymentOption
     */
    public function getEmbeddedPaymentOption()
    {
        $embeddedOption = new PaymentOption();
        $embeddedOption->setCallToActionText($this->l('Credit/Debit Card (Transact Pro)'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
        ;

        if ($this->paymentCapture === TransactproService::PAYMENT_SIDE_GATEWAY) {
            $embeddedOption->setAdditionalInformation($this->context->smarty->fetch('module:transact_pro/views/templates/hook/payment.tpl'));
        }

        return $embeddedOption;
    }

    /**
     * @param array $params
     * @return string
     */
    public function hookPaymentReturn($params)
    {
        $state = $params['order']->getCurrentState();

        if (in_array(
            $state,
            array(
                Configuration::get('PS_OS_PAYMENT'),
                Configuration::get('PS_OS_PREPARATION'),
                Configuration::get('PS_OS_OUTOFSTOCK'),
            )
        )) {
            $this->smarty->assign(array(
                'total' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'status' => 'ok',
                'id_order' => $params['order']->id
            ));
        } else {
            $this->smarty->assign('status', 'failed');
        }

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    /**
     * @return int|null
     */
    public function getPaymentCapture()
    {
        return $this->paymentCapture;
    }

    /**
     * @return Gateway
     */
    protected function initGateway() {
        $gw = $this->transactproService->createGateway($this->gatewayUrl);

        $this->transactproService->auth($gw, $this->accountId, $this->secretKey);

        return $gw;
    }

    /**
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function makeTransaction($data) {
        $gw = $this->initGateway();

        $paymentData = array(
            'customer' => array_merge($data['customer'], array(
                'email' => $this->context->customer->email,
                'birth_date' => $this->context->customer->birthday
            )),
            'system' => array(
                'user_IP' => $_SERVER['REMOTE_ADDR']
            ),
            'money' => array(
                'amount' => $this->transactproService->lowestDenomination($data['amount']),
                'currency' => $data['currency']
            ),
            'order' => array(
                'recipient_name' => Configuration::get('PS_SHOP_NAME')
            )
        );

        if (isset($data['payment_instrument'])) {
            $paymentData['payment_method'] = array(
                'PAN' => $data['payment_instrument']['pan'],
                'expire' => $data['payment_instrument']['exp_month'].'/'.substr($data['payment_instrument']['exp_year'], 2),
                'CVV' => $data['payment_instrument']['cvc'],
                'card_holder_name' => $data['payment_instrument']['holder']
            );
        }

        return $this->transactproService->createTransaction($gw, (int)$this->paymentMethod, $paymentData);
    }

    /**
     * @return string
     */
    protected function getModuleConfigUrl() {
        return $this->context->link->getAdminLink('AdminModules').'&configure=' . $this->name;
    }

    /**
     * @return bool
     */
    private function createTable()
    {
        return TransactionRepository::createTable();
    }

    /**
     * @return bool
     */
    private function validatePostRequest()
    {
        $fields = [
            'gateway_url',
            'account_id',
            'secret_key',
            'payment_method',
            'payment_capture'
        ];

        foreach ($fields as $field) {
            if (empty(Tools::getValue($field))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string
     */
    private function processPostRequest()
    {
        Configuration::updateValue(self::CONFIG_GATEWAY_URL, Tools::getValue('gateway_url'));
        Configuration::updateValue(self::CONFIG_ACCOUNT_ID, Tools::getValue('account_id'));
        Configuration::updateValue(self::CONFIG_SECRET_KEY, Tools::getValue('secret_key'));
        Configuration::updateValue(self::CONFIG_PAYMENT_METHOD, Tools::getValue('payment_method'));
        Configuration::updateValue(self::CONFIG_PAYMENT_CAPTURE, Tools::getValue('payment_capture'));

        return $this->displayConfirmation($this->l('Settings updated'));
    }
}
