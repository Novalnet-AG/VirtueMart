/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_cc
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: novalnet_cc_admin.js
 */

jQuery(document).ready(function () {
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
    jQuery('#params_onhold_amount').keydown(
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
});
