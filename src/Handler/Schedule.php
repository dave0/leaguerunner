<?php
register_page_handler('league_schedule_addweek', 'LeagueScheduleAddWeek');
register_page_handler('league_schedule_edit', 'LeagueScheduleEdit');
register_page_handler('league_schedule_view', 'LeagueScheduleView');

/**
 * League schedule add week
 */
class LeagueScheduleAddWeek extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'add_past_week'			=> false,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);

		$this->op = 'league_schedule_addweek';
		$this->set_title("Schedule &raquo; Add Week");

		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->_permissions['add_past_week'] = true;
		} 
	}

	function process ()
	{
		global $DB;

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
	 * TODO: Fix this.  See drupal's archive.module for a better way.
	 * TODO: Highlight days of week used by this league (in yellow)
	 */
	function generateForm ( $id )
	{
		global $DB;
		
		$league = $DB->getRow(
			"SELECT name,tier FROM league WHERE league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($league)) {
			return false;
		}

		$output = blockquote("Select a date below to add a new week of 
			games to the schedule.  Days on which this league usually 
			plays are highlighted.");

		$today = getdate();
		
		$month = var_from_getorpost("month");
		if(! ctype_digit($month)) {
			$month = $today['mon'];
		}

		$month_name = date("F", mktime (0,0,0,$month,1,0));
	
		$year = var_from_getorpost("year");
		if(! ctype_digit($year)) {
			$year = $today['year'];
		}

		$cal_info = shell_exec("cal $month $year");
		
		$cal_info = preg_replace("/(?:(?<=^)|(?<=\D))(\d{1,2})(?=(?:\D|$))/", "<a href='".$_SERVER['PHP_SELF']."?op=" . $this->op . "&step=confirm&id=$id&year=$year&month=$month&day=$1'>$1</a>", $cal_info);

		if($month == 1) {
			$next_month = $month + 1;
			$next_year  = $year;
			$prev_month = "12";
			$prev_year  = $year - 1;
		} else if ($month == 12) {
			$next_month = "1";
			$next_year  = $year + 1;
			$prev_month = $month - 1;
			$prev_year  = $year;
		} else {
			$next_month = $month + 1;
			$next_year  = $year;
			$prev_month = $month - 1;
			$prev_year  = $year;
		}

		$this->set_title($this->title . " &raquo; " . $league['name']);
		if($league['tier']) {
			$this->set_title($this->title . " Tier " . $league['tier']);
		}
		
		$output .= "<table border='1'>";
		$output .= tr(
			td(l("<--", "op=" . $this->op . "&id=$id&month=$prev_month&year=$prev_year"), array('align' => 'left'))
			. td("$month_name $year", array('align' => 'middle'))
			. td(l("-->", "op=" . $this->op . "&id=$id&month=$next_month&year=$next_year"), array('align' => 'right'))
		);
		$output .= tr(
			td(pre($cal_info), array('colspan' => 3)));
		
		$output .= "</table>";

		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;
	}

	/**
	 * Generate simple confirmation page
	 */
	function generateConfirm ( $id )
	{
		global $DB;
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid);
		}
		
		$league = $DB->getRow(
			"SELECT name, tier FROM league WHERE league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($league)) {
			return false;
		}
		
		$year = var_from_getorpost('year');
		$month = var_from_getorpost('month');
		$day = var_from_getorpost('day');
		$date = date("d F Y", mktime (0,0,0,$month,$day,$year));

		$output = blockquote(
			para("Do you wish to add games on <b>$date</b>?")
			. para("If so, click 'Submit' to continue.  Otherwise, use your browser's back button to go back and select a new date."));

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('year', $year);
		$output .= form_hidden('month', $month);
		$output .= form_hidden('day', $day);
		$output .= para(form_submit('submit'));
		
		if($league['name']) {
			$this->set_title($this->title . " &raquo; " . $league['name']);
			if($league['tier']) {
				$this->set_title($this->title . " Tier " . $league['tier']);
			}
		}
		
		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;
	}

	/**
	 * Add week to schedule.
	 */
	function perform ( $id )
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid);
		}

		$num_teams = $DB->getOne("SELECT COUNT(*) from leagueteams where league_id = ?", array($id));

		if($this->is_database_error($num_teams)) {
			return false;
		}

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

		$league = $DB->getRow("SELECT current_round, start_time from league where league_id = ?", array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league)) {
			return false;
		}

		/* Use the first start time in the list, by default */
		$startTimes = split(",", $league['start_time']);

		/* All the game_ date values have already been validated by
		 * isDataInvalid()
		 */
		$gametime = join("-",array(var_from_getorpost("year"), var_from_getorpost("month"), var_from_getorpost("day")));
		$gametime .= " " . $startTimes[0];

		$sth = $DB->prepare("INSERT INTO schedule (league_id,date_played,round) values (?,?,?)");
		for($i = 0; $i < $num_games; $i++) {
			$res = $DB->execute($sth, array($id, $gametime, $league['current_round']));
			if($this->is_database_error($res)) {
				return false;
			}
		}

		return true;
	}
}

/**
 * Edit league schedule
 */
class LeagueScheduleEdit extends Handler
{
	function initialize ()
	{
		$this->set_title("Schedule &raquo; Edit");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
		);
		$this->op = 'league_schedule_edit';
		return true;
	}
	
	function process ()
	{
		global $DB;

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
		global $DB;

		$id = var_from_getorpost('id');
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$games = var_from_post('games');
		
		$league = $DB->getRow(
			"SELECT name, tier FROM league WHERE league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($league)) {
			return false;
		}

		$output = blockquote(
			"Confirm that the changes below are correct, and click 'Submit' to proceed.");
		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);

		$output .= "<table border='0' cellpadding='3' cellspacing='0'>";
		
		$output .= tr(
			td("Game ID", array('class' => 'schedule_subtitle'))
			. td("Round", array('class' => 'schedule_subtitle'))
			. td("Game Time", array('class' => 'schedule_subtitle'))
			. td("Home", array('class' => 'schedule_subtitle'))
			. td("Away", array('class' => 'schedule_subtitle'))
			. td("Field", array('class' => 'schedule_subtitle'))
		);

		while (list ($game_id, $game_info) = each ($games) ) {
			$output .= tr(
				td( form_hidden("games[$game_id][game_id]", $game_id) . $game_id)
				. td( form_hidden("games[$game_id][round]", $game_info['round']) . $game_info['round'])
				. td( form_hidden("games[$game_id][start_time]", $game_info['start_time']) . $game_info['start_time'])
				. td( form_hidden("games[$game_id][home_id]", $game_info['home_id']) 
					. $DB->getOne("SELECT name from team where team_id = ?", array($game_info['home_id']))
				)
				. td( form_hidden("games[$game_id][away_id]", $game_info['away_id']) 
					. $DB->getOne("SELECT name from team where team_id = ?", array($game_info['away_id']))
				)
				. td( form_hidden("games[$game_id][field_id]", $game_info['field_id']) 
					. get_field_name($game_info['field_id'])
				)
			);
		}
		
		$output .= "</table>";
		$output .= para(form_submit('submit'));
		
		if($league['name']) {
			$this->set_title($this->title . " &raquo; " . $league['name']);
			if($league['tier']) {
				$this->set_title($this->title . " Tier " . $league['tier']);
			}
		}

		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;
	}
	
	function perform () 
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid);
		}
		
		$games = var_from_post('games');

		$sth = $DB->prepare("UPDATE schedule SET home_team = ?, away_team = ?, field_id = ?, round = ?, date_played = ? WHERE game_id = ?");
		
		while (list ($game_id, $game_info) = each ($games) ) {

			/* 
			 * TODO: Fix this
			 * This is intolerably stupid.  Date and time should be split into
			 * two fields, in order to allow them to be easily set
			 * independantly.
			 */
			$date = $DB->getOne('SELECT DATE_FORMAT(date_played, "%Y-%m-%d") FROM schedule WHERE game_id = ?', array($game_id));
			if($this->is_database_error($date)) {
				return false;
			}

			$res = $DB->execute($sth, array(
				$game_info['home_id'],
				$game_info['away_id'],
				$game_info['field_id'],
				$game_info['round'],
				$date . " " . $game_info['start_time'],
				$game_id));
			if($this->is_database_error($res)) {
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
		$this->set_title("Schedule &raquo; View");
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
		global $DB;
		
		$output = "<table border='0' cellpadding='3' cellspacing='0'>";

		$id = var_from_getorpost('id');
		$week_id = var_from_getorpost('week_id');
	
		$league = $DB->getRow(
			"SELECT 
				name, 
				tier,
				start_time
			 FROM league WHERE league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($league)) {
			return false;
		}

		$this->set_title($this->title . " &raquo; " . $league['name']);
		if($league['tier']) {
			$this->set_title($this->title . " Tier " . $league['tier']);
		}

		/* 
		 * Now, grab the schedule
		 */
		$sched_rows = $DB->getAll(
			"SELECT 
				s.game_id     AS id, 
				s.league_id,
				DATE_FORMAT(s.date_played, '%a %b %d %Y') as date, 
				TIME_FORMAT(s.date_played,'%H:%i') as time,
				s.home_team   AS home_id,
				s.away_team   AS away_id, 
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
			  WHERE 
				s.league_id = ? 
			  ORDER BY s.date_played",
			array($id), DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($sched_rows)) {
			$this->error_exit("The league [$id] does not exist");
			return false;
		}

		$prevWeekId = 0;
		$thisWeekGames = array();
		/* For each game in the schedule for this league */
		while(list(,$game) = each($sched_rows)) {

			if( ($prevWeekId != 0) && ($game['week_id'] != $prevWeekId) ) {	

				$output .= $this->processOneWeek( $thisWeekGames, $league, $week_id, $id );
				$thisWeekGames = array($game);
				$prevWeekId = $game['week_id'];	
			} else {
				$thisWeekGames[] = $game;
				$prevWeekId = $game['week_id'];	
			}
		}

		/* Make sure to process the last week obtained */
		$output .= $this->processOneWeek( $thisWeekGames, $league, $week_id, $id );
		$output .= "</table>";

		$links = array();
		if($this->_permissions['edit_schedule']) {
			$links[] = l("add new week", "op=league_schedule_addweek&id=$id");
		}

		print $this->get_header();
		print h1($this->title);
		print blockquote(theme_links($links));
		print form($output);
		print blockquote(theme_links($links));
		print $this->get_footer();
		return true;
	}

	function processOneWeek( &$games, $league, $week_id, $id )
	{
		$weekData = $games[0];
	
		if( $this->_permissions['edit_schedule'] && ($week_id != $weekData['week_id'])) {
			$editLink = l("edit week", "op=league_schedule_view&id=$id&week_id=" . $weekData['week_id'], array('class' => 'topbarlink'));
		} else {
			$editLink = "&nbsp";
		}
		
		$output .= tr(
			td("<b>" . $weekData['date'] . "</b>", array( 'class' => 'schedule_title', 'valign' => 'top', 'colspan' => 7 ))
			. td($editLink, array('class' => 'schedule_title', 'colspan' => 2))
		);

		if($this->_permissions['view_spirit']) {
			$sotgHeader = td("SOTG", array('class' => 'schedule_subtitle', 'colspan' => 2));
			$sotgSubHeader = 
				td("Home", array('class' => 'schedule_subtitle'))
				. td("Away", array('class' => 'schedule_subtitle'));
		} else {
			$sotgHeader = td("&nbsp;", array('class' => 'schedule_subtitle', 'colspan' => 2, 'rowspan' => 2, 'width' => '10%'));
			$sotgSubHeader = "";
		}
		
		$output .= tr(
			td("Round", array( 'class' => 'schedule_subtitle', 'rowspan' => 2 ))
			. td("Game Time", array( 'class' => 'schedule_subtitle', 'rowspan' => 2 ))
			. td("Home", array( 'class' => 'schedule_subtitle', 'rowspan' => 2 ))
			. td("Away", array( 'class' => 'schedule_subtitle', 'rowspan' => 2 ))
			. td("Field", array( 'class' => 'schedule_subtitle', 'rowspan' => 2 ))
			. td("Score", array( 'class' => 'schedule_subtitle', 'colspan' => 2 ))
			. $sotgHeader
		);

		$output .= tr(
			td("Home", array('class' => 'schedule_subtitle'))
			. td("Away", array('class' => 'schedule_subtitle'))
			. $sotgSubHeader);
			
		if( $this->_permissions['edit_schedule'] && ($week_id == $weekData['week_id'])) {
			/* If editable, start off an editable form */
			$output .= $this->createEditableWeek( $games, $league['start_time'], $week_id, $id);
		} else {
			$output .= $this->createViewableWeek( $games );
		}

		return $output;
	}

	function createEditableWeek( &$games, $leagueStartTimes, $weekId, $id )
	{
		global $DB;
		
		$leagueStartTimes = split(",", $leagueStartTimes);
		$startTimes = array();
		while(list(,$time) = each($leagueStartTimes)) {
			$startTimes[$time] = $time;
		}
		
		$leagueTeams = $DB->getAssoc(
			"SELECT t.team_id, t.name 
			 FROM leagueteams l
			 LEFT JOIN team t ON (l.team_id = t.team_id) 
		     WHERE l.league_id = ?", false,
			array($id));
		if($this->is_database_error($leagueTeams)) {
			$this->error_exit("There may be no teams in this league");
		}
		/* Pop in a --- element.  Can't use unshift() or array_merge() on
		 * the assoc array, unfortunately. */
		$leagueTeams = array_reverse($leagueTeams, true);
		$leagueTeams["0"] = "---";
		$leagueTeams = array_reverse($leagueTeams, true); 


		$leagueFields = $DB->getAssoc(
			"SELECT DISTINCT
				f.field_id,
				CONCAT(s.name,' ',f.num,' (',s.code,' ',f.num,')')
			  FROM
			    field_assignment a
				LEFT JOIN field f ON (a.field_id = f.field_id)
				LEFT JOIN site s ON (f.site_id = s.site_id)
		 	  WHERE
		    	a.league_id = ?", false,
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($leagueFields)) {
			$this->error_exit("There are no fields assigned to this league");
		}
		/* Pop in a --- element.  Can't use unshift() or array_merge() on
		 * the assoc array, unfortunately. */
		$leagueFields = array_reverse($leagueFields, true);
		$leagueFields["0"] = "---";
		$leagueFields = array_reverse($leagueFields, true); 

		/* 
		 * Rounds
		 */
		$leagueRounds = array();
		for($i = 1; $i <= $GLOBALS['LEAGUE_MAX_ROUNDS'];  $i++) {
			$leagueRounds[$i] = $i;
		}
		
		$output = form_hidden('op', 'league_schedule_edit');
		$output .= form_hidden('week_id', $weekId);
		$output .= form_hidden('id', $id);
		
		while(list(,$game) = each($games)) {
			$output .= tr(
				td( 
					form_hidden('games[' . $game['id'] . '][game_id]', $game['id'])
					. form_select('','games[' . $game['id'] . '][round]', $game['round'], $leagueRounds)
				)
				. td( form_select('','games[' . $game['id'] . '][start_time]', $game['time'], $startTimes) )
				. td( form_select('','games[' . $game['id'] . '][home_id]', $game['home_id'], $leagueTeams) )
				. td( form_select('','games[' . $game['id'] . '][away_id]', $game['away_id'], $leagueTeams) )
				. td( form_select('','games[' . $game['id'] . '][field_id]', $game['field_id'], $leagueFields) )
				. td( $game['home_score'] )
				. td( $game['away_score'] )
				. td($game['home_spirit'] )
				. td($game['away_spirit'] )
			);
		}

		$output .= tr(
			td(
				para(form_submit('submit') . form_reset('reset')),
				array('colspan' => '9')
			)
		);
		return form($output);
	}
	
	function createViewableWeek( &$games )
	{
		global $DB;
		$output = "";
		while(list(,$game) = each($games)) {
		
			if($game['home_id']) {
				$homeName = $DB->getOne("SELECT name FROM team WHERE team_id = ?", array($game['home_id']));
				$homeTeam = l($homeName, "op=team_view&id=" . $game['home_id']);
			} else {
				$homeTeam = "Not yet scheduled.";
			}
			
			if($game['away_id']) {
				$awayName = $DB->getOne("SELECT name FROM team WHERE team_id = ?", array($game['away_id']));
				$awayTeam = l($awayName, "op=team_view&id=" . $game['away_id']);
			} else {
				$awayTeam = "Not yet scheduled.";
			}

			$spiritInfo = "";
			if($game['defaulted'] != 'no') {
				$spiritInfo = td("(default)", array('colspan' => 2));
			} else {
				if($this->_permissions['view_spirit']) {
					$spiritInfo = 
						td($game['home_spirit'])
						. td($game['away_spirit']);
				}
			}
			
			$output .= tr(
				td( $game['round'] )
				. td( $game['time'] ) 
				. td( $homeTeam )
				. td( $awayTeam )
				. td( l( get_field_name($game['field_id']), "op=site_view&id=" . $game['site_id']))
				. td( $game['home_score'] )
				. td( $game['away_score'] )
				. $spiritInfo
			);
		}
		return $output;
	}
}
?>
