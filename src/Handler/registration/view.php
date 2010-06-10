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
		$this->title= 'View Registration';

		$person = $this->registration->user();

		$userrows = array();
		$userrows[] = array ('Name', l($person->fullname, url("person/view/{$person->id}")) );
		$userrows[] = array ('Member&nbsp;ID', $person->member_id);
		$userrows[] = array ('Event', l($this->event->name, url("event/view/{$this->event->registration_id}")));
		$userrows[] = array ('Registered Price', $this->registration->total_amount);
		$userrows[] = array ('Payment Status', $this->registration->payment);
		$userrows[] = array ('Payment Amount', $this->registration->paid_amount);
		$userrows[] = array ('Payment Method', $this->registration->payment_method);
		$userrows[] = array ('Payment Date', $this->registration->date_paid);
		$userrows[] = array ('Paid By (if different)', $this->registration->paid_by);
		$userrows[] = array ('Created', $this->registration->time);
		$userrows[] = array ('Last Modified', $this->registration->modified);
		$userrows[] = array ('Notes', $this->registration->notes);
		$output = form_group('Registration details', '<div class="pairtable">' . table(NULL, $userrows) . '</div>');

		if( ! $this->event->anonymous && $this->formbuilder ) {
			$this->formbuilder->bulk_set_answers_sql(
				'SELECT qkey, akey FROM registration_answers WHERE order_id = ?',
				array( $this->registration->order_id)
			);

			$output .= form_group('Registration answers', $this->formbuilder->render_viewable() );
		}

		// Get payment audit information, if available
		$sth = $dbh->prepare('SELECT *
				FROM registration_audit
				WHERE order_id = ?');
		$sth->execute( array(
			$this->registration->order_id
		));

		$payrows = array();
		$row = $sth->fetch(PDO::FETCH_ASSOC);
		if( $row ) {
			foreach($row as $key => $value) {
				$payrows[] = array($key, $value);
			}
			$output .= form_group('Payment details', '<div class="pairtable">' . table(NULL, $payrows) . '</div>');
		}

		return $output;
	}
}

?>
