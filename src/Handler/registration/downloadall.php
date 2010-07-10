<?php
class registration_downloadall extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','download');
	}

	function process ()
	{
		global $dbh;
		global $CONFIG;

		header('Content-type: text/x-csv');
		header('Content-Disposition: attachment; filename="registrations.csv"');
		$out = fopen('php://output', 'w');
		fputcsv($out, array(
			'Date', 'Order ID', 'Event', 'User ID', 'First name', 'Last name', 'Email', 'Payment Status', 'Payment Date', 'Payment From', 'Amt Paid', 'Total Cost'
		));

		$sth = $dbh->prepare("SELECT
					r.time,
					r.order_id,
					e.name,
					p.user_id,
					p.firstname,
					p.lastname,
					p.email,
					r.payment,
					DATE_ADD(r.date_paid, INTERVAL ? MINUTE) as date_paid,
					r.paid_by,
					r.paid_amount,
					r.total_amount
				FROM
					registrations r
					LEFT JOIN registration_events e ON r.registration_id = e.registration_id
					LEFT JOIN person p ON r.user_id = p.user_id
				ORDER BY r.time");
		$sth->execute ( array(-$CONFIG['localization']['tz_adjust']) );

		while($row = $sth->fetch()) {
			$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);
			fputcsv($out, array(
				$row['time'],
				$order_id,
				$row['name'],
				$row['user_id'],
				$row['firstname'],
				$row['lastname'],
				$row['email'],
				$row['payment'],
				$row['date_paid'],
				$row['paid_by'],
				$row['paid_amount'],
				$row['total_amount']
			));
		}

		fclose($out);

		// Returning would cause the Leaguerunner menus to be added
		exit;
	}
}
?>
