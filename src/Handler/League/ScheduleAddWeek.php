<?php
register_page_handler('league_schedule_addweek', 'LeagueScheduleAddWeek');

/**
 * League schedule add week
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class LeagueScheduleAddWeek extends Handler
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("League Schedule View");
		$this->_permissions = array(
			'add_past_week'			=> false,
		);

		return true;
	}

	/**
	 * Check if the current session has permission to view this schedule
	 *
	 * @access public
	 * @return boolean success/fail
	 */
	function has_permission ()
	{
		global $DB, $session, $id;

		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide a league ID");
			return false;
		}
	
		/* Administrator can add weeks in future or in past */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}

		/* League coordinator or assistant can only add future weeks */
		if($session->is_coordinator_of($id)) { 
			return true;	
		}

		return true;
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
		global $id;
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=league_schedule_view&id=$id");
		}
		return parent::display();
	}

	/*
	 * Validate that the date provided is OK
	 */
	function validate_data ()
	{
		/* TODO: Validate that date provided is 
		 * 	a) legitimately a valid date (ie: no Jan 32 or Feb 30)
		 * 	b) Valid for the user to add.  Only administrator can add weeks in
		 * 	past.
		 */
		
	}

	/**
	 * Generate the calendar for selecting day to add to schedule.
	 * TODO: Fix this.  See drupal's archive.module for a better way.
	 */
	function generate_form ()
	{
		global $DB, $id;

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
		
		$cal_info = preg_replace("/(?:(?<=^)|(?<=\s))(\d{1,2})(?=(?:\s|$))/", "<a href='".$GLOBALS['APP_CGI_LOCATION']."?op=$page_op&step=confirm&id=$id&year=$year;month=$month;day=$1'>$1</a>", $cal_info);

		$this->tmpl->assign("calendar_data", $cal_info);
		$this->tmpl->assign("year", $year);
		$this->tmpl->assign("current_month", $month);
		if($month == 1) {
			$next_month = $month + 1;
			$prev_month = "12&year=" . ($year - 1);
		} else if ($month == 12) {
			$next_month = "1&year=" . ($year + 1);
			$prev_month = $month - 1;
		} else {
			$next_month = $month + 1;
			$prev_month = $month - 1;
		}
		$this->tmpl->assign("next_month", $next_month);
		$this->tmpl->assign("prev_month", $prev_month);
		$this->tmpl->assign("month_name", $month_name);
		$this->tmpl->assign("league_id", $id);

		return true;
	}

	/**
	 * Generate simple confirmation page
	 * TODO: write this.
	 */
	function generate_confirm ()
	{
		global $DB, $id;
		
		if(! $this->validate_data()) {
			return false;
		}
		
		return true;
	}

	/**
	 * Add week to schedule.
	 * TODO: test this
	 */
	function perform ()
	{
		global $DB, $id;
		
		if(! $this->validate_data()) {
			return false;
		}

		$this->set_template_file("League/schedule.tmpl");

		$num_teams = $DB->getOne("SELECT COUNT(*) from leagueteams where league_id = ?", array($id));

		if($this->is_database_error($num_teams)) {
			return false;
		}

		if($num_teams < 2) {
			$this->error_text = gettext("Cannot schedule games in a league with less than two teams");
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
		$gametime = join("-",var_from_getorpost("game_year"), var_from_getorpost("game_month"), var_from_getorpost("game_day"));
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

?>
