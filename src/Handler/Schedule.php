<?php

function schedule_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'day':
			return new ScheduleViewDay;
		case 'edit':
			return new ScheduleEdit;
		case 'view':
			return new ScheduleView;
	}
	return null;
}

/**
 * View all games on a single day
 */
class ScheduleViewDay extends Handler
{
	function initialize ()
	{
		$this->title = "View Day";

		$this->_permissions = array(
			"administer_league" => false,
		);
		
		$this->_required_perms = array(
			'allow',
		);

		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} else if($type == 'coordinator') {
			$this->enable_all_perms();
		} 
	}

	function process ()
	{
		$today = getdate();

		$year  = arg(2);
		$month = arg(3);
		$day   = arg(4);
		
		if(! validate_number($month)) {
			$month = $today['mon'];
		}

		if(! validate_number($year)) {
			$year = $today['year'];
		}
		
		if( $day ) {
			if( !validate_date_input($year, $month, $day) ) {
				return "That date is not valid";
			}
			$formattedDay = strftime("%A %B %d %Y", mktime (0,0,0,$month,$day,$year));
			$this->setLocation(array(
				"$this->title &raquo; $formattedDay" => 0));
			return $this->displayGamesForDay( $year, $month, $day );
		} else {
			$this->setLocation(array( "$this->title" => 0));
			$output = para("Select a date below on which to view all scheduled games");
			$output .= generateCalendar( $year, $month, $day, 'schedule/day', 'schedule/day');
			return $output;
		}
	}

	/**
	 * List all games on a given day.
	 */
	function displayGamesForDay ( $year, $month, $day )
	{
		$result = game_query ( array( 'game_date' => sprintf('%d-%d-%d', $year, $month, $day), '_order' => 'g.game_start, field_code') );
		
		if( ! $result ) {
			$this->error_exit("That league does not have a schedule");
		}

		$rows = array( 
			schedule_heading(strftime('%a %b %d %Y',mktime(0,0,0,$month,$day,$year))),
			schedule_subheading( ),
		);
		while($game = db_fetch_array($result)) {
			$rows[] = schedule_render_viewable($this->_permissions['administer_league'], $game);
		}
		$output .= "<div class='schedule'>" . table($header, $rows) . "</div>";
		return $output;
	}
}

/**
 * Edit league schedule
 */
class ScheduleEdit extends Handler
{
	function initialize ()
	{
		$this->title = "Edit Schedule";
		$this->_permissions = array(
			"administer_league" => false,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny'
		);
		return true;
	}
	
	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} else if($type == 'coordinator') {
			$this->enable_all_perms();
		} 
	}
	
	function process ()
	{
		$id    = arg(2);
		$dayId = arg(3);
		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'perform':
				$this->perform($id, $dayId, $edit);
				local_redirect(url("schedule/view/$id"));
				break;
			case 'confirm':
				$rc = $this->generateConfirm( $id, $dayId, $edit );
				break;
			default:
				$rc = $this->generateForm( $id, $dayId );
				break;
		}
		return $rc;
	}

	function generateForm ( $id, $timestamp )
	{
		$league = league_load( array( 'league_id' => $id ) );
		if( ! $league ) {
			$this->error_exit("That league does not exist");
		}

		$this->setLocation(array(
			$league->fullname => "league/view/$id",
			$this->title => 0));
		
		
		/* Grab data for pulldowns if we need an edit form */
		$teams = $league->teams_as_array();
		if( ! count($teams) ) {
			$this->error_exit("There may be no teams in this league");
		}
		$teams[0] = "---";
		 
		$league->teams = $teams;

		// Load available gameslots for scheduling
		// We get any unbooked slots, as well as any currently in use by this
		// set of games.
		$result = db_query(
			"SELECT 
				s.slot_id AS slot_id,
				IF( f.parent_fid, 
					CONCAT_WS(' ', s.game_start, p.name, f.num),
					CONCAT_WS(' ', s.game_start, f.name, f.num)
				) AS value
			 FROM
			 	gameslot s 
				INNER JOIN field f ON (s.fid = f.fid) 
				LEFT JOIN field p ON (p.fid = f.parent_fid) 
				LEFT JOIN league_gameslot_availability a ON (s.slot_id = a.slot_id)
				LEFT JOIN schedule g ON (s.game_id = g.game_id) 
			 WHERE 
			 	UNIX_TIMESTAMP(s.game_date) = %d
				AND ( 
					(a.league_id=%d AND ISNULL(s.game_id)) 
					OR
					g.league_id=%d
				)
			 ORDER BY s.game_start, value", $timestamp, $id,$id);
			
		if( ! db_num_rows($result) ) {
			$this->error_exit("There are no fields assigned to this league");
		}

		$league->gameslots[0] = "---";
		while($slot = db_fetch_object($result)) {
			$league->gameslots[$slot->slot_id] = $slot->value;
		}

		$league->rounds = $league->rounds_as_array();

		$result = game_query ( array( 'league_id' => $id, '_order' => 'g.game_date,g.game_start') );
		
			
		if( ! $result ) {
			$this->error_exit("That league does not have a schedule");
		}

		$prevDayId = -1;
		$rows = array();
		/* For each game in the schedule for this league */
		while($game = db_fetch_array($result)) {

			if( $game['day_id'] != $prevDayId ) {
				if( $timestamp == $prevDayId) {
					/* ensure we add the submit buttons for schedule editing */
					$rows[] = array(
						array('data' => para( form_hidden('edit[step]', 'confirm') . form_submit('submit') . form_reset('reset')), 'colspan' => 9)
					);
				}
				
				$rows[] = schedule_heading( 
					strftime('%a %b %d %Y', $game['timestamp']),
					false,
					$game['day_id'], $id );
				
				if($timestamp == $game['day_id']) {
					$rows[] = schedule_edit_subheading();
				} else {
					$rows[] = schedule_subheading( );
				}
			}
			
			if($timestamp == $game['day_id']) {
				$rows[] = schedule_render_editable($game, $league);
			} else {
				$rows[] = schedule_render_viewable($this->_permissions['administer_league'], $game);
			}
			$prevDayId = $game['day_id'];	
		}
		if( $timestamp == $prevDayId ) {
			/* ensure we add the submit buttons for schedule editing */
			$rows[] = array(
				array('data' => para( form_hidden('edit[step]', 'confirm') . form_submit('submit') . form_reset('reset')), 'colspan' => 9)
			);
		}

		$output .= "<div class='schedule'>" . table(null, $rows) . "</div>";

		return form($output);
	}
	
	function isDataInvalid ($games) 
	{
		if(!is_array($games) ) {
			return "Invalid data supplied for games";
		}
	
		$rc = true;
		$seen_slot = array();
		$seen_team = array();
		foreach($games as $game) {

			if( !validate_number($game['game_id']) ) {
				return "Game entry missing a game ID";
			}
			if( !validate_number($game['home_id']) ) {
				return "Game entry missing home team ID";
			}
			if( !validate_number($game['away_id']) ) {
				return "Game entry missing away team ID";
			}
			if( !validate_number($game['slot_id']) ) {
				return "Game entry missing field ID";
			}
			
			if(in_array($game['slot_id'], $seen_slot) ) {
				return "Cannot schedule the same gameslot twice";
			} else {
				$seen_slot[] = $game['slot_id'];
			}

			$seen_team[$game['home_id']]++;
			$seen_team[$game['away_id']]++;

			if( ($seen_team[$game['home_id']] > 1) || ($seen_team[$game['away_id']] > 1) ) {
				// TODO: Needs to be fixed to deal with doubleheader games.
				return "Cannot schedule a team to play two games at the same time";
			}

			if( $game['home_id'] != 0 && ($game['home_id'] == $game['away_id']) ) {
				return "Cannot schedule a team to play themselves.";
			}
			
			// TODO Check the database to ensure that no other game is
			// scheduled on this field for this timeslot
		}
		
		return false;
	}

	function generateConfirm ( $id, $gameId, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit['games'] );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$league = league_load( array('league_id' => $id) );
		if( !$league ) {
			$this->error_exit("That league does not exist");
		}

		$output = para(
			"Confirm that the changes below are correct, and click 'Submit' to proceed.");
		$output .= form_hidden('edit[step]', 'perform');

		$header = array(
			"Game ID", "Round", "Game Slot", "Home", "Away",
		);
		$rows = array();

		while (list ($game_id, $game_info) = each ($edit['games']) ) {
			$slot = slot_load( array('slot_id' => $game_info['slot_id']) );
			$rows[] = array(
				form_hidden("edit[games][$game_id][game_id]", $game_id) . $game_id,
				form_hidden("edit[games][$game_id][round]", $game_info['round']) . $game_info['round'],
				form_hidden("edit[games][$game_id][slot_id]", $game_info['slot_id']) . $slot->game_start . " at " . $slot->field_name . ' ' . $slot->field_num,
				form_hidden("edit[games][$game_id][home_id]", $game_info['home_id']) .  db_result(db_query("SELECT name from team where team_id = %d", $game_info['home_id'])),
				form_hidden("edit[games][$game_id][away_id]", $game_info['away_id']) . db_result(db_query("SELECT name from team where team_id = %d", $game_info['away_id'])),
			);
		}
		
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		
		$output .= para(form_submit('submit'));
		
		$this->setLocation(array(
			$league->fullname => "league/view/$id",

			$this->title => 0));

		return form($output);
	}
	
	function perform ( $id, $gameId, $edit ) 
	{
		$dataInvalid = $this->isDataInvalid( $edit['games'] );
		if($dataInvalid) {
			$this->error_exit($dataInvalid);
		}

		while (list ($game_id, $game_info) = each ($edit['games']) ) {
			$game = game_load( array('game_id' => $game_id) );
			if( !$game ) {
				$this->error_exit("Attempted to edit game info for a nonexistant game!");
			}

			$game->set('round', $game_info['round']);
			$game->set('home_team', $game_info['home_id']);
			$game->set('away_team', $game_info['away_id']);
			$game->set('slot_id', $game_info['slot_id']);

			if( !$game->save() ) {
				$this->error_exit("Couldn't save game information!");
			}
		}

		return true;
	}
}

/**
 * League schedule viewing handler
 */
class ScheduleView extends Handler
{
	function initialize ()
	{
		$this->title = "View Schedule";
		$this->_permissions = array(
			"edit_schedule" => false,
			"administer_league" => false,
		);
		
		$this->_required_perms = array(
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);

		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} else if($type == 'coordinator') {
			$this->enable_all_perms();
		} 
	}

	function process ()
	{
		$id = arg(2);
		
		$league = league_load( array('league_id' => $id) );
		
		if( !$league ) {
			$this->error_exit("That league does not exist");
		}

		$this->setLocation(array(
			$league->fullname => "league/view/$id",
			$this->title => 0));
			
		/* 
		 * Now, grab the schedule
		 */
		$result = game_query ( array( 'league_id' => $id, '_order' => 'g.game_date, g.game_id, g.game_start') );
		if( ! $result ) {
			$this->error_exit("That league does not have a schedule");
		}

		$prevDayId = -1;
		$rows = array();
		/* For each game in the schedule for this league */
		while($game = db_fetch_array($result)) {

			if( $game['day_id'] != $prevDayId ) {
				$rows[] = schedule_heading( 
					strftime('%a %b %d %Y', $game['timestamp']),
					$this->_permissions['edit_schedule'], 
					$game['day_id'], $id );
				$rows[] = schedule_subheading( );
			}
			
			$rows[] = schedule_render_viewable($game);
			$prevDayId = $game['day_id'];	
		}
		$output .= "<div class='schedule'>" . table(null, $rows) . "</div>";
		league_add_to_menu($this, $league);
		return form($output);
	}
}
	
function schedule_heading( $date, $canEdit = false, $dayId = 0, $leagueId = 0 )
{
	$header = array(
		array('data' => $date, 'colspan' => 7, 'class' => 'gamedate')
	);

	if( $canEdit ) {
		$header[] = array(
			'data' => l("edit week", "schedule/edit/$leagueId/$dayId"),
			'colspan' => 2,
			'class' => 'gamedate'
		);
	}
	$header[] = array('data' => '&nbsp;', 'class' => 'gamedate');
	return $header;
}

function schedule_subheading( )
{
	$subheadings = array("GameID", "Rnd", "Time/Place", "Home", "Away", "Home<br />Score", "Away<br />Score");
	foreach($subheadings as $subheading) {
		$subheadingRow[] = array('data' => $subheading, 'class' => 'column-heading');
	}
	$subheadingRow[] = array('data' => '&nbsp;', 'class' => 'column-heading');
	return $subheadingRow;
}

function schedule_edit_subheading( )
{
	return array(
		array( 'data' => 'Round' , 'class' => 'column-heading'),
		array( 'data' => 'Time/Place', 'colspan' => 2, 'class' => 'column-heading'),
		array( 'data' => 'Home', 'colspan' => 2, 'class' => 'column-heading'),
		array( 'data' => 'Away', 'colspan' => 2, 'class' => 'column-heading'),
		''
	);
}

function schedule_render_editable( &$game, &$league )
{

	// Ensure the given teams are listed in pulldown
	$league->teams[$game['home_id']] = $game['home_name'];
	$league->teams[$game['away_id']] = $game['away_name'];

	return array(
		form_hidden('edit[games][' . $game['game_id'] . '][game_id]', $game['game_id']) 
		. form_select('','edit[games][' . $game['game_id'] . '][round]', $game['round'], $league->rounds),
		array( 
			'data' => form_select('','edit[games][' . $game['game_id'] . '][slot_id]', $game['slot_id'], $league->gameslots), 
			'colspan' => 2
		),
		array( 
			'data' => form_select('','edit[games][' . $game['game_id'] . '][home_id]', $game['home_id'], $league->teams),
			'colspan' => 2
		),
		array( 
			'data' => form_select('','edit[games][' . $game['game_id'] . '][away_id]', $game['away_id'], $league->teams),
			'colspan' => 2
		),
	);
}

function schedule_render_viewable( &$game )
{
	if($game['home_name']) {
		$homeTeam = l($game['home_name'], "team/view/" . $game['home_id']);
	} else {
		$homeTeam = "Not yet scheduled.";
	}
	if($game['away_name']) {
		$awayTeam = l($game['away_name'], "team/view/" . $game['away_id']);
	} else {
		$awayTeam = "Not yet scheduled.";
	}
	
	$gameRow = array(
		$game['game_id'],
		$game['round'],
		l($game['game_start'], 'game/view/' . $game['game_id']) . " at " .  l( $game['field_code'], "field/view/" . $game['fid']),
		$homeTeam,
		$awayTeam,
		$game['home_score'],
		$game['away_score']
	);
	
	if($game['status'] == 'home_default' || $game['status'] == 'away_default') {
		$gameRow[] = array('data' => '(default)', 'colspan' => 2);
	}

	return $gameRow;
}
?>
