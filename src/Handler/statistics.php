<?php
class statistics extends Handler
{
	function __construct ( $type )
	{
		if( ! preg_match('/^[a-z]+$/', $type)) {
			error_exit('invalid type');
		}

		$function = $type . '_statistics';
		if(! function_exists( $function ) ) {
			error_exit('invalid type');
		}
		$this->type = $type;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->is_admin();
	}

	function process ()
	{
		$this->title = ucfirst($this->type) . ' Statistics';
		$this->setLocation(array($this->title => 0));

		$function = $this->type . '_statistics';
		return $function( $message );
	}
}

function person_statistics ( )
{
	global $dbh;
	$rows = array();

	$sth = $dbh->prepare('SELECT status, COUNT(*) FROM person GROUP BY status');
	$sth->execute();

	$sub_table = array();
	$sum = 0;
	while($row = $sth->fetch(PDO::FETCH_NUM)) {
		$sub_table[] = $row;
		$sum += $row[1];
	}
	$sub_table[] = array('Total', $sum);
	$rows[] = array('Players by account status:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT class, COUNT(*) FROM person GROUP BY class');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by account class:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT gender, COUNT(*) FROM person GROUP BY gender');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by gender:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT FLOOR((YEAR(NOW()) - YEAR(birthdate)) / 5) * 5 as age_bucket, COUNT(*) AS count FROM person GROUP BY age_bucket');
	$sth->execute();
	$sub_table = array();
	while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
		$sub_table[] = array($row['age_bucket'] . ' to ' . ($row['age_bucket'] + 4), $row['count']);
	}
	$rows[] = array('Players by age:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT addr_city, COUNT(*) AS num FROM person GROUP BY addr_city HAVING num > 2 ORDER BY num DESC');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by city:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT skill_level, COUNT(*) FROM person GROUP BY skill_level');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by skill level:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT year_started, COUNT(*) FROM person GROUP BY year_started');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by starting year:', table(null, $sub_table));

	if (variable_get('dog_questions', 1)) {
		$sth = $dbh->prepare("SELECT COUNT(*) FROM person where has_dog = 'Y'");
		$sth->execute();
		$rows[] = array('Players with dogs :', $sth->fetchColumn());
	}

	$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
	return form_group('Player Statistics', $output);
}

function team_statistics ( )
{
	global $dbh;
	$rows = array();

	$current_season = variable_get('current_season', 'Summer');

	$sth = $dbh->prepare('SELECT COUNT(*) FROM team');
	$sth->execute();
	$rows[] = array("Number of teams (total):", $sth->fetchColumn() );

	$sth = $dbh->prepare('SELECT l.season, COUNT(*) FROM leagueteams t, league l WHERE t.league_id = l.league_id AND l.status = "open" GROUP BY l.season');
	$sth->execute();
	$sub_table = array();
	while($row = $sth->fetch(PDO::FETCH_ASSOC) ) {
		$sub_table[] = $row;
	}
	$rows[] = array("Teams by season:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT t.team_id,t.name, COUNT(r.player_id) as size 
        FROM teamroster r, league l, leagueteams lt
        LEFT JOIN team t ON (t.team_id = r.team_id) 
        WHERE
                lt.team_id = r.team_id
                AND l.league_id = lt.league_id
				AND l.status = 'open'
                AND l.schedule_type != 'none'
				AND l.season = ?
                AND (r.status = 'player' OR r.status = 'captain' OR r.status = 'assistant')
        GROUP BY t.team_id
        HAVING size < 12
        ORDER BY size desc, t.name");
	$sth->execute( array($current_season) );
	$sub_table = array();
	$sub_sth = $dbh->prepare("SELECT COUNT(*) FROM teamroster r WHERE r.team_id = ? AND r.status = 'substitute'");
	while($row = $sth->fetch() ) {
		if( $row['size'] < 12 ) {
			$sub_sth->execute( array($row['team_id']) );
			$substitutes = $sub_sth->fetchColumn();
			if( ($row['size'] + floor($substitutes / 3)) < 12 ) {
				$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), ($row['size'] + floor($substitutes / 3)));
			}
		}
	}
	$rows[] = array("$current_season teams with too few players:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT t.team_id, t.name, t.rating
		FROM team t, league l, leagueteams lt
		WHERE
			lt.team_id = t.team_id
			AND l.league_id = lt.league_id
			AND l.status = 'open'
			AND l.schedule_type != 'none'
			AND l.season = ?
		ORDER BY t.rating DESC LIMIT 10");
	$sth->execute( array( $current_season ) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), $row['rating']);
	}
	$rows[] = array("Top-rated $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT t.team_id, t.name, t.rating
		FROM team t, league l, leagueteams lt
		WHERE
			lt.team_id = t.team_id
			AND l.league_id = lt.league_id
			AND l.status = 'open'
			AND l.schedule_type != 'none'
			AND l.season = ?
		ORDER BY t.rating ASC LIMIT 10");
	$sth->execute( array($current_season) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), $row['rating']);
	}
	$rows[] = array("Lowest-rated $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT COUNT(*) AS num,
			IF(s.status = 'home_default',s.home_team,s.away_team) AS team_id
		FROM schedule s, league l
		WHERE
			s.league_id = l.league_id
			AND l.status = 'open'
			AND l.season = ?
			AND (s.status = 'home_default' OR s.status = 'away_default')
		GROUP BY team_id ORDER BY num DESC");
	$sth->execute( array($current_season) );
	$sub_table = array();
	while($row = $sth->fetch()) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['num']);
	}
	$rows[] = array("Top defaulting $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT COUNT(*) AS num,
			IF(s.approved_by = -3,s.home_team,s.away_team) AS team_id
		FROM schedule s, league l
		WHERE
			s.league_id = l.league_id
			AND l.status = 'open'
			AND l.season = ?
			AND (s.approved_by = -2 OR s.approved_by = -3)
		GROUP BY team_id ORDER BY num DESC");
	$sth->execute( array($current_season) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['num']);
	}
	$rows[] = array("Top non-score-submitting $current_season teams:", table(null, $sub_table));

	$sotg_query = "SELECT
			ROUND( AVG( COALESCE(
				s.entered_sotg,
				s.score_entry_penalty + s.timeliness + s.rules_knowledge + s.sportsmanship + s.rating_overall )
			), 2) AS avgspirit,
			s.tid AS team_id
		FROM league l, leagueteams lt, spirit_entry s
		WHERE
			lt.league_id = l.league_id
			AND lt.team_id = s.tid
			AND l.status = 'open'
			AND l.season = ?
		GROUP BY team_id";

	$sth = $dbh->prepare( $sotg_query . " ORDER BY avgspirit DESC LIMIT 10");
	$sth->execute ( array ($current_season) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['avgspirit']);
	}
	$rows[] = array("Best spirited $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare( $sotg_query . " ORDER BY avgspirit ASC LIMIT 10");
	$sth->execute ( array ($current_season) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['avgspirit']);
	}
	$rows[] = array("Lowest spirited $current_season teams:", table(null, $sub_table));

	$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
	return form_group("Team Statistics", $output);
}

function registration_statistics($args)
{
	global $dbh;
	$level = arg(2);
	global $CONFIG;

	if (!$level || $level == 'past')
	{
		if( $level == 'past' ) {
			$year = arg(3);
		} else {
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
	else
	{
		if ($level == 'summary')
		{
			$id = arg(3);
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

		else if ($level == 'users')
		{
			$id = arg(3);
			$page = arg(4);
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

		else if ($level == 'list')
		{
			$id = arg(3);

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

		else if ($level == 'survey')
		{
			$id = arg(3);

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

		else if ($level == 'unpaid')
		{
			$total = array();

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

		else
		{
			return para( "Unknown statistics requested: $level" );
		}

	}
}

?>
