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
 * Script: novalnet.php
 */

// No direct access
defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . 'is not allowed.');
/**
 * System plugin Novalnet class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class PlgSystemNovalnet extends JPlugin
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
        $affliateId = JRequest::getVar('nn_aff_id');
        if (isset($affliateId) && is_numeric($affliateId))
            JFactory::getSession()->set('nn_aff_id', $affliateId, 'novalnet');

        parent::__construct($subject, $config);
    }
}
