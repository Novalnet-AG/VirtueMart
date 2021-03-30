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
 * Script: admin.js
 */

if (typeof NovalnetPaypal === "undefined")
    NovalnetPaypal = {};


jQuery().ready(
    function ($) {
        NovalnetPaypal.manual_check_limit();
        NovalnetPaypal.paypaloneclickcheck();
        jQuery('#params_novalnet_paypal_manual_check_limit').keydown(
            function (event) {
                if (event.keyCode == 46 || event.keyCode == 8 || event.keyCode == 9 || event.keyCode == 27 || event.keyCode == 13 || (event.keyCode == 65 && event.ctrlKey === true) || (event.keyCode >= 35 && event.keyCode <= 39)) {
                    return;
                } else {
                    if (event.shiftKey || (event.keyCode < 48 || event.keyCode > 57) && (event.keyCode < 96 || event.keyCode > 105)) {
                        event.preventDefault();
                    }
                }
            }
        );
        jQuery('#params_nn_end_customer').blur(function(){
        var myContent = jQuery('#params_nn_end_customer').val();
        var rex = /(<([^>]+)>)/ig;
        jQuery('#params_nn_end_customer').val(myContent.replace(rex , ""));
        });
    }
);
NovalnetPaypal.manual_check_limit = function () {
    if (jQuery('#params_onhold_action1').prop('checked')) {
        jQuery("#params_onhold_amount, #params_onhold_amount-lbl").closest("div").css({
            "display": "block"
        });
    } else {
        jQuery("#params_onhold_amount, #params_onhold_amount-lbl").closest("div").css({
            "display": "none"
        });
    }
    jQuery("#params_onhold_action1").click(function () {
        jQuery("#params_onhold_amount, #params_onhold_amount-lbl").closest("div").css({
            "display": "block"
        });
    });
    jQuery("#params_onhold_action0").click(function () {
        jQuery("#params_onhold_amount, #params_onhold_amount-lbl").closest("div").css({
            "display": "none"
        });
    });
}
NovalnetPaypal.paypaloneclickcheck = function () {

    if (jQuery('.paypal_shopping_type').val() != '0') {
        jQuery(".paypal_oneclick").parents('.control-group').show();
        jQuery(".paypal_oneclick").parents('.control-label').css({
            "background-color": "#cacaca",
            "font-weight": "bolder",
            "color": "#3a87ad",
            "margin": "10px 0 5px",
            "padding": "5px",
            "width": "100%",
        });
    } else {
        jQuery(".paypal_oneclick").parents('.control-group').hide();
    }
}

