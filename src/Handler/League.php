<?php

register_page_handler('league_create', 'LeagueCreate');
register_page_handler('league_edit', 'LeagueEdit');
register_page_handler('league_list', 'LeagueList');
register_page_handler('league_schedule_addweek', 'LeagueScheduleAddWeek');
register_page_handler('league_schedule_edit', 'LeagueScheduleEdit');
register_page_handler('league_schedule_view', 'LeagueScheduleView');
register_page_handler('league_standings', 'LeagueStandings');
register_page_handler('league_view', 'LeagueView');
register_page_handler('league_captemail', 'LeagueCaptainEmails');
register_page_handler('league_moveteam', 'LeagueMoveTeam');
register_page_handler('league_verifyscores', 'LeagueVerifyScores');

/**
 * Create handler
 */
class LeagueCreate extends LeagueEdit
{
	function initialize ()
	{
		if(parent::initialize() == false) {
			return false;
		}
		$this->set_title("Create New League");
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		return true;
	}

	/**
	 * Fill in pulldowns for form.
	 */
	function generate_form () 
	{
		if($this->populate_pulldowns() == false) {
			return false;
		}
		return true;
	}

	function perform ()
	{
		global $DB, $session;
		$league_name = trim(var_from_getorpost("league_name"));
		
		$res = $DB->query("INSERT into league (name,coordinator_id) VALUES (?,?)", array($league_name, $session->data['user_id']));
		if($this->is_database_error($res)) {
			return false;
		}
		
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from league");
		if($this->is_database_error($id)) {
			return false;
		}

		$this->_id = $id;
		
		$this->tmpl->assign("perm_edit_info", true);
		$this->tmpl->assign("perm_edit_coordinator", true);
		$this->tmpl->assign("perm_edit_flags", true);
		return parent::perform();
	}

	function validate_data ()
	{
		global $session;
		$err = true;
		
		$league_name = trim(var_from_getorpost("league_name"));
		if(0 == strlen($league_name)) {
			$this->error_text .= "League name cannot be left blank<br>";
			$err = false;
		}

		return $err;
	}

}

/**
 * League edit handler
 */
class LeagueEdit extends Handler
{

	var $_id;

	function initialize ()
	{
		$this->set_title("Edit League");

		$this->_permissions = array(
			'edit_info'			=> false,
			'edit_coordinator'		=> false,
			'edit_flags'		=> false,
		);
		
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);

		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->_permissions['edit_info'] = true;
			$this->_permissions['edit_flags'] = true;
			$this->_permissions['edit_coordinator'] = true;
		} else if($type == 'coordinator') {
			$this->_permissions['edit_info'] = true;
			$this->_permissions['edit_flags'] = true;
		} 
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');

		$this->_id = var_from_getorpost('id');
		
		switch($step) {
			case 'confirm':
				$this->set_template_file("League/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
			default:
				$this->set_template_file("League/edit_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
		}
		$this->tmpl->assign("page_op", var_from_getorpost('op'));
		
		/* ... and set permissions flags */
		reset($this->_permissions);
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return $rc;
	}

	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=league_view&id=".$this->_id);
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB;

		$row = $DB->getRow(
			"SELECT 
				l.name as league_name,
				l.day  as league_day,
				l.season as league_season,
				l.tier as league_tier,
				l.ratio as league_ratio,
				l.max_teams as max_teams,
				l.coordinator_id,
				l.alternate_id,
				l.stats_display as stats_display,
				l.current_round as current_round,
				l.year,
				l.allow_schedule,
				l.start_time as league_start_time
			FROM league l WHERE l.league_id = ?", 
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		if($this->populate_pulldowns() == false) {
			return false;
		}

		/* Deal with multiple days */
		if(strpos($row['league_day'], ",")) {
			$row['league_day'] = split(",",$row['league_day']);
		}

		$this->tmpl->assign($row);
		$this->tmpl->assign("id", $this->_id);
		
		return true;
	}

	function populate_pulldowns ( )
	{
		global $DB;
		$volunteers = $DB->getAll(
			"SELECT
				p.user_id AS value,
				CONCAT(p.firstname,' ',p.lastname) AS output
			 FROM
			 	person p
			 WHERE
			 	p.class = 'volunteer'
				OR p.class = 'administrator'
			 ORDER BY p.lastname",
			DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($volunteers)) {
			return false;
		}
		/* Pop in a --- element */
		array_unshift($volunteers, array('value' => 0, 'output' => '---'));
		$this->tmpl->assign("volunteers", $volunteers);

		$days = $this->get_enum_options('league','day');
		if(is_bool($days)) {
			return $days;
		}
		$this->tmpl->assign("days", $days);
		
		$ratios = $this->get_enum_options('league','ratio');
		if(is_bool($ratios)) {
			return $ratios;
		}
		$this->tmpl->assign("ratios", $ratios);
		
		$seasons = $this->get_enum_options('league','season');
		if(is_bool($seasons)) {
			return $seasons;
		}
		$this->tmpl->assign("seasons", $seasons);

		/* TODO: 10 is a magic number.  Make it a config variable */
		$this->tmpl->assign("tiers", $this->get_numeric_options(0,10));
		/* TODO: 4 is a magic number.  Make it a config variable */
		$this->tmpl->assign("rounds", $this->get_numeric_options(1,4));
	
		return true;
	}

	function generate_confirm ()
	{
		global $DB;

		if(! $this->validate_data()) {
			$this->error_text .= "<br>Please use your back button to return to the form, fix these errors, and try again";
			return false;
		}
		
		$this->tmpl->assign("id", $this->_id);

		$this->tmpl->assign("league_name", var_from_getorpost('league_name'));
		$this->tmpl->assign("league_season", var_from_getorpost('league_season'));
		$this->tmpl->assign("league_day", join(",",var_from_getorpost('league_day')));
		$this->tmpl->assign("league_tier", var_from_getorpost('league_tier'));
		$this->tmpl->assign("league_round", var_from_getorpost('league_round'));
		$this->tmpl->assign("league_ratio", var_from_getorpost('league_ratio'));
		$this->tmpl->assign("league_allow_schedule", var_from_getorpost('league_allow_schedule'));
		$this->tmpl->assign("league_start_time_Hour", var_from_getorpost('league_start_time_Hour'));
		$this->tmpl->assign("league_start_time_Minute", var_from_getorpost('league_start_time_Minute'));
		
		$c_id = var_from_getorpost('coordinator_id');
		$c_name = $DB->getOne("SELECT CONCAT(p.firstname,' ',p.lastname) FROM person p WHERE p.user_id = ?",array($c_id));
		$this->tmpl->assign("coordinator_id",   $c_id);
		$this->tmpl->assign("coordinator_name", $c_name);
	
		$a_id = var_from_getorpost('alternate_id');
		if($a_id > 0) {
			$a_name = $DB->getOne("SELECT CONCAT(p.firstname,' ',p.lastname) FROM person p WHERE p.user_id = ?",array($a_id));
			$this->tmpl->assign("alternate_id",   $a_id);
			$this->tmpl->assign("alternate_name", $a_name);
		} else {
			$this->tmpl->assign("alternate_id",   $a_id);
			$this->tmpl->assign("alternate_name", "N/A");
		}

		return true;
	}

	function perform ()
	{
		global $DB;

		if(! $this->validate_data()) {
			$this->error_text .= "<br>Please use your back button to return to the form, fix these errors, and try again";
			return false;
		}
		
		$fields      = array();
		$fields_data = array();

		if($this->_permissions['edit_info']) {
			$fields[] = "name = ?";
			$fields_data[] = var_from_getorpost("league_name");
			$fields[] = "day = ?";
			$fields_data[] = var_from_getorpost("league_day");
			$fields[] = "season = ?";
			$fields_data[] = var_from_getorpost("league_season");
			$fields[] = "tier = ?";
			$fields_data[] = var_from_getorpost("league_tier");
			$fields[] = "ratio = ?";
			$fields_data[] = var_from_getorpost("league_ratio");
			$fields[] = "allow_schedule = ?";
			$fields_data[] = var_from_getorpost("league_allow_schedule");
			$fields[] = "start_time = ?";
			$fields_data[] = var_from_getorpost("league_start_time_Hour") . ":" . var_from_getorpost("league_start_time_Minute");
		}
		
		if($this->_permissions['edit_coordinator']) {
			$fields[] = "coordinator_id = ?";
			$fields_data[] = var_from_getorpost("coordinator_id");
			$fields[] = "alternate_id = ?";
			$fields_data[] = var_from_getorpost("alternate_id");
		}
			
		$sql = "UPDATE league SET ";
		$sql .= join(",", $fields);	
		$sql .= "WHERE league_id = ?";

		$sth = $DB->prepare($sql);
		
		$fields_data[] = $this->_id;
		$res = $DB->execute($sth, $fields_data);

		if($this->is_database_error($res)) {
			return false;
		}

		return true;
	}

	/* TODO: Properly validate other data */
	function validate_data ()
	{
		$rc = true;

		$league_name = var_from_getorpost("league_name");
		if ( ! validate_nonhtml($league_name)) {
			$this->error_text .= "<li>A valid league name must be entered";
			$rc = false;
		}

		$coord_id = var_from_getorpost("coordinator_id");
		if($coord_id <= 0) {
			$this->error_text .= "<li>A coordinator must be selected";
			$rc = false;
		}
		
		$league_allow_schedule = var_from_getorpost("league_allow_schedule");
		if( $league_allow_schedule != 'Y' && $league_allow_schedule != 'N' ) {
			$this->error_text .= "<li>Values for allow schedule are Y and N";
			$rc = false;
		}

		if($league_allow_schedule == 'Y') {
			$league_day = var_from_getorpost("league_day");
			if( !isset($league_day) ) {
				$this->error_text .= "<li>One or more days of play must be selected";
				$rc = false;
			}
		}
		
		return $rc;
	}

}

/**
 * League list handler
 */
class LeagueList extends Handler
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("List Leagues");
		$this->_required_perms = array(
			'allow'
		);

		return true;
	}

	function process ()
	{
		global $DB;

		$season = var_from_getorpost('season');
		if(!isset($season)) {
			$season = 'none';
		} else {
			if( !validate_nonhtml($season) ) {
				$this->error_text = "That is not a valid season"; 
				return false;
			}
		}
	
		if($season != 'none') {
			$this->set_title("List $season Leagues");
		}
		
		$this->set_template_file("League/list.tmpl");
	
		/* Fetch league names */
		$row = $DB->getRow("SHOW COLUMNS from league LIKE 'season'", array(), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}
		$str = preg_replace("/^(enum|set)\(/","",$row['Type']);
		$str = str_replace(")","",$str);
		$str = str_replace("'","",$str);
		$ary = preg_split("/,/",$str);
		$this->tmpl->assign('season_names', $ary);
		$this->tmpl->assign("current_season", $season);
		
		$found = $DB->getAll(
			"SELECT 
				league_id AS id, name, tier, ratio
			 FROM league
			 WHERE season = ? ORDER BY day,name,tier",
			array($season), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("page_op", "league_list");
		$this->tmpl->assign("leagues", $found);
		
		return true;
	}
}

/**
 * League schedule add week
 */
class LeagueScheduleAddWeek extends Handler
{
	function initialize ()
	{
		$this->set_title("League Schedule View");
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
		switch($step) {
			case 'confirm':
				$this->set_template_file("League/schedule_addweek_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
			default:
				$this->set_template_file("League/schedule_addweek_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
		}
		$this->tmpl->assign("page_op", var_from_getorpost('op'));
		
		return $rc;
	}
	
	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		$id = var_from_getorpost('id');
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=league_schedule_view&id=$id");
		}
		return parent::display();
	}

	/**
	 * Validate that date provided is 
	 * legitimately a valid date (ie: no Jan 32 or Feb 30)
	 */
	function validate_data ()
	{
		$year = var_from_getorpost('year');
		$month = var_from_getorpost('month');
		$day = var_from_getorpost('day');

		if( !validate_date_input($year, $month, $day) ) {
			$this->error_text = "That date is not valid";
			return false;
		}
		
		return true;
	}

	/**
	 * Generate the calendar for selecting day to add to schedule.
	 * TODO: Fix this.  See drupal's archive.module for a better way.
	 */
	function generate_form ()
	{
		global $DB;
		
		$id = var_from_getorpost('id');
		$row = $DB->getRow(
			"SELECT 
				l.name,
				l.day,
				l.season,
				l.current_round
			FROM league l
			WHERE l.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		
		$this->tmpl->assign("league_id", $id );
		$this->tmpl->assign("league_name", $row['name']);
		$this->tmpl->assign("league_day",   $row['day']);
		$this->tmpl->assign("league_season", $row['season']);
		$this->tmpl->assign("league_current_round", $row['current_round']);
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

		$page_op = var_from_getorpost('op');

		$cal_info = shell_exec("cal $month $year");
		
		$cal_info = preg_replace("/(?:(?<=^)|(?<=\s))(\d{1,2})(?=(?:\s|$))/", "<a href='".$_SERVER['PHP_SELF']."?op=$page_op&step=confirm&id=$id&year=$year&month=$month&day=$1'>$1</a>", $cal_info);

		$this->tmpl->assign("calendar_data", $cal_info);
		$this->tmpl->assign("year", $year);
		$this->tmpl->assign("current_month", $month);
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
		$this->tmpl->assign("next_month", $next_month);
		$this->tmpl->assign("prev_month", $prev_month);
		$this->tmpl->assign("next_year", $next_year);
		$this->tmpl->assign("prev_year", $prev_year);
		$this->tmpl->assign("month_name", $month_name);

		return true;
	}

	/**
	 * Generate simple confirmation page
	 */
	function generate_confirm ()
	{
		global $DB;
		
		$id = var_from_getorpost('id');
		
		if(! $this->validate_data()) {
			return false;
		}
		
		$year = var_from_getorpost('year');
		$month = var_from_getorpost('month');
		$day = var_from_getorpost('day');
		
		$this->tmpl->assign("year", $year);
		$this->tmpl->assign("month", $month);
		$this->tmpl->assign("month_name", date("F", mktime (0,0,0,$month,1,0)));
		$this->tmpl->assign("day", $day);
		$this->tmpl->assign("league_id", $id);
		
		return true;
	}

	/**
	 * Add week to schedule.
	 */
	function perform ()
	{
		global $DB;

		$id = var_from_getorpost('id');
		
		if(! $this->validate_data()) {
			return false;
		}

		$num_teams = $DB->getOne("SELECT COUNT(*) from leagueteams where league_id = ?", array($id));

		if($this->is_database_error($num_teams)) {
			return false;
		}

		if($num_teams < 2) {
			$this->error_text = "Cannot schedule games in a league with less than two teams";
			return false;
		}

		/*
		 * TODO: We only schedule floor($num_teams / 2) games.  This means
		 * that the odd team out won't show up on the schedule.  Perhaps we
		 * should schedule ceil($num_teams / 2) and have the coordinator
		 * explicitly set a bye?
		 */
		$num_games = floor($num_teams / 2);

		$row = $DB->getRow("SELECT current_round, start_time from league where league_id = ?", array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		/* All the game_ date values have already been validated by
		 * validate_data()
		 */
		$gametime = join("-",array(var_from_getorpost("year"), var_from_getorpost("month"), var_from_getorpost("day")));
		$gametime .= " " . $row['start_time'];

		$sth = $DB->prepare("INSERT INTO schedule (league_id,date_played,round) values (?,?,?)");
		for($i = 0; $i < $num_games; $i++) {
			$res = $DB->execute($sth, array($id, $gametime, $row['current_round']));
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
		$this->set_title("League Schedule View");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
		);
		return true;
	}
	
	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		switch($step) {
			case 'perform':
				return $this->perform();
				break;
			case 'confirm':
			default:
				$this->set_template_file("League/schedule_edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
		}
		$this->tmpl->assign("page_op", var_from_getorpost('op'));
		
		return $rc;
	}
	
	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		$id = var_from_getorpost('id');
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=league_schedule_view&id=$id");
		}
		return parent::display();
	}

	function validate_data () 
	{
		$games = var_from_post('games');
		if(!is_array($games) ) {
			$this->error_text = "Invalid data supplied for games";
			return false;
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
				$this->error_text = "Game entry missing a game ID";
				return false;
			}
			if( !validate_number($game['home_id']) ) {
				$this->error_text = "Game entry missing home team ID";
				return false;
			}
			if( !validate_number($game['away_id']) ) {
				$this->error_text = "Game entry missing away team ID";
				return false;
			}
			if( !validate_number($game['field_id']) ) {
				$this->error_text = "Game entry missing field ID";
				return false;
			}

			if( $game['home_id'] != 0 && ($game['home_id'] == $game['away_id']) ) {
				$this->error_text = "Cannot schedule a team to play themselves.";
				return false;
			}
			
			if( in_array( $game['away_id'], $seen_team[$game['start_time']] ) || in_array( $game['home_id'], $seen_team[$game['start_time']] )) {
				$this->error_text = "Cannot schedule a team to play multple games in the same timeslot.";
				return false;
			}

			if( in_array( $game['field_id'], $seen_field[$game['start_time']] )) {
				$this->error_text = "Cannot schedule multiple games to play on the same field.";
				return false;
			}
			// Don't push 0 onto the seen list, as it is 'special'
			if($game['home_id']) {$seen_team[$game['start_time']][] = $game['home_id']; }
			if($game['away_id']) {$seen_team[$game['start_time']][] = $game['away_id']; }
			if($game['field_id']){$seen_field[$game['start_time']][] = $game['field_id']; }
			
			// TODO Check the database to ensure that no other game is
			// scheduled on this field for this timeslot

		}
		
		return $rc;
	}

	function generate_confirm () 
	{
		global $DB;

		$id = var_from_getorpost('id');
		
		if(! $this->validate_data()) {
			return false;
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

		$id = var_from_getorpost('id');
		if(! $this->validate_data()) {
			return false;
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
			$this->error_text .= "There may be no teams in this league";
			return false;
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
			$this->error_text .= "There may be no fields assigned to this league";
			return false;
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
			$this->error_text .= "The league [$id] may not exist";
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

/**
 * League standings handler
 */
class LeagueStandings extends Handler
{
	function initialize ()
	{
		$this->set_title("View League Standings");
		$this->_permissions = array(
			"view_spirit" => false,
			"view_team" => true,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);

		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->_permissions['view_spirit'] = true;
		} else if($type == 'coordinator') {
			$this->_permissions['view_spirit'] = true;
		} 
	}

	function process ()
	{
		global $DB;

		$id = var_from_getorpost('id');

		$this->set_template_file("League/standings.tmpl");
	
		$row = $DB->getRow(
			"SELECT 
				l.name,
				l.tier,
				l.ratio,
				l.season,
				l.current_round,
				l.stats_display,
				l.allow_schedule
			FROM 
				league l
			WHERE 
				l.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		if($row['allow_schedule'] == 'N') {
			$this->error_text = "This league does not have a schedule or standings.";
			return false;
		}

		$this->tmpl->assign("league_id", $id);
		$this->tmpl->assign("league_name",   $row['name']);
		$this->tmpl->assign("league_tier",   $row['tier']);
		$this->tmpl->assign("league_ratio",  $row['ratio']);
		$this->tmpl->assign("league_season", $row['season']);
		$this->tmpl->assign("league_current_round", $row['current_round']);

		$round = var_from_getorpost('round');
		if(! isset($round) ) {
			$round = $row['current_round'];
		}
		
		/* ... and set permissions flags */
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		$this->tmpl->assign("current_round", $round);
		
		/* Now, crunch the stats */
		return $this->fill_standings($id, $round);
	}
	
	function fill_standings ($id, $current_round)
	{
		global $DB;
		$teams = $DB->getAll(
				"SELECT
					t.team_id AS id, t.name
				FROM
					leagueteams l
					LEFT JOIN team t ON (l.team_id = t.team_id)
				WHERE
					league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($teams)) {
			return false;
		}

		$season = array();
		$round  = array();
		
		$this->init_season_array($season, $teams);
		$this->init_season_array($round, $teams);

		/* Now, fetch the schedule.  Get all games played by anyone who is
		 * currently in this league, regardless of whether or not their
		 * opponents are still here
		 */
		$games = $DB->getAll(
			"SELECT DISTINCT
				s.game_id, 
				s.home_team, 
				s.away_team, 
				s.home_score, 
				s.away_score,
				s.home_spirit, 
				s.away_spirit,
				s.round,
				s.defaulted
			 FROM
			  	schedule s, leagueteams t
			 WHERE 
				t.league_id = ?
				AND (s.home_team = t.team_id OR s.away_team = t.team_id)
		 		ORDER BY s.game_id",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($games)) {
			return false;
		}

		while(list(,$game) = each($games)) {
			if(is_null($game['home_score']) || is_null($game['away_score'])) {
				/* Skip unscored games */
				continue;
			}
			$this->record_game($season, $game);
			if($current_round == $game['round']) {
				$this->record_game($round, $game);
			}
		}

		/* Now, sort it all */
		if($current_round > 0) {
			uasort($round, array($this, 'sort_standings'));	
			$sorted_order = &$round;
		} else {
			uasort($season, array($this, 'sort_standings'));	
			$sorted_order = &$season;
		}

		/* and display */
		$standings = array();
		while(list(, $data) = each($sorted_order)) {
			$id = $data['id'];
			$srow = $season[$id];
			if($season[$id]['games'] > 2) {
				$srow['sotg'] = sprintf("%.2f", $season[$id]['spirit'] / ($season[$id]['games'] - ($season[$id]['defaults_for'] + $season[$id]['defaults_against'])));
				
			} else {
				$srow['sotg'] = "---";
			}
			$srow['plusminus'] = $srow['points_for'] - $srow['points_against'];
			
			/* TODO: round standings */
			$srow['round_win'] = $round[$id]['win'];
			$srow['round_loss'] = $round[$id]['loss'];
			$srow['round_tie'] = $round[$id]['tie'];
			$srow['round_defaults_against'] = $round[$id]['defaults_against'];
			$srow['round_defaults_for'] = $round[$id]['defaults_for'];
			$srow['round_points_for'] = $round[$id]['points_for'];
			$srow['round_points_against'] = $round[$id]['points_against'];
			$srow['round_plusminus'] = $round[$id]['points_for'] - $round[$id]['points_against'];

			$standings[] = $srow;
		}
		$this->tmpl->assign("standings", $standings);
		$this->tmpl->assign("want_round", true);
		return true;
	}

	/*
	 * Initialise an empty array of season info
	 */
	function init_season_array(&$season, &$teams) 
	{
		while(list(,$team) = each($teams)) {
			$season[$team['id']] = array(
				'name' => $team['name'],
				'id' => $team['id'],
				'points_for' => 0,
				'points_against' => 0,
				'spirit' => 0,
				'win' => 0,
				'loss' => 0,
				'tie' => 0,
				'defaults_for' => 0,
				'defaults_against' => 0,
				'games' => 0,
				'vs' => array()
			);
		}
		reset($teams);

	}

	function record_game(&$season, &$game)
	{
	
		if(isset($season[$game['home_team']])) {
			$data = &$season[$game['home_team']];
			
			$data['games']++;
			$data['points_for'] += $game['home_score'];
			$data['points_against'] += $game['away_score'];

			/* Need to initialize if not set */
			if(!isset($data['vs'][$game['away_team']])) {
				$data['vs'][$game['away_team']] = 0;
			}
			
			if($game['defaulted'] == 'home') {
				$data['defaults_against']++;
			} else if($game['defaulted'] == 'away') {
				$data['defaults_for']++;
			} else {
				$data['spirit'] += $game['home_spirit'];
			}

			if($game['home_score'] == $game['away_score']) {
				$data['tie']++;
				$data['vs'][$game['away_team']]++;
			} else if($game['home_score'] > $game['away_score']) {
				$data['win']++;
				$data['vs'][$game['away_team']] += 2;
			} else {
				$data['loss']++;
				$data['vs'][$game['away_team']] += 0;
			}
		}
		if(isset($season[$game['away_team']])) {
			$data = &$season[$game['away_team']];
			
			$data['games']++;
			$data['points_for'] += $game['away_score'];
			$data['points_against'] += $game['home_score'];

			/* Need to initialize if not set */
			if(!isset($data['vs'][$game['home_team']])) {
				$data['vs'][$game['home_team']] = 0;
			}
			
			if($game['defaulted'] == 'away') {
				$data['defaults_against']++;
			} else if($game['defaulted'] == 'home') {
				$data['defaults_for']++;
			} else {
				$data['spirit'] += $game['away_spirit'];
			}

			if($game['away_score'] == $game['home_score']) {
				$data['tie']++;
				$data['vs'][$game['home_team']]++;
			} else if($game['away_score'] > $game['home_score']) {
				$data['win']++;
				$data['vs'][$game['home_team']] += 2;
			} else {
				$data['loss']++;
				$data['vs'][$game['home_team']] += 0;
			}
		}
	}

	function sort_standings (&$a, &$b) 
	{

		/* First, order by wins */
		$b_points = (( 2 * $b['win'] ) + $b['tie']);
		$a_points = (( 2 * $a['win'] ) + $a['tie']);
		$rc = cmp($b_points, $a_points);  /* B first, as we want descending */
		if($rc != 0) {
			return $rc;
		}

		/* Next, check +/- */
		$rc = cmp($b['points_for'] - $b['points_against'], $a['points_for'] - $a['points_against']);
		if($rc != 0) {
			return $rc;
		}
		
		/* Check SOTG */
		if($a['games'] > 0 && $b['games'] > 0) {
			$rc = cmp( $b['spirit'] / $b['games'], $a['spirit'] / $a['games']);
			if($rc != 0) {
				return $rc;
			}
		}
		
		/* Then, check head-to-head wins */
		if(isset($b['vs'][$a['id']]) && isset($a['vs'][$b['id']])) {
			$rc = cmp($b['vs'][$a['id']], $a['vs'][$b['id']]);
			if($rc != 0) {
				return $rc;
			}
		}

		/* 
		 * Finally, check losses.  This ensures that teams with no record
		 * appear above teams who have losses.
		 */
		$rc = cmp($a['loss'], $b['loss']);
		if($rc != 0) {
			return $rc;
		}
	}
}

/**
 * League viewing handler
 */
class LeagueView extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			"administer_league" => false,
		);
		$this->set_title("View League");

		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);

		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->_permissions['administer_league'] = true;
		} else if($type == 'coordinator') {
			$this->_permissions['administer_league'] = true;
		} 
	}

	function process ()
	{
		global $DB, $session;

		$this->set_template_file("League/view.tmpl");

		$id = var_from_getorpost('id');
	
		$row = $DB->getRow(
			"SELECT 
				l.name,
				l.tier,
				l.ratio,
				l.day,
				l.season,
				l.max_teams,
				CONCAT(c.firstname,' ',c.lastname) AS coordinator_name, 
				l.coordinator_id,
				CONCAT(co.firstname,' ',co.lastname) AS co_coordinator_name, 
				l.alternate_id as co_coordinator_id,
				l.current_round,
				l.allow_schedule,
				l.start_time
			FROM 
				league l
				LEFT JOIN person c ON (l.coordinator_id = c.user_id) 
				LEFT JOIN person co ON (l.alternate_id = co.user_id)
			WHERE 
				l.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		
		$this->tmpl->assign("player_id", $session->attr_get("user_id"));
		$this->tmpl->assign("league_id", $id);
		$this->tmpl->assign("league_name",   $row['name']);
		$this->tmpl->assign("league_tier",   $row['tier']);
		$this->tmpl->assign("league_day",   $row['day']);
		$this->tmpl->assign("league_ratio",  $row['ratio']);
		$this->tmpl->assign("league_season", $row['season']);
		$this->tmpl->assign("league_maxteams", $row['max_teams']);
		$this->tmpl->assign("league_current_round", $row['current_round']);
		$this->tmpl->assign("league_start_time", $row['start_time']);
		$this->tmpl->assign("coordinator_name", $row['coordinator_name']);
		$this->tmpl->assign("coordinator_id", $row['coordinator_id']);
		$this->tmpl->assign("league_allow_schedule", $row['allow_schedule']);

		if( $row['co_coordinator_id'] > 1 ) {
			$this->tmpl->assign("co_coordinator_name", $row['co_coordinator_name']);
			$this->tmpl->assign("co_coordinator_id", $row['co_coordinator_id']);
		}

		/* Now, fetch teams */
		$rows = $DB->getAll(
			"SELECT 
				t.team_id AS id,
				t.name,
				t.shirt_colour,
				t.status AS team_status
			 FROM
				leagueteams l
				LEFT JOIN team t ON (l.team_id = t.team_id)
			 WHERE
				l.league_id = ?
			 ORDER BY 
			 	name",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($rows)) {
			return false;
		}
		$this->tmpl->assign("teams", $rows);

		/* ... and set permissions flags */
		reset($this->_permissions);
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return true;
	}
}

class LeagueCaptainEmails extends Handler
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

		return true;
	}

	function process ()
	{
		global $DB;

		$id = var_from_getorpost('id');

		$this->page_data = "# League captain email address list\n";

		$addrs = $DB->getAll("SELECT 
				p.firstname, p.lastname, p.email
			FROM 
				leagueteams l, teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				l.league_id = ?
				AND l.team_id = r.team_id
				AND (r.status = 'captain' OR r.status = 'assistant')",array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($addrs)) {
			return false;
		}
		if(count($addrs) <= 0) {
			return true;
		}
		foreach($addrs as $addr) {
			$this->page_data .= sprintf("\"%s %s\" <%s>,\n",
				$addr['firstname'],
				$addr['lastname'],
				$addr['email']);
		}
		
		return true;
	}

	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		Header("Content-type: text/plain");
		print $this->page_data;
	}
}

class LeagueMoveTeam extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'require_var:team_id',
			'admin_sufficient',
			'coordinate_league_containing:team_id',
			'deny'
		);

		return true;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');

		$this->_id = var_from_getorpost('id');
		$this->_team_id = var_from_getorpost('team_id');
		
		if( !validate_number($this->_id) ) {
			$this->error_text = "You must supply a valid league ID";
			return false;
		}
		if( !validate_number($this->_team_id) ) {
			$this->error_text = "You must supply a valid team ID";
			return false;
		}
		
		switch($step) {
			case 'confirm':
				$this->set_template_file("League/move_team_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
			default:
				$this->set_template_file("League/move_team_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
		}
		
		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		return $rc;
	}
	
	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=league_view&id=".$this->_id);
		}
		return parent::display();
	}

	function perform ()
	{
		global $DB, $session;

		$target_id = var_from_getorpost('target_id');
		if($target_id < 1) {
			$this->error_text="That is not a valid league to move to";
			return false;
		}
		if( ! $session->is_coordinator_of($target_id) ) {
			$this->error_text = "Sorry, you cannot move teams to leagues you do not coordinate";
			return false;
		}

		$res = $DB->query("UPDATE leagueteams SET league_id = ? WHERE team_id = ? AND league_id = ?", array( $target_id, $this->_team_id, $this->_id ));
		if($this->is_database_error($res)) {
			return false;
		}
		if( $DB->affectedRows() != 1 ) {
			$this->error_text = "Couldn't move team between leagues";
			return false;
		}

		return true;
	}

	function generate_confirm ()
	{
		global $DB, $session;

		$target_id = var_from_getorpost('target_id');
		if( ! $session->is_coordinator_of($target_id) ) {
			$this->error_text = "Sorry, you cannot move teams to leagues you do not coordinate";
			return false;
		}

		$from_league = $DB->getRow("SELECT l.league_id AS id, l.season, l.day, l.name, l.tier FROM league l WHERE l.league_id = ?", array( $this->_id ), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($from_league)) {
			return false;
		}
		if( ! $from_league ) {
			$this->error_text = "That is not a valid league to move from";
			return false;
		}

		$to_league = $DB->getRow("SELECT l.league_id AS id, l.season, l.day, l.name, l.tier FROM league l WHERE l.league_id = ?", array( $target_id ), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($to_league)) {
			return false;
		}
		if( ! $to_league ) {
			$this->error_text = "That is not a valid league to move to";
			return false;
		}

		$team_name = $DB->getOne("SELECT name FROM team WHERE team_id = ?",array($this->_team_id));
		if($this->is_database_error($team_name)) {
			return false;
		}
		if(! $team_name ) {
			$this->error_text = "That is not a valid team";
			return false;
		}

		$this->set_title("Moving $team_name");

		$this->tmpl->assign("team_name", $team_name);
		$this->tmpl->assign("team_id", $this->_team_id);
		$this->tmpl->assign("id", $this->_id);
		$this->tmpl->assign("from_league", $from_league);
		$this->tmpl->assign("to_league", $to_league);

		return true;
	}
	
	function generate_form ()
	{
		global $DB, $session;

		$leagues = $DB->getAll("SELECT DISTINCT
				l.league_id AS id,
				l.season,
				l.day,
				l.name,
				l.tier
		  	FROM
		  		league l,
				person p
			WHERE
				l.league_id = 1 
				OR l.coordinator_id = ?
				OR l.alternate_id = ?
				OR (p.class = 'administrator' AND p.user_id = ?)
			ORDER BY l.season,l.day,l.name,l.tier",
			array( $session->attr_get('user_id'), $session->attr_get('user_id'), $session->attr_get('user_id')),
			DB_FETCHMODE_ASSOC
		);
		if($this->is_database_error($leagues)) {
			return false;
		}

		$team_name = $DB->getOne("SELECT name FROM team WHERE team_id = ?",array($this->_team_id));
		if($this->is_database_error($team_name)) {
			return false;
		}
		if(! $team_name ) {
			$this->error_text = "That is not a valid team";
			return false;
		}

		$this->set_title("Moving $team_name");

		$this->tmpl->assign("team_name", $team_name);
		$this->tmpl->assign("team_id", $this->_team_id);
		$this->tmpl->assign("id", $this->_id);
		$this->tmpl->assign("leagues", $leagues);

		return true;
	}
}

class LeagueVerifyScores extends Handler
{

	var $_id;
	
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny'
		);

		return true;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		$this->_id = var_from_getorpost('id');
		
		if( !validate_number($this->_id) ) {
			$this->error_text = "You must supply a valid league ID";
			return false;
		}

		/* Get league info */
		$league = $DB->getRow("SELECT name,tier,season,ratio,year FROM league WHERE league_id = ?", array($this->_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league)) {
			return false;
		}

		$title = "Verify Scores for " . $league['name'];
		if($league['tier'] > 0) {
			$title .= " Tier ". $league['tier'];
		}

		$this->set_title($title);
		$this->set_template_file("League/review_scores.tmpl");
		$this->tmpl->assign("league_info",$league);
		$this->tmpl->assign("id",$this->_id);

		/* Now fetch games in need of verification */
		$games = $DB->query("SELECT DISTINCT
			se.game_id,
			UNIX_TIMESTAMP(s.date_played) as timestamp,
			s.home_team,
			h.name AS home_name,
			s.away_team,
			a.name AS away_name
			FROM schedule s, score_entry se
			    LEFT JOIN team h ON (s.home_team = h.team_id)
			    LEFT JOIN team a ON (s.away_team = a.team_id)
			WHERE s.league_id = ? AND s.game_id = se.game_id ORDER BY timestamp", 
			array($this->_id));
		if($this->is_database_error($games)) {
			return false;
		}

		$game_data = array();
		$se_query = "SELECT score_for, score_against, spirit FROM score_entry WHERE team_id = ? AND game_id = ?";
		
		while($game = $games->fetchRow(DB_FETCHMODE_ASSOC)) {
			$one_game = array(
				'id' => $game['game_id'],
				'date'    => strftime("%A %B %d %Y, %H%Mh",$game['timestamp']),
				'home_name' => $game['home_name'],
				'home_id' => $game['home_team'],
				'away_name' => $game['away_name'],
				'away_id' => $game['away_team']);
				
			$home = $DB->getRow($se_query,
				array($game['home_team'],$game['game_id']),DB_FETCHMODE_ASSOC);
			if(isset($home)) {
				$one_game['home_self_score'] = $home['score_for'];
				$one_game['home_opp_score'] = $home['score_against'];
				$one_game['home_opp_sotg'] = $home['spirit'];
			} else {
				$one_game['home_self_score'] = "not entered";
				$one_game['home_opp_score'] = "not entered";
				$one_game['home_opp_sotg'] = "not entered";
			}
			$away = $DB->getRow($se_query,
				array($game['away_team'],$game['game_id']),DB_FETCHMODE_ASSOC);
			if(isset($away)) {
				$one_game['away_self_score'] = $away['score_for'];
				$one_game['away_opp_score'] = $away['score_against'];
				$one_game['away_opp_sotg'] = $away['spirit'];
			} else {
				$one_game['away_self_score'] = "not entered";
				$one_game['away_opp_score'] = "not entered";
				$one_game['away_opp_sotg'] = "not entered";
			}
			$game_data[] = $one_game;
			$home = null;
			$away = null;
		}
		$games->free();
		
		$this->tmpl->assign("games", $game_data);	
		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		return true;
	}
}

?>
