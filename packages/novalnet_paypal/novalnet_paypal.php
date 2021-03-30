<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_paypal
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: novalnet_paypal.php
 */

// No direct access
if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    include JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';

if (!class_exists('NovalnetUtilities'))
    include JPATH_PLUGINS . DS . 'vmpayment' . DS . 'novalnet_payment' . DS . 'novalnet_payment' . DS . 'helpers' . DS . 'NovalnetUtilities.php';

/**
 * Paypal payment class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class plgVmPaymentnovalnet_paypal extends vmPSPlugin
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
        $this->helpers     = new NovalnetUtilities;
        $varsToPush        = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->helpers->loadNovalnetJs('novalnet_paypal', 'admin');
    }

    /**
     * To create a payment method table
     *
     * @return boolean
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Novalnet paypal payment Table');
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
            'nn_mask' => 'int(4) UNSIGNED COMMENT \'Novalnet masking refernce\'',
            'booked' => 'int(4) UNSIGNED COMMENT \'Novalnet booking reference\'',
            'one_click_shopping' => 'int(4) UNSIGNED COMMENT \'Novalnet one click reference\'',
            'payment_key' => 'int(4) UNSIGNED COMMENT \'payment method key\'',
            'customer_id' => 'int(11) UNSIGNED COMMENT \'Customer ID from shop\'',
            'affiliate_id' => 'int(11) UNSIGNED COMMENT \'Customer affiliate id\'',
            'order_total' => 'int(11) UNSIGNED COMMENT \'Customer order amount in cents\'',
            'payment_details' => 'blob COMMENT \'Customer payment details\'',
            'payment_request' => 'blob COMMENT \'Novalnet payment request\'',
            'payment_ref' => 'bigint(20) UNSIGNED COMMENT \'Novalnet reference transaction ID\''
        );
    }

    /**
     * This event is fired after the order has been confirmed.
     *
     * @param   object $cart  which is current cart object
     * @param   array  $order which is current order object
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
        $this->helpers->handleSession('novalnet_paypal', 'set', 'nncart', serialize($cart));
        $paymentDetails = $this->helpers->handleSession('novalnet_paypal', 'get', 'paymentDetails');

        if ($method->shopping_type == 'one_click' && !empty($paymentDetails['nnpaypal_oneclick' . $order['details']['BT']->virtuemart_paymentmethod_id]) && !empty($paymentDetails['payment_ref_' . $order['details']['BT']->virtuemart_paymentmethod_id]))
        {
            if (!empty($paymentDetails['payment_ref_' . $order['details']['BT']->virtuemart_paymentmethod_id]))
                unset($data['create_payment_ref']);
            $data['payment_ref']         = $paymentDetails['payment_ref_' . $order['details']['BT']->virtuemart_paymentmethod_id];
            $this->helpers->handleSession('novalnet_paypal', 'set', 'payment_request', $data);
            $response                    = $this->helpers->sendRequest($data);
            $response                    = array_merge($data, $response);
            $response['payment_name']    = $method->payment_name;
            $response['payment_element'] = $method->payment_element;
            $dbValues                    = $this->helpers->storePaymentdetails($order, $response, $method, $basicDetails);
            $message                     = $this->helpers->buildTransactionComments($response);
            if (in_array($response['status'], array('90','100')))
            {
                $this->storePSPluginInternalData($dbValues);
                $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
                $orderstatus = ($response['status'] == '100' && $response['tid_status'] == '100') ? $method->nn_order_status : $method->nn_callback_status;
                $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, $orderstatus, $message);
                $html = $this->helpers->renderNovalnetHtml($dbValues);
                $this->helpers->handleSession('novalnet_paypal', 'reset');
                $cart->emptyCart();
                vRequest::setVar('html', $html);
                return true;
            }
            else
            {
                $msg = $this->helpers->getPaygateMessage($response);
                $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, 'X', $msg . '<br />' . $message);
                $this->helpers->handleSession('novalnet_paypal', 'reset');
                $this->helpers->handleSession('novalnet_paypal', 'clear', 'nncart');
                $this->helpers->handleMessage($msg);
            }
        }
        else
        {
            $this->helpers->handleSession($method->payment_element, 'set', 'payment_request', $data);
            $cart->setCartIntoSession();
            $html                 = $this->helpers->buildRedirectform($data, 'novalnet_paypal');
            $cart->_confirmDone   = false;
            $cart->_dataValidated = false;
            vRequest::setVar('html', $html);
        }
    }

    /**
     * Event triggered on backend payment details display of an order
     *
     * @param   integer $virtuemartOrderId   which is current order id
     * @param   string  $virtuemartPaymentId which is current payment method id
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
     * @param   object $cart        which is current cart object
     * @param   object $method      which is current payment method object
     * @param   object $cartPrices  which is current cart prices
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
     * @param   object VirtueMartCart $cart which is current cart object
     * @param   array                 $msg  the message
     *
     * @return boolean
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
        {
            $this->helpers->handleSession('novalnet_paypal', 'reset');
            return null;
        }

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id)))
        {
            $this->helpers->handleSession('novalnet_paypal', 'reset');
            return false;
        }
        $save_card     = vRequest::getVar('paypal_save_card' . $cart->virtuemart_paymentmethod_id, '');
        if ($save_card == '1')
                $this->helpers->handleSession('novalnet_paypal', 'set', 'save_card', $save_card);
    }

    /**
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment)
     *
     * @param   object VirtueMartCart $cart     which is current cart object
     * @param   integer               $selected the selected payment
     * @param   string                $htmlIn   the html template
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
            {
                JFactory::getApplication()->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
            }
            return false;
        }
        $html  = '';
        $htmla = array();
        include JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'novalnet_paypal' . DS . 'novalnet_paypal' . DS . 'tmpl' . DS . 'NovalnetpaypalForm.php';
        $nnPaypalForm   = new NovalnetpaypalForm;
        $paymentDetails = $this->helpers->handleSession('novalnet_paypal', 'get', 'paymentDetails');

        foreach ($this->methods as $this->_currentMethod)
        {
            if ($this->checkConditions($cart, $this->_currentMethod, $cart->pricesUnformatted))
            {
                if ($this->_currentMethod->shopping_type == 'one_click')
                {
                    $maskedPatterns = $this->helpers->getStoredPattern($this->_currentMethod);
                }
                else
                {
                    $maskedPatterns = false;
                }
                $methodSalesPrice                  = $this->setCartPrices($cart, $cart->pricesUnformatted, $this->_currentMethod);
                $this->_currentMethod->$methodName = $this->renderNovalnetPlugin($this->_currentMethod, $maskedPatterns);
                $html                              = $this->getPluginHtml($this->_currentMethod, $selected, $methodSalesPrice);
                $html .= $this->helpers->showZeroAmountBookingNotificationMessage($this->_currentMethod);
                $html                              .= '<input type="hidden" name="paypal_payment_id" id="paypal_payment_id" value="'.$this->_currentMethod->virtuemart_paymentmethod_id . '"">';
                if ($this->_currentMethod->shopping_type == 'one_click')
                {
                    $html .= ' <br><div class="w3-container" id ="paypal_oneclick" ><input type="checkbox" id="paypal_save_card' . $this->_currentMethod->virtuemart_paymentmethod_id . '" name="paypal_save_card' . $this->_currentMethod->virtuemart_paymentmethod_id . '" class="custom-control-input" value="1"><label class="checkbox-custom custom-control-label required" id ="save_card_text' . $this->_currentMethod->virtuemart_paymentmethod_id . '" for="paypal_save_card' . $this->_currentMethod->virtuemart_paymentmethod_id . '">'.vmText::_('VMPAYMENT_NOVALNET_SAVE_PAYPAL_PAYMENT_DATA').'</label></div>';
                }
                if ($this->_currentMethod->shopping_type == 'one_click' && ($maskedPatterns))
                    $html .= $nnPaypalForm->renderMaskedForm($maskedPatterns, $this->_currentMethod, true);
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
     * @param   object $cart           which is current cart object
     * @param   array  $cartPrices     which is current cart price
     * @param   string $cartPricesName which is current cart price name
     *
     * @return boolean
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cartPrices, &$cartPricesName)
    {
        return $this->onSelectedCalculatePrice($cart, $cartPrices, $cartPricesName);
    }

    /**
     * Event triggered on payment selection
     *
     * @param   object $cart           which is current cart object
     * @param   array  $cartPrices     which is current cart price
     * @param   array  $paymentCounter which is current paymentCounter
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
     * @param   integer $virtuemartOrderId         which is current order id
     * @param   integer $virtuemartPaymentMethodId which is current payment method id
     * @param   string  $paymentName               which is current payment name
     *
     * @return string
     */
    public function plgVmOnShowOrderFEPayment($virtuemartOrderId, $virtuemartPaymentMethodId, &$paymentName)
    {
        if (!($paymentTable = $this->getDataByOrderId($virtuemartOrderId)))
            return null;

        $this->onShowOrderFE($virtuemartOrderId, $virtuemartPaymentMethodId, $paymentName);
    }

    /**
     * Event triggered on payment save
     *
     * @param   object $cart which is current cart object
     *
     * @return boolean
     */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
            return null;

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id)))
            return false;

        if (vRequest::getInt('virtuemart_paymentmethod_id'))
        {
            $nnpaypalOneclick = vRequest::getVar('nnpaypal_oneclick' . $cart->virtuemart_paymentmethod_id, '');
            if ($nnpaypalOneclick == '1')
            {
                $accountParams = array('payment_ref_', 'nnpaypal_oneclick', 'paypal_order_type');
                foreach ($accountParams as $accountParam)
                {
                    $accountParam                  = $accountParam . $cart->virtuemart_paymentmethod_id;
                    $paymentDetails[$accountParam] = trim(vRequest::getVar($accountParam, ''));
                }
            }
            else
            {
                $accountParams = array('nnpaypal_oneclick', 'paypal_order_type');
                foreach ($accountParams as $accountParam)
                {
                    $accountParam                  = $accountParam . $cart->virtuemart_paymentmethod_id;
                    $paymentDetails[$accountParam] = trim(vRequest::getVar($accountParam, ''));
                }
            }
             $save_card     = vRequest::getVar('paypal_save_card' . $cart->virtuemart_paymentmethod_id, '');
			 if ($save_card == '1')
                $this->helpers->handleSession('novalnet_paypal', 'set', 'save_card', $save_card);
            $this->helpers->handleSession('novalnet_paypal', 'set', 'paymentDetails', $paymentDetails);
        }
    }

    /**
     * Even triggered on print option in an order
     *
     * @param   string  $orderNumber the current order number
     * @param   integer $methodId    the current payment method id
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
     * @param   string $data which is current data
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
     * @param   string  $name  which is current plugin name
     * @param   integer $id    which is current plugin id
     * @param   string  $table which is current plugin table
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
     * @param   string $html which is current html template
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
        if (in_array($response['status'], array('90','100')))
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
                $orderstatus = (in_array(array('100'), array($response['tid_status'], $response['status']))) ? $method->nn_order_status : ($response['tid_status'] == '85' ? $this->helpers->confirmed_order_status : (($response['tid_status'] == '90' && $response['status'] == '100') ? $method->nn_callback_status : $method->nn_order_status));
                $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, $orderstatus, $comments);
                $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
                $cart = VirtueMartCart::getCart();
                $cart->emptyCart();
                $this->helpers->handleSession('novalnet_paypal', 'clear', 'nncart');
                $this->helpers->handleSession('novalnet_paypal', 'reset');
                return true;
            }
            else
            {
                $app = JFactory::getApplication();
                $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart', false));
            }
        }
        else
        {
            $messsage = $this->helpers->getPaygateMessage($response);
            $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, 'X', $messsage . '<br />' . $comments);
            $this->helpers->handleSession('novalnet_paypal', 'reset');
            $this->helpers->handleSession('novalnet_paypal', 'clear', 'nncart');
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
		
        $response                    = $this->helpers->decode($response);
        $data                        = $this->helpers->handleSession($method->payment_element, 'get', 'payment_request');
        $orderModel                  = VmModel::getModel('orders');
        $order                       = $orderModel->getOrder($virtuemartOrderId);
        $response                    = array_merge($data, $response);
        $response['payment_name']    = $method->payment_name;
        $response['payment_element'] = $method->payment_element;
        $dbValues                    = $this->helpers->storePaymentdetails($order, $response, $method, $basicDetails);
        $this->storePSPluginInternalData($dbValues);
        $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
        $message  = $this->helpers->getPaygateMessage($response);
        $comments = $this->helpers->buildTransactionComments($response);
        $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, 'X', $message . '<br />' . $comments);
        $this->helpers->handleSession('novalnet_paypal', 'reset');
		$this->helpers->handleSession('novalnet_paypal', 'clear', 'nncart');
        $this->helpers->handleMessage($message);
        return true;
    }

    /**
     * To get the payment plugin name
     *
     * @param   object   $plugin     the payment plugin name
     * @param   boolean  $oneClick   the payment plugin name
     *
     * @return mixed
     */
    public function renderNovalnetPlugin($plugin, $oneClick = false)
    {
        $return     = '';
        $testMode   = '';
        $pluginName = $this->_psType . '_name';
        $pluginDesc = $this->_psType . '_desc';
        $logos      = $plugin->nn_payment_logos;
        if (!empty($logos))
        {
            $return = $this->helpers->displayLogos($logos, $pluginName) . ' ';
        }
        else
        {
            $return = '<span class="vmCartLogo" ><a><img align="middle" src="' . JURI::root() . 'plugins/vmpayment/novalnet_payment/novalnet_payment/assets/images/' . 'novalnet_paypal.png"  alt="novalnet_paypal" /></a></span> ';
        }

        $description    = '<br/>' . JText::_('VMPAYMENT_NOVALNET_REDIRECTION_DESC');
        $paymentDetails = $this->helpers->handleSession($plugin->payment_element, 'get', 'paymentDetails');

        if (empty($paymentDetails['paypal_order_type' . $plugin->virtuemart_paymentmethod_id]))
        {
            $block  = 'style="display:block"';
            $block2 = 'style="display:none"';
        }
        else
        {
            $block  = 'style="display:none"';
            $block2 = 'style="display:block"';
        }

        if ($plugin->shopping_type == 'one_click' && $oneClick)
        {
            $description = '<br/><span id="oneclick" ' . $block . '>' . vmText::_('VMPAYMENT_NOVALNET_PAYPAL_ONE_CLICK') . '</span><span id="oneclick_new" ' . $block2 . '>' . vmText::_('VMPAYMENT_NOVALNET_REDIRECTION_DESC') . '</span>';
        }

        if (!empty($plugin->nn_end_customer))
        {
            $description .= '<br/><br/>' . $plugin->nn_end_customer;
        }

        if (!empty($plugin->$pluginDesc))
        {
            $description = '<span class="payment_name_description"><br>' . $plugin->$pluginDesc . '</span>';
        }

        if (!empty($plugin->nn_test_mode))
        {
            $testMode = '<br/><span style="color:red;font-weight:bold">' . JText::_('VMPAYMENT_NOVALNET_TEST_MODE_DESCRIPTION') . '</span><br />';
        }
        return '<span class="' . $this->_type . '_name"><b>' . $plugin->$pluginName . '</b></span>   ' . $return . '' . $description . $testMode;
    }

    /**
     * To get the payment currency
     *
     * @param   integer $virtuemartPaymentMethodId the payment method id
     * @param   integer $paymentCurrencyId         the payment method currency id
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
}
