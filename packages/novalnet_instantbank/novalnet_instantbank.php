<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_instantbank
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: novalnet_instantbank.php
 */

// No direct access
if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    include JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';

if (!class_exists('NovalnetUtilities'))
    include JPATH_PLUGINS . DS . 'vmpayment' . DS . 'novalnet_payment' . DS . 'novalnet_payment' . DS . 'helpers' . DS . 'NovalnetUtilities.php';

/**
 * Online bank transfer payment class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class plgVmPaymentnovalnet_instantbank extends vmPSPlugin
{
    /**
     * Constructor for the class.
     *
     * @param   object $subject which is subject of the class
     * @param   object $config  the configuration of the payment method
     *
     * @return void
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable   = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey  = 'id';
        $this->_tableId    = 'id';
        $varsToPush        = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->helpers = new NovalnetUtilities;
        $this->helpers->loadNovalnetJs('novalnet_instantbank', 'admin');
    }

    /**
     * To create a payment method table
     *
     * @return boolean
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Novalnet Onlinebanktransfer payment Table');
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
            'order_total' => 'int(11) UNSIGNED COMMENT \'Customer order amount in cents\'',
            'paid_amount' => 'int(11) UNSIGNED COMMENT \'Customer paid amount in cents\''
        );
    }

    /**
     * This event is fired after the order has been confirmed.
     *
     * @param   object $cart  the actual cart
     * @param   array  $order the order object
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
        $this->helpers->handleSession('novalnet_instantbank', 'set', 'payment_request', $data);
        $html                 = $this->helpers->buildRedirectform($data, 'novalnet_instantbank');
        $cart->_confirmDone   = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession();
        vRequest::setVar('html', $html);
    }

    /**
     * Event triggered on backend payment details display of an order
     *
     * @param   integer $virtuemartOrderId   the order id from shop
     * @param   string  $virtuemartPaymentId the payment method id
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
     * @param   object $cart        the actual cart
     * @param   object $method      the payment method
     * @param   object $cartPrices  the actual cart prices
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
     * @param   int $jpluginId the plugin id
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
     * @param   array                 $msg  the message text
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
     * @param   object VirtueMartCart $cart     the actual cart
     * @param   boolean               $selected the selected payment method id
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
        $html  = '';
        $htmla = array();
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
     * @param   object VirtueMartCart $cart           the actual cart object
     * @param   array                 $cartPrices     the cart prices
     * @param   string                $cartPricesName the cart price name
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
     * @param   object VirtueMartCart $cart           the actual cart
     * @param   array                 $cartPrices     the actual cart prices
     * @param   array                 $paymentCounter the payment counter
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
     * @param   integer $virtuemartOrderId         the order id
     * @param   integer $virtuemartPaymentMethodId the payment method id
     * @param   string  $paymentName               the payment name
     *
     * @return void
     */
    public function plgVmOnShowOrderFEPayment($virtuemartOrderId, $virtuemartPaymentMethodId, &$paymentName)
    {
        $this->onShowOrderFE($virtuemartOrderId, $virtuemartPaymentMethodId, $paymentName);
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
     * @param   string  $orderNumber the order number from shop
     * @param   integer $methodId    the method id
     *
     * @return html
     */
    public function plgVmonShowOrderPrintPayment($orderNumber, $methodId)
    {
        return $this->onShowOrderPrint($orderNumber, $methodId);
    }

    /**
     * Event triggered on declaration of our payment params
     *
     * @param   string $data the payment data
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

    /**
     * Event triggered when external response(success) received
     *
     * @param   string $html the html content
     *
     * @return boolean
     */
    public function plgVmOnPaymentResponseReceived(&$html)
    {
        if (!class_exists('VirtueMartCart'))
            include VMPATH_SITE . DS . 'helpers' . DS . 'cart.php';

        if (!class_exists('shopFunctionsF'))
            include VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php';

        if (!class_exists('VirtueMartModelOrders'))
            include VMPATH_ADMIN . DS . 'models' . DS . 'orders.php';

        if (class_exists('vmLanguage'))
            vmLanguage::loadJLang('com_virtuemart_orders', true);

        $virtuemartPaymentId = vRequest::getInt('pm', 0);
        $orderNumber         = vRequest::getString('on', 0);

        if (!($method = $this->getVmPluginMethod($virtuemartPaymentId)))
            return null;

        if (!$this->selectedThisElement($method->payment_element))
            return null;

        if (!($virtuemartOrderId = VirtueMartModelOrders::getOrderIdByOrderNumber($orderNumber)))
            return null;

        $orderModel = VmModel::getModel('orders');
        $order      = $orderModel->getOrder($virtuemartOrderId);
        
        $response = JRequest::get('post');
		$session  = JFactory::getSession();
		$sessionValue = (isset($response['inputval1']) ? $response['inputval1'] : (isset($response['nn_sid']) ? $response['nn_sid'] : ''));
		
		if( !empty($sessionValue) && $session->getId() != $sessionValue ) {
			$this->helpers->handleResponseParams( $response['return_url'], $response );
		}
		
        $comments = $this->helpers->buildTransactionComments($response);
        if ($response['status'] == '100')
        {
            $hash2 = $this->helpers->generateHashValue($response, true);
            if (($hash2 != $response['hash2']))
                    $comments .= vmText::_('VMPAYMENT_NOVALNET_CHECK_HASH_FAILED_ERROR');
            $response = $this->helpers->decode($response);
            $dbValues = array();
            if (!empty($this->helpers->handleSession($method->payment_element, 'get', 'payment_request')))
            {
                $response                    = array_merge($this->helpers->handleSession($method->payment_element, 'get', 'payment_request'), $response);
                $response['payment_name']    = $method->payment_name;
                $response['payment_element'] = $method->payment_element;
                $dbValues                    = $this->helpers->storePaymentdetails($order, $response, $method, $basicDetails);
            }
            $html = $this->helpers->renderNovalnetHtml($dbValues);
            if ($html)
            {
                $this->storePSPluginInternalData($dbValues);
                $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, $method->nn_order_status, $comments);
                $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
                $cart = VirtueMartCart::getCart();
                $cart->emptyCart();
                return true;
            }
            else
            {
                JFactory::getApplication()->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart', false));
            }
        }
        else
        {
            $messsage = $this->helpers->getPaygateMessage($response);
            $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, 'X', $messsage . '<br />' . $comments);
            $this->helpers->handleMessage($messsage);
        }
    }

    /**
     * Event triggered when external response(cancel) received
     *
     * @return boolean
     */
    public function plgVmOnUserPaymentCancel()
    {
        if (!class_exists('VirtueMartCart'))
            include VMPATH_SITE . DS . 'helpers' . DS . 'cart.php';

        if (!class_exists('shopFunctionsF'))
            include VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php';

        if (!class_exists('VirtueMartModelOrders'))
            include VMPATH_ADMIN . DS . 'models' . DS . 'orders.php';

        if (class_exists('vmLanguage'))
            vmLanguage::loadJLang('com_virtuemart_orders', true);

        $virtuemartPaymentMethodId = vRequest::getInt('pm', 0);
        $orderNumber               = vRequest::getString('on', 0);

        if (!($method = $this->getVmPluginMethod($virtuemartPaymentMethodId)))
            return null;

        if (!$this->selectedThisElement($method->payment_element))
            return null;

        if (!($virtuemartOrderId = VirtueMartModelOrders::getOrderIdByOrderNumber($orderNumber)))
            return null;

        
        $response = JRequest::get('post');
		$session  = JFactory::getSession();
		$sessionValue = (isset($response['inputval1']) ? $response['inputval1'] : (isset($response['nn_sid']) ? $response['nn_sid'] : ''));
		
		if( !empty($sessionValue) && $session->getId() != $sessionValue ) {
			$this->helpers->handleResponseParams( $response['error_return_url'], $response );
		}
		
        $orderModel                  = VmModel::getModel('orders');
        $order                       = $orderModel->getOrder($virtuemartOrderId);
        $response                    = array_merge($this->helpers->handleSession($method->payment_element, 'get', 'payment_request'), $response);
        $response['payment_name']    = $method->payment_name;
        $response['payment_element'] = $method->payment_element;
        $response                    = $this->helpers->decode($response);
        $dbValues                    = $this->helpers->storePaymentdetails($order, $response, $method, $basicDetails);
        $this->storePSPluginInternalData($dbValues);
        $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
        $message  = $this->helpers->getPaygateMessage($response);
        $comments = $this->helpers->buildTransactionComments($response);
        $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, 'X', $message . '<br />' . $comments);
        $this->helpers->handleMessage($message);
        return true;
    }
}
