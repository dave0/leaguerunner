<?php
register_page_handler('league_schedule_addweek', 'LeagueScheduleAddWeek');
register_page_handler('league_schedule_edit', 'LeagueScheduleEdit');
register_page_handler('league_schedule_view', 'LeagueScheduleView');
register_page_handler('schedule_view_day', 'ScheduleViewDay');

/**
 * League schedule add week
 */
class LeagueScheduleAddWeek extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);

		$this->op = 'league_schedule_addweek';
		$this->section = 'league';
		$this->title = "Add Week";

		return true;
	}

	function process ()
	{
		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				$this->perform( $id );
				local_redirect("op=league_schedule_view&id=$id");
				break;
			default:
				$rc = $this->generateForm( $id );
		}
		
		return $rc;
	}
	
	/**
	 * Validate that date provided is 
	 * legitimately a valid date (ie: no Jan 32 or Feb 30)
	 */
	function isDataInvalid ()
	{
		$year = var_from_getorpost('year');
		$month = var_from_getorpost('month');
		$day = var_from_getorpost('day');

		if( !validate_date_input($year, $month, $day) ) {
			return "That date is not valid";
		}
		
		return false;
	}

	/**
	 * Generate the calendar for selecting day to add to schedule.  
	 * Days of play for this league are highlighted.
	 */
	function generateForm ( $id )
	{

		/* TODO: league_load() */
		$result = db_query(
			"SELECT 
				name,
				tier,
				day
			 FROM league WHERE league_id = %d", $id);
			 
		if( 1 != db_num_rows($result)) {
			return false;
		}
		
		$league = db_fetch_array($result);

		$league['day'] = split(',',$league['day']);

		$output = para("Select a date below to add a new week of 
			games to the schedule.  Days on which this league usually 
			plays are highlighted.");

		$today = getdate();
		
		$month = var_from_getorpost("month");
		if(! ctype_digit($month)) {
			$month = $today['mon'];
		}

		$year = var_from_getorpost("year");
		if(! ctype_digit($year)) {
			$year = $today['year'];
		}

		$output .= generateCalendar( $year, $month, -1, 
			$this->op . "&id=$id",
			$this->op . "&step=confirm&id=$id",
			$league['day']
		);

		$leagueName = $league['name'];
		if($league['tier']) {
			$leagueName .= " Tier " . $league['tier'];
		}
		$this->setLocation(array(
			$leagueName => "op=league_view&id=$id",
			$this->title => 0));

		return $output;
	}

	/**
	 * Generate simple confirmation page
	 */
	function generateConfirm ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid);
		}
		
		/* TODO: league_load() */
		$result = db_query(
			"SELECT 
				name,
				tier,
				day
			 FROM league WHERE league_id = %d", $id);
		if( 1 != db_num_rows($result)) {
			return false;
		}
		
		$league = db_fetch_array($result);

		$year = var_from_getorpost('year');
		$month = var_from_getorpost('month');
		$day = var_from_getorpost('day');
		$date = date("d F Y", mktime (0,0,0,$month,$day,$year));

		$output = para("Do you wish to add games on <b>$date</b>?")
			. para("If so, click 'Submit' to continue.  Otherwise, use your browser's back button to go back and select a new date.");

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('year', $year);
		$output .= form_hidden('month', $month);
		$output .= form_hidden('day', $day);
		$output .= para(form_submit('submit'));
		
		$leagueName = $league['name'];
		if($league['tier']) {
			$leagueName .= " Tier " . $league['tier'];
		}
		$this->setLocation(array(
			$leagueName => "op=league_view&id=$id",
			$this->title => 0));
		
		return form($output);
	}

	/**
	 * Add week to schedule.
	 */
	function perform ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid);
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
		
		/* TODO: league_load() */
		$result = db_query(
			"SELECT 
				current_round,
				start_time
			 FROM league WHERE league_id = %d", $id);
			 
		if( 1 != db_num_rows($result)) {
			return false;
		}
		$league = db_fetch_array($result);

		/* Use the first start time in the list, by default */
		$startTimes = split(",", $league['start_time']);

		/* All the game_ date values have already been validated by
		 * isDataInvalid()
		 */
		$gametime = join("-",array(var_from_getorpost("year"), var_from_getorpost("month"), var_from_getorpost("day")));
		$gametime .= " " . $startTimes[0];

		for($i = 0; $i < $num_games; $i++) {
			db_query("INSERT INTO schedule (league_id,date_played,round) values (%d,'%s',%d)", $id, $gametime, $league['current_round']);
			
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
		$this->_required_perms = array(
			'require_valid_session',
			'allow',
		);

		$this->op = 'schedule_view_day';
		$this->section = 'league';
		$this->title = "View Day";

		return true;
	}

	function process ()
	{
		$step = var_from_getorpost('step');
		
		$today = getdate();
		
		$month = var_from_getorpost("month");
		if(! ctype_digit($month)) {
			$month = $today['mon'];
		}

		$year = var_from_getorpost("year");
		if(! ctype_digit($year)) {
			$year = $today['year'];
		}
		
		$day = var_from_getorpost('day');
		if(! ctype_digit($day)) {
			$day = $today['mday'];
		}
		
		switch($step) {
			case 'perform':
				if( !validate_date_input($year, $month, $day) ) {
					return "That date is not valid";
				}
				$formattedDay = strftime("%A %B %d %Y", mktime (0,0,0,$month,$day,$year));
				$this->setLocation(array(
					"$this->title &raquo; $formattedDay" => 0));
				return $this->displayGamesForDay( $year, $month, $day );
				break;
			default:
				$this->setLocation(array( "$this->title" => 0));
				return $this->generateCalendar( $year, $month, $day );
		}
	}

	/**
	 * Generate the calendar for selecting day to view.
	 * Today is highlighted.
	 */
	function generateCalendar ( $year, $month, $day = 0 )
	{
		$output = para("Select a date below on which to view all scheduled games");

		$output .= generateCalendar( $year, $month, $day, 
			$this->op,
			"$this->op&step=perform"
		);
		
		return $output;
	}

	/**
	 * List all games on a given day.
	 */
	function displayGamesForDay ( $year, $month, $day )
	{
		$result = db_query(
			"SELECT
				s.game_id,
				TIME_FORMAT(s.date_played,'%%H:%%i') as time,
				f.site_id, 
				s.home_team,
				s.away_team,
				s.field_id,
				h.name AS home_name, 
				a.name AS away_name
			 FROM
			    schedule s
				LEFT JOIN field f ON (s.field_id = f.field_id)
				LEFT JOIN team h ON (s.home_team = h.team_id)
				LEFT JOIN team a ON (s.away_team = a.team_id)
			 WHERE
			    YEAR(s.date_played) = %d
				AND DAYOFYEAR(s.date_played) = DAYOFYEAR('%d-%d-%d')
			 ORDER BY time,site_id",$year,$year,$month,$day);
		$rows = array();
		$header = array( "Time", "Home", "Away", "Location", "&nbsp;");
		while($game = db_fetch_object($result)) {
			$rows[] = array(
				$game->time,
				l($game->home_name, "op=team_view&id=$game->home_team"),
				l($game->away_name, "op=team_view&id=$game->away_team"),
				get_field_name($game->field_id),
				l('details', "op=game_view&id=$game->game_id")
			);
		}
		$output .= "<div class='schedule'>" . table($header, $rows) . "</div>";
		return $output;
	}
}

/**
 * Edit league schedule
 */
class LeagueScheduleEdit extends Handler
{
	function initialize ()
	{
		$this->title = "Edit";
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
		);
		$this->op = 'league_schedule_edit';
		$this->section = 'league';
		return true;
	}
	
	function process ()
	{
		$id = var_from_getorpost('id');
		$step = var_from_getorpost('step');
		switch($step) {
			case 'perform':
				$this->perform();
				local_redirect("op=league_schedule_view&id=$id");
				break;
			case 'confirm':
			default:
				$rc = $this->generateConfirm( $id );
				break;
		}
		return $rc;
	}
	
	function isDataInvalid () 
	{
		$games = var_from_post('games');
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

	function generateConfirm ( $id ) 
	{
		$id = var_from_getorpost('id');
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$games = var_from_post('games');
		
		/* TODO: league_load() */
		$result = db_query(
			"SELECT 
				name,
				tier
			 FROM league WHERE league_id = %d", $id);
			 
		if( 1 != db_num_rows($result)) {
			return false;
		}
		
		$league = db_fetch_array($result);

		$output = para(
			"Confirm that the changes below are correct, and click 'Submit' to proceed.");
		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);

		$header = array(
			"Game ID", "Round", "Game Time", "Home", "Away", "Field",
		);
		$rows = array();

		while (list ($game_id, $game_info) = each ($games) ) {
			$rows[] = array(
				form_hidden("games[$game_id][game_id]", $game_id) . $game_id,
				form_hidden("games[$game_id][round]", $game_info['round']) . $game_info['round'],
				form_hidden("games[$game_id][start_time]", $game_info['start_time']) . $game_info['start_time'],
				form_hidden("games[$game_id][home_id]", $game_info['home_id']) .  db_result(db_query("SELECT name from team where team_id = %d", $game_info['home_id'])),
				form_hidden("games[$game_id][away_id]", $game_info['away_id']) . db_result(db_query("SELECT name from team where team_id = %d", $game_info['away_id'])),
				form_hidden("games[$game_id][field_id]", $game_info['field_id']) . get_field_name($game_info['field_id'])
			);
		}
		
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		
		$output .= para(form_submit('submit'));
		
		$leagueName = $league['name'];
		if($league['tier']) {
			$leagueName .= " Tier " . $league['tier'];
		}
		$this->setLocation(array(
			$leagueName => "op=league_view&id=$id",
			$this->title => 0));

		return form($output);
	}
	
	function perform () 
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid);
		}
		
		$games = var_from_post('games');

		while (list ($game_id, $game_info) = each ($games) ) {

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
		reset($games);

		return true;
		
	}
}

/**
 * League schedule viewing handler
 */
class LeagueScheduleView extends Handler
{
	function initialize ()
	{
		$this->title = "View Schedule";
		$this->_permissions = array(
			"edit_schedule" => false,
			"view_spirit" => false,
		);
		
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);

		$this->op = 'league_schedule_view';
		$this->section = 'league';

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
		$id = var_from_getorpost('id');
		$week_id = var_from_getorpost('week_id');

		$links = array();
		if($this->_permissions['edit_schedule']) {
			$links[] = l("add new week", "op=league_schedule_addweek&id=$id");
		}
		/* TODO: league_load() */
		$result = db_query(
			"SELECT 
				name, 
				tier,
				start_time,
				current_round
			 FROM league WHERE league_id = %d", $id);
			 
		if( 1 != db_num_rows($result)) {
			return false;
		}
		
		$league = db_fetch_array($result);

		$leagueName = $league['name'];
		if($league['tier']) {
			$leagueName .= " Tier " . $league['tier'];
		}
		$this->setLocation(array(
			$leagueName => "op=league_view&id=$id",
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
				s.field_id, 
				s.defaulted,
				f.site_id, 
				s.home_score, 
				s.away_score,
				CONCAT(YEAR(s.date_played),DAYOFYEAR(s.date_played)) as week_id,
				s.home_spirit, 
				s.away_spirit,
				s.round,
				UNIX_TIMESTAMP(s.date_played) as timestamp
			  FROM
			  	schedule s
				LEFT JOIN field f ON (s.field_id = f.field_id)
				LEFT JOIN team  h ON (s.home_team = h.team_id)
				LEFT JOIN team  a ON (s.away_team = a.team_id)
			  WHERE 
				s.league_id =  %d
			  ORDER BY s.date_played", $id);
			
		if( ! $result ) {
			$this->error_exit("The league [$id] does not exist");
			return false;
		}

		$prevWeekId = 0;
		$thisWeekGames = array();
		$rows = array();
		/* For each game in the schedule for this league */
		while($game = db_fetch_array($result)) {

			if( ($prevWeekId != 0) && ($game['week_id'] != $prevWeekId) ) {	
				$this->processOneWeek( $rows, $thisWeekGames, $league, $week_id, $id );
				$thisWeekGames = array($game);
				$prevWeekId = $game['week_id'];	
			} else {
				$thisWeekGames[] = $game;
				$prevWeekId = $game['week_id'];	
			}
		}

		/* Make sure to process the last week obtained */
		$this->processOneWeek( $rows, $thisWeekGames, $league, $week_id, $id );
		$output = theme_links($links);
		
		$output .= "<div class='schedule'>" . table(null, $rows) . "</div>";

		$output .= theme_links($links);

		return form($output);
	}

	function processOneWeek( &$rows, &$games, &$league, $week_id, $id )
	{
		$weekData = $games[0];
	
		if( $this->_permissions['edit_schedule'] && ($week_id != $weekData['week_id'])) {
			$editLink = l("edit week", "op=league_schedule_view&id=$id&week_id=" . $weekData['week_id'], array('class' => 'topbarlink'));
		} else {
			$editLink = "&nbsp";
		}

		$rows[] = array(
			array('data' => $weekData['date'], 'colspan' => 7, 'class' => 'gamedate'),
			array('data' => $editLink, 'colspan' => 2, 'class' => 'gamedate')
		);

		$subheadings = array("Round", "Game Time", "Home", "Away", "Field", "Home<br />Score", "Away<br />Score");

		if($this->_permissions['view_spirit']) {
			$subheadings[] = "Home<br />SOTG";
			$subheadings[] = "Away<br /> SOTG";
		} else {
			$subheadings[] = "";
			$subheadings[] = "";
		}
		$subheadingRow = array();
		foreach($subheadings as $subheading) {
			$subheadingRow[] = array('data' => $subheading, 'class' => 'column-heading');
		}

		$rows[] = $subheadingRow;
			
		if( $this->_permissions['edit_schedule'] && ($week_id == $weekData['week_id'])) {
			/* If editable, start off an editable form */
			$this->createEditableWeek( $rows, $games, $league, $week_id, $id);
		} else {
			$this->createViewableWeek( $rows, $games, $league );
		}
	}

	function createEditableWeek( &$rows, &$games, &$league, $weekId, $id )
	{
		$startTimes = getOptionsFromTimeRange(900,2400,15);
		
		$result = db_query(
			"SELECT t.team_id, t.name 
			 FROM leagueteams l
			 LEFT JOIN team t ON (l.team_id = t.team_id) 
		     WHERE l.league_id = %s", $id);
			 
		if( ! db_num_rows($result) ) {
			$this->error_exit("There may be no teams in this league");
		}
		$leagueTeams[0] = "---";
		while($team = db_fetch_object($result)) {
			$leagueTeams[$team->team_id] = $team->name;
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
		
		$leagueFields[0] = "---";
		while($field = db_fetch_object($result)) {
			$leagueFields[$field->field_id] = $field->name;
		}

		/* 
		 * Rounds
		 */
		$leagueRounds = array();
		for($i = 1; $i <= 5;  $i++) {
			$leagueRounds[$i] = $i;
		}
	
		while(list(,$game) = each($games)) {
			$rows[] = array(
				form_hidden('games[' . $game['id'] . '][game_id]', $game['id']) 
				. form_select('','games[' . $game['id'] . '][round]', $game['round'], $leagueRounds),

				form_select('','games[' . $game['id'] . '][start_time]', $game['time'], $startTimes),
				form_select('','games[' . $game['id'] . '][home_id]', $game['home_id'], $leagueTeams),
				form_select('','games[' . $game['id'] . '][away_id]', $game['away_id'], $leagueTeams),
				form_select('','games[' . $game['id'] . '][field_id]', $game['field_id'], $leagueFields),
				$game['home_score'],
				$game['away_score'],
				$game['home_spirit'],
				$game['away_spirit']
			);
		}
		
		$hiddenFields = form_hidden('op', 'league_schedule_edit');
		$hiddenFields .= form_hidden('week_id', $weekId);
		$hiddenFields .= form_hidden('id', $id);

		$rows[] = array(
			array('data' => $hiddenFields . para(form_submit('submit') . form_reset('reset')), 'colspan' => 9)
		);
	}
	
	function createViewableWeek( &$rows, &$games, &$league )
	{
		while(list(,$game) = each($games)) {
		
			if($game['home_name']) {
				$homeTeam = l($game['home_name'], "op=team_view&id=" . $game['home_id']);
			} else {
				$homeTeam = "Not yet scheduled.";
			}
			if($game['away_name']) {
				$awayTeam = l($game['away_name'], "op=team_view&id=" . $game['away_id']);
			} else {
				$awayTeam = "Not yet scheduled.";
			}
			
			$gameRow = array(
				$game['round'],
				$game['time'],
				$homeTeam,
				$awayTeam,
				l( get_field_name($game['field_id']), "op=site_view&id=" . $game['site_id']),
				$game['home_score'],
				$game['away_score']
			);
			
			if($game['defaulted'] != 'no') {
				$gameRow[] = array('data' => '(default)', 'colspan' => 2);
			} else {
				if($this->_permissions['view_spirit']) {
					$gameRow[] = $game['home_spirit'];
					$gameRow[] = $game['away_spirit'];
				}
			}
			$rows[] = $gameRow;
		}
	}
}

?>
