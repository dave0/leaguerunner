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
			'Order ID',
			'Event',
			'Member ID',
			'First name',
			'Last name',
			'Email',
			'Date Created',
			'Date Modified',
			'Payment Status',
			'Total Amount',

			'Deposit Date',
			'Deposit Amount',
			'Deposit By',
			'Deposit Method',

			'Balance Date',
			'Balance Amount',
			'Balance By',
			'Balance Method'
		));

		$sth = $dbh->prepare("SELECT
					r.time,
					r.order_id,
					e.name AS event_name,
					p.member_id,
					p.firstname,
					p.lastname,
					p.email,
					r.payment AS payment_status,
					r.total_amount,
					DATE_ADD(r.time, INTERVAL ? MINUTE)     as reg_created,
					DATE_ADD(r.modified, INTERVAL ? MINUTE) as reg_modified,

					dp.payment_amount AS deposit_paid_amount,
					dp.paid_by        AS deposit_paid_by,
					dp.date_paid      AS deposit_date_paid,
					dp.payment_method AS deposit_payment_method,

					bp.payment_amount AS balance_paid_amount,
					bp.paid_by        AS balance_paid_by,
					bp.date_paid      AS balance_date_paid,
					bp.payment_method AS balance_payment_method

				FROM
					registrations r
					LEFT JOIN registration_events e ON r.registration_id = e.registration_id
					LEFT JOIN registration_payments dp ON (dp.payment_type = 'Deposit' AND dp.order_id = r.order_id)
					LEFT JOIN registration_payments bp ON (bp.payment_type IN ('Full', 'Remaining Balance') AND bp.order_id = r.order_id)
					LEFT JOIN person p ON r.user_id = p.user_id
				ORDER BY r.order_id");
		$sth->execute ( array( -$CONFIG['localization']['tz_adjust'], -$CONFIG['localization']['tz_adjust'],) );

		while($row = $sth->fetch()) {
			fputcsv($out, array(
				sprintf(variable_get('order_id_format', '%d'), $row['order_id']),
				$row['event_name'],
				$row['member_id'],
				$row['firstname'],
				$row['lastname'],
				$row['email'],
				$row['reg_created'],
				$row['reg_modified'],
				$row['payment_status'],
				$row['total_amount'],

				$row['deposit_date_paid'],
				$row['deposit_paid_amount'],
				$row['deposit_paid_by'],
				$row['deposit_method'],

				$row['balance_date_paid'],
				$row['balance_paid_amount'],
				$row['balance_paid_by'],
				$row['balance_method']
			));
		}

		fclose($out);

		// Returning would cause the Leaguerunner menus to be added
		exit;
	}
}
?>
