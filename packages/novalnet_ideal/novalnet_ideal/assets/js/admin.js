/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_cashpayment
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: admin.js
 */

jQuery().ready(function ($) {
        jQuery('#params_nn_end_customer').blur(function(){
        var myContent = jQuery('#params_nn_end_customer').val();
        var rex = /(<([^>]+)>)/ig;
        jQuery('#params_nn_end_customer').val(myContent.replace(rex , ""));
        });
    });
