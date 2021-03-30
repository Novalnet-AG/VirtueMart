<?php
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
 * Script: adminconfiguration.php
 */

// No direct access
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');

/**
 * JFormFieldAdminconfiguration payment method class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class JFormFieldAdminconfiguration extends JFormField
{
    /**
     * To get Novalnet admin portal details
     *
     * @return mixed
     */
    protected function getInput()
    {
        $css = '
        div .novalnetAdminInfoUpdate {
            float: left;
            margin: 5px;
            padding: 15px;
            height: auto;
            border: 1px solid black;
            border-color : #ddd
        }
        div .novalnetAdminInfoUpdateInside {
            float: left;
            margin: 5px;
            padding: 15px;
            height: auto;
        }
        .nn_img {
            border        : 1px solid #CFC6C6;
            border-radius : 5px;
            box-shadow    : 2px 2px 5px 0 #B9ACAC;
        }
        span#novalnetDesc {
            float: left;
            margin: 5px;
            text-align:justify;
        }
        h3#novalnetDesc {
            margin-left: 15px;
        }
        .update_page_config_1{
            float:left;
            height:100%;
            width:100%;
        }
        .update_page_config_2{
            float:right;
            height:100%;
            width:100%;
        }
        .update_page_config_3{
            height:100%;
            width:100%;
        }
        .update_page_config_4{
            width: 33%;
            float:left;
            height:100%;
        }
        ';

        JFactory::getDocument()->addStyleDeclaration($css);

        return "
        <a href=" . JText::_('VMPAYMENT_NOVALNET_HOME_PAGE_URL') . " target=_blank> <img src=" . JURI::root() . "plugins/vmpayment/novalnet_payment/novalnet_payment/assets/images/updates/novalnet_logo.png></a><br><br>
        <tr>
           <div style='font-size:15px;font-weight:bold;padding:4px;text-align:center;'>
              <h2>" . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_VERSION') . "</h2>
           </div>
           <div class='novalnetAdminInfo'>" . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE') . "
              <br><br>
              " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_IT') . "<br>
           </div>
           <div class='novalnetAdminInfoUpdate'>
              <div class='novalnetAdminInfoUpdateInside'>
                    <h3>
                       " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_KEY') . "
                    </h3>
                 <div class='update_page_config_1'>

                    " . $this->loadNovalnetImages('projects_tab.png') . "
                 </div>
                 <div class='update_page_config_2'>

                    <span id='novalnetDesc'>
                    " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_KEY_DESC') . "
                    </span>
                 </div>
              </div>
              <div class='novalnetAdminInfoUpdateInside'>
                 <div class='update_page_config_1'>
                    " . $this->loadNovalnetImages('product_activation_key.png') . "
                 </div>
                 <div class='update_page_config_2'>
                    <span id='novalnetDesc'>
                    " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_KEY_DESCS') . "
                    </span>
                 </div>
              </div>
           </div>
           <div class='novalnetAdminInfoUpdate'>
              <div class='novalnetAdminInfoUpdateInside'>
                    <h3>
                       " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_IP') . "
                    </h3>
                 <div class='update_page_config_1'>

                    " . $this->loadNovalnetImages('projects_tab.png') . "
                 </div>
                 <div class='update_page_config_2'>

                    <span id='novalnetDesc'>
                    " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_IP_DESC') . "
                    </span>
                 </div>
              </div>
              <div class='novalnetAdminInfoUpdateInside'>
                 <div class='update_page_config_1'>
                    " . $this->loadNovalnetImages('system_ip_configuration.png') . "
                 </div>
                 <div class='update_page_config_2'>
                    <span id='novalnetDesc'>
                    " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_IP_DESCS') . "
                    </span>
                 </div>
              </div>
           </div>

           <div class='novalnetAdminInfoUpdate'>
              <div class='novalnetAdminInfoUpdateInside'>
                    <h3>
                       " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_VENDOR_URL') . "
                    </h3>
                 <div class='update_page_config_1'>

                    " . $this->loadNovalnetImages('projects_tab.png') . "
                 </div>
                 <div class='update_page_config_2'>
                    <span id='novalnetDesc'>
                    " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_VENDOR_URL_DESC') . "
                    </span>
                 </div>
              </div>
              <div class='novalnetAdminInfoUpdateInside'>
                 <div class='update_page_config_3>
                    " . $this->loadNovalnetImages('vendor_script_configuration.png') . "
                 </div>
                 <div class='update_page_config_3>
                    <span id='novalnetDesc'>
                    " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_VENDOR_URL_DESCS') . "
                    </span>
                 </div>
              </div>
           </div>
           <div class='novalnetAdminInfoUpdate'>
           <div class='novalnetAdminInfoUpdateInside' style='width:96.5%';>
             <h3 >
                       " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_PAYPAL') . "
            </h3>

                 <div class='update_page_config_3>
                    " . $this->loadNovalnetImages('paypal_config_home.png') . "
                 </div>
                 <div class='update_page_config_3>

                    <span id='novalnetDesc'>
                    " . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_PAYPAL_DESC') . "
                    </span>
                 </div>
              </div>
              <div class='novalnetAdminInfoUpdateInside'>
                 <div style='float:left;height:100%;'>
                    " . $this->loadNovalnetImages('paypal_config.png') . "
                 </div>
                 <div class='update_page_config_3>
                    <span id='novalnetDesc'>
                    " . JText::_('VMPAYMENT_NOVALNET_PAYPAL_API_CONFIG_NOVALNET_ADMIN_PORTAL') . "
                    </span>
                 </div>
              </div>
           </div>
           <div class='novalnetAdminInfo' style='display:inline-block;float:left;'>
              <span style='text-align:center;color:rgb(0, 128, 201);'>" . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_MORE') . "</span>
           </div>
           <div class='novalnetAdminInfoUpdate'>
            <div class='novalnetAdminInfoUpdateInside'
              <br />
              <div class='update_page_config_4'>
                 <h3>" . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_ONE_CLICK') . "
                 </h3>
                 <span id='novalnetDesc'>" . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_ONE_CLICK_DESC') . "</span>
              </div>
              <div class='update_page_config_4'>
                 <h3>" . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_ZERO_AMOUNT') . "</h3>
                 <span id='novalnetDesc'>" . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_ZERO_AMOUNT_DESC') . "</span>
              </div>
              <div class='update_page_config_4'>
                 <h3>" . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_CC_IFRAME') . "</h3>
                 <span id='novalnetDesc'>" . JText::_('VMPAYMENT_NOVALNET_ADMIN_CONFIG_PAYMENT_MODULE_UPDATE_CC_IFRAME_DESC') . "</span>
              </div>
              </div>
           </div>
        </tr>";
    }

    /**
     * To get Novalnet admin portal configuration images
     *
     * @param   string $images  this is loaded images name
     *
     * @return mixed
     */
    private function loadNovalnetImages($images)
    {
        $language = JFactory::getLanguage();
        $language = strtolower(substr($language->get('tag'), 0, 2));
        return '<img class="nn_img" src=' . JURI::root() . 'plugins/vmpayment/novalnet_payment/novalnet_payment/assets/images/updates/' .$language .'/' . $images . '>';
    }
}
