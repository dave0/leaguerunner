<?php
require_once('Handler/LeagueHandler.php');
/*
 * RK: report of which fields are available for use
 */
class league_slots extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit', $this->league->league_id);
	}

	function process ()
	{
		$this->title = 'League Field Availability Report';

		$this->setLocation(array(
			$this->league->fullname => 'league/slots/'.$this->league->league_id,
			$this->title => 0,
		));

		$today = getdate();

		$year  = arg(3);
		$month = arg(4);
		$day   = arg(5);

		if(! validate_number($month)) {
			$month = $today['mon'];
		}

		if(! validate_number($year)) {
			$year = $today['year'];
		}
		if( $day ) {
			if( !validate_date_input($year, $month, $day) ) {
				return 'That date is not valid';
			}
			$formattedDay = strftime('%A %B %d %Y', mktime (6,0,0,$month,$day,$year));
			$this->setLocation(array(
				"$this->title &raquo; $formattedDay" => 0));
			return $this->displaySlotsForDay( $year, $month, $day );
		} else {
			$this->setLocation(array( "$this->title" => 0));
			$output = para('Select a date below on which to view all available gameslots');
			$output .= generateCalendar( $year, $month, $day,
										 'league/slots/'.$this->league->league_id,
										 'league/slots/'.$this->league->league_id);
			return $output;
		}
	}

	/**
	 * List all games on a given day.
	 */
	function displaySlotsForDay ( $year, $month, $day )
	{
		global $dbh;

		menu_add_child($this->league->fullname."/slots", "$league->fullname/slots/$year/$month/$day","$year/$month/$day", array('weight' => 1, 'link' => "league/slots/".$this->league->league_id."/$year/$month/$day"));

		$rows = array(
			array(
				array('data' => strftime('%a %b %d %Y',mktime(6,0,0,$month,$day,$year)), 'colspan' => 7, 'class' => 'gamedate')
			),
        		array(
				 array('data' => 'Slot', 'class' => 'column-heading'),
				 array('data' => 'Field', 'class' => 'column-heading'),
				 array('data' => 'Game', 'class' => 'column-heading'),
				 array('data' => 'Home', 'class' => 'column-heading'),
				 array('data' => 'Away', 'class' => 'column-heading'),
				 array('data' => 'Field Region', 'class' => 'column-heading'),
				 array('data' => 'Home Pref', 'class' => 'column-heading'),
			 )
		);

		$sth = $dbh->prepare('SELECT
			g.slot_id,
			COALESCE(f.code, pf.code) AS field_code,
			COALESCE(f.num, pf.num)   AS field_num,
			COALESCE(f.region, pf.region) AS field_region,
			g.fid,
			t.region_preference AS home_region_preference,
			IF(g.fid = t.home_field,
				1,
				COALESCE(f.region,pf.region) = t.region_preference) AS is_preferred,
			g.game_id

		FROM
			league_gameslot_availability l,
			gameslot g
				LEFT JOIN schedule s ON (g.game_id = s.game_id)
				LEFT JOIN team t ON (s.home_team = t.team_id),
			field f LEFT JOIN field pf ON (f.parent_fid = pf.fid)
		WHERE l.league_id = ?
			AND g.game_date = ?
			AND g.slot_id = l.slot_id
			AND f.fid = g.fid
			ORDER BY field_code, field_num');
		$sth->execute( array ($this->league->league_id,
				sprintf('%d-%d-%d', $year, $month, $day)) );

		$num_open = 0;
		while($g = $sth->fetch()) {

			$row = array(
				$g['slot_id'],
				l($g['field_code'] . $g['field_num'], "field/view/" . $g['fid'])
			);

			// load game info, if game scheduled
			if ($g['game_id']) {
				$game = game_load( array('game_id' => $g['game_id']) );
				$sched = schedule_render_viewable($game);
				$row[] = l($g['game_id'], "game/view/".$g['game_id']);
				$row[] = $sched[3];
				$row[] = $sched[5];

				$color = 'white';
				if( ! $g['is_preferred'] && ($g['home_region_preference'] && $g['home_region_preference'] != '---') ) {
					/* Show in red if it's an unsatisfied preference */
					$color = 'red';
				}
				$row[] = array( 'data' => $g['field_region'], 'style' => "background-color: $color");
				$row[] = array( 'data' => $g['home_region_preference'], 'style' => "background-color: $color");
			} else {
				$row[] = array('data' => "<b>---- field open ----</b>",
							   'colspan' => '3');
				$row[] = $g['field_region'];
				$row[] = '&nbsp;';
				$num_open++;
			}

			$rows[] = $row;
		}
		if( ! count( $rows ) ) {
			error_exit("No gameslots available for this league on this day");
		}
		$num_fields = count($rows);

		$output .= "<div class='schedule'>" . table(null, $rows) . "</div>"
			. para("There are $num_fields fields available for use this week, currently $num_open of these are unused.");
		return $output;
	}

}

?>
