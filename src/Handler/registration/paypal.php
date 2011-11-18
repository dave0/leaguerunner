<?php
require_once('Handler/RegistrationHandler.php');
class registration_paypal extends RegistrationHandler
{
	function __construct ( $id )
	{
		parent::__construct($id);
		$this->form_load(true);
	}
	
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','paypal', $this->registration->order_id);
	}

	function process ()
	{
		global $lr_session;
		
		$talkback_results = $this->pdt();
		if(!$talkback_results)
		{
			return false;
		}
		
		// confirm that data from PayPal matches registration
		// most importantly, the registration id and price
		if (($talkback_results['mc_gross'] != $this->event->cost) ||
			($talkback_results['item_number'] != $this->registration->formatted_order_id())) 
		{
			return false;
		}
		
		// reg# and price match, should be good.
		$payment = new RegistrationPayment;
		$payment->set('order_id', $this->registration->order_id);
		// TODO:  PDT returns from PayPal are logged under the Paypal account.
		// Would be nice to find a better way to do this instead of a Paypal user account
		$payment->set('entered_by', 999);
		$fields  = array('payment_type', 'payment_amount', 'payment_method', 'paid_by', 'date_paid');
		
		// assign requrired values to the RegistrationPayment from the talkback results
		$payment->set('payment_type', 'Full');
		$payment->set('payment_amount', $talkback_results['mc_gross']);
		$payment->set('payment_method', 'PayPal');
		$payment->set('paid_by',$lr_session->user->user_id);
		$payment->set('date_paid', $talkback_results['payment_date']);
		
		if( ! $this->registration->save() ) {
			error_exit("Internal error: couldn't save changes to registration");
		}
		
		// output confirmation view
		$this->smarty->assign('payment', $payment);
		$this->template_name = 'pages/registration/paypal.tpl';
		
		return true;
	}
	
	function pdt()
	{
		// collect tx info from the GET
		$request = curl_init();
		
		if(isset($_GET['tx']))
		{
			$tx = $_GET['tx'];
		}
		
		// configure curl options
		// determine if we're submitting to the sandbox or the real PayPal
		if (variable_get('paypal_url',''))
		{
			$curl_url = variable_get('paypal_sandbox_url','');
			$at = variable_get('paypal_sandbox_pdt','');
		} else {
			$curl_url = variable_get('paypal_live_url','');
			$at = variable_get('paypal_live_pdt','');
		}
		
		curl_setopt_array($request, array
		(
			CURLOPT_URL => $curl_url,
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => http_build_query(array
			(
				'cmd' => '_notify-synch',
				'tx' => $tx,
				'at' => $at,
			)
		),
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_HEADER => FALSE,
		CURLOPT_SSL_VERIFYPEER => TRUE,
		CURLOPT_CAINFO => '/home/todd/Documents/cacert.pem',
		));
		
		// Execute request and get response and status code
		$response = curl_exec($request);
		echo(curl_error($request));
		$status   = curl_getinfo($request, CURLINFO_HTTP_CODE);
		
		// Close connection
		curl_close($request);
		
		// Validate response
		if($status == 200 AND strpos($response, 'SUCCESS') === 0)
		{
		// Remove SUCCESS part (7 characters long)
		$response = substr($response, 7);
		
			// Urldecode it
			$response = urldecode($response);
		
			// Turn it into associative array
			preg_match_all('/^([^=\r\n]++)=(.*+)/m', $response, $m, PREG_PATTERN_ORDER);
			$response = array_combine($m[1], $m[2]);
				
			// Fix character encoding if needed
			if(isset($response['charset']) AND strtoupper($response['charset']) !== 'UTF-8')
			{
				foreach($response as $key => &$value)
				{
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
}