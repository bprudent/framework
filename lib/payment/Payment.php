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

/**
 * Sets up the abstract definition of a Payment
 *
 * @category     Payment
 */

abstract class Payment extends RequestObject
{
    public $firstName;
    public $lastName;
    public $phoneNumber;
    public $creditCardType;
    public $creditCardNumber;
    public $expirationMonth;
    public $expirationYear;
    public $cvv2Number;
    public $address1;
    public $address2;
    public $city;
    public $state;
    public $zip;
    public $amount;

    /**
     * Purchases the goods or services
     *
     * @access public
     * @return boolean true upon success
     */
    abstract public function purchase();

    public function validate()
    {
        return Params::Validate($this, array(
            'firstName' => I18N::String('Please enter a first name'),
            'lastName' => I18N::String('Please enter a last name'),
            'creditCardType' => I18N::String('Please select your credit card type'),
            'creditCardNumber' => I18N::String('Please enter your credit card number'),
            'expirationMonth' => I18N::String('Please select the month this credit card expires'),
            'expirationYear' => I18N::String('Please select the year this credit card expires'),
            'cvv2Number' => I18N::String('Please enter your security code'),
            'address1' => I18N::String('Please enter your billing address'),
            'city' => I18N::String('Please enter your billing city'),
            'state' => I18N::String('Please enter your billing state'),
            'zip' => I18N::String('Please enter your billing zipcode'),
            'amount' => array(
                array(Params::VALIDATE_EMPTY, I18N::String('The amount of your purchase could not be found')),
                array(Params::VALIDATE_NUMERIC, I18N::String('The amount of the service should be numeric')),
            )
     ));
    }
}



?>
