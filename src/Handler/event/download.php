<?php
require_once('Handler/EventHandler.php');
class event_download extends EventHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','download');
	}

	function process ( )
	{
		global $dbh;

		if( ! $this->event->anonymous ) {
			$formbuilder = $this->event->load_survey( true, null );
		}

		$data = array(
			'User ID',
			'Member ID',
			'First Name',
			'Last Name',
			'Email',
			'Home Phone',
			'Mobile Phone',
			'Gender',
			'Skill Level',
			'Order ID',
			'Date Registered',
			'Date Modified',
			'Total Amount',
			'Payment Status',

			'Deposit Date',
			'Deposit Amount',
			'Deposit By',
			'Deposit Method',

			'Balance Date',
			'Balance Amount',
			'Balance By',
			'Balance Method',
		);


		if( $formbuilder )
		{
			foreach ($formbuilder->_questions as $question)
			{
				if( $question->qkey == '__auto__team_id' ) {
					$data[] = 'Team Name';
					$data[] = 'Team Rating';
					$data[] = 'Team ID';
				} else {
					$data[] = $question->qkey;
				}
			}
		}

		$data[] = 'Notes';

		// Start the output, let the browser know what type it is
		header('Content-type: text/x-csv');
		header("Content-Disposition: attachment; filename=\"{$this->event->name}.csv\"");
		$out = fopen('php://output', 'w');
		fputcsv($out, $data);

		$sth = $dbh->prepare("SELECT
			r.order_id,
			DATE_ADD(r.time, INTERVAL ? MINUTE) as time,
			DATE_ADD(r.modified, INTERVAL ? MINUTE) as modified,
			r.total_amount,
			r.payment,

			dp.payment_amount AS deposit_paid_amount,
			dp.paid_by        AS deposit_paid_by,
			dp.date_paid      AS deposit_date_paid,
			dp.payment_method AS deposit_payment_method,

			bp.payment_amount AS balance_paid_amount,
			bp.paid_by        AS balance_paid_by,
			bp.date_paid      AS balance_date_paid,
			bp.payment_method AS balance_payment_method,

			r.notes,
			p.user_id,
			p.member_id,
			p.firstname,
			p.lastname,
			p.email,
			p.home_phone,
			p.mobile_phone,
			p.gender,
			p.skill_level
		FROM registrations r
			LEFT JOIN person p ON r.user_id = p.user_id
			LEFT JOIN registration_payments dp ON (dp.payment_type = 'Deposit' AND dp.order_id = r.order_id)
			LEFT JOIN registration_payments bp ON (bp.payment_type IN ('Full', 'Remaining Balance') AND bp.order_id = r.order_id)
		WHERE r.registration_id = ?
		ORDER BY payment, order_id");
		$sth->execute( array( -$CONFIG['localization']['tz_adjust'], -$CONFIG['localization']['tz_adjust'], $this->event->registration_id) );

		while($row = $sth->fetch() ) {
			$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

			$data = array( $row['user_id'],
				$row['member_id'],
				$row['firstname'],
				$row['lastname'],
				$row['email'],
				$row['home_phone'],
				$row['mobile_phone'],
				$row['gender'],
				$row['skill_level'],
				$order_id,
				$row['time'],
				$row['modified'],
				$row['total_amount'],
				$row['payment'],

				$row['deposit_date_paid'],
				$row['deposit_paid_amount'],
				$row['deposit_paid_by'],
				$row['deposit_payment_method'],

				$row['balance_date_paid'],
				$row['balance_paid_amount'],
				$row['balance_paid_by'],
				$row['balance_payment_method'],
			);

			// Add all of the answers
			if( $formbuilder )
			{
				$fsth = $dbh->prepare('SELECT akey FROM registration_answers WHERE order_id = ? AND qkey = ?');
				foreach ($formbuilder->_questions as $question)
				{
					$fsth->execute( array( $row['order_id'], $question->qkey));
					$item = $fsth->fetchColumn();
					// HACK! this lets us output team names as well as ID
					if( $question->qkey == '__auto__team_id' ) {
						$usth = $dbh->prepare('SELECT name, rating FROM team WHERE team_id = ?');
						$usth->execute( array( $item ) );
						$team_info = $usth->fetch();
						$data[] = $team_info['name'];
						$data[] = $team_info['rating'];
					}

					$item = preg_replace('/\s\s+/', ' ', $item);

					$data[] = $item;
				}
			}

			$data[] = preg_replace('/\s\s+/', ' ', $row['notes']);

			// Output the data row
			fputcsv($out, $data);
		}

		fclose($out);

		exit;
	}
}
?>
