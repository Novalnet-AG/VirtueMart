<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_invoice
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: novalnet_invoice.php
 */

// No direct access
if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    include JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';

if (!class_exists('NovalnetUtilities'))
    include JPATH_PLUGINS . DS . 'vmpayment' . DS . 'novalnet_payment' . DS . 'novalnet_payment' . DS . 'helpers' . DS . 'NovalnetUtilities.php';

/**
 * Invoice payment class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class plgVmPaymentnovalnet_invoice extends vmPSPlugin
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
        $this->helpers     = new NovalnetUtilities;
        $this->helpers->loadNovalnetJs('novalnet_invoice', 'admin');
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    /**
     * To create a payment method table
     *
     * @return boolean
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Novalnet invoice payment Table');
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
            'paid_amount' => 'int(11) UNSIGNED COMMENT \'Customer paid amount in cents\'',
            'payment_details' => 'blob COMMENT \'Customer payment details\''
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

        VmConfig::loadJLang('com_virtuemart', true);
        VmConfig::loadJLang('com_virtuemart_orders', true);

        if (!class_exists('VirtueMartModelOrders'))
            include VMPATH_ADMIN . DS . 'models' . DS . 'orders.php';

        $this->helpers->handleSession('novalnet_invoice', 'set', 'nncart', serialize($cart));
        $data = $this->helpers->getNovalnetParameters($method, $cart, $order);
		
		$guaranteeDetails = $this->helpers->handleSession($method->payment_element, 'get', 'guaranteeDetails');
        if (!empty($guaranteeDetails) )
        {
            $data             = array_merge($data, $guaranteeDetails);
        }
        $data['invoice_type'] = 'INVOICE';
        $data['invoice_ref'] = "BNR-" . $data['product'] . "-" . $data['order_no'];
        if (!empty($method->nn_due_date) && $method->nn_due_date >= 7)
            $data['due_date'] = date('Y-m-d', strtotime('+ ' . $method->nn_due_date . ' day'));
        $response                    = $this->helpers->sendRequest($data);
        $response                    = array_merge($data, $response);
        $response['payment_name']    = $method->payment_name;
        $response['payment_element'] = $method->payment_element;
        $response['order_no']        = $order['details']['BT']->order_number;
        $message                     = $this->helpers->buildTransactionComments($response);
        if ($response['status'] == '100')
        {
            $dbValues = $this->helpers->storePaymentdetails($order, $response, $method, $basicDetails);
            $this->storePSPluginInternalData($dbValues);
            $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
            if ($response['tid_status'] != '75')
            {
                $message .= $this->helpers->invoicePrepaymentTransactionComments($response);
            }
            $orderStatus = ($response['tid_status'] == '75') ? $method->guarantee_order_status : (($response['tid_status'] == '91') ? $this->helpers->confirmed_order_status : $method->nn_order_status);
            
            $paymentKey = ( isset( $response['key'] ) && !empty( $response['key'] ) ) ? $response['key'] : ( isset( $response['payment_id'] ) && !empty( $response['payment_id'] ) ) ? $response['payment_id'] : '';
            if ( $paymentKey == '41' && $response['tid_status'] == '100' ) {
				$orderStatus = $method->nn_callback_status;
			}
            $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, $orderStatus, $message);
            $html = $this->helpers->renderNovalnetHtml($dbValues);
            $cart->setCartIntoSession();
            $this->helpers->handleSession('novalnet_invoice', 'reset');
            $this->helpers->handleSession('novalnet_invoice', 'clear', 'nncart');
            $cart->emptyCart();
            vRequest::setVar('html', $html);
            return true;
        }
        else
        {
            $this->helpers->handleSession('novalnet_invoice', 'reset');
            $this->helpers->handleSession('novalnet_invoice', 'clear', 'birthDate');
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
     * @return string
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
     * @param   int $jpluginId the current plugin id
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
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
        {
            $this->helpers->handleSession('novalnet_invoice', 'reset');
            return null;
        }

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id)))
        {
            $this->helpers->handleSession('novalnet_invoice', 'reset');
            return false;
        }

        if (vRequest::getInt('virtuemart_paymentmethod_id'))
        {
            if (!$this->validateDateOfBirth($this->_currentMethod, $cart))
                return false;
        }
        return true;
    }

    /**
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment)
     *
     * @param   object VirtueMartCart $cart     cart object
     * @param   integer               $selected ID of the method selected
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
        $errorPayment = $this->helpers->handleSession('novalnet_invoice', 'get', 'error_payment');

        if ((!empty($errorPayment) && ($errorPayment[0] + (30 * 60)) > time()))
        {
            return false;
        }
        else
        {
            $this->helpers->handleSession('novalnet_invoice', 'clear', 'error_payment');
        }
        $html   = '';
        $htmla  = array();
        $prices = $cart->getCartPrices();

        // To format the order amount
        $orderTotal = $this->helpers->formatAmount($prices['billTotal']);
        $this->helpers->handleSession('novalnet_invoice', 'set', 'sendPinAmount', $orderTotal);
        include JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'novalnet_invoice' . DS . 'novalnet_invoice' . DS . 'tmpl' . DS . 'NovalnetInvoiceForm.php';
        $nnInvoiceForm = new NovalnetInvoiceForm;

        foreach ($this->methods as $this->_currentMethod)
        {
           if ($this->checkConditions($cart, $this->_currentMethod, $cart->cartPrices))
           {
                $methodSalesPrice                  = $this->setCartPrices($cart, $cart->pricesUnformatted, $this->_currentMethod);
                $this->_currentMethod->$methodName = $this->helpers->renderNovalnetPluginName($this->_currentMethod);
                $html                              = $this->getPluginHtml($this->_currentMethod, $selected, $methodSalesPrice);
                $html .= '<br /><table id="novalnet_invoice_form" border="0" cellspacing="0" cellpadding="2" width="100%" >';
                $html .= $nnInvoiceForm->renderGuarantee($cart, $this->_currentMethod);
                $html .= '</table> ';
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
     * @param   object $cart        cart object
     * @param   array  $cartPrices  cart prices
     * @param   string $paymentName payment name
     *
     * @return boolean
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cartPrices, &$paymentName)
    {
        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id)))
            return null;

        if (!$this->selectedThisElement($this->_currentMethod->payment_element))
            return false;

        $cartPrices['payment_tax_id'] = 0;
        $cartPrices['payment_value']  = 0;
        if (!$this->checkConditions($cart, $this->_currentMethod, $cartPrices))
            return false;

        $paymentName = $this->renderPluginName($this->_currentMethod);
        $this->setCartPrices($cart, $cartPrices, $this->_currentMethod);
        return true;
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
     * @param   string  $paymentName               payment name
     *
     * @return string $html
     */
    public function plgVmOnShowOrderFEPayment($virtuemartOrderId, $virtuemartPaymentId, &$paymentName)
    {
        if (!($paymentTable = $this->getDataByOrderId($virtuemartOrderId)))
            return null;

        $this->onShowOrderFE($virtuemartOrderId, $virtuemartPaymentId, $paymentName);
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
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
            return null;

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id)))
            return false;

        return $this->validateDateOfBirth($this->_currentMethod, $cart);
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

    /**
     * To get the payment currency
     *
     * @param   integer $virtuemartPaymentmethodId the payment method id
     * @param   integer $paymentCurrencyId         the payment method currency id
     *
     * @return boolean
     */
    public function plgVmgetPaymentCurrency($virtuemartPaymentmethodId, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemartPaymentmethodId)))
            return null;

        if (!$this->selectedThisElement($method->payment_element))
            return false;

        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    /**
     * Event triggered on validation purpose
     *
     * @param   object  $_currentMethod                 current plugin
     * @param   integer $cart      						cart contents
     *
     * @return boolean
     */
    public function validateDateOfBirth($_currentMethod, $cart)
    {
        if (!empty($_currentMethod->guarantee))
        {
            $guranteeError = $this->helpers->handleSession('novalnet_invoice', 'get', 'error_guarantee');
			$guaranteeErrorMessage = $this->helpers->handleSession('novalnet_invoice', 'get', 'guarantee_error_message');
            if (isset($guranteeError) && !empty($guranteeError))
            {
                vmWarn(JText::_($guaranteeErrorMessage));
                return false;
            }

            $return = true;
            $birthDate = vRequest::getVar('birthDate' . $cart->virtuemart_paymentmethod_id, '');
            $company   = $cart->BT['company'] != '' ? $cart->BT['company'] : $cart->ST['company'];

            if (!empty($birthDate) && $company == '')
            {
                $this->helpers->handleSession($_currentMethod->payment_element, 'set', 'birthDate', $birthDate);
            }
            $sessionBirthDate = $this->helpers->handleSession($_currentMethod->payment_element, 'get', 'birthDate');
            
            // To format the order amount
            $prices    = $cart->getCartPrices();
            $billTotal = $this->helpers->formatAmount($prices['billTotal']);
            $minAmount = ($_currentMethod->min_amount_guarantee) ? $_currentMethod->min_amount_guarantee : 999;
            
            if ( !empty( $_currentMethod->guarantee_force ) && ( !empty($sessionBirthDate) || $company != '' ) && $billTotal <= $minAmount ) {
				$paymentDetails 	  = $this->helpers->getPaymentDetails($_currentMethod->payment_element, false);
				$data['key']          = $paymentDetails['key'];
				$data['payment_type'] = $paymentDetails['payment_type'];
				$this->helpers->handleSession($_currentMethod->payment_element, 'set', 'guaranteeDetails', $data);
				return true;
			}
            
            if (empty($_currentMethod->guarantee_force) && empty($sessionBirthDate) && $company == '')
            {
                vmWarn(JText::_('VMPAYMENT_NOVALNET_EMPTY_BIRTHDATE'));
                $return = false;
            }

            if (!empty($_currentMethod->guarantee) && !$this->helpers->ageValidation($_currentMethod, (!$birthDate) ? $sessionBirthDate :$birthDate, $company, $_currentMethod->guarantee_force))
            {
                vmWarn(JText::_('VMPAYMENT_NOVALNET_GURENTEE_PAYMENT_AGE_VALIDATION'));
                $return = false;
            }
            return $return;
        }
        return true;
    }
}
