<?php
register_page_handler('league_schedule_view', 'LeagueScheduleView');

/**
 * League schedule viewing handler
 *
 * @package Leaguerunner
 * @version $Id $
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class LeagueScheduleView extends Handler
{
	/** 
	 * Initializer for LeagueScheduleView class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "League Schedule View";
		$this->_permissions = array(
			"edit_schedule" => false,
			"edit_anytime" => false,
			"view_spirit" => false,
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
		
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}

		/* TODO: set edit_schedule perm if:
		 * 	- coordinator or co-coord
		 */

		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->set_template_file("League/schedule.tmpl");

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
			"SELECT 
				t.team_id AS value, 
				t.name    AS output
			  FROM
			  	team t, leagueteams l
			  WHERE
			  	l.league_id = ?
				AND l.status = 'confirmed'
				AND l.team_id = t.team_id",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league_teams)) {
			$this->error_text .= gettext("There may be no teams in this league");
			return false;
		}
		/* Pop in a --- element */
		array_unshift($league_teams, array('value' => 0, 'output' => '---'));
		$this->tmpl->assign("league_teams", $league_teams);

		/* 
		 * Fetch fields and set league_fields variable 
		 */
		$league_fields = $DB->getAll(
			"SELECT 
				f.field_id AS value, 
				f.name    AS output
			  FROM
		  		field_info f, field_assignment a
		 	  WHERE
		    	a.league_id = ?
			  AND a.field_id = f.field_id",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league_fields)) {
			$this->error_text .= gettext("There may be no fields assigned to this league");
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
				s.home_score, 
				s.away_score,
				CONCAT(YEAR(s.date_played),DAYOFYEAR(s.date_played)) as week_id,
				s.home_spirit, 
				s.away_spirit,
				s.round,
				UNIX_TIMESTAMP(s.date_played) as timestamp
			  FROM
			  	schedule s
			  WHERE 
				s.league_id = ? 
			  ORDER BY s.date_played",
			array($id), DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($sched_rows)) {
			$this->error_text .= gettext("The league [$id] may not exist");
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
			$game['field_name'] = $DB->getOne("SELECT name FROM field_info WHERE field_id = ?", array($game['field_id']));

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
