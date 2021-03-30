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
 * Script: admin.js
 */

if (typeof NovalnetInvoice === "undefined")
    NovalnetInvoice = {};

NovalnetInvoice.manual_check_limit = function () {
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
jQuery().ready(
    function ($) {
        /*****************/
        /* Initial call */
        /*****************/
        NovalnetInvoice.manual_check_limit();
    }
);
jQuery().ready(
    function ($) {
        jQuery('.min_amount_invoice').parents('.control-group').hide();
        jQuery('.invoice_guarantee_min_amount').blur(
            function () {
                var min_amount = jQuery(this).val();
                if (min_amount != '') {
                    if (min_amount >= 999) {
                        jQuery('.min_amount_invoice').parents('.control-group').hide();

                        return true;
                    } else {
                        NovalnetInvoice.setInvoiceGuaranteeStyle('min_amount_invoice');
                        jQuery('#params_min_amount_guarantee').val('');
                    }
                } else {
                    jQuery('.min_amount_invoice').parents('.control-group').hide();
                }
            }
        );
        jQuery('.invoice_duedate, .onhold_amount, .invoice_guarantee_min_amount,.cashpayment_duedate').keydown(
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
        jQuery(".guarantee_invoice").parents('.control-label').css({
            "font-weight": "bolder",
            "width": "100%",
        });

        NovalnetInvoice.setInvoiceGuaranteeStyle = function (divclass) {
            jQuery("." + divclass).parents('.control-label').css({
                "background-color": "#cacaca",
                "font-weight": "bolder",
                "color": "red",
                "margin": "10px 0 5px",
                "padding": "5px",
                "width": "100%",
            });
            jQuery('.' + divclass).parents('.control-group').show();
        }
    }
);
