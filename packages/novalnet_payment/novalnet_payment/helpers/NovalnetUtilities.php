<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage NovalnetPayments
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: NovalnetUtilities.php
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\Registry\Registry;

/**
 * NovalnetUtilities payment class
 *
 * @package NovalnetPayments
 * @since   11.1
 */

class NovalnetUtilities
{
    /**
     * @var array $redirectPayments Novalnet redirect payment
     */
    public $redirectPayments = array('novalnet_instantbank', 'novalnet_ideal', 'novalnet_eps', 'novalnet_paypal', 'novalnet_giropay', 'novalnet_przelewy24');

    /**
     * @var array $dataParams Encode/Decode params
     */
    public $dataParams = array('auth_code', 'product', 'tariff', 'test_mode', 'amount');

    /**
     * @var string $tableName from current table name
     */
    public $tableName;

    /**
     * To get parameters from the shop
     *
     * @param   object $method current payment method object
     * @param   object $cart   current cart object
     * @param   object $order  current order object
     *
     * @return mixed
     */
    public function getNovalnetParameters($method, $cart, $order)
    {
        $this->getMerchantDetails();
        if (!empty($order))
        {
            $usrBT = !empty($order['details']['BT']) ? $order['details']['BT'] : $order['details']['ST'];

            // To format the order amount
            $orderTotal = !empty($order['details']['ST']->order_total) ? $order['details']['ST']->order_total : $order['details']['BT']->order_total;
            $orderTotal = $this->formatAmount($orderTotal);
            $currency   = $order['details']['BT']->order_currency;
            $company    = !empty($order['details']['ST']->company) ? $order['details']['ST']->company : (($order['details']['BT']->company) ? $order['details']['BT']->company : '');
        }
        else
        {
            $usrBT  = !empty($cart->BT) ? (object) $cart->BT : (object) $cart->ST;
            $prices = $cart->getCartPrices();
            // To format the order amount
            $orderTotal = $this->formatAmount($prices['billTotal']);
            $currency   = $cart->pricesCurrency;
            $company    = !empty($cart->ST['company']) ? $cart->ST['company'] : (($cart->BT['company']) ? $cart->BT['company'] : '');
        }

        // To get the payment keys & payment types
        $paymentData = $this->getPaymentDetails($method->payment_element);
        $street      = $usrBT->address_1;

        if (!empty($usrBT->address_2))
            $street = $usrBT->address_1 . ' ' . $usrBT->address_2;

        $tariffVal          = explode('-', $this->tariff_id);
        $tariffType         = explode('-', $this->getMerchantDetails('tariff_type_selected'));
        $data               = array(
            'vendor'        => $this->vendor_id,
            'product'       => $this->product_id,
            'tariff'        => $tariffVal[1],
            'auth_code'     => $this->auth_code,
            'key'           => $paymentData['key'],
            'payment_type'  => $paymentData['payment_type'],
            'test_mode'     => $method->nn_test_mode,
            'amount'        => $orderTotal,
            'gender'        => 'u',
            'currency'      => shopFunctions::getCurrencyByID($currency, 'currency_code_3'),
            'email'         => $usrBT->email,
            'first_name'    => $usrBT->first_name,
            'last_name'     => $usrBT->last_name,
            'street'        => $street,
            'city'          => $usrBT->city,
            'zip'           => $usrBT->zip,
            'country'       => ShopFunctions::getCountryByID($usrBT->virtuemart_country_id, 'country_2_code'),
            'country_code'  => ShopFunctions::getCountryByID($usrBT->virtuemart_country_id, 'country_2_code'),
            'search_in_street' => '1',
            'lang'          => strtoupper($this->getLang()),
            'remote_ip'     => $this->getRemoteAddr(),
            'customer_no'   => JFactory::getSession()->get('user')->id,
            'system_name'   => 'Joomla-Virtuemart',
            'system_version'=> JVERSION . '-' . vmVersion::$RELEASE . '-NN11.3.0',
            'system_url'    => JURI::root(),
            'system_ip'     => $this->getServerAddr(),
            'input1'        => 'nn_sid',
            'inputval1'     => JFactory::getSession()->getId()
        );

        if (in_array($method->payment_element, array('novalnet_invoice', 'novalnet_cc', 'novalnet_sepa', 'novalnet_paypal')) && $method->onhold_action == 'authorize' && $data['amount'] >= $method->onhold_amount)
            $data['on_hold'] = '1';

        if (!empty($usrBT->phone_1))
            $data['tel'] = $usrBT->phone_1;

        if (!empty($usrBT->phone_2))
            $data['mobile'] = $usrBT->phone_2;

        if (!empty($usrBT->fax))
            $data['fax'] = $usrBT->fax;

        if (!empty($company))
            $data['company'] = $company;

        if (!empty($this->notify_url))
            $data['notify_url'] = $this->notify_url;

        if (!empty($order))
            $data['order_no'] = $order['details']['BT']->order_number;

        if (!empty($this->referrer_id) && is_numeric($this->referrer_id))
            $data['referrer_id'] = $this->referrer_id;

       if (in_array($method->payment_element, array('novalnet_cc', 'novalnet_sepa', 'novalnet_paypal')) && (!empty($method->shopping_type) && $method->shopping_type == 'zero_amount' || ($method->shopping_type == 'one_click' && $this->handleSession($method->payment_element, 'get', 'save_card') == '1')))
       {
           $data['create_payment_ref'] = '1';
           if ($method->shopping_type == 'zero_amount' && $tariffType[0] == '2')
           {
               $data['amount'] = 0;
               unset($data['on_hold']);
           }
           $this->handleSession($method->payment_element, 'set', 'payment_request', $data);
       }
       // Adds redirect payment parameters.
        if (in_array($method->payment_element, $this->redirectPayments) || ($method->payment_element == 'novalnet_cc' && ($method->enable_cc3d == '1'|| $method->cc3d_force == '1')))
        {
            $data['uniqid']              = $this->randomString();
            $data                        = $this->encode($data);
            $data['hash']                = $this->generateHashValue($data);
            $data['return_url']          = JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . vRequest::getInt('Itemid') . '&lang=' . vRequest::getCmd('lang', '');
            $data['return_method']       = $data['error_return_method'] = 'POST';
            $data['error_return_url']    = JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . vRequest::getInt('Itemid') . '&lang=' . vRequest::getCmd('lang', '');
            $data['user_variable_0']     = JURI::root();
            $data['implementation']      = 'ENC';
       }
       return $data;
    }

    /**
     * Function to check affiliate order
     *
     * @return boolean
     */
    public function checkAffiliateOrder()
    {
        $affiliateId = JFactory::getSession()->get('nn_aff_id', 0, 'novalnet');
        $userId      = JFactory::getSession()->get('user')->id;
        if (!$affiliateId && $userId)
        {
            $dbResult = (array) $this->selectQuery(array(
                'table_name' => '#__virtuemart_payment_plg_novalnet_payment',
                'column_name' => array(
                    'affiliate_id'
                ),
                'condition' => 'customer_id=' . $userId,
                'order' => 'virtuemart_order_id DESC'
            ));

            $affiliateId = !empty($dbResult['affiliate_id']) ? $dbResult['affiliate_id'] : '';
            JFactory::getSession()->set('nn_aff_id', $affiliateId, 'novalnet');
        }

        if (!empty($affiliateId))
        {
            $dbResult = $this->selectQuery(array(
                'table_name' => '#__novalnet_affiliate_detail',
                'column_name' => array(
                    'aff_authcode',
                    'aff_accesskey'
                ),
                'condition' => 'aff_id=' . $affiliateId,
                'order' => 'id DESC'
            ));

            if (!empty($dbResult))
            {
                $this->vendor_id  = $affiliateId;
                $this->auth_code  = $dbResult->aff_authcode;
                $this->access_key = $dbResult->aff_accesskey;
                return true;
            }
        }
        return false;
    }

    /**
     * To get the merchant details from the shop
     *
     * @param   string $type type of configuration will get
     *
     * @return mixed
     */
    public function getMerchantDetails($type = 'basic')
    {
        $plugin = JPluginHelper::getPlugin('system', 'novalnet');

        if ($plugin)
        {
           $pluginParams = new JRegistry($plugin->params);
           switch ($type)
           {
                case 'basic':
                $params    = array('vendor_id', 'auth_code', 'product_id', 'tariff_id', 'access_key', 'manual_check_limit', 'payment_logo', 'notify_url', 'referrer_id', 'curl_timeout', 'confirmed_order_status', 'canceled_order_status');
                $affiliate = $this->checkAffiliateOrder();

                    if ($affiliate)
                        unset($params[0], $params[1], $params[4]);
                break;
                case 'vendorscript':
                $params = array(
                    'vendorscript_test_mode',
                    'enable_vendorscript_mail',
                    'vendorscript_mail_to',
                    'vendorscript_mail_bcc'
                );
                break;
                case 'auto_key':
                    return $pluginParams->get('auto_key');

                case 'tariff_type_selected':
                    return $pluginParams->get('tariff_id');

            }
            foreach ($params as $param)
                $this->{$param} = $pluginParams->get($param);
        }
    }


    /**
     * To format the order amount
     *
     * @param   decimal $amount current order amount
     * @param   boolean $cents  used to get amount was cents or euro
     *
     * @return int
     */
    public function formatAmount($amount, $cents = false)
    {
        return ($cents) ? sprintf('%0.2f', $amount) / 100 : sprintf('%0.2f', $amount) * 100;
    }

    /**
     * To get server Ip adddress
     *
     * @return mixed
     */
    public function getServerAddr()
    {
        return JRequest::get('SERVER')['SERVER_ADDR'];
    }

    /**
     * To get the payment keys & payment types
     *
     * @param   string  $payment   current payment method name
     * @param   boolean $guarantee current payment method name was guarantee or not
     *
     * @return string
     */
    public function getPaymentDetails($payment, $guarantee = false)
    {
        $paymentDetails = array(
            'novalnet_instantbank' => array('key' => 33, 'payment_type' => 'ONLINE_TRANSFER'),
            'novalnet_cc' => array('key' => 6, 'payment_type' => 'CREDITCARD'),
            'novalnet_ideal' => array('key' => 49, 'payment_type' => 'IDEAL'),
            'novalnet_invoice' => array('key' => 27, 'payment_type' => 'INVOICE'),
            'novalnet_paypal' => array('key' => 34, 'payment_type' => 'PAYPAL'),
            'novalnet_prepayment' => array('key' => 27, 'payment_type' => 'PREPAYMENT'),
            'novalnet_sepa' => array('key' => 37, 'payment_type' => 'DIRECT_DEBIT_SEPA'),
            'novalnet_eps' => array('key' => 50, 'payment_type' => 'EPS'),
            'novalnet_giropay' => array('key' => 69, 'payment_type' => 'GIROPAY'),
            'novalnet_przelewy24' => array('key' => 78, 'payment_type' => 'PRZELEWY24'),
            'novalnet_cashpayment' => array('key' => 59, 'payment_type' => 'CASHPAYMENT'));
        $guaranteeDetails = array(
            'novalnet_invoice' => array('key' => 41, 'payment_type' => 'GUARANTEED_INVOICE'),
            'novalnet_sepa' => array('key' => 40, 'payment_type' => 'GUARANTEED_DIRECT_DEBIT_SEPA'));
        return (!empty($guarantee)) ? $guaranteeDetails[$payment] : $paymentDetails[$payment];
    }

    /**
     * To get shop language
     *
     * @return string
     */
    public function getLang()
    {
        $language = JFactory::getLanguage();
        return strtolower(substr($language->get('tag'), 0, 2));
    }

    /**
     * Handles request and response with Novalnet server
     *
     * @param   array  $request to request param
     * @param   string $type    to request type
     *
     * @return mixed
     */
    public function sendRequest($request, $type = 'paygate')
    {
        // To get the merchant details from the shop
        $this->getMerchantDetails();
        $options   = new Registry;
        $transport = JHttpFactory::getAvailableDriver($options);
        $response  = $transport->request('POST', new JUri($this->getPayportUrl($type)), $request, null, $this->curl_timeout, '');
        $parsed    = isset($response->body) ? $response->body : '';
        parse_str($parsed, $result);
        return $result;
    }
    
    /**
     * To handle response params
     *
     * @param  string  $url       which url to redirect
     * @param  array   $response  transaction response
     *
     * @return mixed
     */
    public function handleResponseParams( $url, $response ) {
		header_remove('Set-Cookie');
		
		$html  = vmText::_('VMPAYMENT_NOVALNET_REDIRECT_RESPONSE_MSG');
		$html .= '<form action="' . $url . '" method="post" name="vm_nnresponse_form" id="vm_nnresponse_form" accept-charset="UTF-8">';
        foreach ($response as $name => $value)
            $html .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';

        $html .= '<input type="submit" value="" style="display:none;" /></form>';
        $html .= '<script>
					document.getElementById("vm_nnresponse_form").submit();
				</script>';
		echo $html;
		JExit();
	}

    /**
     * To get the payport Url
     *
     * @param   string $payment payment type
     *
     * @return string
     */
    public function getPayportUrl($payment)
    {
        $payportUrl = array(
            'paygate' => 'paygate.jsp',
            'novalnet_cc' => 'pci_payport',
            'novalnet_paypal' => 'paypal_payport',
            'novalnet_przelewy24' => 'globalbank_transfer',
            'novalnet_ideal' => 'online_transfer_payport',
            'novalnet_instantbank' => 'online_transfer_payport',
            'novalnet_giropay' => 'giropay',
            'novalnet_eps' => 'giropay'
        );
        return 'https://payport.novalnet.de/' . $payportUrl[$payment];
    }

    /**
     * To set order comments
     *
     * @param   array $response Which response values based comments
     *
     * @return string
     */
    public function buildTransactionComments($response)
    {
        if (class_exists('vmLanguage'))
            vmLanguage::loadJLang('com_virtuemart_countries');
        $server  = JRequest::get('SERVER');
        $message = Jtext::_('VMPAYMENT_NOVALNET_TRANSACTION_ID') . ': ' . $response['tid'] . '<br>';
        $message .= $response['test_mode'] ? Jtext::_('VMPAYMENT_NOVALNET_TEST_ORDER') . '<br>' : '';
        if (in_array($response['payment_id'], array('40', '41')))
        {
            $message .= '<br>' . Jtext::_('VMPAYMENT_NOVALNET_ADMIN_GUARANTEE_COMMENTS') . '<br>';
            if($response['tid_status'] == '75')
             $message .= ($response['payment_type'] == '41') ? Jtext::_('VMPAYMENT_NOVALNET_INVOICE_GUARANTEE_COMMENTS') . '<br>' : Jtext::_('VMPAYMENT_NOVALNET_SEPA_GUARANTEE_COMMENTS') . '<br>';
        }
        return $message;
    }

    /**
     * To display order details in success page
     *
     * @param   array $viewData current order response array
     * @param   array $response current order response
     *
     * @return mixed
     */
    public function renderNovalnetHtml($viewData, $response = false)
    {
        if (!empty($viewData['tid']))
        {
            $orderInfo = '<div class="novalnet_payment_payment_name" style="width: 100%">
                <span class="novalnet_payment_payment_name_title">' . JText::_('VMPAYMENT_NOVALNET_PAYMENT_NAME') . '</span>
                 : ' . $viewData["payment_name"] . '</div>';
            $orderInfo .= '<div class="novalnet_payment_payment_name" style="width: 100%">
                <span class="novalnet_payment_payment_name_title">' . JText::_('VMPAYMENT_NOVALNET_ORDER_NUMBER') . '</span>
                 : ' . $viewData["order_number"] . '</div>';
            $orderInfo .= '<div class="novalnet_payment_payment_name" style="width: 100%">
                <span class="novalnet_payment_payment_name_title">' . JText::_('VMPAYMENT_NOVALNET_TRANSACTION_ID') . '</span>: ' . $viewData["tid"] . '</div>';
            $testMode = unserialize($viewData['vendor_details']);
            if ($testMode['test_mode'] == '1')
                $orderInfo .= '<div class="novalnet_payment_order_type" style="width: 100%">
                <span class="novalnet_payment_order_type_title">' . JText::_('VMPAYMENT_NOVALNET_TEST_ORDER') . '</span></div>';
            $orderInfo .= '<a class="vm-button-correct" href="' . JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $viewData["order_number"], false) . '"> ' . JText::_('VMPAYMENT_NOVALNET_ORDER_VIEW_ORDER') . '</a>';
            if ($viewData["payment_element"] == "novalnet_cashpayment")
            {
                $url = ($response["test_mode"] == '1') ? "https://cdn.barzahlen.de/js/v2/checkout-sandbox.js" : "https://cdn.barzahlen.de/js/v2/checkout.js";
                $orderInfo .= '<a class="vm-button-correct" href="javascript:bzCheckout.display();"> ' . JText::_('VMPAYMENT_PAYNOW_BARZHALEN') . '</a>';
                $orderInfo .= "<style type='text/css'>
                                #bz-checkout-modal { position: fixed !important; }
                               </style>";
                $orderInfo .= '<script src="' . $url . '" class="bz-checkout" data-token="' . $response["cp_checkout_token"] . '"></script>';
            }
            $this->handleSession($viewData["payment_element"], 'reset');
            return $orderInfo;
        }
        else
        {
            return false;
        }
    }

    /**
     * Render the payment information in checkout page
     *
     * @param   object  $plugin   which is current plugin name
     *
     * @return mixed
     */
    public function renderNovalnetPluginName($plugin)
    {
        $return     = '';
        $testMode   = '';
        $pluginName = 'payment_name';
        $pluginDesc = 'payment_desc';
        $image      = '';
        $paymentElement = !empty($plugin->element) ? $plugin->element : $plugin->payment_element;
        $logo = $plugin->nn_payment_logos;

        if (in_array($paymentElement, array('novalnet_ideal', 'novalnet_eps', 'novalnet_giropay', 'novalnet_instantbank', 'novalnet_przelewy24')) || ($paymentElement == 'novalnet_cc' && ($plugin->enable_cc3d == '1' || $plugin->cc3d_force == '1'))) {
            $description = '<br/>' . JText::_('VMPAYMENT_NOVALNET_REDIRECTION_DESC');
        }
        else
        {
            $description = '<br/>' . JText::_('VMPAYMENT_' . strtoupper($paymentElement) . '_PAYMENT_DESC');
        }

        if (!empty($plugin->$pluginDesc))
            $description = '<span class="payment_name_description">' . $plugin->$pluginDesc . '</span>';

        if ($paymentElement == 'novalnet_cc')
        {
            $image .= $this->displayLogos($logo, $paymentElement);
            if ($plugin->amex_logo == '1')
                $image .= $this->displayLogos('novalnet_cc_amex.png', $paymentElement);
            if ($plugin->maestro_logo == '1')
                $image .= $this->displayLogos('novalnet_cc_maestro.png', $paymentElement);
        }
        else
        {
            $image .= $this->displayLogos($logo, $paymentElement);
        }
        if (!empty($plugin->nn_test_mode))
            $testMode = '<br/><span style="color:red;font-weight:bold">' . JText::_('VMPAYMENT_NOVALNET_TEST_MODE_DESCRIPTION') . '</span>';

        if ($plugin->nn_end_customer)
            $description .= '<br><br>' . $plugin->nn_end_customer;

        return $return . '<span class="payment_name"><b>' . $plugin->$pluginName . '</b></span>' . $image . '' . $description . $testMode;
    }

    /**
     * Render extension to backend
     *
     * @param   object $paymentTable which is order details object
     * @param   object $order        which is order object
     *
     * @return mixed
     */
    public function renderExtension($paymentTable, $order)
    {
        if (in_array($paymentTable->status, array('91', '99', '98', '85', '86', '100', '90')))
        {
            $this->loadNovalnetJs('novalnet_payment', 'admin');
            $zeroBookingPayments  = array('novalnet_cc', 'novalnet_sepa', 'novalnet_paypal');
            $voidcaptureStatuses  = array('91', '99', '98', '85');
            $html                 = '<table class="adminlist table">' . "\n";
            $html .= '<input type="hidden" id="confirm_text" value="' . JText::_('VMPAYMENT_NOVALNET_CONFIRM_CAPTURE') . '" />';
            $html .= '<input type="hidden" id="cancel_text" value="' . JText::_('VMPAYMENT_NOVALNET_CONFIRM_CANCEL') . '" />';
            $html .= '<input type="hidden" id="amount_update_text" value="' . JText::_('VMPAYMENT_NOVALNET_CONFIRM_AMOUNT_UPDATE') . '" />';
            if ($paymentTable->payment_element == 'novalnet_cashpayment')
            {
                $html .= '<input type="hidden" id="duedate_text" value="' . JText::_('VMPAYMENT_NOVALNET_CONFIRM_SLIP_DUEDATE_UPDATE') . '" />';
            }
            else
            {
                $html .= '<input type="hidden" id="duedate_text" value="' . JText::_('VMPAYMENT_NOVALNET_CONFIRM_DUEDATE_UPDATE') . '" />';
            }
            $html .= '<input type="hidden" id="refund_text" value="' . JText::_('VMPAYMENT_NOVALNET_CONFIRM_REFUND') . '" />';
            $html .= '<input type="hidden" id="booked_text" value="' . JText::_('VMPAYMENT_NOVALNET_CONFIRM_BOOKED') . '" />';
            $html .= '<input type="hidden" id="due_date_text" value="' . JText::_('VMPAYMENT_NOVALNET_ADMIN_DUEDATE_EMPTY_ERROR') . '" />';
            $html .= '<input type="hidden" id="sepa_acc_text" value="' . JText::_('VMPAYMENT_NOVALNET_SEPA_DETAILS_INVALID_ERROR') . '" />';
            $html .= '<input type="hidden" name="order_number" id="order_number" value="' . $paymentTable->order_number . '" />';
            $html .= '<span id="loading-img" style="display:none"><img src="' . JURI::root() . '/plugins/vmpayment/novalnet_payment/novalnet_payment/assets/images/novalnet_loader.gif"></span>';

            if (in_array($paymentTable->status, $voidcaptureStatuses))
                 $this->renderVoidCapture($html); // To render void capture field

            $this->zeroBookingEnabled = '0';
            if (in_array($paymentTable->payment_element, $zeroBookingPayments) && ($paymentTable->booked == 0) && (!in_array($paymentTable->status, $voidcaptureStatuses)))
            {
                // To render zero amount booking form
                $this->renderzeroBooking($html);
                $this->zeroBookingEnabled = 1;
            }
            $paidAmount = isset($paymentTable->paid_amount) ? $paymentTable->paid_amount : '';
            $invoiceAmount = (int) $paymentTable->order_total - (int) $paidAmount;

            if (((in_array($paymentTable->payment_key, array('27', '59')) && $paymentTable->status == '100') || $paymentTable->payment_key == '37' && $paymentTable->status == '99') && $invoiceAmount > 0)
                $this->renderAmountUpdate($html);

            if (in_array($paymentTable->payment_key, array('27', '59')) && $paymentTable->status == '100' && $invoiceAmount > 0)
            {
                $paymentDetails = unserialize($paymentTable->payment_details);
                $this->renderDueDateUpdate($paymentDetails['due_date'], $paymentTable->payment_element, $html);
            }
            if ($paymentTable->status == '100' && $this->zeroBookingEnabled != '1')
                // To render the amount refund form
                $this->renderRefund($html);
            
            $html .= '</table>' . "\n";
            return $html;
        }
           return false;
    }

    /**
     * Render the due date update form
     *
     * @param   boolean $dueDate        which is duedate values
     * @param   string  $paymentName    to render html template in extension
     * @param   string  $html           this is payment name
     *
     * @return void
     */
    public function renderDueDateUpdate($dueDate, $paymentName, &$html)
    {
        $title = ($paymentName == 'novalnet_cashpayment') ? JText::_('VMPAYMENT_NOVALNET_CASH_PAYMENT_PAYMENT_SLIP_DATE_CHANGE_TITLE') : JText::_('VMPAYMENT_NOVALNET_ADMIN_DUE_DATE_CHANGE_TITLE');
        $this->setNovalnetTableHeader($title, $html);
        $html .= '<tr>
        <td>' . JHTML::_('behavior.calendar') . JHTML::_('calendar', date("Y-m-d", strtotime($dueDate)), 'duedateUpdate', 'duedateUpdate', '%Y-%m-%d') . '</td><td>
            <a class="NovalnetupdateOrder" name="duedate_update" href="#"><span class="icon-nofloat vmicon vmicon-16-save"></span>' . JText::_('VMPAYMENT_NOVALNET_ADMIN_DEBIT_BUTTON') . '</a>
        </td></tr>';

    }

    /**
     * To get table header
     *
     * @param   string $heading Header of the table
     * @param   string $html    to render html template in extension
     *
     * @return mixed
     */
    public function setNovalnetTableHeader($heading, &$html)
    {
        $html .= '<thead><tr><th style="text-align: center;" colspan="2">' . $heading . '</th></tr></thead>';
    }

    /**
     * Render the zero amount update form
     *
     * @param   string $html to render html template in extension
     *
     * @return void
     */
    public function renderAmountUpdate(&$html)
    {
        $this->setNovalnetTableHeader(JText::_('VMPAYMENT_NOVALNET_ADMIN_AMOUNT_UPDATE_TITLE'), $html);
        $html .= '<tr>';
        $html .= '
        <td>
            <label>' . JText::_('VMPAYMENT_UPDATE_IN_NOVALNET') . '</label>
        </td>
        <td>
            <a class="NovalnetupdateOrder" name="amount_update" href="#"><span class="icon-nofloat vmicon vmicon-16-save"></span>' . JText::_('VMPAYMENT_NOVALNET_ADMIN_DEBIT_BUTTON') . '</a>
        </td>';
        $html .= '</tr>';
    }

    /**
     * encoding scheme
     *
     * @param   array $data  which is payment param
     *
     * @return mixed
     */
    public function encode(&$data)
    {
        foreach ($this->dataParams as $value)
        {
            if (isset($data[$value]))
                $data[$value] = $this->encrypt($data[$value], $data['uniqid']);
        }
        return $data;
    }

    /**
     * Encrypts the input data on the openssl encrypt method.
     *
     * @param string  $input    get the current data
     * @param integer $salt     get the uniqid value
     *
     * @return string
     */
    protected function encrypt($input, $salt)
    {
        // Return Encrypted Data.
        return htmlentities(base64_encode(openssl_encrypt($input, "aes-256-cbc", $this->access_key, true, $salt)));

    }

    /**
     * To build  parameters for redirect payments
     *
     * @param   array  $data         which is payment required parameter
     * @param   string $paymentName  which is currenct payment name
     *
     * @return mixed
     */
    public function buildRedirectform($data, $paymentName)
    {
        $html = '<form action="' . $this->getPayportUrl($paymentName) . '" method="post" name="vm_nnredirect_form" id="nnredirect_form" accept-charset="UTF-8">';
        foreach ($data as $name => $value)
            $html .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';

        $html .= '<input type="submit" value="' . JText::_('VMPAYMENT_NOVALNET_LOADING_MESSAGE') . '" /></form>';
        $html .= '<script>document.getElementById("nnredirect_form").submit();</script>';
        return $html;
    }

    /**
     * To insert the values in the database
     *
     * @param   string $tableName    which is inserted table name
     * @param   array  $insertValues which is inserted values
     *
     * @return boolean
     */
    public function insertQuery($tableName, $insertValues)
    {
        $db      = JFactory::getDbo();
        $query   = $db->getQuery(true);
        $columns = array_keys($insertValues);
        $values  = array_values($insertValues);
        try {
            foreach ($values as $value)
                $insertVal[] = $db->quote($value);

            $query->insert($db->quoteName($tableName))->columns($db->quoteName($columns))->values(implode(',', $insertVal));
            $db->setQuery($query);
            $result = $db->execute();
        }
        catch (RuntimeException $e)
        {
            return false;
        }

        if (!empty($result))
        {
            return $result;
        }
        else
        {
            return false;
        }
    }

    /**
     * To handle the payment session
     *
     * @param   string $paymentName   current payment name
     * @param   string $operation     which is current operation name like (reset|set|get)
     * @param   string $sessionName   which is current name name
     * @param   array  $sessionValues current session values
     *
     * @return void
     */
    public function handleSession($paymentName, $operation, $sessionName = null, $sessionValues = array())
    {
        $session = JFactory::getSession();
        switch ($operation)
        {
            case 'set':
                return $session->set($sessionName, json_encode($sessionValues), $paymentName);

            case 'get':

                if (!is_array($sessionName))
                    return $sessionName = json_decode($session->get($sessionName, 0, $paymentName), true);

                foreach ($sessionName as $name)
                    $nameSession[$name] = json_decode($session->get($name, 0, $paymentName), true);

                return $nameSession;

            case 'clear':

                if (!is_array($sessionName))
                    return $session->clear($sessionName, $paymentName);

                foreach ($sessionName as $name)
                    $session->clear($name, $paymentName);

                return true;

            case 'reset':
                $session->clear('payment_request', $paymentName);
                $session->clear('paymentDetails', $paymentName);
                $session->clear('guaranteeDetails', $paymentName);
                $session->clear('response', $paymentName);
                $session->clear('birthDate', $paymentName);
                $session->clear('save_card', $paymentName);
                break;
        }
    }

    /**
     * To handle the messages
     *
     * @param   string  $msg        success or failure message
     * @param   boolean $successMsg success or failure message to decide the error message
     * @param   boolean $submit     success or failure message to decide the error message
     *
     * @return boolean
     */
    public function handleMessage($msg, $successMsg = false, $submit = false)
    {
        $app = JFactory::getApplication();
        if ($successMsg)
        {
            $app->enqueueMessage(JText::_($msg));
            return false;
        }
        elseif (empty($submit))
        {
            JError::raiseWarning(100, JText::_($msg));
            $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=editpayment', false));
            return false;
        }
        else
        {
            return false;
        }
    }

    /**
     * To get payment stored details
     *
     * @param   object  $order        current order object
     * @param   array   $response     current order response array
     * @param   object  $method       current payment method object
     * @param   object  $basicDetails current payment details object
     * @param   boolean $vendorScript which comment will be formed based on thos vendorScript
     *
     * @return array
     */
    public function storePaymentdetails($order, $response, $method, &$basicDetails, $vendorScript = '')
    {
        $this->getMerchantDetails();
        $tariffId = explode('-', $this->tariff_id);
        $vendorDetails = array(
            'vendor' => $this->vendor_id,
            'product' => $this->product_id,
            'tariff' => $tariffId[1],
            'auth_code' => $this->auth_code,
            'test_mode' => $response['test_mode'],
        );

        if ($response['payment_element'] == 'novalnet_cashpayment')
            $vendorDetails['cash_comments'] = $this->buildOrderComments($response, true);

        $dbValues['virtuemart_order_id']         = $order['details']['BT']->virtuemart_order_id;
        $dbValues['payment_name']                = $response['payment_name'];
        $dbValues['order_number']                = $order['details']['BT']->order_number;
        $dbValues['customer_id']                 = (isset($response['customer_no'])) ? $response['customer_no'] : $order['details']['BT']->virtuemart_user_id;
        $dbValues['payment_element']             = $response['payment_element'];
        $dbValues['tid']                         = $response['tid'];
        $dbValues['vendor_details']              = serialize($vendorDetails);
        $affliateId                              = JFactory::getSession()->get('nn_aff_id', 0, 'novalnet');
        $affliateId                              = (!empty($affliateId)) ? $affliateId : '';
        $dbValues['affiliate_id']                = $affliateId;
        $dbValues['status']                      = isset($response['tid_status']) ? $response['tid_status'] : 0;
        $dbValues['payment_key']                 = $response['key'];
        $dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['order_total']                 = $this->formatAmount($order['details']['BT']->order_total);
        $basicDetails                            = $dbValues;
        $dbValues['tax_id']                      = $method->tax_id;
        $payment_request                         = $this->handleSession($response['payment_element'], 'get', 'payment_request');
        $save_card                               = $this->handleSession($response['payment_element'], 'get', 'save_card');

        if (in_array($response['payment_element'], array('novalnet_cc', 'novalnet_sepa', 'novalnet_paypal', 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_cashpayment')))
        {
            if ($response['payment_element'] == 'novalnet_sepa')
            {
                if ($save_card)
                {
                    $paymentDetails = array(
                        'bankaccount_holder' => $response['bankaccount_holder'],
                        'iban' => $response['iban']
                    );
                }
            }
            elseif ($response['payment_element'] == 'novalnet_paypal')
            {
                $paymentDetails = array(
                    'tid' => $response['tid'],
                    'paypal_transaction_id' => isset($response['paypal_transaction_id']) ? $response['paypal_transaction_id'] : ''
                );
            }
            elseif ($response['payment_element'] == 'novalnet_cc')
            {
                if ($save_card && $method->enable_cc3d != '1')
                {
                    $paymentDetails = array(
                        'cc_holder' => $response['cc_holder'],
                        'cc_no' => $response['cc_no'],
                        'cc_exp_year' => $response['cc_exp_year'],
                        'cc_exp_month' => $response['cc_exp_month'],
                        'cc_card_type' => $response['cc_card_type']
                    );
                }
            }
            elseif (in_array($response['payment_element'], array('novalnet_invoice', 'novalnet_prepayment')))
            {
                $paymentDetails = array(
                    'due_date' => $response['due_date'],
                    'invoice_iban' => $response['invoice_iban'],
                    'invoice_bic' => $response['invoice_bic'],
                    'invoice_bankname' => $response['invoice_bankname'],
                    'invoice_bankplace' => $response['invoice_bankplace'],
                    'invoice_ref' => $response['invoice_ref'],
                    'invoice_account_holder' => $response['invoice_account_holder']
                );
            }
            elseif ($response['payment_element'] == 'novalnet_cashpayment')
            {
                $paymentDetails = array('due_date' => $response['cp_due_date']);
            }
        }

        if ($response['payment_type'] == 'GUARANTEED_INVOICE')
            $dbValues['paid_amount'] = $this->formatAmount($order['details']['BT']->order_total);

        if (!empty($paymentDetails))
            $dbValues['payment_details'] = serialize($paymentDetails);

        if (in_array($response['payment_element'], array('novalnet_cc', 'novalnet_sepa', 'novalnet_paypal')))
        {
            $dbValues['payment_request'] = !empty($payment_request) ? serialize($payment_request) : null;
            $dbValues['booked']          = ($response['amount'] == 0) ? 0 : 1;
            $mask                        = (!empty($response['create_payment_ref'])) ? '1' : '';
            $dbValues['nn_mask']         = ($method->shopping_type == 'one_click') ? $mask : '';
            if (!empty($response['payment_ref']))
            {
                $dbValues['payment_ref']        = $response['payment_ref'];
                $dbValues['one_click_shopping'] = 1;
            }
        }
        JFactory::getSession()->clear('nn_aff_id', 'novalnet');
        return $dbValues;
    }

    /**
     * Update the order comments
     *
     * @param   integer $virtuemartOrderId   current virteumart order id
     * @param   string  $orderstatus         to set order status
     * @param   string  $comments            which was used update the order comments
     *
     * @return void
     */
    public function updateTransactionInOrder($virtuemartOrderId, $orderstatus, $comments)
    {
        $db = JFactory::getDbo();
        $db->setQuery('SELECT * FROM `#__virtuemart_order_histories` WHERE `virtuemart_order_id`="' . $virtuemartOrderId . '" ORDER BY virtuemart_order_history_id DESC LIMIT 1');
        $oldHistoryRow = $db->loadObject();
        if (!$orderstatus)
            $orderstatus = $oldHistoryRow->order_status_code;

        $modelOrder                 = VmModel::getModel('orders');
        $order['order_status']      = $orderstatus;
        $order['customer_notified'] = 1;
        $order['comments']          = $comments;
        $date                       = JFactory::getDate();
        $today                      = $date->toSQL();
        $modelOrder->updateStatusForOneOrder($virtuemartOrderId, $order);
        if (vmVersion::$RELEASE <= '3.4.5')
        {
        if($oldHistoryRow->order_status_code == $order['order_status']) {
                $data = array(
                'order_status_code'   => $order['order_status'],
                'customer_notified'   => 1,
                'comments'            => $comments,
                'virtuemart_order_id' => $virtuemartOrderId,
                'created_on' => $today,
                'created_by' => $oldHistoryRow->created_by,
                'modified_on' => $today,
                'modified_by' => $oldHistoryRow->modified_by
            );
            $this->insertQuery('#__virtuemart_order_histories', $data);
        }
	}
        $db->setQuery('SELECT * FROM `#__virtuemart_order_histories` WHERE `virtuemart_order_id`="' . $virtuemartOrderId . '" ORDER BY virtuemart_order_history_id DESC LIMIT 1');
        $oldHistoryRow = $db->loadObject();
        $addminute     = strtotime($today . ' + 5 minute');
        $order_time    = date('Y-m-d H:i', $addminute);
        $db->setQuery('UPDATE `#__virtuemart_order_histories` set `created_on`="' . $order_time . '" WHERE `virtuemart_order_history_id`="' . $oldHistoryRow->virtuemart_order_history_id . '"');
        $db->execute();
    }
    /**
     * get message from Novalnet payment gateway
     *
     * @param   array $response  which response message will be occuered
     *
     * @return string
     */
    public function getPaygateMessage($response)
    {
        return (!empty($response['status_desc']) ? $response['status_desc'] : (!empty($response['status_text']) ? $response['status_text'] : (!empty($response['status_message']) ? $response['status_message'] : '')));
    }


    /**
     * To validate the age for Guaranteed payments
     *
     * @param   object $_currentMethod which is current payment menthod object
     * @param   string $birthDate      which is current user date of birth
     * @param   string $force          which is current user date of birth
     *
     * @return boolean
     */
    public function ageValidation($_currentMethod, $birthDate, $company, $force = false)
    {
        if (empty($_currentMethod->guarantee_force) && empty($birthDate) && empty($company))
            return false;

        if (empty($_currentMethod->guarantee_force) && (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $birthDate)) && empty($company))
            return false;

        if((empty($company) && !$birthDate) && $force)
        {
            $paymentDetails = $this->getPaymentDetails($_currentMethod->payment_element, false);
            $data['key']          = $paymentDetails['key'];
            $data['payment_type'] = $paymentDetails['payment_type'];
            $this->handleSession($_currentMethod->payment_element, 'set', 'guaranteeDetails', $data);
            return true;
        }

        $bdate = new DateTime((!$birthDate) ? 'today': $birthDate);
        $today = new DateTime('today');

        // To get the payment keys & payment types
        $paymentDetails = $this->getPaymentDetails($_currentMethod->payment_element, true);
        if ($bdate->diff($today)->y > 17 || !empty($company))
        {
            $data['key']          = $paymentDetails['key'];
            $data['payment_type'] = $paymentDetails['payment_type'];
            if(!empty($birthDate))
              $data['birth_date']   = $birthDate;
            $this->handleSession($_currentMethod->payment_element, 'set', 'guaranteeDetails', $data);
            return true;
        }

        if (!empty($_currentMethod->guarantee_force))
        {
            $response = false;
        }
        else
        {
            return false;
        }

        if (!$response)
        {
            // To get the payment keys & payment types
            $paymentDetails = $this->getPaymentDetails($_currentMethod->payment_element, false);
            $data['key']          = $paymentDetails['key'];
            $data['payment_type'] = $paymentDetails['payment_type'];
            $this->handleSession($_currentMethod->payment_element, 'set', 'guaranteeDetails', $data);
            return true;
        }
    }

    /**
     * To get the payment details using payment method id
     *
     * @param   integer $virtuemartPaymentMethodId which is current payment menthod id
     *
     * @return boolean
     */
    public function getPaymentParams($virtuemartPaymentMethodId)
    {
        $params        = $this->selectQuery(array(
            'table_name' => '#__virtuemart_paymentmethods',
            'column_name' => 'payment_params',
            'condition' => 'virtuemart_paymentmethod_id=' . $virtuemartPaymentMethodId
        ), false);
        $paymentParams = explode("|", $params->payment_params);

        foreach ($paymentParams as $values)
        {
            if (empty($values))
                continue;

            $param           = explode('=', $values);
            $data[$param[0]] = substr($param[1], 1, -1);
        }
        return (object) $data;
    }

    /**
     * Parameter formation for extension process.
     *
     * @param   array  $order          which is current order object
     * @param   object $cart           which is current cart object
     * @param   object $_currentMethod which is current payment menthod object
     *
     * @return void
     */
    public function doExtensionProcess($order, $cart, $_currentMethod)
    {
        $this->getMerchantDetails();
        $this->tableName = '#__virtuemart_payment_plg_' . $_currentMethod->payment_element;
        // To get the order details using order number
        $paymentTable    = $this->getDataFromOrder($order['details']['BT']->virtuemart_order_id);
        $request         = JRequest::get('post');
        $vendorDetails   = unserialize($paymentTable->vendor_details);
        $paymentParams = explode("|", $_currentMethod->payment_params);
        foreach ($paymentParams as $values)
        {
            $param = explode('=', $values);
            $status[$param[0]] = substr($param[1], 1, -1);
        }

        if (!empty($paymentTable->payment_details))
            $paymentDetails = unserialize($paymentTable->payment_details);
            
		$extensionCall = array(
            'vendor' => $vendorDetails['vendor'],
            'auth_code' => $vendorDetails['auth_code'],
            'tariff' => $vendorDetails['tariff'],
            'product' => $vendorDetails['product'],
            'key' => $paymentTable->payment_key,
            'tid' => $paymentTable->tid,
            'status' => 100,
            'remote_ip' => $this->getRemoteAddr()
        );
        $comments      = array();
        // To format the order amount
        $orderTotal    = $this->formatAmount($order['details']['BT']->order_total);
        $refundAmount  = $paymentTable->order_total - $orderTotal;

        switch ($request['operation'])
        {
            case 'zero_booking':
                if (empty($orderTotal))
                {
                    echo '<span id="status_desc">' . JText::_('VMPAYMENT_NOVALNET_ADMIN_AMOUNT_INVALID_ERROR') . '</span>';
                    JExit();
                }
                
                $extensionCall           = unserialize($paymentTable->payment_request);
                $extensionCall['amount'] = $orderTotal;
                
                if ( $extensionCall['payment_type'] == "PAYPAL" ) {
					$extensionCall = $this->decode($extensionCall);
					$unsetParameters = array( 'uniqid', 'hash', 'return_url', 'error_return_method', 'return_method', 'error_return_url', 'user_variable_0', 'implementation');
					foreach ( $unsetParameters as $key) {
						unset($extensionCall[$key]);
					}
					$extensionCall['amount'] = $orderTotal;
				}
                
                if ($extensionCall['nn_it'])
                    unset($extensionCall['nn_it']);

                unset($extensionCall['notify_url'], $extensionCall['create_payment_ref']);
                $extensionCall['payment_ref'] = $paymentTable->tid;
                break;

            case 'capture':
                $extensionCall['status']      = 100;
                $extensionCall['edit_status'] = 1;
                $fieldsToUpdate               = array('status' => 100);
                $message                      = JText::_('VMPAYMENT_NOVALNET_TRANSACTION_CONFIRM_SUCCESS_MESSAGE') . ' ' . date('Y-m-d H:i:s');
                $orderStatus                  = $status['nn_order_status'];

                if (in_array($_currentMethod->payment_element, array('novalnet_invoice', 'novalnet_prepayment')))
                {
                    $commentsParams['order_no'] = $paymentTable->order_number;
                    $commentsParams['amount']   = $this->formatAmount($orderTotal, true);
                    $commentsParams             = array_merge($extensionCall, $commentsParams, $paymentDetails);
                    $message .= $this->invoicePrepaymentTransactionComments($commentsParams);
                }
                break;

            case 'void':
                $extensionCall['status']      = 103;
                $extensionCall['edit_status'] = 1;
                $fieldsToUpdate               = array('status' => 103);
                $message                      = JText::_('VMPAYMENT_NOVALNET_TRANSACTION_DEACTIVATED_MESSAGE') . ' ' . date('Y-m-d H:i:s');
                $orderStatus                  = $this->canceled_order_status;
                break;

            case 'duedate_update':
                $date1 = date_create($paymentDetails['due_date']);
                $date2 = date_create($request['due_date']);
                $diff  = date_diff($date1, $date2)->format("%R%a");

                if ($diff < 0)
                {
                    echo '<span id="status_desc">' . JText::_('VMPAYMENT_NOVALNET_ADMIN_DUEDATE_INVALID_ERROR') . '</span>';
                    JExit();
                }
                $extensionCall['update_inv_amount'] = 1;
                $extensionCall['edit_status']       = 1;
                $extensionCall['amount']            = $orderTotal;
                $extensionCall['due_date']          = $paymentDetails['due_date'] = $request['due_date'];
                $fieldsToUpdate                     = array('payment_details' => serialize($paymentDetails));
                $message        = JText::_('VMPAYMENT_NOVALNET_INVOICE_ADMIN_DUE_DATE_UPDATE') . ' ' . $extensionCall['due_date'] . '<br>';
                if ($_currentMethod->payment_element == 'novalnet_cashpayment')
                    $message .= $vendorDetails['cash_comments'];

                if (in_array($_currentMethod->payment_element, array('novalnet_invoice', 'novalnet_prepayment')))
                {
                    $commentsParams['order_no'] = $paymentTable->order_number;
                    $commentsParams['amount']   = $this->formatAmount($orderTotal, true);
                    $commentsParams             = array_merge($extensionCall, $commentsParams, $paymentDetails);
                    $message .= $this->invoicePrepaymentTransactionComments($commentsParams);
                }
                break;

            case 'amount_update':
                $extensionCall['update_inv_amount'] = 1;
                $extensionCall['edit_status']       = 1;
                $extensionCall['amount']            = $orderTotal;
                $fieldsToUpdate                     = array('order_total' => $orderTotal, 'payment_details' => serialize($paymentDetails));
                $message        = JText::_('VMPAYMENT_NOVALNET_TRANSACTION_AMOUNT_MESSAGE') . CurrencyDisplay::getInstance()->priceDisplay($this->formatAmount($orderTotal, true)) . JText::_('VMPAYMENT_NOVALNET_TRANSACTION_UPDATED_SUCCESS_MESSAGE') . date('Y-m-d H:i:s');

                $message = str_replace(array('&&', '%dd%', '%tt%'), array(CurrencyDisplay::getInstance()->priceDisplay($this->formatAmount($orderTotal, true)), date('Y-m-d'), date('H:i:s')), JText::_('VMPAYMENT_NOVALNET_TRANSACTION_AMOUNT_UPDATED_SUCCESS_MESSAGE'));
                $message .= '<br>';
                if (in_array($_currentMethod->payment_element, array('novalnet_cashpayment')))
                    $message .= $vendorDetails['cash_comments'];

                if (in_array($_currentMethod->payment_element, array('novalnet_invoice', 'novalnet_prepayment')))
                {
                    $commentsParams['order_no'] = $paymentTable->order_number;
                    $commentsParams['amount']   = $this->formatAmount($orderTotal, true);
                    $commentsParams             = array_merge($extensionCall, $commentsParams, $paymentDetails);
                    $message .= $this->invoicePrepaymentTransactionComments($commentsParams);
                }
                break;

            case 'refund':
                if (is_numeric($refundAmount) && $refundAmount == 0)
                {
                    echo '<span id="status_desc">' . JText::_('VMPAYMENT_NOVALNET_ADMIN_AMOUNT_INVALID_ERROR') . '</span>';
                    JExit();
                }
                $extensionCall['refund_request'] = 1;
                $extensionCall['refund_param']   = $refundAmount;
                unset($extensionCall['edit_status']);
                $fieldsToUpdate                 = array('order_total' => $orderTotal);
                $message                        = JText::_('VMPAYMENT_NOVALNET_ADMIN_FULL_REFUND_MESSAGE') . $extensionCall['tid'] . JText::_('VMPAYMENT_NOVALNET_ADMIN_PARTIAL_REFUND_AMT_MESSAGE') . CurrencyDisplay::getInstance()->priceDisplay($this->formatAmount($refundAmount, true));
                break;
        }
        // To handle the request and response with Novalnet server
        $response = $this->sendRequest($extensionCall);
        if (!empty($response['status']) && $response['status'] == 100)
        {
            if ($request['operation'] == 'zero_booking')
            {
                $fieldsToUpdate = array('tid' => $response['tid'], 'order_total' => $orderTotal, 'status' => $response['tid_status']);
                $this->updateQuery(array(
                    'table_name' => '#__virtuemart_payment_plg_novalnet_payment',
                    'column_name' => $fieldsToUpdate,
                    'condition' => array(
                        'tid' => $paymentTable->tid
                    )
                ));
                $fieldsToUpdate['booked'] = 1;
            }
            $orderTotal = (int) $orderTotal;

            if (empty($orderTotal) && ($request['operation'] == 'refund') & empty($response['tid']))
            {
                $orderStatus    = $this->canceled_order_status;
                $fieldsToUpdate = array('order_total' => $orderTotal);
            }
            if (!empty($response['paypal_transaction_id']))
                $fieldsToUpdate['payment_details'] = serialize(array('paypal_transaction_id' => $response['paypal_transaction_id'], 'tid' => $paymentTable->tid));

            if (!empty($response['tid_status']))
                $fieldsToUpdate['status'] = $response['tid_status'];

            $this->updateQuery(array(
                'table_name' => $this->tableName,
                'column_name' => $fieldsToUpdate,
                'condition' => array(
                    'tid' => $paymentTable->tid
                )
            ));

            if (isset($response['tid']))
            {
                switch ($request['operation'])
                {
                    case 'refund':
                        $message .= ' ' . JText::_('VMPAYMENT_NOVALNET_ADMIN_NEW_TID_MESSAGE') . $response['tid'];
                        break;

                    case 'zero_booking':
                        $message = $this->buildTransactionComments($response);
                        $message .= '<br>' . str_replace(array('%%', '&&'), array(CurrencyDisplay::getInstance()->priceDisplay($this->formatAmount($orderTotal, true)), $response['tid']), JText::_('VMPAYMENT_NOVALNET_ADMIN_AMOUNT_BOOK_SUCCESS'));
                        break;
                }
            }
            // To update the order details
            $this->updateTransactionInOrder($order['details']['BT']->virtuemart_order_id, $orderStatus, $message);
            echo '<span id="status_desc">' . $this->getPaygateMessage($response) . '</span>';
            JExit();
        }
        else
        {
            // If the request got failure
            echo '<span id="status_desc">' . $this->getPaygateMessage($response) . '</span>';
            JExit();
        }
    }

    /**
     * To get the order details using order id
     *
     * @param   integer $virtuemartOrderId   which is current payment order id
     * @param   integer $tid                 which is current tid
     * @param   string  $tableName           which is current updated table name
     *
     * @return mixed
     */
    public function getDataFromOrder($virtuemartOrderId, $tid = null, $tableName = false)
    {
        $tableNames = $this->tableName;
        if ($tableName)
            $tableNames = $tableName;

        $condition = 'virtuemart_order_id = ' . $virtuemartOrderId;
        if (!empty($tid))
            $condition = 'tid = ' . $tid;

        return $this->selectQuery(array(
            'table_name' => $tableNames,
            'column_name' => '*',
            'condition' => $condition
        ), false);
    }

    /**
     * To update the values in database
     *
     * @param   array $sql which is sql object
     *
     * @return object
     */
    public function updateQuery($sql)
    {
        $db    = JFactory::getDbo();
        $query = $db->getQuery(true);

        // Fields to update.
        foreach ($sql['column_name'] as $key => $value)
            $fields[] = $db->quoteName($key) . ' = ' . $db->quote($value);

        // Conditions for which records should be updated.
        foreach ($sql['condition'] as $key => $value)
            $conditions[] = $db->quoteName($key) . ' = ' . $db->quote($value);

        try {
            $query->update($db->quoteName($sql['table_name']))->set($fields)->where($conditions);
            $db->setQuery($query);
            $db->execute();
        }
        catch (RuntimeException $e)
        {
            return false;
        }
    }

    /**
     * Render the amount refund form
     *
     * @param   string $html  to render html template for extension
     *
     * @return void
     */
    public function renderRefund(&$html)
    {
        $this->setNovalnetTableHeader(JText::_('VMPAYMENT_NOVALNET_ADMIN_REFUND_TITLE'), $html);
        $html .= '
        <td>
            <label>' . JText::_('VMPAYMENT_UPDATE_IN_NOVALNET') . '</label>
        </td>
        <td>
            <a class="NovalnetupdateOrder" name="refund" href="#"><span class="icon-nofloat vmicon vmicon-16-save"></span>' . JText::_('VMPAYMENT_NOVALNET_ADMIN_REFUND_BUTTON') . '</a>&nbsp;&nbsp;&nbsp;
        </td>';
    }

    /**
     * To get the stored patterns from database.
     *
     * @param   object  $method which is current payment menthod object
     * @param   boolean $hash   which is used to set payment ref value based on condtions
     *
     * @return mixed
     */
    public function getStoredPattern($method, $hash = false)
    {
        if ($userId = JFactory::getSession()->get('user')->id)
        {
            $where = ' AND one_click_shopping="0" AND payment_ref="0" AND nn_mask="1"';

            // To fetch the values from database
            $storedPattern = $this->selectQuery(array(
                'table_name' => '#__virtuemart_payment_plg_' . $method->payment_element,
                'column_name' => array(
                    'payment_details',
                    'tid'
                ),
                'condition' => 'customer_id="' . $userId . '"' . $where . '',
                'order' => 'virtuemart_order_id DESC'
            ));

            if (!empty($storedPattern->payment_details))
            {
                if (empty($hash))
                    $this->handleSession($method->payment_element, 'set', $method->payment_element, array('payment_ref' . $method->virtuemart_paymentmethod_id => $storedPattern->tid));

                return unserialize($storedPattern->payment_details);
            }
            return false;
        }
        return false;
    }

    /**
     * To fetch the values from database
     *
     * @param   array $sql      which is current sql object
     * @param   array $mulitple which is current sql object
     *
     * @return object
     */
    public function selectQuery($sql, $mulitple = false)
    {
        $db    = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select($sql['column_name'])->from($sql['table_name']);

        if (!empty($sql['condition']))
            $query->where($sql['condition'], 'AND');

        if (!empty($sql['order']))
            $query->order($sql['order']);

        try {
            $db->setQuery($query);
            $result = $db->loadObject();

            if (!empty($mulitple))
                $result = (array) $db->loadColumn();

            if (!empty($result))
            {
                return $result;
            }
            else
            {
                return false;
            }
        }
        catch (RuntimeException $e)
        {
            return false;
        }
    }

    /**
     * Generates the unique hash string using ENC implementation.
     *
     * @param $data     Get the response
     *
     * @return string
     */
    public function generateHashValue($data)
    {
        $string = '';
        $this->getMerchantDetails();
        foreach (array('auth_code', 'product', 'tariff', 'amount', 'test_mode', 'uniqid') as $param)
            $string .= $data[$param];

        $string .= strrev($this->access_key);
        return hash('sha256', $string);

    }

    /**
     * Generates the random string for the given length.
     *
     * @return string
     */
    public static function randomString()
    {
        $randomWord = explode(',', '8,7,6,5,4,3,2,1,9,0,9,7,6,1,2,3,4,5,6,7,8,9,0');
        shuffle($randomWord);
        return substr(implode($randomWord, ''), 0, 16);
    }

    /**
     * To build transaction comments for Invoice and Prepayment
     *
     * @param   array  $response      which is current payment response array
     * @param   object $callback      boolean
     *
     * @return string
     */
    public function invoicePrepaymentTransactionComments($response, $callback = false)
    {
		if(!empty($callback) && is_array($callback)) {
		   $response = [];
		   $response = array_merge($response, $callback);
	    }
        $accountInformations = '<br>' . JText::_('VMPAYMENT_NOVALNET_INVOICE_COMMENTS') . '<br>';
        if (!empty($response['due_date']))
            $accountInformations .= JText::_('VMPAYMENT_NOVALNET_INVOICE_DUE_DATE') . $response['due_date'] . '<br>';
        $accountInformations .= JText::_('VMPAYMENT_INVOICE_ACCOUNT_HOLDER') . $response['invoice_account_holder'] . '<br>';
        $accountInformations .= JText::_('VMPAYMENT_NOVALNET_IBAN') . $response['invoice_iban'] . '<br>' . JText::_('VMPAYMENT_NOVALNET_BIC') . $response['invoice_bic'] . '<br>';
        $accountInformations .= JText::_('VMPAYMENT_NOVALNET_BANK') . $response['invoice_bankname'] . ' ' . $response['invoice_bankplace'] . '<br>';
        if ($callback)
            $response['amount'] = $callback['amount'] / 100;
        $accountInformations .= JText::_('VMPAYMENT_NOVALNET_AMOUNT') . CurrencyDisplay::getInstance()->priceDisplay($response['amount']) . '<br>';
        $accountInformations .= JText::_('VMPAYMENT_NOVALNET_REFER_ANY_ONE') . '<br>';
        $accountInformations .= JText::_('VMPAYMENT_NOVALNET_REFERENCE_1') . ': ' . (!empty($response['tid']) ? $response['tid'] : $callback['tid']) . '<br>';
        $accountInformations .= JText::_('VMPAYMENT_NOVALNET_REFERENCE_2') . ': ' . $response['invoice_ref'] . '<br>';
        return $accountInformations;
    }

    /**
     * get payment name for communication failure
     *
     * @param   integer $virtuemartPaymentMethodId which is current payment method array
     *
     * @return string
     */
    public function getPaymentMethodName($virtuemartPaymentMethodId)
    {
        $language = JFactory::getLanguage();
        $params   = $this->selectQuery(array(
            'table_name' => '#__virtuemart_paymentmethods_' . strtolower(substr($language->get('locale'), 0, 5)),
            'column_name' => 'payment_name',
            'condition' => 'virtuemart_paymentmethod_id=' . $virtuemartPaymentMethodId
        ), false);
        return $params->payment_name;
    }

    /**
     * displays the logos
     *
     * @param   array $logoList     which is current payment Logo array
     * @param   array $paymentName  which is current payment type array
     *
     * @return mixed
     */
    public function displayLogos($logoList, $paymentName)
    {
        $image = "";
        $url   = JURI::root() . 'plugins/vmpayment/novalnet_payment/novalnet_payment/assets/images/';
        if (!(empty($logoList)))
        {
            if (!is_array($logoList))
               $logoList = (array) $logoList;

            foreach ($logoList as $logo)
            {
                if (!empty($logo))
                {
                    $altText = substr($logo, 0, strpos($logo, '.'));
                    $image .= '<span class="vmCartLogo" ><a><img align="middle" src="' . $url . $logo . '"  alt="' . $altText . '" /></a></span> ';
                }
            }
            return $image;
        }
        if ($paymentName == 'novalnet_cc')
        {
            $image = '<span class="vmCartLogo" ><a><img align="middle" src="' . $url . 'novalnet_cc_visa.png"  alt="novalnet_cc" /><img align="middle" src="' . $url . 'novalnet_cc_master.png"  alt="novalnet_cc" /></a></span> ';
        }
        else
        {
            $image = '<span class="vmCartLogo" ><a><img align="middle" src="' . $url . $paymentName . '.png"  alt="' . $paymentName . '" /></a></span> ';
        }
        return $image;
    }

    /**
     * Load novalnet js files
     *
     * @param   string $payment which is current payment name
     * @param   string $file    which is current js file
     *
     * @return mixed
     */
    public function loadNovalnetJs($payment, $file)
    {
        vmJsApi::addJScript('/plugins/vmpayment/' . $payment . '/' . $payment . '/assets/js/' . $file . '.js');
    }

    /**
     * decoding scheme
     *
     * @param   array $data which is after payment response array
     *
     * @return boolean
     */
    public function decode($data)
    {
        $this->getMerchantDetails();
        foreach ($this->dataParams as $value)
        {
            if (isset($data[$value]))
                $data[$value] = openssl_decrypt(base64_decode($data[$value]), "aes-256-cbc", $this->access_key, true, $data['uniqid']);
        }
        return $data;
    }

    /**
     * Render the void capture
     *
     * @param   string $html to render html template in extension
     *
     * @return void
     */
    protected function renderVoidCapture(&$html)
    {
        $this->setNovalnetTableHeader(JText::_('VMPAYMENT_NOVALNET_ADMIN_MANAGE_TRANSACTION'), $html);
        $html .= '<tr>';
        $html .= '<td><a class="NovalnetupdateOrder" name="capture" href="#"><span class="icon-nofloat vmicon vmicon-16-save"></span>' . JText::_('VMPAYMENT_NOVALNET_ADMIN_DEBIT_BUTTON') . '</a></td>';
        $html .= '<td><a href="#" class="NovalnetupdateOrder" name="void" ><span class="icon-nofloat vmicon vmicon-16-remove 4remove"></span>&nbsp;' . JText::_('VMPAYMENT_NOVALNET_ADMIN_CANCEL_BUTTON') . '</a>
                    &nbsp;&nbsp;</td>';
        $html .= '</tr>';
    }

    /**
     * Render the render zero amount Booking
     *
     * @param   string $html to render html template in extension
     *
     * @return void
     */
    protected function renderzeroBooking(&$html)
    {
        $this->setNovalnetTableHeader(JText::_('VMPAYMENT_NOVALNET_ZERO_AMOUNT_BOOKING'), $html);
        $html .= '<tr>';
        $html .= '<td>' . JText::_('VMPAYMENT_NOVALNET_ADMIN_AMOUNT_BOOK_LABEL') . '</td>';
        $html .= '<td><a class="NovalnetupdateOrder" name="zero_booking" href="#"><span class="icon-nofloat vmicon vmicon-16-save"></span>' . JText::_('VMPAYMENT_NOVALNET_ADMIN_AMOUNT_BOOK') . '</a></td>';
        $html .= '</tr>';
    }

    /**
     * get remote address
     *
     * @return string
     */
    public function getRemoteAddr()
    {
        $serverVar = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        $server    = JRequest::get('SERVER');
        foreach ($serverVar as $key)
        {
            if (array_key_exists($key, $server) === true)
            {
                foreach (explode(',', $server[$key]) as $ipAddr)
                    return $ipAddr;
            }
        }
    }

    /**
     * To form cashpayment order comments
     *
     * @param   string $response
     * @param   boolean $dudate
     *
     * return string
     */
    public function buildOrderComments($response, $dudate = false)
    {
        $storeCounts = 1;
        foreach ($response as $key => $value)
        {
            if (strpos($key, 'nearest_store_title') !== false)
                $storeCounts++;
        }
        $comments = '<br>';
        if ($dudate != true)
        {
            if ($response['cp_due_date'])
                $comments .= JText::_('VMPAYMENT_NOVALNET_CASH_PAYMENT_PAYMENT_SLIP_DATE') . $response['cp_due_date'];
        }
        $comments .= '<br><br>';
        $comments .= JText::_('VMPAYMENT_NOVALNET_CASH_PAYMENT_PAYMENT_STORE') . '<br><br>';
        for ($i = 1; $i < $storeCounts; $i++)
        {
            $comments .= $response['nearest_store_title_' . $i] . '<br>';
            $comments .= $response['nearest_store_street_' . $i] . '<br>';
            $comments .= $response['nearest_store_city_' . $i] . '<br>';
            $comments .= $response['nearest_store_zipcode_' . $i] . '<br>';
            $comments .= ShopFunctions::getCountryByID(ShopFunctions::getCountryIDByName($response['nearest_store_country_' . $i])) . '<br><br>';
        }
        return $comments;
    }
    /**
     * Notification message showing for zero amount booking
     *
     * @param $method object
     * return string
     */
    public function showZeroAmountBookingNotificationMessage($method)
    {
        if (!empty($method->shopping_type) && $method->shopping_type == 'zero_amount')
            return '<br/><span colspan= "4" style="color:red !important;font-weight:bold">' . JText::_('VMPAYMENT_NOVALNET_ZERO_AMOUNT_NOTIFY_DESC') . '</span>';
    }
}
