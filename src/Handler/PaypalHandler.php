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
	 * URL for submitting PayPal payment Request.  Variation based on Sandbox vs. Live
	 * @var String
	 */
	private $submit_url;

	/**
	 * URL that PayPal uses to return user to Leaguerunner after payment.  PDT enabled Paypal accounts will
	 * send enough data to update Leaguerunner Registrations
	 * @var String
	 */
	private $return_url;


	/**
	 * Returns user to Leaguerunner instead of checking out through PayPal.
	 * @var String
	 */
	private $shopping_url;


	/**
	 * Email address associated with the PayPal account that receives payments
	 * @var String
	 */
	private $account_email;

	/**
	 * PDT Enabled PayPal accounts require a transmission token to verify the site being communicated with
	 * @var String
	 */
	private $pdt_token;

	/**
	 * Just the Magics
	 * @param unknown_type $property
	 */
	public function __get($property) {
		if (property_exists($this, $property)) {
			return $this->$property;
		}
	}

	/**
	 * Just the Magics
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public function __set($property, $value) {
		if (property_exists($this, $property)) {
			$this->$property = $value;
		}
		return $this;
	}

	function __construct() {
		global $CONFIG;
		// Default to Sandbox if no value set
		if (variable_get('paypal_url','true')) {
			$this->account_email = variable_get('paypal_sandbox_email','');
			$this->submit_url = variable_get('paypal_sandbox_url','');
			$this->pdt_token = variable_get('paypal_sandbox_pdt','');
		} else {
			$this->account_email = variable_get('paypal_live_email','');
			$this->submit_url = variable_get('paypal_live_url','');
			$this->pdt_token = variable_get('paypal_live_pdt','');
		}

		$this->shopping_url = 'http://'.$CONFIG['session']['session_name'].$CONFIG['paths']['base_url'];
		$this->return_url = 'http://'.$CONFIG['session']['session_name'].$CONFIG['paths']['base_url'];

		$this->return_url .= '?q=registration/paypal/'; // dirty url
		//$this->return_url .= '/registration/paypal/'; // clean url
	}

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

		if ($talkback_results['message']['receiver_email'] != $this->account_email) {
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
	function talkback( $type )
	{
		$request = curl_init();
		$curl_url = $this->submit_url;
		$at = $this->pdt_token;

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
	private function parsePDT($response)
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
	function validatePayment(
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
		//if ($registration->payment_type == 'Paid') {
		//	$status = array('status' => false, 'message' =>'Registration '.$order_id.' already paid in full');
		//	return $status;
		//}

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
		$payment->set('date_paid', date("Y-m-d"));

		// Save the payment if it's not already stored in the database
		// It's possible that the IPN payment beats the user PDT return.
		// Still need to ensure user is informed correctly, while not displaying any errors.
		if ($registration->payment_type != 'Paid') {
			if( ! $payment->save() ) {
				$status = array('status'=>false, message=>"Couldn't save payment to database");
				return $status;
			}

			// update registration in question
			$registration->set('payment', 'Paid');
			if( ! $registration->save() ) {
				$status = array('status'=>false, message=>"Internal error: couldn't save changes to registration");
			}
		}
		// if successful, return the $payment to handle/display to user
		return array('status' => true, 'message' => $payment);
	}
}
?>
