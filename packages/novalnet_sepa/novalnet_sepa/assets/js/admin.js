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
 * Script: admin.js
 */
if (typeof NovalnetSepa === "undefined")
    NovalnetSepa = {};

jQuery().ready(
    function ($) {
        /*****************/
        /* Initial call */
        /*****************/
        NovalnetSepa.manual_check_limit();
    }
);
jQuery().ready(
    function ($) {
        jQuery('.min_amount_sepa').parents('.control-group').hide();
        jQuery('.sepa_duedate').parents('.control-group').hide();
        jQuery('.sepa_guarantee_min_amount').blur(
            function () {
                var min_amount = jQuery(this).val();
                if (min_amount != '') {
                    if (min_amount >= 999) {
                        jQuery('.min_amount_sepa').parents('.control-group').hide();
                        return true;
                    } else {
                        NovalnetSepa.setCssStyle('min_amount_sepa');
                        jQuery('#params_min_amount_guarantee').val('');
                    }
                } else {
                    jQuery('.min_amount_sepa').parents('.control-group').hide();
                }
            }
        );
        jQuery('.sepa_nn_due_date').blur(
            function () {
                var paramssepa_due_date = jQuery(this).val();
                if (paramssepa_due_date) {
                    if (paramssepa_due_date < 2 || paramssepa_due_date > 14) {
                        NovalnetSepa.setCssStyle('sepa_duedate');
                        jQuery('.sepa_nn_due_date').val('');
                    } else {
                        jQuery('.sepa_duedate').parents('.control-group').hide();
                    }
                } else {
                    jQuery('.sepa_duedate').parents('.control-group').hide();
                }
            }
        );
        jQuery('.sepa_guarantee_min_amount,.sepa_nn_due_date,.sepa_pin_amount,#params_onhold_amount').keydown(
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
        jQuery(".guarantee_sepa").parents('.control-label').css({
            "font-weight": "bolder",
            "width": "100%",
        });
        jQuery('.account_holder').on('keypress', function (event){
         var char_code = "which" in(event = event || window.event) ? event.which : event.keyCode;
         if (/^[\[\]\/\\#,+@!^()$~%'"=:;<>{}\_\|*?1234567890`]/g.test(String.fromCharCode(char_code)))
             event.preventDefault();
         });
        jQuery('.bank_account').on('keypress', function (event) {
         var char_code = "which" in(event = event || window.event) ? event.which : event.keyCode;
         if (/^[\[\]\/\\#,+@!^()$~%&_.'"=:;<>{}\_\|*?`]/g.test(String.fromCharCode(char_code)))
             event.preventDefault();
        });
        jQuery('#params_nn_end_customer').blur(function(){
        var myContent = jQuery('#params_nn_end_customer').val();
        var rex = /(<([^>]+)>)/ig;
        jQuery('#params_nn_end_customer').val(myContent.replace(rex , ""));
        });
    }
);
NovalnetSepa.manual_check_limit = function () {
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
NovalnetSepa.setCssStyle = function (divclass) {
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

