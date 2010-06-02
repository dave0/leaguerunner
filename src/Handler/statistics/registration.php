<?php
require_once('Handler/StatisticsHandler.php');
class statistics_registration extends StatisticsHandler
{
	protected $level;
	protected $arg2;
	protected $arg3;

	function __construct ( $level = null, $arg3 = null, $arg4 = null )
	{
		$this->level = $level;
		$this->arg3 = $arg3;
		$this->arg4 = $arg4;
	}

	function process ()
	{
		global $dbh;
		global $CONFIG;

		$this->title = 'Registration Statistics';
		$this->setLocation(array($this->title => 0));

		switch( $this->level ) {
			case 'summary':
				return $this->event_summary( $this->arg3 );
				break; // unreached
			case 'users':
				return $this->event_user_stats( $this->arg3, $this->arg4 );
				break; // unreached
			case 'list':
				return $this->event_user_csv( $this->arg3 );
				break; // unreached
			case 'survey':
				return $this->event_survey_csv ( $this->arg3 );
				break; // unreached
			case 'unpaid':
				return $this->event_unpaid_registrations ( );
				break; // unreached
			case 'past':
			case null:
				return $this->registrations_by_year( $this->arg3 );
				break;
			default:
				return para( 'Unknown statistics requested: ' . $this->level);
		}
	}

	function registrations_by_year ( $year )
	{
		global $dbh;

		if( ! $year ) {
			$year = date('Y');
		}

		$sth = $dbh->prepare('SELECT r.registration_id, e.name, e.type, COUNT(*)
			FROM registrations r
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
			WHERE r.payment != "Refunded"
				AND (
					YEAR(e.open) = :year
					OR YEAR(e.close) = :year
				)
			GROUP BY r.registration_id
			ORDER BY e.type, e.open DESC, e.close DESC, r.registration_id');
		$sth->execute( array( 'year' => $year ) );

		$type_desc = event_types();
		$last_type = '';
		$rows = array();

		while($row = $sth->fetch() ) {
			if ($row['type'] != $last_type) {
				$rows[] = array( array('colspan' => 4, 'data' => h2($type_desc[$row['type']])));
				$last_type = $row['type'];
			}
			$rows[] = array( l($row['name'], "statistics/registration/summary/${row['registration_id']}"),
							$row['COUNT(*)'] );
		}

		$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";

		$sth = $dbh->prepare('SELECT YEAR(MIN(open)) FROM registration_events');
		$sth->execute();
		$first_year = $sth->fetchColumn();
		$current_year = date('Y');
		if( $first_year != $current_year ) {
			$output .= '<p><p>Historical data:';
			for( $year = $first_year; $year <= $current_year; ++ $year ) {
				$output .= ' ' . l($year, "statistics/registration/past/$year");
			}
		}

		return form_group('Registrations by event', $output);
	}

	function event_summary ( $id )
	{
		global $dbh;

		$event = event_load( array('registration_id' => $id) );
		if (! $event )
		{
			return para( "Unknown event ID $id" );
		}
		$output = h2('Event: ' .
						l($event->name, "event/view/$id"));
		$rows = array();

		if( ! $event->anonymous )
		{
			$sth = $dbh->prepare('SELECT p.gender, COUNT(order_id)
				FROM registrations r
					LEFT JOIN person p ON r.user_id = p.user_id
				WHERE r.registration_id = ?
					AND r.payment != "Refunded"
				GROUP BY p.gender
				ORDER BY gender');
			$sth->execute( array( $id) );

			$sub_table = array();
			while($row = $sth->fetch(PDO::FETCH_ASSOC) ) {
				$sub_table[] = $row;
			}
			$rows[] = array("By gender:", table(null, $sub_table));
		}

		$sth = $dbh->prepare('SELECT payment, COUNT(order_id)
			FROM registrations
			WHERE registration_id = ?
			GROUP BY payment
			ORDER BY payment');
		$sth->execute( array($id) );

		$sub_table = array();
		while($row = $sth->fetch(PDO::FETCH_ASSOC) ) {
			$sub_table[] = $row;
		}
		$rows[] = array("By payment:", table(null, $sub_table));

		$formbuilder = formbuilder_load($event->formkey());
		if( $formbuilder )
		{
			foreach ($formbuilder->_questions as $question)
			{
				$qkey = $question->qkey;

				// We don't want to see text answers here, they won't group
				// well
				if ($question->qtype == 'multiplechoice' )
				{
					$sth = $dbh->prepare('SELECT
							akey,
							COUNT(registration_answers.order_id)
						FROM registration_answers
							LEFT JOIN registrations ON registration_answers.order_id = registrations.order_id
						WHERE registration_id = ?
							AND qkey = ?
							AND payment != "Refunded"
						GROUP BY akey
						ORDER BY akey');
					$sth->execute( array( $id, $qkey) );

					$sub_table = array();
					while($row = $sth->fetch(PDO::FETCH_ASSOC) ) {
						$sub_table[] = $row;
					}
					$rows[] = array("$qkey:", table(null, $sub_table));
				}
			}
		}

		if( ! count( $rows ) )
		{
			$output .= para( 'No statistics to report, as this event is anonymous and has no survey.' );
		}
		else
		{
			$output .= "<div class='pairtable'>" . table(NULL, $rows) . "</div>";

			$opts = array(
				l('See detailed registration list', "statistics/registration/users/$id/1"),
				l('download detailed registration list', "statistics/registration/list/$id"),
			);
			if( $event->anonymous ) {
				$opts[] = l('download survey results', "statistics/registration/survey/$id");
			}
			$output .= para( join( ' or ', $opts ) );
		}

		return form_group('Summary of registrations', $output);
	}

	function event_user_stats ( $id, $page)
	{
		global $dbh;
		if( $page < 1 )
		{
			$page = 1;
		}

		$event = event_load( array('registration_id' => $id) );
		if (! $event )
		{
			return para( "Unknown event ID $id" );
		}
		$output = h2('Event: ' .
						l($event->name, "event/view/$id"));

		$items = variable_get('items_per_page', 25);
		if( $items == 0 ) {
			$items = 1000000;
		}
		$from = ($page - 1) * $items;
		$sth = $dbh->prepare('SELECT COUNT(order_id)
			FROM registrations
			WHERE registration_id = ?');
		$sth->execute( array($id));
		$total = $sth->fetchColumn();

		if( $from <= $total )
		{
			$sth = $dbh->prepare("SELECT
					order_id,
					DATE_ADD(time, INTERVAL ? MINUTE) as time,
					payment,
					p.user_id,
					p.firstname,
					p.lastname
				FROM registrations r
					LEFT JOIN person p ON r.user_id = p.user_id
				WHERE r.registration_id = ?
				ORDER BY payment, order_id
				LIMIT $from, $items");
			$sth->execute( array(-$CONFIG['localization']['tz_adjust'], $id) );

			$rows = array();
			while($row = $sth->fetch() ) {
				$order_id = l(sprintf(variable_get('order_id_format', '%d'), $row['order_id']), 'registration/view/' . $row['order_id']);

				$rows[] = array( $order_id,
								l("${row['firstname']} ${row['lastname']}", "person/view/${row['user_id']}"),
								$row['time'], $row['payment']);
			}

			$header = array( 'Order ID', 'Player', 'Date/Time', 'Payment' );
			$output .= "<div class='pairtable'>" . table($header, $rows) . "</div>";

			if( $total )
			{
				$output .= page_links( url("statistics/registration/users/$id/"), $page, $total );
			}
		}
		else
		{
			$output .= para( 'There are no ' . ($page == 1 ? '' : 'more ') .
							'registrations for this event.' );
		}

		return form_group('Registrations by user', $output);
	}

	function event_user_csv ( $id )
	{
		global $dbh;

		$event = event_load( array('registration_id' => $id) );
		if (! $event )
		{
			return para( "Unknown event ID $id" );
		}
		if( ! $event->anonymous ) {
			$formbuilder = $event->load_survey( true, null );
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
		header("Content-Disposition: attachment; filename=\"$event->name.csv\"");
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
		$sth->execute( array( -$CONFIG['localization']['tz_adjust'], -$CONFIG['localization']['tz_adjust'], -$CONFIG['localization']['tz_adjust'], $id) );

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

		// Returning would cause the Leaguerunner menus to be added
		exit;
	}

	function event_survey_csv ( $id )
	{
		global $dbh;

		$event = event_load( array('registration_id' => $id) );
		if (! $event )
		{
			return para( "Unknown event ID $id" );
		}
		$formbuilder = $event->load_survey( true, null );

		$data = array();

		foreach ($formbuilder->_questions as $question) {
			$data[] = $question->qkey;
		}

		if( empty( $data ) ) {
			return para( 'No details available for download.' );
		}

		// Start the output, let the browser know what type it is
		header('Content-type: text/x-csv');
		header("Content-Disposition: attachment; filename=\"{$event->name}_survey.csv\"");
		$out = fopen('php://output', 'w');
		fputcsv($out, $data);

		$sth = $dbh->prepare('SELECT order_id FROM registrations r
			WHERE r.registration_id = ?  ORDER BY order_id');
		$sth->execute( array($id) );

		while($row = $sth->fetch() ) {
			$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);
			$data = array();

			// Add all of the answers
			if( $formbuilder )
			{
				$fsth = $dbh->prepare('SELECT akey
					FROM registration_answers
					WHERE order_id = ?
					AND qkey = ?');
				foreach ($formbuilder->_questions as $question)
				{
					$fsth->execute( array( $row['order_id'], $question->qkey));
					$data[] = $fsth->fetchColumn();
				}
			}

			// Output the data row
			fputcsv($out, $data);
		}

		fclose($out);

		// Returning would cause the Leaguerunner menus to be added
		exit;
	}

	function event_unpaid_registrations ( )
	{
		global $dbh;

		$sth = $dbh->prepare('SELECT
				r.order_id, r.registration_id,
				r.payment, r.modified, r.notes, e.name,
				p.user_id, p.firstname, p.lastname
			FROM registrations r
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
				LEFT JOIN person p ON r.user_id = p.user_id
			WHERE r.payment = "Unpaid"
				OR r.payment = "Pending"
			ORDER BY r.payment, r.modified');
		$sth->execute();
		$rows = array();
		while($row = $sth->fetch() ) {
			$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);
			$rows[] = array(
							l($order_id, "registration/view/${row['order_id']}"),
							l("${row['firstname']} ${row['lastname']}", "person/view/${row['user_id']}"),
							$row['modified'],
							$row['payment'],
							l('Unregister', "registration/unregister/${row['order_id']}"),
							l('Edit', "registration/edit/${row['order_id']}")
							);
			$rows[] = array( '', array( 'data' => l($row['name'], "event/view/${row['registration_id']}"), 'colspan' => 5 ) );
			if( $row['notes'] ) {
				$rows[] = array( '', array( 'data' => $row['notes'], 'colspan' => 5 ) );
			}
			$rows[] = array('&nbsp;');
			$total[$row['payment']] ++;
		}

		$total_output = array();
		foreach ($total as $key => $value) {
			$total_output[] = array ($key, $value);
		}

		$output = '<div class="pairtable">' . table(null, $rows) . table(array('Totals:'), $total_output) . '</div>';

		return form_group('Unpaid registrations', $output);
	}

}
?>
