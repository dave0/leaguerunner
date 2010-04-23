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
		global $lr_session, $dbh;

		$this->title= 'View Registration History';
		$rows = array();

		$sth = $dbh->prepare('SELECT
				e.registration_id, e.name, r.order_id, r.time, r.payment
			FROM registrations r
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
			WHERE r.user_id = ?
			ORDER BY r.time');
		$sth->execute( array( $this->person->user_id ) );
		while($row = $sth->fetch() ) {
			$name = l($row['name'], 'event/view/' . $row['registration_id']);
			$order = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

			if( $lr_session->has_permission('registration', 'view', $row['order_id']) ) {
				$order = l($order, 'registration/view/' . $row['order_id']);
			}

			$rows[] = array( $name, $order, substr($row['time'], 0, 10), $row['payment'] );
		}

		/* Add in any preregistrations */
		$sth = $dbh->prepare('SELECT e.registration_id, e.name
			FROM preregistrations r
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
			WHERE r.user_id = ?
			ORDER BY r.registration_id');
		$sth->execute( array( $this->person->user_id) );
		while($row = $sth->fetch() ) {
			$name = l($row['name'], 'event/view/' . $row['registration_id']);
			$order = 'Prereg';
			$rows[] = array( $name, $order, '', 'No' );
		}

		$header = array('Event', 'Order ID', 'Date', 'Payment');
		$output = table($header, $rows);

		$this->setLocation(array($this->title => 0));

		return $output;
	}
}

?>
