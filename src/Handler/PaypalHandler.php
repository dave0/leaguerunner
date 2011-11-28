<?php 
require_once('classes/lrobject.php');
require_once('classes/registration.php');
require_once('classes/event.php');
require_once('classes/registration_payment.php');
require_once('Handler.php');
require_once('Handler/RegistrationHandler.php');
require_once('Handler/EventHandler.php');

class PaypalHandler 
{
	/**
	 * Handles IPN messages.  IPN does not require a full UI, so it's a modified Handler
	 * 
	 *  @return Status information on success or failure of the message handling
	 */
	function process()
	{
		$status = array();
		$payments = array();
		
		// Get details back from PayPal
		$talkback_results = $this->talkback('ipn');
		if ($talkback_results['status'] != true) {
			$status = array('status' => false, 'message' =>$talkback_results['message']);
			return $status;
		}
		
		// Check response for correct data
		if($talkback_results['message']['payment_status'] != 'Completed') {
			$status = array('status' => false, 'message' =>'Payment status != Completed');
			return $status;
		}
		
		if (variable_get('paypal_url','')) {
			$receiver_email = variable_get('paypal_sandbox_email','');
		} else {
			$receiver_email = variable_get('paypal_live_email','');
		}
		
		if ($talkback_results['message']['receiver_email'] != $receiver_email) {
			$status = array('status' => false, 'message' =>'Receiver Email does not match');
			return $status;
		}
		
		// basic data is confirmed, update db as required
		$item_numbers = preg_grep_keys('/item_number[0-9]*/',$talkback_results['message']);
		foreach($item_numbers as $key => $value) {
			// get current Item # from PayPal, which is the last character in $key
			$item = substr($key,-1);
			
			// TODO FIXME Need some way to get a PayPal user account
			$status = $this->validatePayment($value, $talkback_results['message']['mc_gross_'.$item], 999);
			if ($status['status'] == false) {
				return $status;
			} else {
				// PaymentRegistration object passed back in message on success
				$payments[] = $status['message'];		
			}
		}
		
		// successfully processed all payments, return to caller for output
		return array('status'=>true, 'message'=>$payments);
	}
	
	/**
	 * Controls Hand-shakes with PayPal to verify identity and gather payment details.
	 * 
	 * @param string $type Marks whether the type of handshaking is PDT or IPN
	 * @return Parameterized array of tokens sent by PayPal
	 */
	static function talkback( $type )
	{
		$request = curl_init();
		
		// determine if we're working to the sandbox or the real PayPal
		if (variable_get('paypal_url','')) {
			$curl_url = variable_get('paypal_sandbox_url','');
			$at = variable_get('paypal_sandbox_pdt','');
		} else {
			$curl_url = variable_get('paypal_live_url','');
			$at = variable_get('paypal_live_pdt','');
		}
		
		switch($type) {
			default:
			case 'ipn':		// Paypal sending a POST to leaguerunner
				// Paypal sends a POST to the URL, which must be returned exactly with an extra parameter
				$ipn_post = $_POST;
				$postfields = http_build_query(array('cmd' => '_notify-validate') + $ipn_post);
				$validate = 'VERIFIED';				
				break;
			case 'pdt':		// User selected to return to site, can update leaguerunner on GET
				$tx = $_GET['tx'];
				$postfields = http_build_query(array('cmd' => '_notify-synch',	'tx' => $tx, 'at' => $at));
				$validate = 'SUCCESS';				
				break;
		}

		// configure cURL for response
		curl_setopt_array($request, array(
			CURLOPT_URL => $curl_url,
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => $postfields,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HEADER => FALSE,
	
			// TODO FIXME: Needed only when running from local machine, as it's fine on host
			//CURLOPT_SSL_VERIFYPEER => TRUE,
			//CURLOPT_CAINFO => '/home/todd/Documents/cacert.pem',
		));
			
		// Execute request and get response and status code
		$response = curl_exec($request);
		if ($response === false) {
			return array('status'=> false, 'message' => curl_error($request));	
		}
		
		$status   = curl_getinfo($request, CURLINFO_HTTP_CODE);
	
		// Close connection
		curl_close($request);
	
		// Validate response
		if($status == 200 AND strpos($response, $validate) === 0) {
			
			/* If IPN, all data received in the POST has been verified, so
			 * pass it back up to the caller.
			 * If it's PDT, build an array out of the response to pass back
			 */
			switch($type) {
				default:
				case 'ipn':
					return array('status'=>true, 'message'=>$ipn_post);
				case 'pdt':
					return array('status'=>true, 'message'=>self::parsePDT($response));				
			}
		}
		return array('status'=>false, 'message'=>'Failed Talkback');
	}
	
	/**
	 * PDT requests require the response to be parsed into an associative array
	 * in order to use it
	 * 
	 * @param string $response URL values returned from PDT query
	 * @return Ambigous <string, multitype:> parsed array of PDT values
	 */
	private static function parsePDT($response)
	{
		// Remove first line success
		$response = substr($response, 7);
		
		// Urldecode it
		$response = urldecode($response);
		
		// Turn it into associative array
		preg_match_all('/^([^=\r\n]++)=(.*+)/m', $response, $m, PREG_PATTERN_ORDER);
		$response = array_combine($m[1], $m[2]);
		
		// Fix character encoding if needed
		if(isset($response['charset']) AND strtoupper($response['charset']) !== 'UTF-8') {
			foreach($response as $key => &$value) {
				$value = mb_convert_encoding($value, 'UTF-8', $response['charset']);
			}
		
			$response['charset_original'] = $response['charset'];
			$response['charset'] = 'UTF-8';
		}
			
		// Sort on keys
		ksort($response);
			
		return $response;
	}
	
	/**
	 * Checks information received from PayPal to ensure it's correct data.
	 * If correct, stores updated transaction details.
	 * 
	 * @param int $order_id Registration # of event being paid
	 * @param float $mc_gross Amount paid by user during the PayPal checkout
	 * @param int $paid_by User ID of player who made the payment
	 */
	static function validatePayment(
		$order_id,
		$mc_gross,
		$paid_by )
	{
		// is the item number a valid registration?
		$registration = Registration::load( array('order_id' => $order_id) );
		if (!$registration) {
			$status = array('status' => false, 'message' =>'Invalid Registration ID');
			return $status;
		}
		
		// has registration been already paid?
		if ($registration->payment_type == 'Full') {
			$status = array('status' => false, 'message' =>'Registration '.$order_id.' already paid in full');
			return $status;
		}
				
		// is the registration attached to the correct Event
		$event = Event::load( array('registration_id' => $registration->registration_id) );
		if (!$event) {
			$status = array('status' => false, 'message' =>'Invalid Event ID');
			return $status;
		}
				
		// does the price paid and registration cost match?		
		if ($mc_gross != $event->cost) {
			$status = array('status' => false, 'message' =>'Amount Paid does not match Registration Cost');
			return $status;
		}
		
		// Payment is valid, and should be saved
		$payment = new RegistrationPayment;
		$payment->set('order_id', $registration->order_id);
		
		// TODO:  PDT returns from PayPal are logged under the Paypal account.
		// Would be nice to find a better way to do this instead of a Paypal user account
		$payment->set('entered_by', 999);
			
		// assign requrired values to the RegistrationPayment from the talkback results
		$payment->set('payment_type', 'Full');
		$payment->set('payment_amount', $mc_gross);
		$payment->set('payment_method', 'PayPal');
		$payment->set('paid_by', $paid_by);
		$payment->set('date_paid', time());
			
		if( ! $payment->save() ) {
			$status = array('status'=>false, message=>"Couldn't save payment to database");
			return $status;
		}
			
		// update registration in question
		$registration->set('payment', 'Paid');
		if( ! $registration->save() ) {
			$status = array('status'=>false, message=>"Internal error: couldn't save changes to registration");
		}
		
		// if successful, return the $payment to handle/display to user
		return array('status' => true, 'message' => $payment);
	}
}
?>