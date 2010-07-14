<?php
require_once('Handler/RegistrationHandler.php');
class registration_delpayment extends RegistrationHandler
{
	protected $payment;

	function __construct ( $id, $type )
	{
		parent::__construct( $id );

		$this->payment = registration_payment_load( array('order_id' => $id, 'payment_type' => $type ) );
		if( !$this->payment) {
			error_exit("No such payment for this registration");
		}
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','delpayment', null, $this->registration);
	}

	function process()
	{
		$this->template_name = 'pages/registration/delpayment.tpl';

		$this->smarty->assign('reg', $this->registration);
		$this->smarty->assign('event', $this->event);
		// TODO: should be get_user() for consistency.
		$this->smarty->assign('registrant', $this->registration->user() );

		$this->smarty->assign('payment', $this->payment);

		$this->title = 'Registration ' . $this->registration->formatted_order_id() . ' &raquo; Delete Payment';

		if( $_POST['submit'] == 'Delete' ) {
			if( ! $this->payment->delete() ) {
				error_exit ("Could not delete payment") ;
			}
			$this->registration->set('payment', 'Pending');
			if( ! $this->registration->save() ) {
				error_exit ("Could not save changes to registration") ;
			}
			local_redirect(url("registration/view/" . $this->registration->order_id));
		}
		return true;
	}
}
?>
