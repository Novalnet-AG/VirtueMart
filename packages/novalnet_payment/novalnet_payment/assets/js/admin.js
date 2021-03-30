/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * @package    NovalnetPayments
 * @subpackage novalnet_payment
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet Team. All rights reserved.
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 *
 * Script: admin.js
 */

if (typeof NovalnetPayment === "undefined")
    NovalnetPayment = {};

jQuery().ready(
    function ($) {
        if (jQuery('#novalnet_transaction').val() != undefined && window.location.href.indexOf("#novalnet_transaction") == -1) {
            window.location.href = window.location.href + '#novalnet_transaction';
        }

        jQuery('a.NovalnetupdateOrder').click(
            function (event) {
                event.preventDefault();
                NovalnetPayment.sendXDomainRequest(jQuery(this).attr('name'));
            }
        );
    }
);
NovalnetPayment.sendXDomainRequest = function (operation) {
    switch (operation) {
        case 'duedate_update':

            if (jQuery('#duedateUpdate').val() == '') {
                alert(jQuery('#due_date_text').val());
                window.location.reload();
                return false;
            }

            if (!confirm(jQuery('#duedate_text').val())) {
                return false;
            }
            operation = operation + '&due_date=' + jQuery('#duedateUpdate').val();
            break;

        case 'zero_booking':
            if (!confirm(jQuery('#booked_text').val())) {
                return false;
            }
            break;

        case 'refund':
            if (!confirm(jQuery('#refund_text').val())) {
                return false;
            }
            break;

        case 'capture':
            if (!confirm(jQuery('#confirm_text').val())) {
                return false;
            }
            break;

        case 'void':
            if (!confirm(jQuery('#cancel_text').val())) {
                return false;
            }
            break;

        case 'amount_update':
            if (!confirm(jQuery('#amount_update_text').val())) {
                return false;
            }
            break;
    }

    jQuery('#loading-img').show();

    if (Virtuemart.vmSiteurl == undefined)
        Virtuemart.vmSiteurl = '../';

    var url = Virtuemart.vmSiteurl + 'index.php?';
    var qryString = 'option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&form_type=extension&order_number=' + jQuery('#order_number').val() + '&operation=' + operation;

    if ('XDomainRequest' in window && window.XDomainRequest !== null) {
        var xdr = new XDomainRequest();
        xdr.open('POST', url);
        xdr.onload = function () {
            data = JSON.parse(this.responseText);
            NovalnetPayment.processResponse(data);
        };
        xdr.send(qryString);
    } else {
        var xmlhttp = (window.XMLHttpRequest) ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                NovalnetPayment.processResponse(xmlhttp.responseText);
            }
        }
        xmlhttp.open("POST", url, true);
        xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xmlhttp.send(qryString);
    }
}

NovalnetPayment.processResponse = function (data) {
    jQuery('#loading-img').hide();
    var result = data.match(/<span id="status_desc">(.+?)<\/span>/);
    alert(result[1]);
    window.location.reload();
}

