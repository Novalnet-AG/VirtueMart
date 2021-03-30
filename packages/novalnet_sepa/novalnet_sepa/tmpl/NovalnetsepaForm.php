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
 * Script: NovalnetsepaForm.php
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Direct Debit SEPA payment form field class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class NovalnetSepaForm
{
    /**
     * To render the SEPA local form
     *
     * @param   object $cart           which is current cart object
     * @param   object $currentMethod  which is current payment method object
     * @param   string $display        the show the sepa form based on the conditions
     *
     * @return mixed
     */
    public function renderLocalForm($cart, $currentMethod, $display = 'block')
    {
        $name    = (!empty($cart->BT)) ? $cart->BT['first_name'] . ' ' . $cart->BT['last_name'] : '';
        $ibanValue = vRequest::getVar('bank_account' . $cart->virtuemart_paymentmethod_id, '');
        $helpers = new NovalnetUtilities;
        $helpers->getMerchantDetails();
        $html = '
        <span id="novalnetloader" style="display:none;margin:auto; left:50%; top:50%; height:100%; width:100%; z-index:10; position:fixed;">
             <img src=' . JURI::root() . '/plugins/vmpayment/novalnet_payment/novalnet_payment/assets/images/novalnet_loader.gif>
        </span>
        <tbody id="novalnet_sepa_localform" border="0" cellspacing="0" cellpadding="2" width="100%" style="display:' . $display . '">
            <tr valign="top" >
                <td nowrap align="right" style="padding:1% width:50%;">
                    <label>' . JText::_('VMPAYMENT_NOVALNET_SEPA_OWNER') . '</label>
                </td>
                <td style="padding:1%">
                    <input type="text" name="account_holder' . $currentMethod->virtuemart_paymentmethod_id . '" id="account_holder' . $currentMethod->virtuemart_paymentmethod_id . '" class="account_holder" value="' . $name . '" autocomplete="off" />
                </td>
            </tr>
            <tr valign="top">
                <td nowrap align="right" style="padding:1% width:50%;">
                    <label>' . JText::_('VMPAYMENT_NOVALNET_SEPA_IBAN') . '</label>
                </td>
                <td style="padding:1%">
                    <input type="text" name="bank_account' . $currentMethod->virtuemart_paymentmethod_id . '" id="bank_account' . $currentMethod->virtuemart_paymentmethod_id . '" class="bank_account" value="' . $ibanValue . '"  autocomplete="off"/>
                </td>
            </tr>
            <tr valign="top" class="iban_tr' . $currentMethod->virtuemart_paymentmethod_id . '" style="display:none">
                <td nowrap align="right" style="padding:1% width:50%;">
                    <label>IBAN</label>
                </td>
                <td style="padding:1%">
                    <label id="iban_value' . $currentMethod->virtuemart_paymentmethod_id . '"></label>
                </td>
            </tr>
            <tr valign="top">
                <td nowrap align="right" style="padding:1% width:50%;">
                </td>
                <td style="padding:1%">';
        $html .= '<a><label id="mandate_confirm">' . JText::_('VMPAYMENT_NOVALNET_MANDATE_CONFIRM') . '</label></a>' . JText::_('VMPAYMENT_NOVALNET_MANDATE_CONFIRM_DETAILS') . '</td></tr>';
        if ($currentMethod->shopping_type == 'one_click')
        {
            $html .= '<tr><td><input type="checkbox" style="display:' . $display . ';margin-left: 71px;" value="1" class="sepa_save_card' . $currentMethod->virtuemart_paymentmethod_id . '" id="sepa_save_card' . $currentMethod->virtuemart_paymentmethod_id . '" name="sepa_save_card' . $currentMethod->virtuemart_paymentmethod_id . '"/>' . '' . '<td><span id="save_card_text' . $currentMethod->virtuemart_paymentmethod_id . '"  style="display:' . $display . '">' . vmText::_('VMPAYMENT_NOVALNET_SAVE_SEPA_PAYMENT_DATA') . '</span></td></tr>';
        }
        $html .= '</tbody><input type="hidden" name="sepa_payment_id" value="' . $currentMethod->virtuemart_paymentmethod_id . '"/>
        <input type="hidden" name="sepa_payment_root" id="sepa_payment_root" value="' . JURI::root() . '"/>';
        $helpers->loadNovalnetJs('novalnet_sepa', 'site');
        $js = "
            var novalnet_sepa_details = new Array ()
            novalnet_sepa_details ['selectpayment'] =  '" . addslashes(JText::_('VMPAYMENT_SELECT_PAYMENT')) . "';
            novalnet_sepa_details ['updatetext'] =  '" . addslashes(JText::_('VMPAYMENT_NOVALNET_UPDATE_DETAILS')) . "';
            novalnet_sepa_details ['giventext'] =  '" . addslashes(JText::_('VMPAYMENT_NOVALNET_GIVEN_DETAILS')) . "';
            ";
        vmJsApi::addJScript('novalnet_sepa', $js);
        return $html;
    }

    /**
     * To render the SEPA masked form
     *
     * @param   object  $currentMethod  which is current payment method object
     * @param   object  $cart           which is current cart object
     * @param   array   $maskedPatterns which is current masking patten array
     *
     * @return string $html
     */
    public function renderMaskedForm($currentMethod, $cart, $maskedPatterns)
    {
        $helpers = new NovalnetUtilities;
        $paymentDetails = $helpers->handleSession($currentMethod->payment_element, 'get', $currentMethod->payment_element);
        $paymentrRef    = ($paymentDetails['payment_ref' . $currentMethod->virtuemart_paymentmethod_id]) ? $paymentDetails['payment_ref' . $currentMethod->virtuemart_paymentmethod_id] : $paymentDetails['payment_ref'];
        $html           = '<br/>
        <tbody id="novalnet_sepa_maskedform" >
            <tr valign="top">
                <td nowrap align="right" style="padding:1%;">
                    <label>' . JText::_('VMPAYMENT_NOVALNET_SEPA_OWNER') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['bankaccount_holder'] . '</td>
            </tr>
            <tr valign="top">
                <td nowrap align="right" style="padding:1%;">
                    <label>' . JText::_('VMPAYMENT_NOVALNET_SEPA_IBAN') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['iban'] . '</td>
            </tr>
            <input type="hidden" name="nnsepa_oneclick' . $currentMethod->virtuemart_paymentmethod_id . '" id="nnsepa_oneclick' . $currentMethod->virtuemart_paymentmethod_id . '" value="1"/>
            <input type="hidden" id="payment_ref' . $currentMethod->virtuemart_paymentmethod_id . '" name="payment_ref' . $currentMethod->virtuemart_paymentmethod_id . '" value=' . $paymentrRef . ' />
        </tbody>';

        $document = JFactory::getDocument();
        $style    = '#sepa_toggle_name:hover {
                    background: none;
                    cursor: pointer;
                  }';
        $document->addStyleDeclaration($style);
        return $html;
    }

    /**
     * Render the guarantee payment form
     *
     * @param   object $cart           which is current cart object
     * @param   object $currentMethod  which is current payment method object
     *
     * @return string
     */
    public function renderGuarantee($cart, $currentMethod = false)
    {
        $helpers = new NovalnetUtilities;
        $helpers->handleSession('novalnet_sepa', 'clear', 'error_guarantee');

        if (!empty($currentMethod->guarantee))
        {
            $billingAddress  = array(
                'address_1' => $cart->BT['address_1'],
                'address_2' => $cart->BT['address_2'],
                'zip' => $cart->BT['zip'],
                'city' => $cart->BT['city'],
                'country' => ShopFunctions::getCountryByID($cart->BT['virtuemart_country_id'], 'country_2_code')
            );
            $shippingAddress = array(
                'address_1' => $cart->ST['address_1'],
                'address_2' => $cart->ST['address_2'],
                'zip' => $cart->ST['zip'],
                'city' => $cart->ST['city'],
                'country' => ShopFunctions::getCountryByID($cart->ST['virtuemart_country_id'], 'country_2_code')
            );
            $minAmount       = ($currentMethod->min_amount_guarantee) ? $currentMethod->min_amount_guarantee : 999;
            $country         = ShopFunctions::getCountryByID($cart->BT['virtuemart_country_id'], 'country_2_code');
            $billTotal       = $helpers->formatAmount($cart->pricesUnformatted['billTotal']);
            $pricesCurrency  = CurrencyDisplay::getInstance($cart->pricesCurrency)->_vendorCurrency_code_3;
            if (($shippingAddress === $billingAddress || !empty($cart->STsameAsBT)) && ($helpers->formatAmount($cart->pricesUnformatted['billTotal']) >= $minAmount && in_array($country, array('DE', 'AT', 'CH'))) && $pricesCurrency == 'EUR')
            {
                $helpers->handleSession('novalnet_sepa', 'clear', 'error_guarantee');
                $company = $cart->BT['company'] != '' ? $cart->BT['company'] : $cart->ST['company'];
                if (empty($company))
                    return $this->renderDobField($currentMethod->virtuemart_paymentmethod_id);
            }
            elseif (!empty($currentMethod->guarantee_force))
            {
                $helpers->handleSession('novalnet_sepa', 'clear', 'error_guarantee');

                return '';
            }
            elseif (!empty($currentMethod->guarantee))
            {
                $helpers->handleSession('novalnet_sepa', 'set', 'error_guarantee', '1');
                $error = '';
                if ($billTotal <= $minAmount)
                {
                    $error = sprintf(vmText::_('VMPAYMENT_NOVALNET_GUARANTEE_MINIMUM_AMOUNT_ERROR_NOTIFY_DESC'), $helpers->formatAmount($minAmount, true)) . '<br>';
                }
                else if ($shippingAddress !== $billingAddress || empty($cart->STsameAsBT))
                {
                    $error = vmText::_('VMPAYMENT_NOVALNET_GUARANTEE_ADDRESS_ERROR_NOTIFY_DESC') . '<br>';
                }
                else if (!in_array($country, array('DE', 'AT', 'CH')))
                {
                    $error = vmText::_('VMPAYMENT_NOVALNET_GUARANTEE_COUNTRY_ERROR_NOTIFY_DESC') . '<br>';
                }
                else if ($pricesCurrency != 'EUR')
                {
                    $error = vmText::_('VMPAYMENT_NOVALNET_GUARANTEE_CURRENCY_ERROR_NOTIFY_DESC') . '<br>';
                }
                $helpers->handleSession('novalnet_sepa', 'set', 'guarantee_error_message', $error);
                return '
                    <tr >
                        <td colspan="2" style="padding:1%;color:red !important;">' . $error . '
                        </td>
                    </tr>';
            }
        }
    }

    /**
     * Render the birth date field
     *
     * @param   string $paymentid which is current payment method id
     *
     * @return string
     */
    public function renderDobField($paymentid)
    {
        $helpers   = new NovalnetUtilities;
        $birthDate = $helpers->handleSession('novalnet_sepa', 'get', 'birthDate');
        if (!$birthDate) {
            $birthDate = '';
        }
        return '
        <tr valign="top" id="dobField">
            <td nowrap align="right" style="padding:1%">
                <label>' . JText::_('VMPAYMENT_NOVALNET_DOB') . '</label>
            </td>
                <td style="padding:1%">' . JHTML::_('behavior.calendar') . JHTML::_('calendar', $birthDate, 'birthDate' . $paymentid, 'birthDate' . $paymentid, '%Y-%m-%d', array(
            'placeholder' => 'YYYY-MM-DD'
        )) . '
                </td>
        </tr>';
    }
}
