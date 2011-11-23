<?php 

class PaypalHandler 
{
	function process()
	{
		$status = array();
		$payments = array();
		
		// Get details back from PayPal
		$talkback_results = $this->talkback('ipn');
		if (!$talkback_results) {
			$status = array('status' => false, 'message' =>'Failed parsing Talkback');
			return $status;
		}
		
		// Check response for correct data
		if($talkback_results['payment_status'] != "Completed") {
			$status = array('status' => false, 'message' =>'Payment status != Completed');
			return $status;
		}
		
		if (variable_get('paypal_url','')) {
			$receiver_email = variable_get('paypal_sandbox_email','');
		} else {
			$receiver_email = variable_get('paypal_live_email','');
		}
		
		if ($talkback_results['receiver_email'] != $receiver_email) {
			$status = array('status' => false, 'message' =>'Receiver Email does not match');
			return $status;
		}
		
		// basic data is confirmed, update db as required
		$item_numbers = preg_grep_keys('/item_number[0-9]*/',$talkback_results);
		foreach($item_numbers as $key => $value) {
			// get current Item # from PayPal, which is the last character in $key
			$item = substr($key,-1);
			
			// TODO FIXME Need some way to get a PayPal user account
			$status = validatePayment($value, $talkback_results['mc_gross_'.$item], 999);
			if ($status['status'] == false) {
				return $status;
			} else {
				$payments[] = $status['message'];		// PaymentRegistration object passed back in message
			}
		}
		
		// successfully processed all payments, return to caller for output
		return $payments;
	}
	
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
				$validate_length = 8;
				break;
			case 'pdt':		// User selected to return to site, can update leaguerunner on GET
				$tx = $_GET['tx'];
				$postfields = http_build_query(array('cmd' => '_notify-synch',	'tx' => $tx, 'at' => $at));
				$validate = 'SUCCESS';
				$validate_length = 7;
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
			CURLOPT_SSL_VERIFYPEER => TRUE,
			CURLOPT_CAINFO => '/home/todd/Documents/cacert.pem',
		));
			
		// Execute request and get response and status code
		$response = curl_exec($request);
		$status   = curl_getinfo($request, CURLINFO_HTTP_CODE);
	
		// Close connection
		curl_close($request);
	
		// Validate response
		if($status == 200 AND strpos($response, $validate) === 0) {
			// Remove first line success/verified
			$response = substr($response, $validate_length);
	
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
		return false;
	}
	
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
		if ($mc_gross != $registration->cost) {
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
		$payment->set('date_paid', date());
			
		if( ! $payment->save() ) {
			$status = array('status'=>false, "Couldn't save payment to database");
		}
			
		// update registration in question
		$registration->set('payment', 'Paid');
		if( ! $registration->save() ) {
			error_exit("Internal error: couldn't save changes to registration");
		}
		
		// if successful, return the $payment to handle/display to user
		return array('status' => true, 'message' => $payment);
	}
}
?>