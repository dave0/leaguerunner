<?php
require_once('Handler/PersonHandler.php');
class registration_history extends PersonHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','history', $this->person->user_id);
	}

	function process ()
	{
		global $dbh;

		$this->title= "{$this->person->fullname} &raquo; Registration History";

		$this->template_name = 'pages/registration/history.tpl';

		$sth = $dbh->prepare('SELECT
				e.registration_id, e.name, r.order_id, r.time, r.payment
			FROM registrations r
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
			WHERE r.user_id = ?
			ORDER BY r.time');
		$sth->execute( array( $this->person->user_id ) );

		$this->smarty->assign('registrations', $sth->fetchAll(PDO::FETCH_ASSOC) );
		$this->smarty->assign('order_id_format', variable_get('order_id_format', '%d'));

		return true;
	}
}

?>
