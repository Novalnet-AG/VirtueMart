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
 * Script: site.js
 */

if (typeof novalnet_sepa === "undefined")
    novalnet_sepa = {};

jQuery().ready(
    function ($) {
        novalnet_sepa.loadevent();
        jQuery(document).ajaxComplete(
            function () {
                novalnet_sepa.loadevent();
            }
        );
    }
);
(jQuery);

novalnet_sepa.loadevent = function () {
    var payment_id = jQuery("input[name=virtuemart_paymentmethod_id]:checked").val();
    jQuery('#mandate_confirm').click(function () {
        jQuery('#sepa_mandate_details').toggle();
    });

    jQuery('.account_holder, .bank_account').live(
        'keypress',
        function (event) {
            var payment_id = jQuery("input[name=virtuemart_paymentmethod_id]:checked").val();
            if (payment_id == undefined) {
                alert(novalnet_sepa_details.selectpayment);
                return false;
            }
            jQuery('.bank_account').keyup(function () {
                jQuery(this).val(jQuery(this).val().toUpperCase());
            });
        }
    );
}

novalnet_sepa.sepaToggleName = function () {
    var payment_id = jQuery("input[name=virtuemart_paymentmethod_id]:checked").val();
    var sepa_payment_id = jQuery("input[name=sepa_payment_id]").val();
    if (payment_id != sepa_payment_id) {
        payment_id = sepa_payment_id;
    }

    var toggeleLabel = novalnet_sepa_details.updatetext;
    if (jQuery("#sepa_toggle_name").text() == novalnet_sepa_details.updatetext) {
        toggeleLabel = novalnet_sepa_details.giventext;
        jQuery("#nnsepa_oneclick" + payment_id).val(0);
    } else {
        jQuery("#nnsepa_oneclick" + payment_id).val(1);
    }
    jQuery('#nn_message').hide();
    jQuery("#sepa_toggle_name").text(toggeleLabel);
    jQuery("#novalnet_sepa_localform, #novalnet_sepa_maskedform").toggle();
    if (jQuery("#novalnet_sepa_maskedform").css("display") == "none") {
        jQuery("#save_card_text" + payment_id).css("display", "block");
        jQuery("#sepa_save_card" + payment_id).css("display", "block");
    } else {
        jQuery("#save_card_text" + payment_id).css("display", "none");
        jQuery("#sepa_save_card" + payment_id).css("display", "none");
    }
}
