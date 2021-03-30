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
 * Script: NovalnetInvoiceForm.php
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Invoice payment form fieled class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class NovalnetInvoiceForm
{

    /**
     * Render the guarantee payment form
     *
     * @param   object $cart          the actual cart object
     * @param   object $currentmethod the actual cart object
     *
     * @return string
     */
    public static function renderGuarantee($cart, $currentmethod = false)
    {
        $helpers = new NovalnetUtilities;
        $helpers->handleSession('novalnet_invoice', 'clear', 'error_guarantee');
        if (!empty($currentmethod->guarantee))
        {
            $billingAddress = array(
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
            $minAmount       = ($currentmethod->min_amount_guarantee) ? $currentmethod->min_amount_guarantee : 999;
            $billTotal       = $helpers->formatAmount($cart->pricesUnformatted['billTotal']);
            $country         = ShopFunctions::getCountryByID($cart->BT['virtuemart_country_id'], 'country_2_code');
            $pricesCurrency  = CurrencyDisplay::getInstance($cart->pricesCurrency)->_vendorCurrency_code_3;
            $company         = !empty($cart->BT['company']) ? $cart->BT['company'] : (!empty($cart->BT['company']) ? $cart->BT['company'] : '');
            if (($shippingAddress === $billingAddress || !empty($cart->STsameAsBT)) && ($billTotal >= $minAmount && in_array($country, array('DE', 'AT', 'CH'))) && $pricesCurrency == 'EUR')
            {
                $helpers->handleSession('novalnet_invoice', 'clear', 'error_guarantee');
                // To Render the DOB field
                if (empty($company))
                    return self::renderDobField($currentmethod->virtuemart_paymentmethod_id);
            }
            elseif (!empty($currentmethod->guarantee_force))
            {
                $helpers->handleSession('novalnet_invoice', 'clear', 'error_guarantee');
                return '';
            }
            elseif (!empty($currentmethod->guarantee))
            {
                $helpers->handleSession('novalnet_invoice', 'set', 'error_guarantee', '1');
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
                $helpers->handleSession('novalnet_invoice', 'set', 'guarantee_error_message', $error);
                return '<tr valign="top" ><td style="padding:1%;color:red !important;">' . $error . '</td></tr>';
            }
        }
    }
    /**
     * Render the birth date field
     *
     * @param   string $paymentid the payment method id
     *
     * @return string
     */
    public static function renderDobField($paymentid)
    {
        $helpers = new NovalnetUtilities;
        $birthDate = $helpers->handleSession('novalnet_invoice', 'get', 'birthDate');

        if (!$birthDate)
            $birthDate = '';

        return '
        <tr valign="top">
            <td nowrap align="right" style="padding:1%">
                <label>' . vmText::_('VMPAYMENT_NOVALNET_DOB') . '</label>
            </td>
                <td style="padding:1%">' . JHTML::_('behavior.calendar') . JHTML::_('calendar', $birthDate, 'birthDate' . $paymentid, 'birthDate' . $paymentid, '%Y-%m-%d', array(
            'placeholder' => 'YYYY-MM-DD'
        )) . '</td>
        </tr>';
    }
}
?>
