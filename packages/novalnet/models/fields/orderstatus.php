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
 * Script: orderstatus.php
 */

// No direct access
defined('_JEXEC') or die();
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

if (!class_exists('VmConfig'))
    include JPATH_ROOT . DS . 'administrator' . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php';

VmConfig::loadConfig();
/**
 * order status input class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class JFormFieldOrderstatus extends JFormField
{
    /**
     * To set input field for admin configuration
     *
     * @return mixed
     */
    protected function getInput()
    {
        if (!class_exists('VmConfig'))
            include JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php';

        if (!class_exists('VmModel'))
            include JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmmodel.php';

        VmConfig::loadConfig();
        VmConfig::loadJLang('com_virtuemart');
        $model       = VmModel::getModel('Orderstatus');
        $orderStatus = $model->getOrderStatusList();
        foreach ($orderStatus as $orderState)
            $orderState->order_status_name = JText::_($orderState->order_status_name);
        return JHTML::_('select.genericlist', $orderStatus, $this->name, 'class="inputbox" size="1"', 'order_status_code', 'order_status_name', $this->value, $this->id);
    }
}
