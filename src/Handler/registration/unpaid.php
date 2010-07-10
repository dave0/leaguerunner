<?php
class registration_unpaid extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','download');
	}

	function process ()
	{
		global $dbh;

		$this->title = 'Unpaid Registrations';
		$this->template_name = 'pages/registration/unpaid.tpl';

		$this->smarty->assign('order_id_format', variable_get('order_id_format', '%d'));

		$sth = $dbh->prepare("SELECT
				r.order_id, r.registration_id, r.payment, r.modified, r.notes,
				e.name,
				p.user_id, p.firstname, p.lastname
			FROM registrations r
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
				LEFT JOIN person p ON r.user_id = p.user_id
			WHERE r.payment IN('Unpaid', 'Pending', 'Deposit Paid')
			ORDER BY r.payment, r.modified");
		$sth->execute();
		$this->smarty->assign('unpaid', $sth->fetchAll(PDO::FETCH_ASSOC));

		return true;
	}
}
?>
