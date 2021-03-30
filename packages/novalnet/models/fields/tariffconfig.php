<?php
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
 * Script: tariffconfig.php
 */

define('_JEXEC', 1);

// Filter the request values using trim
$request = array_map('trim', $_REQUEST);

/**
 * novalnet merchant details configuration class
 *
 * @package NovalnetPayments
 * @since   11.1
 */
class NovalnetAdminconfiguration
{
    /**
     * Constructor for the class.
     *
     * @param   array $request Request for merchant configuration params
     */
    public function __construct($request)
    {
        if (!empty($request['api_config_hash'])) {
            $data     = array(
                'hash' => $request['api_config_hash'],
                'lang' => ($request['lang']) ? $request['lang'] : 'EN'
            );
            $response = json_decode($this->sendRequest($data, 'https://payport.novalnet.de/autoconfig', array('curl_timeout' => $request['curl_timeout'])));
            if (empty(json_last_error())) {
                if (isset($response->config_result)) {
                    $api_error = $response->config_result;
                    if ($response->status == '106') {
                        $ipaddressGet = isset($response->ip) ? $response->ip : '';
                        $api_error    = $request['lang'] == 'EN' ? 'IP address ' . ($ipaddressGet) . ' has not been configured for this project Change it to: “You need to configure your outgoing server IP address ' . ($ipaddressGet) . ' at Novalnet. Please configure it in Novalnet admin portal or contact <a href="mailto:technic@novalnet.de">technic@novalnet.de</a>”' : 'IP address ' . ($ipaddressGet) . ' has not been configured for this project Change it to: “You need to configure your outgoing server IP address ' . ($ipaddressGet) . ' at Novalnet. Please configure it in Novalnet admin portal or contact <a href="mailto:technic@novalnet.de">technic@novalnet.de</a> technic@novalnet.de”';
                    }
                    echo json_encode(array(
                        'status_desc' => $response->config_result
                    ));
                    exit();
                }
                
                $merchantDetails = array(
                    'status' => 100,
                    'auto_key' => $request['api_config_hash'],
                    'vendor_id' => $response->vendor,
                    'auth_code' => $response->auth_code,
                    'access_key' => $response->access_key,
                    'test_mode' => $response->test_mode,
                    'product_id' => $response->product,
                    'tariff' => $response->tariff
                );
                echo json_encode($merchantDetails);
                exit();
            }
        }
        exit();
    }
    
    /**
     * Payment menthod callback function to send the request to server using cUrl method
     *
     * @param   array  $data   this is array for merchant details
     * @param   string $url    this is novalnet server url
     * @param   array  $config this is array for configuration details
     *
     * @return mixed
     */
    public function sendRequest($data, $url, $config)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['curl_timeout']);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
/**
 * Create object for merchant configuration
 */
new NovalnetAdminconfiguration($request);
