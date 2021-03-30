<?php
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
 * Script: NovalnetCreditcardForm.php
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * CreditCard payment form class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class NovalnetCreditcardForm
{
    /**
     * To render the Credit Card masking pattern
     *
     * @param   array   $maskedPatterns customer masked details
     * @param   string  $method         payment method id
     * @param   boolean $oneClick       oneclick option
     *
     * @return mixed
     */
    public function renderMaskedForm($maskedPatterns, $method, $oneClick = false)
    {
        $document = JFactory::getDocument();
        $style    = '#cc_toggle_name:hover {
                    background: none;
                  }';
        $document->addStyleDeclaration($style);
        $this->helpers  = new NovalnetUtilities;
        $paymentDetails = $this->helpers->handleSession('novalnet_cc', 'get', 'novalnet_cc');
        $html           = '<br /><a id="cc_toggle_name" onclick="cc_toggle_name()" style="color: #095197;
        text-decoration: underline; font-weight: bold;cursor:pointer;">' . JText::_('VMPAYMENT_NOVALNET_UPDATE_CC') . '</a>
        <br/><br/>
        <table id="novalnet_cc_maskedform" border="0" cellspacing="0" cellpadding="2" width="100%">
            <tr valign="top">
                <td nowrap align="right" style="padding:1%">
                    <label>' . JText::_('VMPAYMENT_NOVALNET_CC_CARD_TYPE') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['cc_card_type'] . '</td>
            </tr>
            <tr valign="top">
                <td nowrap align="right" style="padding:1%">
                    <label>' . JText::_('VMPAYMENT_NOVALNET_CC_OWNER') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['cc_holder'] . '</td>
            </tr>
            <tr valign="top">
                <td nowrap align="right" style="padding:1%">
                    <label>' . JText::_('VMPAYMENT_NOVALNET_CC_NUMBER') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['cc_no'] . '</td>
            </tr>
            <tr>
                <td nowrap align="right" style="padding:1%">' . JText::_('VMPAYMENT_NOVALNET_CC_EXPIRATION') . '</td>
                <td style="padding:1%">' . $maskedPatterns['cc_exp_month'] . '/' . $maskedPatterns['cc_exp_year'] . '</td>
            </tr>
            <input type="hidden" name="nncc_oneclick' . $method->virtuemart_paymentmethod_id . '"
            id="nncc_oneclick' . $method->virtuemart_paymentmethod_id . '" value="1"/>
            <input type="hidden" id="payment_ref' . $method->virtuemart_paymentmethod_id . '" name="payment_ref_' . $method->virtuemart_paymentmethod_id . '" value="' . $paymentDetails['payment_ref' . $method->virtuemart_paymentmethod_id] . '" />
        </table>';

        if ($oneClick)
            $html .= $this->renderLocaliframeForm($method, 'none');
        return $html;
    }

    /**
     * To render the Credit Card iframe form
     *
     * @param   object $paymentDetails payment configuration details
     * @param   string $display        to show or hide the form
     *
     * @return mixed
     */
    public function renderLocaliframeForm($paymentDetails, $display = 'block')
    {
        $html = '<input type="hidden" id="pan_hash' . $paymentDetails->virtuemart_paymentmethod_id . '" name="pan_hash' . $paymentDetails->virtuemart_paymentmethod_id . '" value="" />';
        $html .= '<input type="hidden" id="nn_cc_uniqueid_' . $paymentDetails->virtuemart_paymentmethod_id . '" name="nn_cc_uniqueid_' . $paymentDetails->virtuemart_paymentmethod_id . '" value="" />';
        $html .= '<input type="hidden" value="" id="creditcard_error" name="creditcard_error">';
        $html .= '<input type="hidden" value="" id="nn_chk_confirm" name="nn_chk_confirm">';
        $html .= '<input type="hidden" value="' . JText::_('VMPAYMENT_NOVALNET_UPDATE_CC') . '" id="creditcard_text">';
        $html .= '<input type="hidden" value="' . JText::_('VMPAYMENT_NOVALNET_GIVEN_CC') . '" id="creditcard_given">';
        $html .= '<input type="hidden" value="' . $paymentDetails->virtuemart_paymentmethod_id . '" name="novalnet_cc_id" id="novalnet_cc_id">';
        $html .= '<div id="novalnet_cc_localform" style="display:' . $display . '">';
        $html .= '<div id="creditcard_error_msg" style="color:red"></div>';
        $helpers = new NovalnetUtilities;
        $helpers->getMerchantDetails();
        $html .= '<iframe id="nnIframe" src=https://secure.novalnet.de/cc?api=' . base64_encode("vendor=" . $helpers->vendor_id . "&product=" . $helpers->product_id . "ln=" . $helpers->getLang()) . ' style=" border-style:none !important;"
        onload="loadiframeCreditcard()" width="100%"></iframe></div>';
        if ($paymentDetails->shopping_type == 'one_click' && $paymentDetails->enable_cc3d == '0' && $paymentDetails->cc3d_force == '0')
           $html .= '<table order="0" width="100%" cellspacing="0" cellpadding="2"><tbody id="novalnet_cc_localform_cc" border="0" cellspacing="0" cellpadding="2" width="100%" style="display:' . $display . '"><tr valign="top"><td style="width: 463px;height: 30px;"><input type="checkbox" style="display:' . $display . '" value="1" class="cc_save_card' . $paymentDetails->virtuemart_paymentmethod_id . '" id="cc_save_card' . $paymentDetails->virtuemart_paymentmethod_id . '" name="cc_save_card' . $paymentDetails->virtuemart_paymentmethod_id . '"/>' . ' ' . '<span id="save_card_text' . $paymentDetails->virtuemart_paymentmethod_id . '" style="margin:-4% 0% 0% 6%;display:' . $display . '">' . vmText::_('VMPAYMENT_NOVALNET_SAVE_CC_PAYMENT_DATA') . '</td></tr></tbody></table>';
        $html .= '
                <script type="text/javascript">
                //<![CDATA[
                loadiframeCreditcard = function () {
                    var styleObj = {
                        labelStyle: "' . $paymentDetails->nnlabel . '",
                        inputStyle: "' . $paymentDetails->nninput . '",
                        styleText: "' . $paymentDetails->nncss . '",
                    };
                    var textObj = {
                        cvcHintText: "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_CVC_HINT') . '",
                        errorText:  "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_ERROR') . '",
                        card_holder: {
                            labelText: "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_HOLDER_LABEL_TEXT') . '",
                            inputText: "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_HOLDER_INPUT_TEXT') . '",
                        },
                        card_number: {
                            labelText: "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_NUMBER_LABEL_TEXT') . '",
                            inputText: "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_NUMBER_INPUT_TEXT') . '",
                        },
                        expiry_date: {
                            labelText: "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_EXPIRYDATE_LABEL_TEXT') . '",
                            inputText: "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_EXPIRYDATE_INPUT_TEXT') . '",
                        },
                        cvc: {
                            labelText: "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_CVC_LABEL_TEXT') . '",
                            inputText: "' . JText::_('VMPAYMENT_NOVALNET_CC_IFRAME_CVC_INPUT_TEXT') . '",
                        }
                    };

                    var requestObj = {
                        callBack: \'createElements\',
                        customStyle: styleObj,
                        customText: textObj
                    };
                    loadNovalnetCreditcardIframe(JSON.stringify(requestObj));
                    loadNovalnetCreditcardIframe(JSON.stringify({callBack: "getHeight"}));
                    jQuery("#checkoutFormSubmit").attr("onclick","return getHashValue(event)");
                    jQuery("#tos").attr("onclick","return getHashValue(event)");
                }

                loadNovalnetCreditcardIframe = function (request) {
                    var iframe = jQuery("#nnIframe")[0];
                    var target_orgin = "https://secure.novalnet.de";
                    iframeWindow = iframe.contentWindow ? iframe.contentWindow : iframe.contentDocument.defaultView;
                    iframeWindow.postMessage(request, target_orgin);
                }
                jQuery(window).resize(function () {
                    loadNovalnetCreditcardIframe(JSON.stringify({callBack: "getHeight"}));
                });

                jQuery("#paymentForm").submit(function (event) {
                    var payment_id = jQuery("input[name=virtuemart_paymentmethod_id]:checked").val();
                    var cc_payment_id = jQuery("input[name=novalnet_cc_id]").val();
                    if (jQuery(\'#nncc_oneclick\' + payment_id).val() == 1) return true;
                    if (payment_id == cc_payment_id) {
                        if (jQuery("#pan_hash" + payment_id).val() == "") {
                            (event.preventDefault) ? event.preventDefault() : event.returnValue = false;
                            getHash();
                        }
                    } else {
                        return true;
                    }
                });

            if (window.addEventListener) {
                window.addEventListener("message", function (e) {
                    addEventServerResponse(e);
                }, false);
            } else {
                    window.attachEvent("onmessage", function (e) {
                    addEventServerResponse(e);
                });
            }

            addEventServerResponse = function (e) {
                if (e.origin === "https://secure.novalnet.de") {
                if (typeof e.data === "string") {
                    var data = eval(\'(\' + e.data.replace(/(<([^>]+)>)/gi, "") + \')\');
                } else {
                    var data = e.data;
                }
                    if (data["callBack"] == "getHash") {
                        if (data["error_message"] != undefined) {
                            jQuery("#creditcard_error_msg").html("<br>"+data["error_message"]);
                            jQuery("#creditcard_error").val(data["error_message"]);
                        } else {
                                var payment_id = jQuery("input[name=virtuemart_paymentmethod_id]:checked").val();
                                jQuery("#pan_hash" + payment_id).val(data["hash"]);
                                jQuery("#nn_cc_uniqueid_" + payment_id).val(data["unique_id"]);
                                if (jQuery("#pan_hash" + payment_id).val()) {
                                var nn_chk_confirm = jQuery("#nn_chk_confirm").val();
                                    if(jQuery("#nn_chk_confirm").val()){
                                        jQuery("#"+nn_chk_confirm).trigger("click");
                                        jQuery("#"+nn_chk_confirm).attr("checked","checked");
                                    }
                                    var form_id = jQuery("#checkoutForm").length ? "#checkoutForm" : "#paymentForm";
                                    jQuery(form_id).submit();

                                } else {
                                    jQuery("#creditcard_error").val(data["error_message"]);
                                    var form_id = jQuery("#checkoutForm").length ? "#checkoutForm" : "#paymentForm";
                                    jQuery(form_id).submit();
                                }
                        }
                    } else if (data["callBack"] == "getHeight") {
                        jQuery("#nnIframe").attr("height", data["contentHeight"]);
                    }
                }
            }
            jQuery(".vm-button-correct").click(function(evt) {
                var payment_id = jQuery("input[name=virtuemart_paymentmethod_id]:checked").val();
                var cc_payment_id = jQuery("#novalnet_cc_id").val();
                if (jQuery(\'#nncc_oneclick\' + payment_id).val() == 1) return true;
                if (payment_id == cc_payment_id) {

                    if (jQuery("#pan_hash" + payment_id).val() == "") {
                        (evt.preventDefault) ? evt.preventDefault() : evt.returnValue = false;
                        evt.stopImmediatePropagation();
                        getHash();
                    }
                } else {
                    return true;
                    }
                });

                getHash = function () {
                    loadNovalnetCreditcardIframe(JSON.stringify({callBack: \'getHash\'}));
                }

                getHashValue = function (evt) {

                    var payment_id = jQuery("input[name=virtuemart_paymentmethod_id]:checked").val();
                    jQuery("#nn_chk_confirm").val(evt.target.id);
                    var cc_payment_id = jQuery("#novalnet_cc_id").val();
                    if (jQuery(\'#nncc_oneclick\' + payment_id).val() == 1) return true;
                    if (payment_id == cc_payment_id) {
                        if (jQuery("#pan_hash" + payment_id).val() == "") {
                            (evt.preventDefault) ? evt.preventDefault() : evt.returnValue = false;
                            evt.stopImmediatePropagation();
                            getHash();
                        }
                    }   else {
                            return true;
                        }
                }
        cc_toggle_name = function () {
        var payment_id = jQuery("input[name=virtuemart_paymentmethod_id]:checked").val();
        var toggeleLabel = jQuery("#creditcard_text").val();
        if (jQuery("#cc_toggle_name").text() == jQuery("#creditcard_text").val())
        {
        jQuery("#cc_save_card" + payment_id).show();
        jQuery("#save_card_text" + payment_id).show();
        jQuery("#novalnet_cc_localform_cc").show();
            loadNovalnetCreditcardIframe(JSON.stringify({callBack: \'getHeight\'}));
            toggeleLabel = jQuery("#creditcard_given").val();
            jQuery("#nncc_oneclick" + payment_id).val(0);
                if(jQuery("#tos").prop("checked")){
                    jQuery("#tos").prop("checked",false);
                }
        } else {
            jQuery("#nncc_oneclick" + payment_id).val(1);
                if(jQuery("#tos").prop("checked"))
                {
                    jQuery("#tos").prop("checked",false);
                }
        jQuery("#cc_save_card" + payment_id).hide();
        jQuery("#save_card_text" + payment_id).hide();
        jQuery("#novalnet_cc_localform_cc").hide();
        }
        jQuery("#cc_toggle_name").text(toggeleLabel);
        jQuery("#novalnet_cc_localform, #novalnet_cc_maskedform").toggle();
    }
//]]> </script>';
        return $html;
    }
}
