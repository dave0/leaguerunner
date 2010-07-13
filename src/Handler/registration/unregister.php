<?php
require_once('Handler/RegistrationHandler.php');
class registration_unregister extends RegistrationHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','unregister', null, $this->registration);
	}

	function process()
	{
		$this->template_name = 'pages/registration/unregister.tpl';

		$this->smarty->assign('reg', $this->registration);
		$this->smarty->assign('event', $this->event);

		// TODO: should be get_user() for consistency.
		$this->smarty->assign('registrant', $this->registration->user() );

		$order_num = $this->registration->formatted_order_id();
		$this->title = "Unregister $order_num";

		if( $_POST['submit'] == 'Unregister' ) {
			if( ! $this->registration->delete() ) {
				error_exit ("There was an error deleting your registration information. Contact the office, quoting order #<b>$order_num</b>, to have the problem resolved.") ;
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}
?>
