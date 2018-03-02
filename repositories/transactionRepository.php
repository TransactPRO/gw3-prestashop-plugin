<?php

class TransactionRepository
{
    /**
     * @return bool
     */
    public static function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'transact_pro_transaction` (
          `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `id_shop` int(10) unsigned NOT NULL,
          `order_id` int(11) UNSIGNED NOT NULL,
          `transaction_guid` char(40) NOT NULL,
          `transaction_status` int(11) UNSIGNED NOT NULL,
          `payment_method` int(11) UNSIGNED NOT NULL,
          `transaction_amount` decimal(15,2) NOT NULL,
          `transaction_currency` char(3) NOT NULL,
          `device_ip` char(15) NOT NULL,
          `created_at` datetime NOT NULL,
          `is_refunded` tinyint(1) NOT NULL,
          `refunded_at` datetime NULL,
          `refunds` text NOT NULL,
          PRIMARY KEY (`id`, `id_shop`, `order_id`),
          KEY `transaction_guid` (`transaction_guid`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;
        ';

        $result = (bool)Db::getInstance()->execute($sql);

        return $result;
    }

    /**
     * @param string $guid
     * @return array
     */
    public static function getTransaction($guid)
    {
        $id_shop = Context::getContext()->shop->id;

        $sql = 'SELECT *
                FROM `' . _DB_PREFIX_ . 'transact_pro_transaction`                
                WHERE `id_shop` = '.(int)$id_shop.' AND `transaction_guid` = \''.pSQL($guid).'\'';

        return Db::getInstance()->getRow($sql);
    }

    /**
     * @param int $limit
     * @return array
     */
    public static function getAllTransactions($limit = 10000) {
        $id_shop = (int)Context::getContext()->shop->id;

        return (array) Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'transact_pro_transaction'
            .'` WHERE `id_shop` = \''.$id_shop.'\' ORDER BY `id` DESC'.($limit ? ' LIMIT '.(int)$limit : ''));
    }

    /**
     * @param string $transaction_guid
     * @param int $order_id
     * @param int $transaction_status
     * @param int $payment_method
     * @param float $amount
     * @param string $currency
     * @param string $user_ip
     * @return bool
     */
    public static function add($transaction_guid, $order_id, $transaction_status, $payment_method, $amount, $currency, $user_ip) {
        $id_shop = Context::getContext()->shop->id;

        $sql = "INSERT INTO `" . _DB_PREFIX_ . "transact_pro_transaction` SET id_shop = " . $id_shop . ", transaction_guid='"
            . pSQL($transaction_guid) . "', order_id='" . (int) $order_id . "', transaction_status='"
            . (int) $transaction_status . "', payment_method='" . (int) $payment_method . "', transaction_amount='"
            . (float) $amount . "', transaction_currency='" . pSQL($currency) . "', device_ip='"
            . pSQL($user_ip) . "', created_at=NOW(), is_refunded='0', refunded_at='', refunds='"
            . pSQL(json_encode(array())) . "'";
        ;

        return Db::getInstance()->execute($sql);
    }

    /**
     * @param int $id
     * @param int $status
     * @return bool
     */
    public static function updateTransactionStatus($id, $status)
    {
        $sql = "UPDATE `" . _DB_PREFIX_ . "transact_pro_transaction` SET transaction_status='"
            . (int) $status . "' where id=".(int)$id;
        ;

        return Db::getInstance()->execute($sql);
    }

    /**
     * @param int $id
     * @param array $refunds
     * @return bool
     */
    public static function updateTransactionRefunds($id, $refunds)
    {
        $sql = "UPDATE `" . _DB_PREFIX_ . "transact_pro_transaction` SET is_refunded='".($refunds ? 1 : 0)
            ."', refunded_at=NOW(), refunds='".pSQL(json_encode($refunds)) . "' where id=".(int)$id;
        ;

        return Db::getInstance()->execute($sql);
    }
}