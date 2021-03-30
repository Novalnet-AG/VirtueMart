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
 * Script: site.js
 */

jQuery().ready(
    function ($) {
		jQuery(document).ajaxComplete(
            function () {
                if (jQuery("#paypal_toggle_name").text() == novalnet_paypal_details.updatetext) {
					jQuery("#paypal_oneclick").hide();
				}
            }
        );
        var payment_id = jQuery("#paypal_paymentid").val();
		
        jQuery("#paypal_oneclick").hide();
        jQuery("#paypal_toggle_name").live(
            "click",
            function (event) {
                var toggeleLabel = novalnet_paypal_details.updatetext;
                if (jQuery("#paypal_toggle_name").text() == novalnet_paypal_details.updatetext) {
                    toggeleLabel = novalnet_paypal_details.giventext;
                }

                jQuery("#novalnet_paypal_maskedform").toggle();
                jQuery("#paypal_toggle_name").text(toggeleLabel);
                var getstyle = jQuery('#novalnet_paypal_maskedform').css('display');
                if (getstyle == 'none') {
                    jQuery("#nnpaypal_oneclick" + payment_id).val('');
                    jQuery("#nnpaypal_oneclick" + payment_id).val(0);
                    jQuery("#paypal_order_type" + payment_id).val('normal');
                    jQuery('#oneclick_new').show();
                    jQuery('#oneclick').hide();
                     jQuery("#paypal_oneclick").show();
                } else {
                    jQuery('#oneclick_new').hide();
                    jQuery('#oneclick').show();
                    jQuery("#nnpaypal_oneclick" + payment_id).val('');
                    jQuery("#nnpaypal_oneclick" + payment_id).val(1);
                    jQuery("#paypal_order_type" + payment_id).val('');
                    jQuery("#paypal_oneclick").hide();
                }
            }
        );
    }
);
