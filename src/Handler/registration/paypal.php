<?php
require_once('Handler/RegistrationHandler.php');
require_once('Handler/PaypalHandler.php');
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
		$payments = array();
		
		$talkback_results = PaypalHandler::talkback('pdt');
		if(!$talkback_results) {
			return false;
		}
		
		// confirm that data from PayPal matches registrations
		$item_numbers = preg_grep_keys('/item_number[0-9]*/',$talkback_results);
		foreach($item_numbers as $key => $value) {
			// get current Item # from PayPal, which is the last character in $key
			$item = substr($key,-1);
			
			$status = PaypalHandler::validatePayment($value, $talkback_results['mc_gross_'.$item], $lr_session->user->user_id);
			
			if ($status['status'] == false) {
				error_exit($status['message']);
			} else {
				$payments[] = $status['message'];		// PaymentRegistration object passed back in message
			}
			
			
			// is the item number a valid registration?
			//$registration = Registration::load( array('order_id' => $value) );
			//if (!$registration) {
			//	error_exit("Incorrect Registration ID returned from PayPal");
			//}
			
			//$event = Event::load( array('registration_id' => $registration->registration_id) );
			//if (!$event) {
			//	error_exit("Could not load associated Event");
			//}
			
			//if ($event->cost != $talkback_results['mc_gross_'.$item]) {
			//	error_exit("Registration price doesn't match paid price");
			//}
			
			// reg# and price match, should be good.
			//$payment = new RegistrationPayment;
			//$payment->set('order_id', $registration->order_id);
			// TODO:  PDT returns from PayPal are logged under the Paypal account.
			// Would be nice to find a better way to do this instead of a Paypal user account
			//$payment->set('entered_by', 999);
			
			// assign requrired values to the RegistrationPayment from the talkback results
			//$payment->set('payment_type', 'Full');
			//$payment->set('payment_amount', $talkback_results['mc_gross_'.$item]);
			//$payment->set('payment_method', 'PayPal');
			//$payment->set('paid_by',$lr_session->user->user_id);
			//$payment->set('date_paid', $talkback_results['payment_date']);
			
			//if( ! $payment->save() ) {
				//error_exit("Internal error: couldn't save payment");
			//}
			
			// update registration in question
			//$registration->set('payment', 'Paid');
			//if( ! $registration->save() ) {
			//	error_exit("Internal error: couldn't save changes to registration");
			//}
			
			// Payment and registration both successful, store $payment for display
			//$payments[] = $payment;
		}
		
		// output confirmation view
		$this->smarty->assign('payment', $payment);
		$this->smarty->assign('order_id_format', variable_get('order_id_format', '%d'));
		$this->template_name = 'pages/registration/paypal.tpl';
		
		return true;
	}
}