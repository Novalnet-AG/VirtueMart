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
 * Script: novalnetsepa.php
 */

// No direct access
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');

/**
 * JFormFieldAdminconfiguration payment method class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class JFormFieldNovalnetsepa extends JFormField
{
    /**
     * To get Novalnet admin portal details
     *
     * @return mixed
     */
    protected function getInput()
    {
        $db    = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('extension_id')->from('#__extensions')->where('name = "Novalnet Global Configuration"');
        $db->setQuery($query);
        $extension_id = $db->loadResult();
        return  vmText::_('VMPAYMENT_NOVALNET_GLOBAL_INFO') . ' <a href="' . JURI::root() . 'administrator/index.php?option=com_plugins&view=plugin&task=plugin.edit&extension_id=' . $extension_id . '" target="_blank">' . vmText::_('VMPAYMENT_NOVALNET_GLOBAL_CONFIG_INFO_2') . '</a><br>' ;
    }
}
