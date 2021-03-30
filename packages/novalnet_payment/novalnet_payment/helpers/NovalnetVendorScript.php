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
 * Script: NovalnetVendorScript.php
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * NovalnetVendorScript class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class NovalnetVendorScript
{
    /**
     * Initial level payments - Level 0
     *
     * @var array
     */
    public $initialLevelPayments = array('CREDITCARD', 'INVOICE_START', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_INVOICE', 'PAYPAL', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'ONLINE_TRANSFER', 'IDEAL', 'EPS', 'GIROPAY', 'PRZELEWY24', 'CASHPAYMENT');

    /**
     * Chargeback level payments - Level 1
     *
     * @var array
     */
    public $chargebackLevelPayments = array('RETURN_DEBIT_SEPA', 'REVERSAL', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'PAYPAL_BOOKBACK', 'PRZELEWY24_REFUND', 'CASHPAYMENT_REFUND', 'GUARANTEED_INVOICE_BOOKBACK', 'GUARANTEED_SEPA_BOOKBACK');

    /**
     * Collection level payments - Level 2
     *
     * @var array
     */
    public $collectionLevelPayments = array('INVOICE_CREDIT', 'CREDIT_ENTRY_CREDITCARD', 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA', 'DEBT_COLLECTION_CREDITCARD', 'ONLINE_TRANSFER_CREDIT', 'CASHPAYMENT_CREDIT', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE');

    /**
     * Critical mail configuration
     *
     * @var string
     */
    protected static $technicMail = 'technic@novalnet.de';

    /**
     * The novalnet success code
     *
     * @var   array
     * @since 11.1
     */
    protected $novalnetSuccessStatus = array('90', '91', '98', '99', '100', '86', '85');

    /**
     * Get the order object details
     *
     * @var   object
     */
    protected $data;

    /**
     * Payment types based on the payment.
     *
     * @var array
     */
    protected $paymentTypes = array(
    'novalnet_invoice'      => array('INVOICE_CREDIT', 'INVOICE_START', 'GUARANTEED_INVOICE', 'GUARANTEED_INVOICE_BOOKBACK', 'TRANSACTION_CANCELLATION', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
    'novalnet_prepayment'   => array('INVOICE_CREDIT', 'INVOICE_START', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
    'novalnet_cashpayment'  => array('CASHPAYMENT', 'CASHPAYMENT_CREDIT', 'CASHPAYMENT_REFUND'),
    'novalnet_sepa'         => array('DIRECT_DEBIT_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA', 'DEBT_COLLECTION_SEPA', 'CREDIT_ENTRY_SEPA', 'REFUND_BY_BANK_TRANSFER_EU', 'GUARANTEED_SEPA_BOOKBACK', 'TRANSACTION_CANCELLATION'),
    'novalnet_cc'           => array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'CREDIT_ENTRY_CREDITCARD', 'DEBT_COLLECTION_CREDITCARD', 'TRANSACTION_CANCELLATION'), 'novalnet_instantbank' => array('ONLINE_TRANSFER', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
    'novalnet_paypal'       => array('PAYPAL', 'PAYPAL_BOOKBACK', 'TRANSACTION_CANCELLATION'),
    'novalnet_ideal'        => array('IDEAL', 'REVERSAL', 'ONLINE_TRANSFER_CREDIT', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
    'novalnet_eps'          => array('EPS', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL'),
    'novalnet_przelewy24'   => array('PRZELEWY24', 'PRZELEWY24_REFUND'),
    'novalnet_giropay'      => array('GIROPAY', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL'));

    /**
     * To initialize the callback process
     *
     * @return  boolean
     */
    public function novalnetCallback()
    {
        // Get the server callback response.
        $response               = JRequest::get('response');
        $this->aryCaptureParams = array_map('trim', $response);
        if (!class_exists('NovalnetUtilities'))
            include JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'novalnet_payment' . DS . 'novalnet_payment' . DS . 'helpers' . DS . 'NovalnetUtilities.php';

        $this->helpers = new NovalnetUtilities();
        $this->helpers->getMerchantDetails('vendorscript');

        // Validate Ip address.
        self::ipAddressValidation($this->helpers->vendorscript_test_mode);

        if (empty($this->aryCaptureParams))
            self::displayMessage('Novalnet callback received. No params passed over!');

        // Validate the request callback parameters.
        $this->nnVendorParams = self::validateCallbackParams();
        $this->helpers->getMerchantDetails();
        if (!empty($this->nnVendorParams['vendor_activation']) && $this->nnVendorParams['vendor_activation'] == 1)
            self::processAffiliate();

        // Loads the order object for the vendor request.
        $this->orderReference = self::getOrderReference();

        // Get callback order status.
        $orderModel           = VmModel::getModel('orders');
        $order                = $orderModel->getOrder($this->orderReference->virtuemart_order_id);
        $this->_currentMethod = $this->helpers->getPaymentParams($order['details']['BT']->virtuemart_paymentmethod_id);
        $orderStatus          = !empty($order['details']['BT']->order_status) ? $order['details']['BT']->order_status : $this->_currentMethod->nn_order_status;
		
        if (!empty($this->data->payment_element))
        {
            $this->tableName = '#__virtuemart_payment_plg_' . $this->data->payment_element;
            $orderDetails    = $this->helpers->getDataFromOrder('', $this->aryCaptureParams['tid_payment'], $this->tableName);
            $paymentTable    = $this->helpers->getDataFromOrder($order['details']['BT']->virtuemart_order_id, null, '#__virtuemart_payment_plg_' . $orderDetails->payment_element);
        }
        $callbackComments = '';
        $checkStauts = $this->aryCaptureParams['status'] == 100 && $this->aryCaptureParams['tid_status'] == 100;

        // Check the transaction cancellation.
        if ($this->nnVendorParams['payment_type'] == 'TRANSACTION_CANCELLATION')
        {
            $callbackComments = sprintf(JText::_('VMPAYMENT_NOVALNET_CALLBACK_CANCELLATION_COMMENTS'), $this->aryCaptureParams['tid'], date('d.m.Y H:i:s'));
            $this->helpers->updateQuery(array(
                'table_name' => '#__virtuemart_payment_plg_novalnet_payment',
                'column_name' => array(
                    'status' => $this->aryCaptureParams['tid_status']
                ),
                'condition' => array(
                    'tid' => $this->aryCaptureParams['tid']
                )
            ));
            $this->helpers->updateQuery(array(
                'table_name' => '#__virtuemart_payment_plg_' . $this->data->payment_element,
                'column_name' => array(
                    'status' => $this->aryCaptureParams['tid_status']
                ),
                'condition' => array(
                    'tid' => $this->aryCaptureParams['tid']
                )
            ));
            $this->updateOrderDetails($this->orderReference->virtuemart_order_id, $this->helpers->canceled_order_status, $callbackComments);
            self::mailNotification($callbackComments, $this->helpers);
            self::displayMessage($callbackComments);
        }

        // Get payment type level.
        $paymentTypeLevel = self::getPaymentTypeLevel();

        // Credit entry of Invoice and Prepayment.
        if ($paymentTypeLevel == 2 && $checkStauts)
        {
            if (in_array($this->aryCaptureParams['payment_type'], array('INVOICE_CREDIT', 'ONLINE_TRANSFER_CREDIT', 'CASHPAYMENT_CREDIT')))
            {
                $orderAmount = $this->helpers->formatAmount($this->orderReference->order_total);
                $totalAmount    = $orderDetails->paid_amount;
                $totalAmountSum = $totalAmount + $this->nnVendorParams['amount'];
                if ($totalAmount < $orderAmount)
                {
                    $callbackComments .= sprintf(JText::_('VMPAYMENT_NOVALNET_CALLBACK_MESSAGE'), $this->aryCaptureParams['tid_payment'], CurrencyDisplay::getInstance()->priceDisplay($this->helpers->formatAmount($this->aryCaptureParams['amount'], true)), date('d.m.Y H:i:s'), $this->aryCaptureParams['tid']);
                    if ($totalAmountSum >= $orderAmount)
                    {
                        $callbackComments = sprintf(JText::_('VMPAYMENT_NOVALNET_CALLBACK_MESSAGE'), $this->aryCaptureParams['tid_payment'], CurrencyDisplay::getInstance()->priceDisplay($this->helpers->formatAmount($this->aryCaptureParams['amount'], true)), date('d.m.Y H:i:s'), $this->aryCaptureParams['tid']);
                        $orderStatus      = $this->_currentMethod->nn_callback_status;
                        if ($this->nnVendorParams['payment_type'] == 'ONLINE_TRANSFER_CREDIT' && $totalAmountSum >= $orderAmount)
                            $callbackComments = sprintf(JText::_('VMPAYMENT_NOVALNET_CALLBACK_ONLINE_TRANSFER_COMMENTS'), $this->aryCaptureParams['tid_payment'], CurrencyDisplay::getInstance()->priceDisplay($this->helpers->formatAmount($this->aryCaptureParams['amount'], true)), date('d.m.Y H:i:s'));
                    }

                    $updateArray = array(
                         'table_name' => $this->tableName,
                          'column_name' => array(
                             'paid_amount' => $totalAmountSum,
                             'status' => $this->aryCaptureParams['tid_status']
                          ),
                          'condition' => array(
                           'tid' => $this->aryCaptureParams['tid_payment']
                       ));
                    $this->helpers->updateQuery($updateArray);
                    $this->updateOrderDetails($this->orderReference->virtuemart_order_id, $orderStatus, $callbackComments);
                    self::mailNotification($callbackComments, $this->helpers);
                    self::displayMessage($callbackComments);
                }
                else
                {
                    self::displayMessage('Novalnet callback received. Callback script executed already. Refer Order :' . $this->orderReference->order_number);
                }
            }
            else
            {
                $callbackComments .= sprintf(JText::_('VMPAYMENT_NOVALNET_CALLBACK_MESSAGE'), $this->aryCaptureParams['tid_payment'], CurrencyDisplay::getInstance()->priceDisplay($this->helpers->formatAmount($this->aryCaptureParams['amount'], true)), date('d.m.Y H:i:s'), $this->aryCaptureParams['tid']);
                $this->updateOrderDetails($this->orderReference->virtuemart_order_id, $orderStatus, $callbackComments);
                self::mailNotification($callbackComments, $this->helpers);
                self::displayMessage($callbackComments);
            }
        }
        // Level 1 payments - Type of charge backs and bookback.
        elseif ($paymentTypeLevel == 1 && $checkStauts)
        {
            $callbackComments = (in_array($this->nnVendorParams['payment_type'], array('PAYPAL_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'CREDITCARD_BOOKBACK', 'PRZELEWY24_REFUND', 'CASHPAYMENT_REFUND', 'GUARANTEED_INVOICE_BOOKBACK', 'GUARANTEED_SEPA_BOOKBACK'))) ? JText::sprintf('VMPAYMENT_NOVALNET_CALLBACK_REFUND_MESSAGE', $this->aryCaptureParams['tid_payment'], CurrencyDisplay::getInstance()->priceDisplay($this->helpers->formatAmount($this->aryCaptureParams['amount'], true)), date('d.m.Y H:i:s'), $this->aryCaptureParams['tid']) : JText::sprintf('VMPAYMENT_NOVALNET_CALLBACK_CHARGEBACK_MESSAGE', $this->aryCaptureParams['tid_payment'], CurrencyDisplay::getInstance()->priceDisplay($this->helpers->formatAmount($this->aryCaptureParams['amount'], true)), date('d.m.Y H:i:s'), $this->aryCaptureParams['tid']);
            $this->updateOrderDetails($this->orderReference->virtuemart_order_id, $orderStatus, $callbackComments);
            self::mailNotification($callbackComments, $this->helpers);
            self::displayMessage($callbackComments);
        }
        elseif ($this->nnVendorParams['payment_type'] == 'PAYPAL' || $this->nnVendorParams['payment_type'] == 'PRZELEWY24')
        {
            $message          = JText::_('VMPAYMENT_NOVALNET_TRANSACTION_ID') . $this->aryCaptureParams['tid'];
            if ($this->aryCaptureParams['test_mode'] == '1')
            $message          .=  '<br>' . JText::_('VMPAYMENT_NOVALNET_TEST_ORDER').'<br>';
            if ($this->data->status == '85' && $this->nnVendorParams['tid_status'] == '100')
            {
                $callbackComments = $message . JText::sprintf('VMPAYMENT_NOVALNET_CALLBACK_CONFIRMED_COMMENTS', date('Y-m-d H:i:s'));
                $orderStatus = $this->_currentMethod->nn_order_status;
                $this->helpers->updateQuery(array(
                    'table_name' => $this->tableName,
                    'column_name' => array(
                        'status' => $this->aryCaptureParams['tid_status']
                    ),
                    'condition' => array(
                        'tid' => $this->aryCaptureParams['tid']
                    )
                ));
                $this->helpers->updateQuery(array(
                    'table_name' => '#__virtuemart_payment_plg_novalnet_payment',
                    'column_name' => array(
                        'status' => $this->aryCaptureParams['tid_status']
                    ),
                    'condition' => array(
                        'tid' => $this->aryCaptureParams['tid']
                    )
                ));
                $this->updateOrderDetails($this->orderReference->virtuemart_order_id, $orderStatus, $callbackComments);
                self::displayMessage($callbackComments);
            }
            elseif ($this->nnVendorParams['payment_type'] == 'PRZELEWY24' || $this->nnVendorParams['payment_type'] == 'PAYPAL' ) {
			  if( ($this->nnVendorParams['tid_status'] == '100' && $this->data->status == '86' ) || ($this->nnVendorParams['tid_status'] == '100' && $this->data->status == '90' )) {
				
                $orderStatus = ($this->nnVendorParams['payment_type'] == 'PRZELEWY24' ) ? $this->_currentMethod->nn_callback_status : $this->_currentMethod->nn_order_status;;
                $callbackComments = $message . JText::sprintf('VMPAYMENT_NOVALNET_CALLBACK_CONFIRMED_COMMENTS', date('Y-m-d H:i:s'));
                $this->helpers->updateQuery(array(
                    'table_name' => $this->tableName,
                    'column_name' => array(
                        'status' => $this->aryCaptureParams['tid_status']
                    ),
                    'condition' => array(
                        'tid' => $this->aryCaptureParams['tid']
                    )
                ));
                $this->helpers->updateQuery(array(
                    'table_name' => '#__virtuemart_payment_plg_novalnet_payment',
                    'column_name' => array(
                        'status' => $this->aryCaptureParams['tid_status']
                    ),
                    'condition' => array(
                        'tid' => $this->aryCaptureParams['tid']
                    )
                ));
                $this->updateOrderDetails($this->orderReference->virtuemart_order_id, $orderStatus, $callbackComments);
                self::displayMessage($callbackComments);
            } elseif ($this->nnVendorParams['payment_type'] == 'PRZELEWY24' && !in_array($this->nnVendorParams['tid_status'], array('86', '100'))) {
                $callbackComments = PHP_EOL . JText::_('VMPAYMENT_NOVALNET_CALLBACK_MESSAGE_FAIL') . $this->helpers->getPaygateMessage($this->nnVendorParams);
                $this->updateOrderDetails($this->orderReference->virtuemart_order_id, 'X', $callbackComments);
                self::mailNotification($callbackComments, $this->helpers);
                self::displayMessage($callbackComments);
            } else {
                // Paid full amount display message.
                self::displayMessage('Novalnet Callbackscript received. Order already Paid');
            }
          } else {
                self::displayMessage('Novalnet callback received. Callback script executed already. Refer Order :' . $this->orderReference->order_number);
          }
        }
        elseif (in_array($this->nnVendorParams['payment_type'], array('GUARANTEED_INVOICE', 'INVOICE_START', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'CREDITCARD')) && ($this->nnVendorParams['status'] == '100') && in_array($this->nnVendorParams['tid_status'], array('91', '98', '99', '100')) && in_array($this->data->status, array('75', '91', '98', '99')))
        {
            $message          = JText::_('VMPAYMENT_NOVALNET_TRANSACTION_ID') . $this->aryCaptureParams['tid'];
            if ($this->aryCaptureParams['test_mode'] == '1')
            $message          .=  '<br>' . JText::_('VMPAYMENT_NOVALNET_TEST_ORDER').'<br>' ;
            $callbackComments = ($this->nnVendorParams['tid_status'] == '100') ? PHP_EOL . JText::sprintf('VMPAYMENT_NOVALNET_CALLBACK_CONFIRMED_COMMENTS', date('Y-m-d H:i:s')) : ($this->nnVendorParams['payment_type'] == 'GUARANTEED_INVOICE' ? PHP_EOL . sprintf(JText::_('VMPAYMENT_NOVALNET_CALLBACK_ONHOLD_COMMENTS'), $this->nnVendorParams['tid'], date('d.m.Y H:i:s')) . '<br>' . $this->helpers->invoicePrepaymentTransactionComments(unserialize($paymentTable->payment_details), $this->nnVendorParams) : PHP_EOL . sprintf(JText::_('VMPAYMENT_NOVALNET_CALLBACK_ONHOLD_COMMENTS'), $this->nnVendorParams['tid'], date('d.m.Y H:i:s')));
            if ($this->data->status == '75' && in_array($this->nnVendorParams['tid_status'], array('91', '99')) && in_array($this->nnVendorParams['payment_type'], array('GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA')))
            {
                $message .= $callbackComments . '<br>';
                $orderStatus = $this->helpers->confirmed_order_status;
            }
            elseif ((in_array($this->data->status, array('75', '91', '98', '99')) && $this->nnVendorParams['tid_status'] == '100'))
            {
                $message                        = ((in_array($this->data->status, array('75', '91')) && $this->nnVendorParams['tid_status'] == '100') && in_array($this->nnVendorParams['payment_type'], array('GUARANTEED_INVOICE', 'INVOICE_START'))) ? $callbackComments . '<br><br>' . $message . $this->helpers->invoicePrepaymentTransactionComments(unserialize($paymentTable->payment_details), $this->nnVendorParams) : $message . $callbackComments;
                $orderStatus                    = ($this->nnVendorParams['payment_type'] == 'GUARANTEED_INVOICE' && $this->nnVendorParams['tid_status'] == '100') ? $this->_currentMethod->nn_callback_status : (((in_array($this->nnVendorParams['payment_type'], array('INVOICE_START', 'GUARANTEED_DIRECT_DEBIT_SEPA'))) && $this->nnVendorParams['tid_status'] == '100') ? $this->_currentMethod->nn_order_status : (in_array($this->nnVendorParams['tid_status'], array('91', '99')) ? $this->helpers->confirmed_order_status : $this->_currentMethod->nn_order_status));
                self::onholdOrderConfirmationMail($message);
            }
            else
            {
                self::displayMessage('Novalnet callback received. Callback script executed already. Refer Order :' . $this->orderReference->order_number);
            }
            $this->helpers->updateQuery(array(
                'table_name' => $this->tableName,
                'column_name' => array(
                    'status' => $this->aryCaptureParams['tid_status']
                ),
                'condition' => array(
                    'tid' => $this->aryCaptureParams['tid']
                )
            ));
            $this->helpers->updateQuery(array(
                'table_name' => '#__virtuemart_payment_plg_novalnet_payment',
                'column_name' => array(
                    'status' => $this->aryCaptureParams['tid_status']
                ),
                'condition' => array(
                    'tid' => $this->aryCaptureParams['tid']
                )
            ));
            $this->updateOrderDetails($this->orderReference->virtuemart_order_id, $orderStatus, $message);
            if (!(in_array($this->data->status, array('75', '91', '98', '99')) && $this->nnVendorParams['tid_status'] == '100'))
                self::mailNotification($message, $this->helpers);
            self::displayMessage($message);
        }
        else
        {
            self::displayMessage('Novalnet callback received. Callback script executed already. Refer Order :' . $this->orderReference->order_number);
        }
        if ($this->nnVendorParams['status'] != '100' || $this->nnVendorParams['tid_status'] != '100')
        {
            $statusKey   = ($this->nnVendorParams['status'] != '100') ? 'status' : 'tid_status';
            $statusValue = $this->nnVendorParams['status'] != '100' ? $this->nnVendorParams['status'] : $this->nnVendorParams['tid_status'];
            self::displayMessage('Novalnet callback received. ' . $statusKey . ' (' . $statusValue . ') is not valid');
        }
        else
        {
            self::displayMessage('Novalnet Callbackscript received. Payment type ( ' . $this->nnVendorParams['payment_type'] . ' ) is not applicable for this process!');
        }
    }

    /**
     * Validates the IP address which processing the callback script
     *
     * @param   int $testMode   Get the testmode value
     *
     * @return  string
     */
    public function ipAddressValidation($testMode)
    {
        // Get the host IP address.
        $hostAddress = gethostbyname('pay-nn.de');
        $callerIp    = $_SERVER['REMOTE_ADDR'];
        if (empty($hostAddress))
            self::showMessage('Novalnet HOST IP missing');

        // IP condition check.
        if ($hostAddress != $callerIp && $testMode != 1)
            self::showMessage('Novalnet callback received. Unauthorised access from the IP ' . $callerIp);
    }

    /**
     * Display the error message
     *
     * @param   string $Message     get   the novalnet message
     * @param   boolean $stopExecution  based on  the   execution show the message
     *
     * @return  void     */
    public static function showMessage($Message, $stopExecution = false)
    {
        echo $Message;
        if ($stopExecution != 'show')
            exit;
    }

    /**
     * Validating the Novalnet callback required parameters
     *
     * @return  array
     */
    public function validateCallbackParams()
    {
        if (empty($this->aryCaptureParams['vendor_activation']))
        {
            $requiredParams = array('vendor_id', 'status', 'payment_type', 'tid', 'tid_status');
            if (isset($this->aryCaptureParams['payment_type']) && in_array($this->aryCaptureParams['payment_type'], array_merge($this->chargebackLevelPayments, $this->collectionLevelPayments)))
                array_push($requiredParams, 'tid_payment');

            // Validate the required parameters
            foreach ($requiredParams as $v)
            {
                if (empty($this->aryCaptureParams[$v]))
                    self::showMessage('Required param ( ' . $v . '  ) missing!');
            }

            // Validate the tid parameter.
            if (!preg_match('/^\d{17}$/', $this->aryCaptureParams['tid']))
                self::showMessage('Novalnet callback received. Invalid TID [' . $this->aryCaptureParams['tid'] . '] for Order.');

            if (in_array($this->aryCaptureParams['payment_type'], array_merge($this->chargebackLevelPayments, $this->collectionLevelPayments)))
            {
                // Level 2 added tid_payment params.
                $this->aryCaptureParams['shop_tid'] = $this->aryCaptureParams['tid_payment'];
            }
            elseif (!empty($this->aryCaptureParams['tid']))
            {
                 $this->aryCaptureParams['shop_tid'] = $this->aryCaptureParams['tid'];
            }
        }
        else
        {
            // Validate the affiliate params.
            foreach (array('vendor_id', 'vendor_authcode', 'product_id', 'aff_id', 'aff_authcode', 'aff_accesskey') as $v)
            {
                if (empty($this->aryCaptureParams[$v]))
                    self::showMessage('Required param ( ' . $v . '  ) missing!');
            }
        }
        return $this->aryCaptureParams;
    }

    /**
     * Get payment type Level based on the payment actions
     *
     * @return  integer
     */
    public function getPaymentTypeLevel()
    {
        if (in_array($this->nnVendorParams['payment_type'], $this->initialLevelPayments))
        {
            return 0;
        }
        elseif (in_array($this->nnVendorParams['payment_type'], $this->chargebackLevelPayments))
        {
            return 1;
        }
        elseif (in_array($this->nnVendorParams['payment_type'], $this->collectionLevelPayments))
        {
            return 2;
        }
        else
        {
            self::showMessage('Novalnet callback received. Payment type [' . $this->nnVendorParams['payment_type'] . '] is mismatched!');
        }
    }

    /**
     * Get the order details for the given TID or order number
     *
     * @return object
     */
    public function getOrderReference()
    {
        $this->data = $this->helpers->selectQuery(array(
            'table_name' => '#__virtuemart_payment_plg_novalnet_payment',
            'column_name' => array(
            'payment_element','status','tid', 'order_total', 'virtuemart_order_id', 'order_number'),
            'condition' => array(
                "(order_number = '" . $this->nnVendorParams['order_no'] . "' AND order_number > '') OR tid = " . $this->nnVendorParams['shop_tid'] . "")
        ));

         $orderNumber = !empty($this->data->order_number) ? $this->data->order_number : $this->nnVendorParams['order_no'];
        // If order number present to get the order details in order table.
        $order = $this->helpers->selectQuery(array(
            'table_name' => '#__virtuemart_orders',
            'column_name' => array(
                'order_total',
                'virtuemart_order_id',
                'order_number',
            ),
            'condition' => array(
                "order_number = '" . $orderNumber . "'"
            ),
            'order' => ''
        ));

        if (empty($this->data) && !empty($order))
            self::handleCommunicationFailure();

        // If order not found in order table sent critical mail.
        if (!$order)
        {
            $this->helpers->getMerchantDetails();
            $callbackComments = 'Dear Technic team,<br><br>Please evalute this transaction and contact our payment module team at Novalnet.' . '<br>' . 'Merchant ID: ' . $this->helpers->vendor_id . '<br>' . 'Project ID: ' . $this->helpers->product_id . '<br>' . 'TID: ' . $this->nnVendorParams['tid'] . '<br>' . 'TID status: ' . $this->nnVendorParams['tid_status'] . '<br>' . 'Order no: ' . $this->nnVendorParams['order_no'] . '<br>' . 'E-mail: ' . $this->nnVendorParams['email'] . '<br><br>' . 'Regards,<br>Novalnet Team';
            $subject          = sprintf('Critical error on shop system' . JFactory::getApplication()->getCfg('sitename') . 'Order not found for TID: ' . $this->nnVendorParams['tid']);
            self::mailNotification($callbackComments, false, $subject);
            self::showMessage('Novalnet callback received. Transaction Mapping Failed');
        }
        else
        {
            if ((!array_key_exists($this->data->payment_element, $this->paymentTypes)) || !in_array($this->aryCaptureParams['payment_type'], $this->paymentTypes[$this->data->payment_element]))
                self::displayMessage('Novalnet callback received. Payment type [' . $this->aryCaptureParams['payment_type'] . '] is mismatched!');
            // Validates and matches the requested order number.
            if (!empty($orderNumber) && $orderNumber != $order->order_number)
                self::showMessage('Novalnet callback received. Order no is not valid.');
        }
        return $order;
    }

   /**
    * Send callback mail notification
    *
    * @param   array   $mailData   get the callback mail show comments
    * @param   array   $config     get the configuration details
    * @param   array   $subject    get the novalnet mail comments
    *
    * @return void
    */
   public static function mailNotification($mailData, $config = false, $subject = false)
   {
        if ($config->enable_vendorscript_mail == '1')
        {
            // Load shop mail configuration using getMailer function.
            $mailer = JFactory::getMailer();

           // Email from name
            $mailData      = str_replace('<br>', PHP_EOL, $mailData);
            if($config)
            {
                $mailer->addRecipient($config->vendorscript_mail_to);
                $mailer->addBcc($config->vendorscript_mail_bcc);
            }
            else
            {
                $mailer->addRecipient(self::$technicMail);
            }
            $mailer->setSender(array($mailer->From, $mailer->FromName));
            $subject = !empty($subject) ? $subject : 'Novalnet Callback script notification';
            $mailer->setSubject($subject);
            $mailer->setBody($mailData);
            $mailer->Encoding = 'base64';
            $send             = $mailer->Send();
            (!empty($config->vendorscript_mail_to || $config->vendorscript_mail_bcc)) ? self::showMessage('Mail sent successfully!', 'show') : self::showMessage('Mail not sent!', 'show');
        }
   }

    /**
     * Handling the communication failure
     *
     * @return void
     */
    public function handleCommunicationFailure()
    {
        $orderModel = VmModel::getModel('orders');
        $orderid    = VirtueMartModelOrders::getOrderIdByOrderNumber($this->nnVendorParams['order_no']);
        $order      = $orderModel->getOrder($orderid);

        // To get the payment details using payment method id
        $this->_currentMethod = $this->helpers->getPaymentParams($order['details']['BT']->virtuemart_paymentmethod_id);
        $orderStatus = $this->_currentMethod->nn_order_status;

        // To get the merchant details from the shop
        $this->helpers->getMerchantDetails();
        // To fetch the values from database
        $paymentName = $this->helpers->selectQuery(array('table_name' => '#__virtuemart_paymentmethods', 'column_name' => 'payment_element', 'condition' => 'virtuemart_paymentmethod_id=' . $order['details']['BT']->virtuemart_paymentmethod_id));

        $data = array(
            'vendor'            => $this->helpers->vendor_id,
            'product'           => $this->helpers->product_id,
            'tariff'            => $this->helpers->tariff_id,
            'auth_code'         => $this->helpers->auth_code,
            'payment_element'   => $paymentName->payment_element,
            'payment_name'      => $this->helpers->getPaymentMethodName($order['details']['BT']->virtuemart_paymentmethod_id),
        );
        $paymentKey  = $this->helpers->getPaymentDetails($paymentName->payment_element);
        $data['key'] = $paymentKey['key'];
        $data        = $this->helpers->storePaymentdetails($order, array_merge($this->aryCaptureParams, $data), $this->_currentMethod, $basicDetails, true);
        unset($data['tax_id']);
        $data['test_mode'] = $this->aryCaptureParams['test_mode'];
        $message           = $this->helpers->buildTransactionComments($data);
        if (in_array($this->aryCaptureParams['tid_status'], $this->novalnetSuccessStatus))
        {
            if (in_array($paymentName->payment_element, array('novalnet_przelewy24', 'novalnet_paypal', 'novalnet_giropay', 'novalnet_eps', 'novalnet_instantbank', 'novalnet_ideal')))
            {
                if ($paymentName->payment_element == 'novalnet_paypal')
                    $basicDetails['booked'] = ($this->aryCaptureParams['amount'] == 0) ? 0 : 1;
                $this->helpers->insertQuery('#__virtuemart_payment_plg_' . $paymentName->payment_element, $basicDetails);
            }
            if (in_array($paymentName->payment_element, array('novalnet_invoice', 'novalnet_prepayment')))
                $message .= $this->helpers->invoicePrepaymentTransactionComments($this->aryCaptureParams, $this->nnVendorParams);
            $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
        }
        else
        {
            $this->helpers->insertQuery('#__virtuemart_payment_plg_novalnet_payment', $basicDetails);
            $this->helpers->insertQuery('#__virtuemart_payment_plg_' . $paymentName->payment_element, $basicDetails);
            $this->orderStatus = 'X';
        }
        $this->updateOrderDetails($order['details']['BT']->virtuemart_order_id, $orderStatus, $message);
        self::mailNotification($message, $this->helpers);
        self::showMessage($message);
    }

    /**
     * Display the error message
     *
     * @param   string $errorMsg      get   the debug message
     * @param   boolean $stopExecution based on  the   execution show the message
     *
     * @return  string
     */
    public static function displayMessage($errorMsg, $stopExecution = false)
    {
        echo $errorMsg;
        if ($stopExecution != 'show')
            exit;
    }

    /**
     * To handle the Affiliate process
     *
     * @return void
     */
    public function processAffiliate()
    {
        $this->helpers->getMerchantDetails();
        $affiliateData = array(
            'vendor_id' => $this->aryCaptureParams['vendor_id'],
            'vendor_authcode' => $this->aryCaptureParams['vendor_authcode'],
            'product_id' => $this->aryCaptureParams['product_id'],
            'product_url' => $this->aryCaptureParams['product_url'],
            'activation_date' => $this->aryCaptureParams['activation_date'],
            'aff_id' => $this->aryCaptureParams['aff_id'],
            'aff_authcode' => $this->aryCaptureParams['aff_authcode'],
            'aff_accesskey' => $this->aryCaptureParams['aff_accesskey']
        );
        $db            = JFactory::getDbo();
        $db->setQuery('CREATE TABLE IF NOT EXISTS #__novalnet_affiliate_detail (
                      id int(11) unsigned  AUTO_INCREMENT COMMENT "Auto increment ID",
                      vendor_id int(11) unsigned COMMENT "Vendor ID",
                      vendor_authcode varchar(50) COMMENT "Authorisation ID",
                      product_id int(11) unsigned COMMENT "Project ID",
                      product_url varchar(200) DEFAULT NULL COMMENT "Product URL",
                      activation_date datetime DEFAULT NULL COMMENT "Affiliate activation date",
                      aff_id int(11) unsigned COMMENT "Affiliate vendor ID",
                      aff_authcode varchar(40) COMMENT "Affiliate authorisation ID",
                      aff_accesskey varchar(40) COMMENT "Affiliate access Key",
                      PRIMARY KEY (id),
                      KEY vendor_id (vendor_id),
                      KEY aff_id (aff_id)
                    )COMMENT="Novalnet merchant / affiliate account information"');
        $db->query();
        // To insert the values in the database
        $this->helpers->insertQuery('#__novalnet_affiliate_detail', $affiliateData);
        self::mailNotification('Novalnet callback script executed successfully with Novalnet account activation information.', $this->helpers);
        self::displayMessage('Novalnet callback script executed successfully with Novalnet account activation information.');
    }

    /**
     * This event triggered to sent the onhold order confirmation mail.
     *
     * @param   string    $comments     get the order comments
     *
     * @return string
     */
    public function onholdOrderConfirmationMail($comments)
    {
        $orderModel           = VmModel::getModel('orders');
        $order                = $orderModel->getOrder($this->orderReference->virtuemart_order_id);
        $this->_currentMethod = $this->helpers->getPaymentParams($order['details']['BT']->virtuemart_paymentmethod_id);
        $message              = 'Dear Mr./Ms' . ' ' . $order['details']['BT']->first_name . ' ' . $order['details']['BT']->last_name . '<br>' . $comments;

        // Load shop mail configuration using getMailer function.
        $mailer = JFactory::getMailer();

        // Email from name
        $message       = str_replace('<br>', PHP_EOL, $message);
        $emailTo       = $order['details']['BT']->email;
        $mailer->addRecipient($emailTo);
        $mailer->setSender(array($mailer->From, $mailer->FromName));
        $mailer->setSubject('Order Confirmation - Your Order ' . $order['details']['BT']->order_number . ' has been confirmed!');
        $mailer->setBody($message);
        $mailer->Encoding = 'base64';
        $send             = $mailer->Send();
        ($send) ? self::showMessage('Mail sent successfully!', 'show') : self::showMessage('Mail not sent!', 'show');
    }

    /**
     * Update the order comments
     *
     * @param   integer $orderId             current virteumart order id
     * @param   string  $orderStatus         to set order status
     * @param   string  $comments            which was used update the order comments
     *
     * @return void
     */
   public function updateOrderDetails($orderId, $orderStatus, $comments)
   {
	    $modelOrder = VmModel::getModel('orders');
	    $order['order_status']      = $orderStatus;
        $order['customer_notified'] = 1;
        $order['comments']          = $comments;
        $modelOrder->updateStatusForOneOrder($orderId, $order);
	    if (vmVersion::$RELEASE <= '3.4.5')
        {
			$db = JFactory::getDbo();
			$db->setQuery('SELECT * FROM `#__virtuemart_order_histories` WHERE `virtuemart_order_id`="' . $orderId . '" ORDER BY virtuemart_order_history_id DESC LIMIT 1');
			$oldHistoryRow = $db->loadObject();
			$date          = JFactory::getDate();
			$data = array(
					'order_status_code'   => $orderStatus,
					'customer_notified'   => 1,
					'comments'            => $comments,
					'virtuemart_order_id' => $orderId,
					'created_on' => $date->toSQL(),
					'created_by' => $oldHistoryRow->created_by,
					'modified_on' => $date->toSQL(),
					'modified_by' => $oldHistoryRow->modified_by
				);
			$this->helpers->insertQuery('#__virtuemart_order_histories', $data);
		}
    }
}
?>
