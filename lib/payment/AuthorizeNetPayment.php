<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is RedTree Framework Payment Module
 *
 * The Initial Developer of the Original Code is RedTree Systems LLC.
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Brandon Prudent <bprudent@redtreesystems.com>
 *   Joshua T Corbin <jcorbin@redtreesystems.com>
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the LGPL or the GPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK *****
 */

//require_once 'lib/constants.inc.php';

/**
 * Sets up the concrete implemenation for the PayPal backend
 *
 * @category     Payment
 */

class AuthorizeNetPayment extends Payment
{
    public $apiUsername = '';
    public $apiPassword = '';
    public $live = false;

    public $customerBirthMonth = '';
    public $customerBirthDay = '';
    public $customerBirthYear = '';

    public static function From(&$where)
    {
        $us = new AuthorizeNetPayment();
        Params::ArrayToObject($where, $us);
        return $us;
    }

    public function __construct()
    {

    }

    /*
     * @NOTICE: this is hardcoded to use US Currency
     */
    public function purchase()
    {
        if($this->live){
            $auth_net_url                = "https://secure.authorize.net/gateway/transact.dll";
        }
        else{
            $auth_net_url                = "https://test.authorize.net/gateway/transact.dll";
        }

        $authnet_values                = array
        (
            "x_login"                => $this->apiUsername,
            "x_version"                => "3.1",
            "x_delim_char"            => "|",
            "x_delim_data"            => "TRUE",
            "x_url"                    => "FALSE",
            "x_type"                => "AUTH_CAPTURE",
            "x_method"                => "CC",
            "x_tran_key"            => $this->apiPassword,
            "x_relay_response"        => "FALSE",
            "x_card_num"            => $this->creditCardNumber,
            "x_exp_date"            => $this->expirationMonth . $this->expirationYear,
            "x_description"            => "LMS Services",
            "x_amount"                => $this->amount,
            "x_first_name"            => $this->firstName,
            "x_last_name"            => $this->lastName,
            "x_address"                => $this->address1,
            "x_city"                => $this->city,
            "x_state"                => $this->state,
            "x_zip"                    => $this->zip,
            "CustomerBirthMonth"    => "Customer Birth Month: " . $this->customerBirthMonth,
            "CustomerBirthDay"        => "Customer Birth Day: " . $this->customerBirthDay,
            "CustomerBirthYear"        => "Customer Birth Year: " . $this->customerBirthYear,
            "SpecialCode"            => "None",
     );

        $fields = "";
        foreach($authnet_values as $key => $value) $fields .= "$key=" . urlencode($value) . "&";

        if($this->live) {
            $ch = curl_init("https://secure.authorize.net/gateway/transact.dll");
        }
        else{
            $ch = curl_init("https://test.authorize.net/gateway/transact.dll");
        }

        curl_setopt($ch, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
        curl_setopt($ch, CURLOPT_POSTFIELDS, rtrim($fields, "& ")); // use HTTP POST to send form data
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // uncomment this line if you get no gateway response. ###
        $resptext = curl_exec($ch); //execute post and get results
        curl_close ($ch);
        $resp = array();

        $text = $resptext;
        $tok = strtok($text, "|");

        while(!($tok === false)) {
            array_push($resp, $tok);
            $tok = strtok("|");
        }

    $resp;

    if ($resp[0] == 1) {
        return true;
    }

    Site::getPage()->addWarning($resp[3]);
    return false;

    }

}

?>
