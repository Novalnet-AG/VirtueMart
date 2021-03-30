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
 * Script: novalnet.js
 */

if (typeof Novalnet === "undefined")
    Novalnet = {};

jQuery().ready(
    function () {

        var tariff_id_options = jQuery('#jform_params_tariff_id_options').val();
        if (tariff_id_options != '') {
            Novalnet.setTariffOptions(JSON.parse(tariff_id_options), 'jform_params_tariff_id');
        }
        jQuery("#jform_params_referrer_id").blur(
            function () {
                var referrerId = jQuery('#jform_params_referrer_id').val();
                if ((referrerId != '') && (/^(?:[0-9]+$)/.test(referrerId) == false)) {
                    jQuery('#jform_params_referrer_id').val('');
                }
            }
        );

        jQuery("#jform_params_curl_timeout").blur(
            function () {
                var curlTimeout = jQuery('#jform_params_curl_timeout').val();
                if ((curlTimeout != '') && (/^(?:[0-9]+$)/.test(curlTimeout) == false)) {
                    jQuery('#jform_params_curl_timeout').val(240);
                }
            }
        );
        jQuery('#jform_params_referrer_id').keydown(
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
    }
);

Novalnet.getConfig = function (event) {
    event.value = event.value.replace (/(^\s*)|(\s*$)/gi, "").replace (/[ ]{2,}/gi," ").replace (/\n +/,"\n");
    var curlTimeout = jQuery('#jform_params_curl_timeout').val() ? jQuery('#jform_params_curl_timeout').val() : '240';
    var nnLang = jQuery("html").attr('lang');
    nnLang = nnLang.substring(0, 2);
    nnLang = nnLang.toUpperCase();
    var filePath = '../plugins/system/novalnet/models/fields/tariffconfig.php';
    var configParams = {
        "api_config_hash": event.value,
        "curl_timeout": curlTimeout,
        "lang": nnLang
    };
    configParams = jQuery.param(configParams);
    if ('XDomainRequest' in window && window.XDomainRequest !== null) {
        var xdr = new XDomainRequest();
        xdr.open('POST', filePath);
        xdr.onload = function () {

            return Novalnet.processResponse(this.responseText);
        }
        xdr.send(configParams);
    } else {
        var xmlhttp = (window.XMLHttpRequest) ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                return Novalnet.processResponse(xmlhttp.responseText);
            }
        }
        xmlhttp.open("POST", filePath, true);
        xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xmlhttp.send(configParams);
    }
}

Novalnet.processResponse = function (response) {
    if (response) {
        var result = jQuery.parseJSON(response);
        if (result.status == 100) {
            jQuery('#jform_params_access_key').val(result.access_key);
            jQuery('#jform_params_vendor_id').val(result.vendor_id);
            jQuery('#jform_params_auth_code').val(result.auth_code);
            jQuery('#jform_params_product_id').val(result.product_id);
            if (result.tariff != '') {
                jQuery('#jform_params_tariff_id_options').val(JSON.stringify(result.tariff));
            }
            if (result.tariff != '') {
                Novalnet.setTariffOptions(result.tariff, 'jform_params_tariff_id');
            }
        } else {
            alert(result.status_desc);
        }
    }
}

Novalnet.setTariffOptions = function (tariff, tariffId) {
    var obj = jQuery('#' + tariffId);
    obj.find("option").eq(0).remove();
    for (var index in tariff) {
        var select = jQuery('#' + tariffId);
        jQuery(
            "<option></option>", {
                value: tariff[index].type + '-' + index,
                text: tariff[index].name
            }
        ).appendTo(select);
    }
    var tariff = jQuery('#' + tariffId + '_selected').val();
    if (tariff != '') {
        jQuery('#' + tariffId).val(jQuery('#' + tariffId + '_selected').val());
    }
}

Novalnet.setTariff = function (event) {
    jQuery('#' + event.id + '_selected').val(event.value);
}
