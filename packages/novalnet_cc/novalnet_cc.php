<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_cc
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: novalnet_cc.php
 */

// No direct access
if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    include JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';

if (!class_exists('NovalnetUtilities'))
    include JPATH_PLUGINS . DS . 'vmpayment' . DS . 'novalnet_payment' . DS . 'novalnet_payment' . DS . 'helpers' . DS . 'NovalnetUtilities.php';

/**
 * CreditCard payment method class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class plgVmPaymentnovalnet_cc extends vmPSPlugin
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
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->helpers->loadNovalnetJs('novalnet_cc', 'admin');
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
     * @param   object $cart  this is cart object required required
     * @param   array  $order this is order object required required
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

        $this->helpers->handleSession($method->payment_element, 'set', 'nncart', serialize($cart));
        $paymentDetails = $this->helpers->handleSession('novalnet_cc', 'get', 'paymentDetails');

        if (empty($paymentDetails['payment_ref']) && empty($paymentDetails['pan_hash']))
        {
            $paymentDetails = $this->helpers->handleSession('novalnet_cc', 'clear', 'paymentDetails');
            $this->helpers->handleMessage(JText::_('VMPAYMENT_NOVALNET_CREDIT_CARD_DETAILS_INVALID_ERROR'));
        }
        $data = $this->helpers->getNovalnetParameters($method, $cart, $order);
        if (!empty($paymentDetails['pan_hash']))
        {
            $data['pan_hash']  = $paymentDetails['pan_hash'];
            $data['unique_id'] = $paymentDetails['cc_unique_id'];
        }
        else
        {
            $data['payment_ref'] = $paymentDetails['payment_ref'];
            unset($data['create_payment_ref']);
        }
        $data['nn_it'] = 'iframe';
        if ($method->enable_cc3d == '1' || $method->cc3d_force == '1')
        {
            if ($method->shopping_type == 'one_click')
                unset($data['create_payment_ref']);
            if ($method->enable_cc3d == '1')
                $data['cc_3d'] = '1';
            $this->helpers->handleSession('novalnet_cc', 'set', 'payment_request', $data);
            $html = $this->helpers->buildRedirectform($data, 'novalnet_cc');
            $this->helpers->handleSession('novalnet_cc', 'clear', 'paymentDetails');
            $cart->setCartIntoSession();
            $cart->_confirmDone   = false;
            $cart->_dataValidated = false;
            vRequest::setVar('html', $html);
        }
        else
        {
            if (!empty($paymentDetails['payment_ref']) && $method->shopping_type == 'one_click')
                unset($data['create_payment_ref']);
            $this->helpers->handleSession('novalnet_cc', 'set', 'payment_request', $data);
            $response = $this->helpers->sendRequest($data);
            $this->helpers->handleSession('novalnet_cc', 'clear', 'paymentDetails');
            $response = array_merge($data, $response);
            $comments = $this->helpers->buildTransactionComments($response);
            if ($response['status'] == '100')
            {
                $response['payment_name']    = $method->payment_name;
                $response['payment_element'] = $method->payment_element;
                $orderStatus                 = ($response['tid_status'] == '98') ? $this->helpers->confirmed_order_status : $method->nn_order_status;
                $dbValues                    = $this->helpers->storePaymentdetails($order, $response, $method, $basicDetails);
                $this->storePSPluginInternalData($dbValues);
                $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
                $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, $orderStatus, $comments);
                $html = $this->helpers->renderNovalnetHtml($dbValues);
                $cart->setCartIntoSession();
                $this->helpers->handleSession('novalnet_cc', 'clear', 'paymentDetails');
                $this->helpers->handleSession('novalnet_cc', 'reset');
                $cart->emptyCart();
                vRequest::setVar('html', $html);
                return true;
            }
            else
            {
                $message = $this->helpers->getPaygateMessage($response) . '<br />';
                $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, 'X', $message . $comments);
                $this->helpers->handleMessage($message);
            }
        }
    }

    /**
     * Event triggered on backend payment details display of an order
     *
     * @param   integer $virtuemartOrderId   which is current order id
     * @param   integer $virtuemartPaymentId which is current payment method id
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
     * @param   object $cart        cart object
     * @param   object $method      current method
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
     * @param   int $jpluginId plugin id
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
     * @param   object VirtueMartCart $cart current cart object
     * @param   array                 $msg  Which is message for payment
     *
     * @return boolean
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
        {
            $this->helpers->handleSession('novalnet_cc', 'reset');
            return null;
        }

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id)))
        {
            $this->helpers->handleSession('novalnet_cc', 'reset');
            return false;
        }
        $panHash    = vRequest::getVar('pan_hash' . $cart->virtuemart_paymentmethod_id, '');
        $paymentRef = vRequest::getVar('payment_ref_' . $cart->virtuemart_paymentmethod_id, '');
        if (vRequest::getInt('virtuemart_paymentmethod_id') && ($panHash || $paymentRef))
        {
            $paymentDetail    = $this->helpers->handleSession('novalnet_cc', 'get', 'paymentDetails');
            $oneClickShopping = vRequest::getVar('nncc_oneclick' . $cart->virtuemart_paymentmethod_id, '');
            $oneClickShopping = (isset($oneClickShopping)) ? $oneClickShopping : $paymentDetail['nncc_oneclick'];
            if (($this->_currentMethod->shopping_type == 'one_click') && !empty($oneClickShopping) && empty($panHash))
            {
                $paymentReference = $this->helpers->handleSession('novalnet_cc', 'get', 'paymentDetails');
                if (empty($paymentReference['payment_ref']) && empty($paymentReference['nncc_oneclick']))
                {
                    $sessionValues = array(
                        'payment_ref' => $paymentRef,
                        'nncc_oneclick' => $oneClickShopping
                    );
                    $this->helpers->handleSession('novalnet_cc', 'set', 'paymentDetails', $sessionValues);
                }
            }
            else
            {
                $paymentDetail = $this->helpers->handleSession('novalnet_cc', 'get', 'paymentDetails');
                if (empty($paymentDetail['pan_hash']))
                {
                    $uniqueId      = vRequest::getVar('nn_cc_uniqueid_' . $cart->virtuemart_paymentmethod_id, '');
                    $save_card     = vRequest::getVar('cc_save_card' . $cart->virtuemart_paymentmethod_id, '');
                    $sessionValues = array(
                        'pan_hash' => $panHash,
                        'cc_unique_id' => $uniqueId
                    );
                    $this->helpers->handleSession('novalnet_cc', 'set', 'paymentDetails', $sessionValues);
                    $this->helpers->handleSession('novalnet_cc', 'set', 'save_card', $save_card);
                }
            }
        }
    }

    /**
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment)
     *
     * @param   object   VirtueMartCart $cart     cart oject
     * @param   integer                 $selected ID of the method selected
     * @param   string                  $htmlIn   html content
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
        $css = '
        input[type="checkbox"]:checked + label::before {
        background: none !important;
        border: none !important;
        content: none !important;
        text-indent: none !important;
        }';
        JFactory::getDocument()->addStyleDeclaration($css);

        $htmla = array();
        $html  = '';
        include JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'novalnet_cc' . DS . 'novalnet_cc' . DS . 'tmpl' . DS . 'NovalnetCreditcardForm.php';
        $nnCreditcardForm = new NovalnetCreditcardForm;
        $getSessionValues = $this->helpers->handleSession('novalnet_cc', 'get', 'paymentDetails');

        foreach ($this->methods as $this->_currentMethod)
        {
            if ($this->checkConditions($cart, $this->_currentMethod, $cart->cartPrices))
            {
                $maskedPatterns                    = $this->helpers->getStoredPattern($this->_currentMethod, false, false);
                $methodSalesPrice                  = $this->setCartPrices($cart, $cart->pricesUnformatted, $this->_currentMethod);
                $this->_currentMethod->$methodName = $this->helpers->renderNovalnetPluginName($this->_currentMethod);
                $html                              = $this->getPluginHtml($this->_currentMethod, $selected, $methodSalesPrice);
                $html .= '<br>'.$this->helpers->showZeroAmountBookingNotificationMessage($this->_currentMethod);
                if ($this->_currentMethod->shopping_type == 'one_click')
                {
                    if (!empty($getSessionValues['pan_hash']))
                    {
                        $html .= '';
                    }
                    elseif (($maskedPatterns) && empty($this->_currentMethod->enable_cc3d) && empty($this->_currentMethod->cc3d_force))
                    {
                        $html .= $nnCreditcardForm->renderMaskedForm($maskedPatterns, $this->_currentMethod, true);
                    }
                    else
                    {
                        $html .= $nnCreditcardForm->renderLocaliframeForm($this->_currentMethod);
                    }
                }
                else
                {
                    if (empty($getSessionValues['pan_hash']))
                        $html .= $nnCreditcardForm->renderLocaliframeForm($this->_currentMethod);
                }
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
     * @param   array  $cartPrices     which is cart prices
     * @param   string $cartPricesName which is cart price name
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
     * @param   integer $virtuemartPaymentMethodId   payment method id
     * @param   integer $paymentCurrencyId           selected payment currency id
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
     * @param   object $cart           cart objects
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
     * @param   integer $virtuemartOrderId         virtumart order id
     * @param   integer $virtuemartPaymentId       payment method id
     * @param   string  $paymentName               payment name
     *
     * @return string
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
     * @param   object $cart cart objects
     *
     * @return boolean
     */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
            return null;

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id)))
            return false;

        $panHash    = vRequest::getVar('pan_hash' . $cart->virtuemart_paymentmethod_id, '');
        $paymentRef = vRequest::getVar('payment_ref_' . $cart->virtuemart_paymentmethod_id, '');
        if (vRequest::getInt('virtuemart_paymentmethod_id') && ($panHash || $paymentRef))
        {
            $paymentDetail    = $this->helpers->handleSession('novalnet_cc', 'get', 'paymentDetails');
            $oneClickShopping = vRequest::getVar('nncc_oneclick' . $cart->virtuemart_paymentmethod_id, '');
            $oneClickShopping = (isset($oneClickShopping)) ? $oneClickShopping : $paymentDetail['nncc_oneclick'];
            if (($this->_currentMethod->shopping_type == 'one_click') && !empty($oneClickShopping) && empty($panHash))
            {
                $paymentReference = $this->helpers->handleSession('novalnet_cc', 'get', 'paymentDetails');
                if (empty($paymentReference['payment_ref']) && empty($paymentReference['nncc_oneclick']))
                {
                    $sessionValues = array(
                        'payment_ref' => $paymentRef,
                        'nncc_oneclick' => $oneClickShopping
                    );
                    $this->helpers->handleSession('novalnet_cc', 'set', 'paymentDetails', $sessionValues);
                }
            }
            else
            {
                $paymentDetail = $this->helpers->handleSession('novalnet_cc', 'get', 'paymentDetails');
                if (empty($paymentDetail['pan_hash']))
                {
                    $uniqueId      = vRequest::getVar('nn_cc_uniqueid_' . $cart->virtuemart_paymentmethod_id, '');
                    $save_card     = vRequest::getVar('cc_save_card' . $cart->virtuemart_paymentmethod_id, '');
                    $sessionValues = array(
                        'pan_hash' => $panHash,
                        'cc_unique_id' => $uniqueId
                    );
                    $this->helpers->handleSession('novalnet_cc', 'set', 'paymentDetails', $sessionValues);
                    $this->helpers->handleSession('novalnet_cc', 'set', 'save_card', $save_card);
                }
            }
        }
        $paymentDetail = $this->helpers->handleSession('novalnet_cc', 'get', 'paymentDetails');
        if (empty($paymentDetail))
            return false;
    }

    /**
     * Even triggered on print option in an order
     *
     * @param   string  $orderNumber The number
     * @param   integer $methodId    method used for this order
     *
     * @return mixed
     */
    public function plgVmonShowOrderPrintPayment($orderNumber, $methodId)
    {
        return $this->onShowOrderPrint($orderNumber, $methodId);
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
     * @param   string  $name  payment name
     * @param   integer $id    id
     * @param   string  $table table
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
     * @param   string $html html content
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

        $virtuemartPaymentMethodId = vRequest::getInt('pm', 0);
        $orderNumber               = vRequest::getString('on', 0);

        if (!($method = $this->getVmPluginMethod($virtuemartPaymentMethodId)))
            return null;

        if (!$this->selectedThisElement($method->payment_element))
            return null;

        if (!($virtuemartOrderId = VirtueMartModelOrders::getOrderIdByOrderNumber($orderNumber)))
            return null;

        if (class_exists('vmLanguage'))
            vmLanguage::loadJLang('com_virtuemart_orders', true);

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
            $orderStatus                 = ($response['tid_status'] == '98') ? $this->helpers->confirmed_order_status : $method->nn_order_status;
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
                $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, $orderStatus, $comments);
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

        $virtuemartPaymentMethodId = vRequest::getInt('pm', 0);
        $orderNumber               = vRequest::getString('on', 0);
        vmLanguage::loadJLang('com_virtuemart_orders', true);

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
