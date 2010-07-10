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
			'Gender',
			'Skill Level',
			'Order ID',
			'Date Registered',
			'Date Modified',
			'Date Paid',
			'Payment Status',
			'Amount Owed',
			'Amount Paid'
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

		$sth = $dbh->prepare('SELECT
			r.order_id,
			DATE_ADD(r.time, INTERVAL ? MINUTE) as time,
			DATE_ADD(r.modified, INTERVAL ? MINUTE) as modified,
			r.payment,
			r.total_amount,
			r.paid_amount,
			r.paid_by,
			DATE_ADD(r.date_paid, INTERVAL ? MINUTE) as date_paid,
			r.payment_method,
			r.notes,
			p.*
		FROM registrations r
			LEFT JOIN person p ON r.user_id = p.user_id
		WHERE r.registration_id = ?
		ORDER BY payment, order_id');
		$sth->execute( array( -$CONFIG['localization']['tz_adjust'], -$CONFIG['localization']['tz_adjust'], -$CONFIG['localization']['tz_adjust'], $this->event->registration_id) );

		while($row = $sth->fetch() ) {
			$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

			$data = array( $row['user_id'],
				$row['member_id'],
				$row['firstname'],
				$row['lastname'],
				$row['email'],
				$row['gender'],
				$row['skill_level'],
				$order_id,
				$row['time'],
				$row['modified'],
				$row['date_paid'],
				$row['payment'],
				$row['total_amount'],
				$row['paid_amount'],
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

					$data[] = $item;
				}
			}

			$data[] = $row['notes'];

			// Output the data row
			fputcsv($out, $data);
		}

		fclose($out);

		exit;
	}
}
?>
