<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_sepa
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: novalnet_sepa.php
 */

// No direct access
if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    include JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';

if (!class_exists('NovalnetUtilities'))
    include JPATH_PLUGINS . DS . 'vmpayment' . DS . 'novalnet_payment' . DS . 'novalnet_payment' . DS . 'helpers' . DS . 'NovalnetUtilities.php';

/**
 * Direct Debit SEPA payment class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class plgVmPaymentnovalnet_sepa extends vmPSPlugin
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
        $this->helpers->loadNovalnetJs('novalnet_sepa', 'admin');
        $this->helpers->loadNovalnetJs('novalnet_sepa', 'site');
    }

    /**
     * To create a payment method table
     *
     * @return boolean
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Novalnet Direct Debit SEPA payment Table');
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

        $paymentDetails = $this->helpers->handleSession('novalnet_sepa', 'get', 'paymentDetails');
        if ((empty($paymentDetails['iban']) || empty($paymentDetails['sepa_account_holder']))  && empty($paymentDetails['payment_ref']))
            $this->helpers->handleMessage(JText::_('VMPAYMENT_NOVALNET_SEPA_DETAILS_INVALID_ERROR'));
        
        $this->helpers->handleSession($method->payment_element, 'set', 'nncart', serialize($cart));
        $paymentDetails = $this->helpers->handleSession('novalnet_sepa', 'get', 'paymentDetails');
        $data                  = $this->helpers->getNovalnetParameters($method, $cart, $order);
        if (!empty($paymentDetails['iban']))
        {
            $data['bank_account_holder'] = html_entity_decode($paymentDetails['sepa_account_holder']);
            $data['iban']                = html_entity_decode($paymentDetails['iban']);
        }
        else
        {
            $data['payment_ref'] = $paymentDetails['payment_ref'];
            unset($data['create_payment_ref']);
        }

        if (!empty($method->sepa_nn_due_date))
            $data['sepa_due_date'] = date('Y-m-d', strtotime('+ ' . $method->sepa_nn_due_date . ' day'));
            
        $guaranteeDetails = $this->helpers->handleSession($method->payment_element, 'get', 'guaranteeDetails');
        if (!empty($guaranteeDetails))
            $data = array_merge($data, $guaranteeDetails);

        VmConfig::loadJLang('com_virtuemart', true);
        VmConfig::loadJLang('com_virtuemart_orders', true);

        if (!class_exists('VirtueMartModelOrders'))
            include VMPATH_ADMIN . DS . 'models' . DS . 'orders.php';

        $response = $this->helpers->sendRequest($data);
        $response = array_merge($data, $response);
        $message = $this->helpers->buildTransactionComments($response);
        if ($response['status'] == '100')
        {
            $response['payment_name']    = $method->payment_name;
            $response['payment_element'] = $method->payment_element;
            $dbValues                    = $this->helpers->storePaymentdetails($order, $response, $method, $basicDetails);
            $this->storePSPluginInternalData($dbValues);
            $orderStatus = ($response['tid_status'] == '75') ? $method->guarantee_order_status : (($response['tid_status'] == '99') ? $this->helpers->confirmed_order_status : $method->nn_order_status);
            $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, $orderStatus, $message);
            $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
            $html = $this->helpers->renderNovalnetHtml($dbValues);
            $cart->setCartIntoSession();
            $this->helpers->handleSession('novalnet_sepa', 'clear', 'paymentDetails');
            $this->helpers->handleSession('novalnet_sepa', 'clear', 'nncart');
            $this->helpers->handleSession('novalnet_sepa', 'clear', 'birthDate');
            $cart->emptyCart();
            vRequest::setVar('html', $html);
            return true;
        }
        else
        {
            $msg = $this->helpers->getPaygateMessage($response);
            $this->helpers->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, 'X', $msg . '<br />' . $message);
            $this->helpers->handleSession('novalnet_sepa', 'clear', 'nncart');
            $this->helpers->handleSession('novalnet_sepa', 'clear', 'birthDate');
            $this->helpers->handleSession('novalnet_sepa', 'clear', 'paymentDetails');
            $this->helpers->handleMessage($msg);
        }
    }

    /**
     * Event triggered on backend payment details display of an order
     *
     * @param   integer $virtuemartOrderId     the order id from shop
     * @param   string  $virtuemartPaymentId   the payment method id
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
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
        {
            $this->helpers->handleSession('novalnet_sepa', 'reset');
            return null;
        }

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id)))
        {
            $this->helpers->handleSession('novalnet_sepa', 'reset');
            return false;
        }
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
        $this->ibanName = '';
        if (!$this->helpers->getMerchantDetails('auto_key'))
            return false;

        $methodName = $this->_psType . '_name';
        if ($this->getPluginMethods($cart->vendorId) === 0)
        {
            if (empty($this->_name))
            {
                $app = JFactory::getApplication();
                $app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
            }
            return false;
        }
        $html  = '';
        $htmla = array();
        include JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'novalnet_sepa' . DS . 'novalnet_sepa' . DS . 'tmpl' . DS . 'NovalnetsepaForm.php';
        $nnSepaForm       = new NovalnetsepaForm;
        $getsessionValues = $this->helpers->handleSession('novalnet_sepa', 'get', 'paymentDetails');

        foreach ($this->methods as $this->_currentMethod)
        {
            if ($this->checkConditions($cart, $this->_currentMethod, $cart->cartPrices))
            {
                $methodSalesPrice                  = $this->setCartPrices($cart, $cart->pricesUnformatted, $this->_currentMethod);
                $this->_currentMethod->$methodName = $this->helpers->renderNovalnetPluginName($this->_currentMethod);
                $html                              = $this->getPluginHtml($this->_currentMethod, $selected, $methodSalesPrice);
                $html .= '<noscript> <br />
                    <input type="hidden" name="novalnetSepaNoscript" value="1">
                    <style>#novalnet_sepa_localform,#novalnet_sepa_maskedform{display:none;}</style>
                    </noscript>';
                $maskedPatterns = $this->helpers->getStoredPattern($this->_currentMethod);
                $html .= $this->helpers->showZeroAmountBookingNotificationMessage($this->_currentMethod);
                if ($this->_currentMethod->shopping_type == 'one_click')
                {
                    if (!empty($getsessionValues['iban']))
                    {
                        $html .= '<br/><table order="0" cellspacing="0" cellpadding="2" width="100%">';
                        $html .= $nnSepaForm->renderGuarantee($cart, $this->_currentMethod);
                        $html .= '</table>';
                    }
                    elseif ($this->_currentMethod->shopping_type == 'one_click' && ($maskedPatterns))
                    {
                        $html .= '<br/><a id="sepa_toggle_name" onclick="novalnet_sepa.sepaToggleName()" style="color: #095197; text-decoration: underline; font-weight: bold;">' . JText::_('VMPAYMENT_NOVALNET_UPDATE_DETAILS') . '</a><table order="0" cellspacing="0" cellpadding="2" width="100%">';
                        $html .= $nnSepaForm->renderMaskedForm($this->_currentMethod, $cart, $maskedPatterns);
                        $html .= $nnSepaForm->renderLocalForm($cart, $this->_currentMethod, 'none');
                        $html .= $nnSepaForm->renderGuarantee($cart, $this->_currentMethod);
                        $html .= '</table>';
                    }
                    else
                    {
                        $html .= '<br/><table order="0" cellspacing="0" cellpadding="2" width="100%">';
                        $html .= $nnSepaForm->renderLocalForm($cart, $this->_currentMethod, '');
                        $html .= $nnSepaForm->renderGuarantee($cart, $this->_currentMethod);
                        $html .= '</table>';
                    }
                }
                else
                {
                    $html .= '<table order="0" cellspacing="0" cellpadding="2" width="100%">';
                    $html .= $nnSepaForm->renderLocalForm($cart, $this->_currentMethod, '');
                    $html .= $nnSepaForm->renderGuarantee($cart, $this->_currentMethod);
                    $html .= '</table>';
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
     * @param   object VirtueMartCart $cart           the actual cart object
     * @param   array                 $cartPrices     the cart prices
     * @param   string                $cartPricesName the cart price name
     *
     * @return boolean
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cartPrices, &$cartPricesName)
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
        return $this->onSelectedCalculatePrice($cart, $cartPrices, $cartPricesName);
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
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cartPrices = array(), &$paymentCounter) {

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
        if (!($paymentTable = $this->getDataByOrderId($virtuemartOrderId)))
            return null;

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
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id))
            return null;

        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id)))
            return false;

        $return         = true;
        $paymentDetails = $this->helpers->handleSession('novalnet_sepa', 'get', 'paymentDetails');

        if (!empty($this->_currentMethod->one_click) && $this->_currentMethod->one_click != '1')
        {
            $accountHolder = vRequest::getVar('account_holder' . $cart->virtuemart_paymentmethod_id, '');

            if (isset($mandateConfirm) && $mandateConfirm != 'on' && !empty($accountHolder))
            {
                $this->helpers->handleSession('novalnet_sepa', 'reset');
                return false;
            }
        }
        $paymentRef = vRequest::getVar('payment_ref' . $cart->virtuemart_paymentmethod_id, '');
        $iban       = vRequest::getVar('bank_account' . $cart->virtuemart_paymentmethod_id, '');
        if (vRequest::getInt('virtuemart_paymentmethod_id'))
        {
            $paymentDetails = $this->helpers->handleSession('novalnet_sepa', 'get', 'paymentDetails');
            $nnsepaOneclick = vRequest::getVar('nnsepa_oneclick' . $cart->virtuemart_paymentmethod_id, '');
            if ($nnsepaOneclick == '1')
            {
                $sessionValues = array('payment_ref' => $paymentRef);
                $this->helpers->handleSession('novalnet_sepa', 'set', 'paymentDetails', $sessionValues);
            }
            else
            {
                if (empty($paymentDetails['iban']))
                {
                    $accountholder = vRequest::getVar('account_holder' . $cart->virtuemart_paymentmethod_id, '');
                    $iban          = vRequest::getVar('bank_account' . $cart->virtuemart_paymentmethod_id, '');
                    $sessionValues = array('sepa_account_holder' => $accountholder, 'iban' => $iban);
                    $save_card     = vRequest::getVar('sepa_save_card' . $cart->virtuemart_paymentmethod_id, '');
                    $this->helpers->handleSession('novalnet_sepa', 'set', 'paymentDetails', $sessionValues);
                    $this->helpers->handleSession('novalnet_sepa', 'set', 'save_card', $save_card);
                }
            }
        }
        if ($this->_currentMethod->guarantee)
            $return = $this->validateDateOfBirth($this->_currentMethod, $cart);
        return $return;
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
     * @param   string $data
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
     * Event triggered on validation purpose
     *
     * @param   object  $_currentMethod   current plugin
     * @param   integer $cartDetails      current payment method id
     *
     * @return boolean
     */
    public function validateDateOfBirth($_currentMethod, $cartDetails)
    {
        if (!empty($_currentMethod->guarantee))
        {
            $guaranteeError        = $this->helpers->handleSession('novalnet_sepa', 'get', 'error_guarantee');
            $guaranteeErrorMessage = $this->helpers->handleSession('novalnet_sepa', 'get', 'guarantee_error_message');
            if (isset($guaranteeError) && !empty($guaranteeError))
            {
                vmWarn(JText::_($guaranteeErrorMessage));
                return false;
            }

            $return    = true;
            $company   = $cartDetails->BT['company'] != '' ? $cartDetails->BT['company'] : $cartDetails->ST['company'];
            $birthDate = vRequest::getVar('birthDate' . $cartDetails->virtuemart_paymentmethod_id, '');
            if (!empty($birthDate) && $company == '')
                $this->helpers->handleSession($_currentMethod->payment_element, 'set', 'birthDate', $birthDate);

            $sessionBirthDate = $this->helpers->handleSession($_currentMethod->payment_element, 'get', 'birthDate');
            
            // To format the order amount
            $prices    = $cartDetails->getCartPrices();
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
            if (!empty($_currentMethod->guarantee) && !$this->helpers->ageValidation($_currentMethod, (!$birthDate) ? $sessionBirthDate : $birthDate, $company, $_currentMethod->guarantee_force))
            {
                vmWarn(JText::_('VMPAYMENT_NOVALNET_GURENTEE_PAYMENT_AGE_VALIDATION'));
                $return = false;
            }
            return $return;
        }
        return true;
    }
}
