<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_cashpayment
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: novalnet_cashpayment.php
 */

// No direct access
if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    include JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';

if (!class_exists('NovalnetUtilities'))
    include JPATH_PLUGINS . DS . 'vmpayment' . DS . 'novalnet_payment' . DS . 'novalnet_payment' . DS . 'helpers' . DS . 'NovalnetUtilities.php';

/**
 * Cash payment class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class plgVmPaymentnovalnet_cashpayment extends vmPSPlugin
{
    /**
     * Constructor for the class.
     *
     * @param   object $subject which is subject of the class
     * @param   object $config  the configuration of the payment method
     *
     * @return void
     */
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable   = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey  = 'id';
        $this->_tableId    = 'id';
        $varsToPush        = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->helpers = new NovalnetUtilities;
        $this->helpers->loadNovalnetJs('novalnet_cashpayment', 'admin');
    }

    /**
     * To create a payment method table
     *
     * @return boolean
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Novalnet Cash Payment Table');
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
            'payment_details' => 'blob COMMENT \'Customer payment details\'',
            'order_total' => 'int(11) UNSIGNED COMMENT \'Customer order amount in cents\'',
            'paid_amount' => 'int(11) UNSIGNED COMMENT \'Customer paid amount in cents\''
        );
    }

    /**
     * This event is fired after the order has been confirmed.
     *
     * @param   object $cart  cart object
     * @param   array  $order order object
     *
     * @return boolean
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
            return null;

        if (!$this->selectedThisElement($method->payment_element))
            return false;

        $data = $this->helpers->getNovalnetParameters($method, $cart, $order);
        if ($method->nn_due_date)
            $data['cp_due_date'] = date('Y-m-d', strtotime('+ ' . $method->nn_due_date . ' day'));
        $cart->setCartIntoSession();
        $response = $this->helpers->sendRequest($data);
        $response = array_merge($data, $response);
        $message  = $this->helpers->buildTransactionComments($response);

        if ($response['status'] == '100')
        {
            $response['payment_name']    = $method->payment_name;
            $response['payment_element'] = $method->payment_element;
            $dbValues                    = $this->helpers->storePaymentdetails($order, $response, $method, $basicDetails);
            $this->storePSPluginInternalData($dbValues);
            $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
            $message .= $this->helpers->buildOrderComments($response);
            $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, $method->nn_order_status, $message);
            $html = $this->helpers->renderNovalnetHtml($dbValues, $response);
            $this->helpers->handleSession('novalnet_cashpayment', 'reset');
            $cart->emptyCart();
            vRequest::setVar('html', $html);
            return true;
        }
        else
        {
            $msg = $this->helpers->getPaygateMessage($response);
            $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, 'X', $msg . '<br />' . $message);
            $this->helpers->handleMessage($msg);
        }
    }

    /**
     * Event triggered on backend payment details display of an order
     *
     * @param   integer $virtuemartOrderId   virtuemart order id
     * @param   string  $virtuemartPaymentId virtuemart payment id
     *
     * @return string $html
     */
    public function plgVmOnShowOrderBEPayment($virtuemartOrderId, $virtuemartPaymentId)
    {
        if (!$this->selectedThisByMethodId($virtuemartPaymentId))
            return null;

        if (!($paymentTable = $this->getDataByOrderId($virtuemartOrderId)))
            return null;

        $order = VmModel::getModel('orders')->getOrder($virtuemartOrderId);
        VmConfig::loadJLang('com_virtuemart');
        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('VMPAYMENT_NOVALNET_ORDER_NUMBER', $paymentTable->order_number);
        $html .= $this->getHtmlRowBE('NOVALNET_PAYMENT_ORDER_TOTAL', CurrencyDisplay::getInstance()->priceDisplay($order['details']['BT']->order_total));
        $html .= $this->getHtmlRowBE('VMPAYMENT_NOVALNET_TRANSACTION_ID', $paymentTable->tid);
        $html .= '</table>' . "\n";
        $html .= $this->helpers->renderExtension($paymentTable, $order);
        return $html;
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @param   object $cart        virtuemart order id
     * @param   object $method      current payment method
     * @param   object $cartPrices  cart prices
     *
     * @return boolean
     */
    protected function checkConditions($cart, $method, $cartPrices)
    {
        $this->convert_condition_amount($method);
        $amount  = $this->getCartAmount($cartPrices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
        $countries = array();

        if ($this->_toConvert)
            $this->convertToVendorCurrency($method);

        if (!($amount >= $method->min_amount && $amount <= $method->max_amount || ($method->min_amount <= $amount && ($method->max_amount == 0))))
            return false;

        if (!empty($method->countries))
        {
            if (!is_array($method->countries))
            {
                $countries[0] = $method->countries;
            }
            else
            {
                $countries = $method->countries;
            }
        }

        if (!is_array($address))
        {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id']))
            $address['virtuemart_country_id'] = 0;

        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries))
            return true;
        return false;
    }

    /**
     * Event triggered on installation of payment plugin
     *
     * @param   int $jpluginId which is current plugin id
     *
     * @return boolean
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jpluginId)
    {
        return $this->onStoreInstallPluginTable($jpluginId);
    }

    /**
     * This event is fired after the payment method has been selected.
     *
     * @param   object VirtueMartCart $cart the actual cart
     * @param   array                 $msg  message
     *
     * @return boolean
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment)
     *
     * @param   object VirtueMartCart $cart     which is current cart object
     * @param   integer               $selected id of the method selected
     * @param   string                $htmlIn   the html content
     *
     * @return boolean
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        if (!$this->helpers->getMerchantDetails('auto_key'))
            return false;

        $methodName = $this->_psType . '_name';
        if ($this->getPluginMethods($cart->vendorId) === 0)
        {
            if (empty($this->_name))
                JFactory::getApplication()->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
            return false;
        }
        $htmla = array();
        $html  = '';
        foreach ($this->methods as $this->_currentMethod)
        {
            if ($this->checkConditions($cart, $this->_currentMethod, $cart->cartPrices))
            {
                $methodSalesPrice                  = $this->setCartPrices($cart, $cart->pricesUnformatted, $this->_currentMethod);
                $this->_currentMethod->$methodName = $this->helpers->renderNovalnetPluginName($this->_currentMethod);
                $html                              = $this->getPluginHtml($this->_currentMethod, $selected, $methodSalesPrice);
            }
        }

        if (!$html)
            return false;

        $htmla[]  = $html;
        $htmlIn[] = $htmla;
        return true;
    }

    /**
     * To get the payment wise price
     *
     * @param   object $cart           cart object
     * @param   array  $cartPrices     cart prices
     * @param   string $cartPricesName cart price name
     *
     * @return boolean
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cartPrices, &$cartPricesName)
    {
        return $this->onSelectedCalculatePrice($cart, $cartPrices, $cartPricesName);
    }

    /**
     * To get the payment currency
     *
     * @param   integer $virtuemartPaymentMethodId   the payment method id
     * @param   integer $paymentCurrencyId           the payment method currency id
     *
     * @return boolean
     */
    public function plgVmgetPaymentCurrency($virtuemartPaymentMethodId, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemartPaymentMethodId)))
            return null;

        if (!$this->selectedThisElement($method->payment_element))
            return false;

        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    /**
     * Event triggered on payment selection
     *
     * @param   object $cart           cart object
     * @param   array  $cartPrices     cart prices
     * @param   array  $paymentCounter payment counter
     *
     * @return boolean
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cartPrices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cartPrices, $paymentCounter);
    }

    /**
     * Event triggered on frontend payment details display of an order
     *
     * @param   integer $virtuemartOrderId         order id
     * @param   integer $virtuemartPaymentId       payment id
     * @param   string  $payment_name              payment name
     *
     * @return string
     */
    public function plgVmOnShowOrderFEPayment($virtuemartOrderId, $virtuemartPaymentId, &$payment_name)
    {
        if (!($paymentTable = $this->getDataByOrderId($virtuemartOrderId)))
            return null;
        $this->onShowOrderFE($virtuemartOrderId, $virtuemartPaymentId, $payment_name);
    }

    /**
     * Event triggered on payment save
     *
     * @param   object $cart the actual cart
     *
     * @return boolean
     */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {
        return null;
    }

    /**
     * Even triggered on print option in an order
     *
     * @param   string  $orderNumber order number
     * @param   integer $methodId    method id
     *
     * @return html
     */
    public function plgVmonShowOrderPrintPayment($orderNumber, $methodId)
    {
        return parent::onShowOrderPrint($orderNumber, $methodId);
    }

    /**
     * Event triggered on declaration of our payment params
     *
     * @param   string $data payment data
     *
     * @return boolean
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * Stores the plugin params on table
     *
     * @param   string  $name  installed plugin name
     * @param   integer $id    installed plugin id
     * @param   string  $table installed plugin table
     *
     * @return boolean
     */
    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
}
