<?php
require_once('Handler/RegistrationHandler.php');
class registration_view extends RegistrationHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','view', $this->registration->order_id);
	}

	function process ()
	{
		global $dbh;
		$this->title = 'Registration ' . $this->registration->formatted_order_id();

		$this->template_name = 'pages/registration/view.tpl';

		$this->smarty->assign('reg', $this->registration);
		$this->smarty->assign('event', $this->event);

		// TODO: should be get_user() for consistency.
		$this->smarty->assign('registrant', $this->registration->user() );

		$this->form_load();
		if( ! $this->event->anonymous && $this->formbuilder ) {
			$this->formbuilder->bulk_set_answers_sql(
				'SELECT qkey, akey FROM registration_answers WHERE order_id = ?',
				array( $this->registration->order_id)
			);

			$this->smarty->assign('formbuilder_render_viewable', $this->formbuilder->render_viewable() );
		}

		$this->smarty->assign('payment_details', $this->registration->get_payments());
/*
		// Get payment audit information, if available
		$sth = $dbh->prepare('SELECT * FROM registration_audit WHERE order_id = ?');
		$sth->execute( array(
			$this->registration->order_id
		));

		$this->smarty->assign('audit_details',  $sth->fetchAll(PDO::FETCH_ASSOC));

		return true;
*/
	}
}

?>
