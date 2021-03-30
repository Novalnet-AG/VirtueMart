<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: normaltariff.php
 */

// No direct access
defined('_JEXEC') or die();
/**
 * tariff class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class JFormFieldNormaltariff extends JFormField
{
    /**
     * To set input field for admin configuration
     *
     * @return mixed
     */
    protected function getInput()
    {
        $document = JFactory::getDocument();
        $document->addScript(JURI::root(true) . '/plugins/system/novalnet/assets/js/novalnet.js');
        $style = 'div #tariff_one,#tariff_two,#tariff_two_amount,#common_field {
                                display: none;
                    }';
        $document->addStyleDeclaration($style);
        return JHTML::_('select.genericlist', array(JText::_('PLG_SYSTEM_NOVALNET_OPTION_SELECT')), $this->name, 'class="inputbox" size="1" onchange="Novalnet.setTariff(this)"', 'value', 'text', $this->value, $this->id);
    }
}
