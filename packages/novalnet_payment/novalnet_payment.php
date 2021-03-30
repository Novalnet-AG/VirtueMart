<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_payment
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: novalnet_payment.php
 */

// No direct access
if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    include JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';

if (!class_exists('NovalnetUtilities'))
    include JPATH_PLUGINS . DS . 'vmpayment' . DS . 'novalnet_payment' . DS . 'novalnet_payment' . DS . 'helpers' . DS . 'NovalnetUtilities.php';

/**
 * admin configuration class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class plgVmPaymentnovalnet_payment extends vmPSPlugin
{
    /**
     * Constructor for the class.
     *
     * @param   object $subject which is subject of the class
     * @param   object $config  the configuration of the payment method
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable   = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey  = 'id';
        $this->_tableId    = 'id';
        $varsToPush        = $this->getVarsToPush();
        $this->helpers     = new NovalnetUtilities;
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    /**
     * To create a payment method table
     *
     * @return boolean
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Novalnet payments Table');
    }

    /**
     * To organise the table structure
     *
     * @return array
     */
    public function getTableSQLFields()
    {
        return array(
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT \'Auto Increment ID\'',
            'virtuemart_order_id' => 'int(11) UNSIGNED COMMENT \'Virtuemart Order Id in shop\'',
            'order_number' => 'char(64) COMMENT \'Order number in shop\'',
            'virtuemart_paymentmethod_id' => 'int(11) UNSIGNED COMMENT \'Virtuemart payment method id in shop\'',
            'payment_element' => 'varchar(256) COMMENT \'Virtuemart payment method element name\'',
            'payment_name' => 'varchar(256) COMMENT \'Virtuemart payment method name\'',
            'tid' => 'bigint(20) unsigned COMMENT \'Novalnet transaction reference ID\'',
            'vendor_details' => 'blob COMMENT \'Vendor details in shop\'',
            'status' => 'int(4) UNSIGNED COMMENT \'Novalnet transaction status in response\'',
            'payment_key' => 'int(4) UNSIGNED COMMENT \'payment method key\'',
            'customer_id' => 'int(11) UNSIGNED COMMENT \'Customer ID from shop\'',
            'affiliate_id' => 'int(11) UNSIGNED COMMENT \'Customer affiliate id\'',
            'order_total' => 'int(11) UNSIGNED COMMENT \'Customer order amount in cents\''
        );
    }

    /**
     * Event triggered on installation of payment plugin
     *
     * @param   integer $jpluginId plugin id
     *
     * @return boolean
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jpluginId)
    {
        return $this->onStoreInstallPluginTable($jpluginId);
    }

    /**
     * Event triggered on external notification
     *
     * @return void
     */
    public function plgVmOnPaymentNotification()
    {
        $formType = vRequest::getVar('form_type', '');
        if (!empty($formType))
        {
            if (!class_exists('VirtueMartModelOrders'))
                include JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';

            if (!class_exists('VirtueMartCart'))
                include JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php';

            if (!class_exists('CurrencyDisplay'))
                include JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php';

            if ($formType == 'vendor_script')
            {
                include JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'novalnet_payment' . DS . 'novalnet_payment' . DS . 'helpers' . DS . 'NovalnetVendorScript.php';
                $nnVendorScript = new NovalnetVendorScript;
                $nnVendorScript->novalnetCallback();
                JExit();
            }

            $orderNumber = vRequest::getVar('order_number', '');
            $orderId     = VirtueMartModelOrders::getOrderIdByOrderNumber($orderNumber);
            $order       = VmModel::getModel('orders')->getOrder($orderId);
            if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
                return null;

            if ($formType == 'extension')
            {
                $cart = VirtueMartCart::getCart();
                // Parameter formation for extension process.
                $this->helpers->doExtensionProcess($order, $cart, $this->_currentMethod);
            }
        }
    }
}
