<?php
require_once('Handler/RegistrationHandler.php');
class registration_addpayment extends RegistrationHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','addpayment', $this->registration->order_id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = 'Registration ' . $this->registration->formatted_order_id() . ' &raquo; Add Payment';

		$this->smarty->assign('reg', $this->registration);
		$this->smarty->assign('event', $this->event);

		// TODO: should be get_user() for consistency.
		$this->smarty->assign('registrant', $this->registration->user() );

		$edit = $_POST['edit'];
		$payment = new RegistrationPayment;
		$payment->set('order_id', $this->registration->order_id);
		$payment->set('entered_by', $lr_session->user->user_id);
		$fields  = array('payment_type', 'payment_amount', 'payment_method', 'paid_by', 'date_paid');
		foreach ($fields as $field) {
			$payment->set($field, $edit[$field]);
		}
		$dataInvalid = $payment->validate();
		if( $dataInvalid ) {
			info_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		switch($edit['step']) {
			default:
			case 'confirm':
				$this->smarty->assign('payment', $payment);
				$this->template_name = 'pages/registration/addpayment.tpl';
				break;
			case 'submit':
				if( ! $payment->save() ) {
					error_exit("Internal error: couldn't save payment");
				}
				switch( $payment->payment_type ) {
					case 'Deposit':
						$this->registration->set('payment', 'Deposit Paid');
						break;
					case 'Full':
					case 'Remaining Balance':
						$this->registration->set('payment', 'Paid');
						break;
				}
				if( ! $this->registration->save() ) {
					error_exit("Internal error: couldn't save changes to registration");
				}
				local_redirect(url("registration/view/" . $this->registration->order_id));
				break;
		}

		return true;
	}
}
?>
