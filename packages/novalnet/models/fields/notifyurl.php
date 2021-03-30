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
 * Script: notifyurl.php
 */

// No direct access
defined('_JEXEC') or die();

/**
 * set notify url class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class JFormFieldNotifyurl extends JFormField
{
    /**
     * To set input field for admin configuration
     *
     * @return mixed
     */
    protected function getInput()
    {
        return '<input name="' . $this->name . '" type="text" value="' . JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&form_type=vendor_script' . '" />';
    }
}
