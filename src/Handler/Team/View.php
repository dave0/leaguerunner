<?php
register_page_handler('team_view', 'TeamView');

/**
 * Team viewing handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class TeamView extends Handler
{
	/** 
	 * Initializer for PlayerView class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->_permissions = array(
			'edit_team'	=> false,
		);

		return true;
	}

	/**
	 * Check if the current session has permission to view this player.
	 *
	 * check that the session is valid (return false if not)
	 * check if the session user is the target player (return true)
	 * check if the session user is the system admin  (return true)
	 * Now, check permissions of session to view this user
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
			 
		if( $session->is_captain_of($id) ) {
			$this->_permissions['edit_team'] = true;
		}
		
		/* 
		 * TODO: 
		 * See if we're looking at a league coordinator.
		 */

		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->set_template_file("Team/view.tmpl");
		
		$row = $DB->getRow("
			SELECT 
				t.team_id, 
				t.name AS team_name, 
				t.website AS team_website, 
				t.status AS team_status, 
				l.name AS league_name, 
				l.tier AS league_tier, 
				l.league_id,
				t.shirt_colour
			FROM 
				team t, 
				league l,
				leagueteams s 
			WHERE 
				s.team_id = t.team_id 
				AND l.league_id = s.league_id 
				AND t.team_id = ?
		", array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			$this->error_text = gettext("The team [$id] may not exist");
			return false;
		}

		if(!isset($row)) {
			$this->error_text = gettext("The team [$id] does not exist");
			return false;
		}

		$this->set_title("View Team: " . $row['team_name']);
		$this->tmpl->assign("team_name", $row['team_name']);
		$this->tmpl->assign("team_id", $id);
		if( !strstr($row['team_website'], "http://") && (strlen($row['team_website']) > 0 ) ) {
			$row['team_website'] = "http://" . $row['team_website'];
		}
		$this->tmpl->assign("team_website", $row['team_website']);
		$this->tmpl->assign("team_status", $row['team_status']);
		$this->tmpl->assign("shirt_colour", $row['shirt_colour']);
		
		$this->tmpl->assign("league_name", $row['league_name']);
		$this->tmpl->assign("league_id", $row['league_id']);
		$this->tmpl->assign("league_tier", $row['league_tier']);

		/* and, grab roster */
		$rows = $DB->getAll("
			SELECT 
				p.user_id as id,
				CONCAT(p.firstname, ' ', p.lastname) as fullname,
				p.gender,
				p.skill_level,
				r.status
			FROM
				person p,
				teamroster r
			WHERE
				p.user_id = r.player_id
				AND r.team_id = ?
			ORDER BY r.status, p.gender, p.lastname",
			array($id),
			DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($rows)) {
			return false;
		}
		
		$this->tmpl->assign("roster", $rows);

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
