<?php
register_page_handler('team_schedule_view', 'TeamScheduleView');

/**
 * Team schedule viewing handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class TeamScheduleView extends Handler
{
	/** 
	 * Initializer for TeamScheduleView class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->_permissions = array(
			'submit_score'	=> false,
		);

		return true;
	}

	/**
	 * Check if the current session has permission to view the schedule
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
			$this->error_text = gettext("You must provide an ID to view");
			return false;
		}

		/* Administrator can do anything */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}
		
		if($session->is_captain_of($id)) {
			$this->_permissions['submit_score'] = true;
		}
		
		/**
		 * TODO: 
		 * See if we're looking at a league coordinator, as they can submit
		 * scores, too.
		 */

		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->set_template_file("Team/schedule_view.tmpl");
		
		$row = $DB->getRow("
			SELECT
				l.name AS league_name, 
				l.tier,
				l.league_id, 
				t.name AS team_name
		  	FROM
		  		league l, leagueteams lt, team t
			WHERE
		  		l.league_id = lt.league_id 
				AND t.team_id = lt.team_id 
				AND lt.team_id = ? ", 
		array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		if(!isset($row)) {
			$this->error_text = gettext("The team [$id] does not exist");
			return false;
		}

		$this->set_title("View Schedule for " . $row['team_name']);

		$this->tmpl->assign("team_name", $row['team_name']);
		$this->tmpl->assign("team_id", $id);
		$this->tmpl->assign("league_name", $row['league_name']);
		$this->tmpl->assign("league_tier", $row['tier']);
		$this->tmpl->assign("league_id", $row['league_id']);

		/* Grab schedule info */
		/* TODO: this is evil. multi-table joins suck */
		$rows = $DB->getAll(
			"SELECT 
				s.game_id, 
				DATE_FORMAT(s.date_played, \"%a %b %d %Y\") as game_date, 
				TIME_FORMAT(s.date_played,\"%l:%i %p\") as game_time,
				s.home_team AS home_id, 
				s.away_team AS away_id, 
				s.field_id, 
				s.home_score, 
				s.away_score,
				h.name AS home_name,
				a.name AS away_name,
				f.name AS field_name,
				s.defaulted
	  		FROM
	  			schedule s, team h, team a, field_info f
			WHERE 
				h.team_id = s.home_team 
				AND a.team_id = s.away_team
				AND f.field_id = s.field_id
				AND (s.home_team = ? OR s.away_team = ?) 
			ORDER BY s.date_played",
		array($id, $id),
		DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($rows)) {
			return false;
		}

		$games = array();
		$schedule = array();
		while(list(,$this_row) = each($rows)) {
			/* Grab game info.  We will assume that we're away, and correct
			 * for it if we're not
			 */
			$week = array(
				'id' => $this_row['game_id'],
				'date' => $this_row['game_date'],
				'time' => $this_row['game_time'],
				'opponent_id' => $this_row['away_id'],
				'opponent_name' => $this_row['away_name'],
				'field_id' => $this_row['field_id'],
				'field_name' => $this_row['field_name'],
				'home_away' => 'home'
			);
			/* now, fix it */
			if($this_row['away_id'] == $id) {
				$week['opponent_id'] = $this_row['home_id'];
				$week['opponent_name'] = $this_row['home_name'];
			}

			/* Now, look for a score entry */
			if(isset($this_row['home_score']) && isset($this_row['away_score']) ) {
				/* Already entered */
				$week['score_type'] = 'final';
				if($week['home_away'] == 'home') {
					$week['score_us'] = $this_row['home_score'];
					$week['score_them'] = $this_row['away_score'];
				} else {
					$week['score_us'] = $this_row['away_score'];
					$week['score_them'] = $this_row['home_score'];
				}
			} else {
				/* Not finalized yet */
				$score = $DB->getRow(
					"SELECT
						score_for,
						score_against
					FROM
						score_entry
					WHERE
						game_id = ?
						AND team_id = ?",
				array($this_row['game_id'], $id),
				DB_FETCHMODE_ASSOC);
				if(! $this->is_database_error($score) ) {
					$week['score_type'] = 'entered';
					$week['score_us'] = $score['score_for'];
					$week['score_them'] = $score['score_against'];
				}
				
			}
			$schedule[] = $week;
		}
		
		$this->tmpl->assign("schedule", $schedule);

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
