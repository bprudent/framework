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
 * Sets up the concrete implemenation for the PayPal backend
 *
 * @category   Payment
 */

class PayPalPayment extends Payment {
  public $apiUsername = "";
  public $apiPassword = "";
  public $apiSignature = "";
  public $live = false;
  public $paymentType = 'Sale';

  /**
   * Returns a PayPalPayment merged with $where
   *
   * @param mixed $where
   * @return PayPalPayment
   */
  public static function From( &$where ) {
    $us = new PayPalPayment();
    Params::ArrayToObject( $where, $us );
    return $us;
  }

  /*
   * @NOTICE: this is hardcoded to use US Currency
   */
  public function purchase() {
    // Note, we used to add extensions/ to include_path here, that should either
    // be standardized in Loader or we need to load more files here to make
    // up for whatever the PayPal code would otherwise try to load
    require_once 'extensions/PayPal.php';
    require_once 'extensions/PayPal/Profile/Handler/Array.php';
    require_once 'extensions/PayPal/Profile/API.php';
    require_once 'extensions/PayPal/Type/DoDirectPaymentRequestType.php';
    require_once 'extensions/PayPal/Type/DoDirectPaymentRequestDetailsType.php';
    require_once 'extensions/PayPal/Type/DoDirectPaymentResponseType.php';

    //  Add all of the types
    require_once 'extensions/PayPal/Type/BasicAmountType.php';
    require_once 'extensions/PayPal/Type/PaymentDetailsType.php';
    require_once 'extensions/PayPal/Type/AddressType.php';
    require_once 'extensions/PayPal/Type/CreditCardDetailsType.php';
    require_once 'extensions/PayPal/Type/PayerInfoType.php';
    require_once 'extensions/PayPal/Type/PersonNameType.php';
    require_once 'extensions/PayPal/CallerServices.php';

    $environment = ( $this->live ? 'live' : 'sandbox' );

    $dp_request = new DoDirectPaymentRequestType();
    $OrderTotal = new BasicAmountType();
    $OrderTotal->setattr( 'currencyID', 'USD' );
    $OrderTotal->setval( $this->amount, 'iso-8859-1' );

    $PaymentDetails = new PaymentDetailsType();
    $PaymentDetails->setOrderTotal( $OrderTotal );

    $shipTo = new AddressType();
    $shipTo->setName( $this->firstName . ' ' . $this->lastName );
    $shipTo->setStreet1( $this->address1 );
    $shipTo->setStreet2( $this->address2 );
    $shipTo->setCityName( $this->city );
    $shipTo->setStateOrProvince( $this->state );
    $shipTo->setCountry( 'US' );
    $shipTo->setPostalCode( $this->zip );
    $PaymentDetails->setShipToAddress( $shipTo );

    $dp_details = new DoDirectPaymentRequestDetailsType();
    $dp_details->setPaymentDetails( $PaymentDetails );

    // Credit Card info
    $card_details = new CreditCardDetailsType();
    $card_details->setCreditCardType( $this->creditCardType );
    $card_details->setCreditCardNumber( $this->creditCardNumber );
    $card_details->setExpMonth( $this->expirationMonth );
    $card_details->setExpYear( $this->expirationYear );
    $card_details->setCVV2( $this->cvv2Number );

    $payer = new PayerInfoType();
    $person_name = new PersonNameType();
    $person_name->setFirstName( $this->firstName );
    $person_name->setLastName( $this->lastName );
    $payer->setPayerName( $person_name );
    $payer->setPayerCountry( 'US' );
    $payer->setAddress( $shipTo );

    $card_details->setCardOwner( $payer );
    $dp_details->setCreditCard( $card_details );
    $dp_details->setIPAddress( $_SERVER [ 'SERVER_ADDR' ] );
    $dp_details->setPaymentAction( 'Sale' );

    $dp_request->setDoDirectPaymentRequestDetails( $dp_details );

    $handler = ProfileHandler_Array::getInstance( array(
      'username' => $this->apiUsername,
      'certificateFile' => null,
      'subject' => null,
      'environment' => $environment
    ) );

    $pid = ProfileHandler::generateID();
    $profile = new APIProfile( $pid, $handler );
    $profile->setAPIUsername( $this->apiUsername );
    $profile->setAPIPassword( $this->apiPassword );
    $profile->setSignature( $this->apiSignature );
    $profile->setEnvironment( $environment );

    $caller = new CallerServices( $profile );
    $response = $caller->DoDirectPayment( $dp_request );

    if ( PayPal::isError( $response ) ) {
      Site::getPage()->addWarning($response->message);
      return false;
    }

    if ( $response->Ack == 'Success' ) {
      return true;
    }

    if ( is_array( $response->Errors ) ) {
      foreach ( $response->Errors as $error ) {
        Site::getPage()->addWarning($error->LongMessage);
      }
    }
    else {
    Site::getPage()->addWarning($response->Errors->LongMessage);
    }

    return false;
  }

}

?>
