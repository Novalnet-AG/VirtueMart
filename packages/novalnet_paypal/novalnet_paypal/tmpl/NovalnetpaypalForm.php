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
 * Script: NovalnetpaypalForm.php
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * paypal payment class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class NovalnetpaypalForm
{
    /**
     * To render the Credit Card masking pattern
     *
     * @param   array  $maskedPatterns which is current masking pattern array
     * @param   object $_currentMethod which is payment method object
     *
     * @return string
     */
    public function renderMaskedForm($maskedPatterns, $_currentMethod)
    {
        $document = JFactory::getDocument();
        $style    = '#paypal_toggle_name:hover {
                    background: none;
                    cursor: pointer;
                  }';
        $document->addStyleDeclaration($style);
        $paypalTid            = '';
        $helpers              = new NovalnetUtilities;
        $type                 = '';
        $sessionPaymentDetail = $helpers->handleSession('novalnet_paypal', 'get', 'paymentDetails');
        $oneClick             = 1;

        if ($sessionPaymentDetail['paypal_order_type' . $_currentMethod->virtuemart_paymentmethod_id])
        {
            $type     = $sessionPaymentDetail['paypal_order_type' . $_currentMethod->virtuemart_paymentmethod_id];
            $oneClick = 0;
        }

        $paymentDetails = $helpers->handleSession($_currentMethod->payment_element, 'get', $_currentMethod->payment_element);
        $paymentRef = ($paymentDetails['payment_ref' . $_currentMethod->virtuemart_paymentmethod_id]) ? $paymentDetails['payment_ref' . $_currentMethod->virtuemart_paymentmethod_id] : $paymentDetails['payment_ref'];

        if (!empty($maskedPatterns['paypal_transaction_id']))
        {
            $paypalTid = '<tr valign="top">
                <td nowrap align="right" style="padding:1%">
                    <label>' . JText::_('VMPAYMENT_NOVALNET_PAYPAL_TRANSACTION_ID') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['paypal_transaction_id'] . '</td>
            </tr>';
        }

        if (!empty($sessionPaymentDetail['paypal_order_type' . $_currentMethod->virtuemart_paymentmethod_id]))
        {
            $text  = JText::_('VMPAYMENT_NOVALNET_GIVEN_PAYPAL_DETAILS');
            $style = 'none !important';
        }
        else
        {
            $text  = JText::_('VMPAYMENT_NOVALNET_UPDATE_PAYPAL_DETAILS');
            $style = 'block !important';
        }

        $html = '<br /><a id="paypal_toggle_name" style="color: #095197; text-decoration: underline; font-weight: bold;">' . $text . '</a><br/><br/><div id="novalnet_paypal_maskedform" style="display:' . $style . '">
        <table border="0" cellspacing="0" cellpadding="2" width="100%" >
            <tr valign="top">
                <td nowrap align="right" style="padding:1%">
                    <label>' . JText::_('VMPAYMENT_NOVALNET_TRANSACTION_ID') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['tid'] . '</td>
            </tr>
            ' . $paypalTid . '
            <input type="hidden" name="nnpaypal_oneclick' . $_currentMethod->virtuemart_paymentmethod_id . '" id="nnpaypal_oneclick' . $_currentMethod->virtuemart_paymentmethod_id . '" value="' . $oneClick . '"/>
            <input type="hidden" id="paypal_paymentid" value="' . $_currentMethod->virtuemart_paymentmethod_id . '"/>
            <input type="hidden" name ="paypal_order_type' . $_currentMethod->virtuemart_paymentmethod_id . '" id="paypal_order_type' . $_currentMethod->virtuemart_paymentmethod_id . '" value="' . $type . '"/>
            <input type="hidden" id="payment_ref' . $_currentMethod->virtuemart_paymentmethod_id . '" name="payment_ref_' . $_currentMethod->virtuemart_paymentmethod_id . '" value="' . $paymentRef . '" />
        </table> </div>';
        $helpers->loadNovalnetJs('novalnet_paypal', 'site');
        $js = "
        var novalnet_paypal_details = new Array ()
        novalnet_paypal_details ['updatetext'] =  '" . addslashes(JText::_('VMPAYMENT_NOVALNET_UPDATE_PAYPAL_DETAILS')) . "';
        novalnet_paypal_details ['giventext'] =  '" . addslashes(JText::_('VMPAYMENT_NOVALNET_GIVEN_PAYPAL_DETAILS')) . "';
        ";
        vmJsApi::addJScript('novalnet_paypal', $js);
        return $html;
    }
}
