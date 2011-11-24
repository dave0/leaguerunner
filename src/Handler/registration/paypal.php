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
				// PaymentRegistration object passed back in message on success
				$payments[] = $status['message'];		
			}			
		}
		
		// output confirmation view
		$this->smarty->assign('payments', $payments);
		$this->smarty->assign('order_id_format', variable_get('order_id_format', '%d'));
		$this->template_name = 'pages/registration/paypal.tpl';
		
		return true;
	}
}