<?php

function schedule_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'day':
			return new ScheduleViewDay;
		case 'add':
			return new ScheduleAddDay;
		case 'edit':
			return new ScheduleEdit;
		case 'view':
			return new ScheduleView;
	}
	return null;
}


/**
 * Add a day to the schedule for a given league
 */
class ScheduleAddDay extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);

		$this->title = "Add Day";
		return true;
	}

	function process ()
	{
		$today = getdate();

		$id    = arg(2);
		$year  = arg(3);
		$month = arg(4);
		$day   = arg(5);
		
		if( $day ) {
			if( !validate_date_input($year, $month, $day) ) {
				return "That date is not valid";
			}
			$edit = $_POST['edit'];
			if( $edit['step'] == 'perform' ) {
				$this->perform( $id, $year, $month, $day );
				local_redirect(url("schedule/view/$id"));
			} else {
				return $this->generateConfirm( $id, $year, $month, $day );
			}
		} else {
			return $this->generateForm( $id, $year, $month, $day);
		}
	}
	
	function generateForm( $id, $year = 0, $month = 0, $day = 0 )
	{
		$league = league_load( array( 'league_id' => $id ) );
		if( ! $league ) {
			$this->error_exit("That league does not exist");
		}
		$league->day = split(',',$league->day);

		$output = para("Select a date below to add a new week of games to the schedule.  Days on which this league usually plays are highlighted.");

		$today = getdate();
	
		if(! ctype_digit($month)) {
			$month = $today['mon'];
		}

		if(! ctype_digit($year)) {
			$year = $today['year'];
		}

		$output .= generateCalendar( $year, $month, $day, "schedule/add/$league->league_id", "schedule/add/$league->league_id", $league->day);

		$this->setLocation(array(
			$league->fullname => "league/view/$id",
			$this->title => 0));

		return $output;
	}
	
	/**
	 * Generate simple confirmation page
	 */
	function generateConfirm ( $id, $year, $month, $day )
	{
		if( !validate_date_input($year, $month, $day) ) {
			$this->error_exit("That date is not valid");
		}
		
		$league = league_load( array( 'league_id' => $id ) );
		if( ! $league ) {
			$this->error_exit("That league does not exist");
		}

		$formattedDay = strftime("%A %B %d %Y", mktime (0,0,0,$month,$day,$year));

		$output = para("Do you wish to add games on <b>$formattedDay</b>?")
			. para("If so, click 'Submit' to continue.  Otherwise, use your browser's back button to go back and select a new date.");

		$output .= form_hidden('edit[step]', 'perform');
		$output .= para(form_submit('submit'));
		
		$this->setLocation(array(
			$league->fullname => "league/view/$id",
			"$this->title &raquo; $formattedDay" => 0));
		
		return form($output);
	}

	/**
	 * Add week to schedule.
	 */
	function perform ( $id, $year, $month, $day )
	{
		if( !validate_date_input($year, $month, $day) ) {
			$this->error_exit("That date is not valid");
		}

		$num_teams = db_result(db_query( "SELECT COUNT(*) from leagueteams where league_id = %d", $id));

		if($num_teams < 2) {
			$this->error_exit("Cannot schedule games in a league with less than two teams");
			return false;
		}

		/*
		 * TODO: We only schedule floor($num_teams / 2) games.  This means
		 * that the odd team out won't show up on the schedule.  Perhaps we
		 * should schedule ceil($num_teams / 2) and have the coordinator
		 * explicitly set a bye?
		 */
		$num_games = floor($num_teams / 2);
		
		$league = league_load( array( 'league_id' => $id ) );
		if( ! $league ) {
			$this->error_exit("That league does not exist");
		}

		/* All the game_ date values have already been validated by
		 * isDataInvalid()
		 */
		$gametime = join("-",array($year,$month, $day));
		$gametime .= " " . $league->start_time;

		for($i = 0; $i < $num_games; $i++) {
			db_query("INSERT INTO schedule (league_id,date_played,round) values (%d,'%s',%d)", $id, $gametime, $league->current_round);
			if(1 != db_affected_rows() ) {
				return false;
			}
		}

		return true;
	}
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
			'require_valid_session',
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
		
		if(! ctype_digit($month)) {
			$month = $today['mon'];
		}

		if(! ctype_digit($year)) {
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
		/* 
		 * Now, grab the schedule
		 */
		$result = db_query(
			"SELECT 
				s.game_id     AS id, 
				s.league_id,
				DATE_FORMAT(s.date_played, '%%a %%b %%d %%Y') as date, 
				TIME_FORMAT(s.date_played,'%%H:%%i') as time,
				s.home_team   AS home_id,
				s.away_team   AS away_id, 
				h.name        AS home_name,
				a.name        AS away_name,
				s.field_id, 
				s.defaulted,
				f.site_id, 
				s.home_score, 
				s.away_score,
				CONCAT(YEAR(s.date_played),DAYOFYEAR(s.date_played)) as day_id,
				s.home_spirit, 
				s.away_spirit,
				s.round
			  FROM
			  	schedule s
				LEFT JOIN field f ON (s.field_id = f.field_id)
				LEFT JOIN team  h ON (s.home_team = h.team_id)
				LEFT JOIN team  a ON (s.away_team = a.team_id)
			  WHERE 
			    YEAR(s.date_played) = %d
				AND DAYOFYEAR(s.date_played) = DAYOFYEAR('%d-%d-%d')
			 ORDER BY time,site_id",$year,$year,$month,$day);
		if( ! $result ) {
			$this->error_exit("That league does not have a schedule");
		}

		$rows = array( 
			schedule_heading(strftime('%a %b %d %Y',mktime(0,0,0,$month,$day,$year))),
			schedule_subheading( $this->_permissions['administer_league'] ),
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

	function generateForm ( $id, $editDayId )
	{
		$league = league_load( array( 'league_id' => $id ) );
		if( ! $league ) {
			$this->error_exit("That league does not exist");
		}

		$this->setLocation(array(
			$league->fullname => "league/view/$id",
			$this->title => 0));
			
		/* Grab data for pulldowns if we need an edit form */
		$league->starttimes = getOptionsFromTimeRange(900,2400,15);
		$result = db_query(
			"SELECT t.team_id, t.name 
			 FROM leagueteams l
			 LEFT JOIN team t ON (l.team_id = t.team_id) 
		     WHERE l.league_id = %s", $id);
		 
		if( ! db_num_rows($result) ) {
			$this->error_exit("There may be no teams in this league");
		}
		$league->teams[0] = "---";
		while($team = db_fetch_object($result)) {
			$league->teams[$team->team_id] = $team->name;
		}

		$result = db_query(
			"SELECT DISTINCT
				f.field_id,
				CONCAT(s.name,' ',f.num,' (',s.code,' ',f.num,')') as name
			  FROM
			    field_assignment a
				LEFT JOIN field f ON (a.field_id = f.field_id)
				LEFT JOIN site s ON (f.site_id = s.site_id)
		 	  WHERE
		    	a.league_id = %d", $id);
			
		if( ! db_num_rows($result) ) {
			$this->error_exit("There are no fields assigned to this league");
		}

		$league->fields[0] = "---";
		while($field = db_fetch_object($result)) {
			$league->fields[$field->field_id] = $field->name;
		}

		/* 
		 * Rounds
		 */
		$league->rounds = array();
		for($i = 1; $i <= 5;  $i++) {
			$league->rounds[$i] = $i;
		}

		/* 
		 * Now, grab the schedule
		 */
		$result = db_query(
			"SELECT 
				s.game_id     AS id, 
				s.league_id,
				DATE_FORMAT(s.date_played, '%%a %%b %%d %%Y') as date, 
				TIME_FORMAT(s.date_played,'%%H:%%i') as time,
				s.home_team   AS home_id,
				s.away_team   AS away_id, 
				h.name        AS home_name,
				a.name        AS away_name,
				s.field_id, 
				s.defaulted,
				f.site_id, 
				s.home_score, 
				s.away_score,
				CONCAT(YEAR(s.date_played),DAYOFYEAR(s.date_played)) as day_id,
				s.home_spirit, 
				s.away_spirit,
				s.round
			  FROM
			  	schedule s
				LEFT JOIN field f ON (s.field_id = f.field_id)
				LEFT JOIN team  h ON (s.home_team = h.team_id)
				LEFT JOIN team  a ON (s.away_team = a.team_id)
			  WHERE 
				s.league_id =  %d
			  ORDER BY s.date_played", $id);
			
		if( ! $result ) {
			$this->error_exit("That league does not have a schedule");
		}

		$prevDayId = -1;
		$rows = array();
		/* For each game in the schedule for this league */
		while($game = db_fetch_array($result)) {

			if( $game['day_id'] != $prevDayId ) {
				if( $editDayId == $prevDayId) {
					/* ensure we add the submit buttons for schedule editing */
					$rows[] = array(
						array('data' => para( form_hidden('edit[step]', 'confirm') . form_submit('submit') . form_reset('reset')), 'colspan' => 9)
					);
				}
				
				$rows[] = schedule_heading( 
					$game['date'], 
					$this->_permissions['administer_league'], 
					false,
					$game['day_id'], $id );
				$rows[] = schedule_subheading( $this->_permissions['administer_league'] );
			}
			
			if($editDayId == $game['day_id']) {
				$rows[] = schedule_render_editable($game, $league);
			} else {
				$rows[] = schedule_render_viewable($this->_permissions['administer_league'], $game);
			}
			$prevDayId = $game['day_id'];	
		}
		if( $editDayId == $prevDayId ) {
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
		$seen_team = array();
		$seen_field = array();
		foreach($games as $game) {

			if(! array_key_exists($game['start_time'], $seen_team) ) {
				$seen_team[$game['start_time']] = array();
				$seen_field[$game['start_time']] = array();
			}
		
			if( !validate_number($game['game_id']) ) {
				return "Game entry missing a game ID";
			}
			if( !validate_number($game['home_id']) ) {
				return "Game entry missing home team ID";
			}
			if( !validate_number($game['away_id']) ) {
				return "Game entry missing away team ID";
			}
			if( !validate_number($game['field_id']) ) {
				return "Game entry missing field ID";
			}

			if( $game['home_id'] != 0 && ($game['home_id'] == $game['away_id']) ) {
				return "Cannot schedule a team to play themselves.";
			}
			
			if( in_array( $game['away_id'], $seen_team[$game['start_time']] ) || in_array( $game['home_id'], $seen_team[$game['start_time']] )) {
				return "Cannot schedule a team to play multple games in the same timeslot.";
			}

			if( in_array( $game['field_id'], $seen_field[$game['start_time']] )) {
				return "Cannot schedule multiple games to play on the same field.";
			}
			// Don't push 0 onto the seen list, as it is 'special'
			if($game['home_id']) {$seen_team[$game['start_time']][] = $game['home_id']; }
			if($game['away_id']) {$seen_team[$game['start_time']][] = $game['away_id']; }
			if($game['field_id']){$seen_field[$game['start_time']][] = $game['field_id']; }
			
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
			"Game ID", "Round", "Game Time", "Home", "Away", "Field",
		);
		$rows = array();

		while (list ($game_id, $game_info) = each ($edit['games']) ) {
			$rows[] = array(
				form_hidden("edit[games][$game_id][game_id]", $game_id) . $game_id,
				form_hidden("edit[games][$game_id][round]", $game_info['round']) . $game_info['round'],
				form_hidden("edit[games][$game_id][start_time]", $game_info['start_time']) . $game_info['start_time'],
				form_hidden("edit[games][$game_id][home_id]", $game_info['home_id']) .  db_result(db_query("SELECT name from team where team_id = %d", $game_info['home_id'])),
				form_hidden("edit[games][$game_id][away_id]", $game_info['away_id']) . db_result(db_query("SELECT name from team where team_id = %d", $game_info['away_id'])),
				form_hidden("edit[games][$game_id][field_id]", $game_info['field_id']) . get_field_name($game_info['field_id'])
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

			/* 
			 * TODO: Fix this
			 * This is intolerably stupid.  Date and time should be split into
			 * two fields, in order to allow them to be easily set
			 * independantly.
			 */
			$date = db_result(db_query('SELECT DATE_FORMAT(date_played, "%%Y-%%m-%%d") FROM schedule WHERE game_id = %d', $game_id));

			db_query("UPDATE schedule SET home_team = %d, away_team = %d, field_id = %d, round = %d, date_played = '%s' WHERE game_id = %d",
				$game_info['home_id'],
				$game_info['away_id'],
				$game_info['field_id'],
				$game_info['round'],
				$date . " " . $game_info['start_time'],
				$game_id);
			if( 1 != db_affected_rows() ) {
				return false;
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
		
		$links = array();
		if($this->_permissions['edit_schedule']) {
			$links[] = l("add new week", "schedule/add/$id");
		}
		
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
		$result = db_query(
			"SELECT 
				s.game_id     AS id, 
				s.league_id,
				DATE_FORMAT(s.date_played, '%%a %%b %%d %%Y') as date, 
				TIME_FORMAT(s.date_played,'%%H:%%i') as time,
				s.home_team   AS home_id,
				s.away_team   AS away_id, 
				h.name        AS home_name,
				a.name        AS away_name,
				h.rating      AS home_rating,
				a.rating      AS away_rating,
				s.field_id, 
				s.defaulted,
				f.site_id, 
				s.home_score, 
				s.away_score,
				CONCAT(YEAR(s.date_played),DAYOFYEAR(s.date_played)) as day_id,
				s.home_spirit, 
				s.away_spirit,
				s.round
			  FROM
			  	schedule s
				LEFT JOIN field f ON (s.field_id = f.field_id)
				LEFT JOIN team  h ON (s.home_team = h.team_id)
				LEFT JOIN team  a ON (s.away_team = a.team_id)
			  WHERE 
				s.league_id =  %d
			  ORDER BY s.date_played", $id);
			
		if( ! $result ) {
			$this->error_exit("That league does not have a schedule");
		}

		$prevDayId = -1;
		$rows = array();
		/* For each game in the schedule for this league */
		while($game = db_fetch_array($result)) {

			if( $game['day_id'] != $prevDayId ) {
				$rows[] = schedule_heading( 
					$game['date'], 
					$this->_permissions['administer_league'], 
					$this->_permissions['edit_schedule'], 
					$game['day_id'], $id );
				$rows[] = schedule_subheading( $this->_permissions['administer_league'] );
			}
			
			$rows[] = schedule_render_viewable($this->_permissions['administer_league'], $game);
			$prevDayId = $game['day_id'];	
		}
		$output = theme_links($links);
		$output .= "<div class='schedule'>" . table(null, $rows) . "</div>";
		$output .= theme_links($links);

		league_add_to_menu($this, $league);
		return form($output);
	}
}
	
function schedule_heading( $date, $canViewSpirit = false, $canEdit = false, $dayId = 0, $leagueId = 0 )
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
	} else if( $canViewSpirit ) {
		$header[] = array(
			'data' => '&nbsp;',
			'colspan' => 2,
			'class' => 'gamedate'
		);
	}
	$header[] = array('data' => '&nbsp;', 'class' => 'gamedate');
	return $header;
}

function schedule_subheading( $canViewSpirit )
{
	$subheadings = array("Round", "Game Time", "Home", "Away", "Field", "Home<br />Score", "Away<br />Score");
	if($canViewSpirit) {
		$subheadings[] = "Home<br />SOTG";
		$subheadings[] = "Away<br /> SOTG";
	}
	foreach($subheadings as $subheading) {
		$subheadingRow[] = array('data' => $subheading, 'class' => 'column-heading');
	}
	$subheadingRow[] = array('data' => '&nbsp;', 'class' => 'column-heading');
	return $subheadingRow;
}

function schedule_render_editable( &$game, &$league )
{
	return array(
		form_hidden('edit[games][' . $game['id'] . '][game_id]', $game['id']) 
		. form_select('','edit[games][' . $game['id'] . '][round]', $game['round'], $league->rounds),
		form_select('','edit[games][' . $game['id'] . '][start_time]', $game['time'], $league->starttimes),
		form_select('','edit[games][' . $game['id'] . '][home_id]', $game['home_id'], $league->teams),
		form_select('','edit[games][' . $game['id'] . '][away_id]', $game['away_id'], $league->teams),
		form_select('','edit[games][' . $game['id'] . '][field_id]', $game['field_id'], $league->fields),
		$game['home_score'],
		$game['away_score'],
		$game['home_spirit'],
		$game['away_spirit'],
		''
	);
}

function schedule_render_viewable( $canViewSpirit, &$game )
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
		$game['round'],
		l($game['time'], 'game/view/' . $game['id']),
		$homeTeam,
		$awayTeam,
		l( get_field_name($game['field_id']), "site/view/" . $game['site_id']),
		$game['home_score'],
		$game['away_score']
	);
	
	if($game['defaulted'] != 'no') {
		$gameRow[] = array('data' => '(default)', 'colspan' => 2);
	} else {
		if($canViewSpirit) {
			$gameRow[] = $game['home_spirit'];
			$gameRow[] = $game['away_spirit'];
		}
	}

	return $gameRow;
}
?>
