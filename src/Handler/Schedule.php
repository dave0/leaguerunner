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
		$this->tmpl->assign("page_op", $this->op);
		
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

		if($league['name']) {
			$this->set_title($this->title . " &raquo; " . $league['name']);
			if($league['tier']) {
				$this->set_title($this->title . " Tier " . $league['tier']);
			}
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
		if($this->is_database_error($row)) {
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
		$this->set_title("League Schedule Edit");
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
				$this->set_template_file("League/schedule_edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
		}
		$this->tmpl->assign("page_op", $this->op);
		
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

	function generate_confirm () 
	{
		global $DB;

		$id = var_from_getorpost('id');
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$games = var_from_post('games');
		
		$row = $DB->getRow(
			"SELECT 
				l.name,
				l.tier
			FROM league l
			WHERE l.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		
		$this->tmpl->assign("league_name", $row['name']);
		$this->tmpl->assign("league_tier",   $row['tier']);

		while (list ($game_id, $game_info) = each ($games) ) {
			$games[$game_id]['home_name'] = $DB->getOne("SELECT name from team where team_id = ?", array($game_info['home_id']));
			$games[$game_id]['away_name'] = $DB->getOne("SELECT name from team where team_id = ?", array($game_info['away_id']));
			$games[$game_id]['field_name'] = get_field_name($game_info['field_id']);
		}
		reset($games);
		
		$this->tmpl->assign("games",     $games);
		$this->tmpl->assign("league_id", $id);

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
		$this->set_title("League Schedule View");
		$this->_permissions = array(
			"edit_schedule" => false,
			"view_spirit" => false,
			"add_weeks" => false,
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
			$this->_permissions['edit_anytime'] = true;
		} else if($type == 'coordinator') {
			$this->enable_all_perms();
		} 
	}

	function process ()
	{
		global $DB;

		$this->set_template_file("League/schedule.tmpl");

		$id = var_from_getorpost('id');
		$week_id = var_from_getorpost('week_id');
	
		$row = $DB->getRow(
			"SELECT 
				l.name,
				l.tier,
				l.tier,
				l.ratio,
				l.season
			FROM league l
			WHERE 
				l.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign("league_id",     $id);
		$this->tmpl->assign("league_name",   $row['name']);
		$this->tmpl->assign("league_tier",   $row['tier']);
		$this->tmpl->assign("league_ratio",  $row['ratio']);
		$this->tmpl->assign("league_season", $row['season']);

		/*
		 * Fetch teams and set league_teams variable
		 */
		$league_teams = $DB->getAll(
			"SELECT t.team_id AS value, t.name AS output 
			 FROM 
			 	leagueteams l 
			    LEFT JOIN team t ON (l.team_id = t.team_id) 
		     WHERE l.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league_teams)) {
			$this->error_exit("There may be no teams in this league");
		}
		/* Pop in a --- element */
		array_unshift($league_teams, array('value' => 0, 'output' => '---'));
		$this->tmpl->assign("league_teams", $league_teams);

		/* 
		 * Fetch fields and set league_fields variable 
		 */
		$league_fields = $DB->getAll(
			"SELECT DISTINCT
				f.field_id AS value, 
				CONCAT(s.name,' ',f.num,' (',s.code,' ',f.num,')') AS output
			  FROM
			    field_assignment a
				LEFT JOIN field f ON (a.field_id = f.field_id)
				LEFT JOIN site s ON (f.site_id = s.site_id)
		 	  WHERE
		    	a.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league_fields)) {
			$this->error_exit("There are no fields assigned to this league");
		}
		/* Pop in a --- element */
		array_unshift($league_fields, array('value' => 0, 'output' => '---'));
		$this->tmpl->assign("league_fields", $league_fields);

		/* 
		 * Generate game start times
		 */
		$league_starttime = array();
		for($i = $GLOBALS['LEAGUE_START_HOUR']; $i < 24; $i++) {
			for($j = 0; $j < 60; $j += $GLOBALS['LEAGUE_TIME_INCREMENT']) {
				$league_starttime[] = array(
					"value" => sprintf("%02.2d:%02.2d",$i,$j)
				);
			}
		}
		$this->tmpl->assign("league_starttime", $league_starttime);

		/* 
		 * Rounds
		 */
		$league_rounds = array();
		for($i = 1; $i <= $GLOBALS['LEAGUE_MAX_ROUNDS'];  $i++) {
			$league_rounds[] = array(
					"value" => $i
			);
		}
		$this->tmpl->assign("league_rounds", $league_rounds);
		

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
			
		/* For each game in the schedule for this league */
		$schedule_weeks = array();
		while(list(,$game) = each($sched_rows)) {
			/* find the week for this game */
			if(!isset($schedule_weeks[$game['week_id']]) ) {
				/* if no week found, add a new one */
				$schedule_weeks[$game['week_id']] = array(
					'date' => $game['date'],
					'id' => $game['week_id'],
					'current_edit' => (($week_id == $game['week_id']) ? true : false),
					'editable' => (($game['timestamp'] > time()) || $this->_permissions['edit_anytime']), 
					'games' => array()
				);
			}
			/* Look up home, away, and field names */
			$game['home_name'] = $DB->getOne("SELECT name FROM team WHERE team_id = ?", array($game['home_id']));
			$game['away_name'] = $DB->getOne("SELECT name FROM team WHERE team_id = ?", array($game['away_id']));
			$game['field_name'] = get_field_name($game['field_id']);

			/* push current game into week list */
			$schedule_weeks[$game['week_id']]['games'][] = $game;
		}
		
		$this->tmpl->assign("schedule_weeks", $schedule_weeks);

		/* ... and set permissions flags */
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return true;
	}
}
?>
