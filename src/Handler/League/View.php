<?php
register_page_handler('league_view', 'LeagueView');

/**
 * League viewing handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class LeagueView extends Handler
{
	/** 
	 * Initializer for LeagueView class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->_permissions = array(
			"administer_league" => false,
		);

		return true;
	}

	/**
	 * Check if the current session has permission to view this league.
	 *
	 * check that the session is valid (return false if not)
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

		/* TODO: set administer_league perm if:
		 * 	- coordinator or co-coord
		 */

		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->set_template_file("League/view.tmpl");
	
		$row = $DB->getRow(
			"SELECT 
				l.name,
				l.tier,
				l.ratio,
				l.season,
				l.max_teams,
				CONCAT(c.firstname,' ',c.lastname) AS coordinator_name, 
				l.coordinator_id,
				l.alternate_id as co_coordinator_id,
				l.current_round
			FROM league l, person c, person co
			WHERE c.user_id = l.coordinator_id 
				AND l.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		
		$title = "View League: " . $row['name'];
		if($row['tier'] > 0) {
			$title .= " " . $row['tier'];
		}
		$this->set_title($title);

		$this->tmpl->assign("league_id", $id);
		$this->tmpl->assign("league_name",   $row['name']);
		$this->tmpl->assign("league_tier",   $row['tier']);
		$this->tmpl->assign("league_ratio",  $row['ratio']);
		$this->tmpl->assign("league_season", $row['season']);
		$this->tmpl->assign("league_maxteams", $row['max_teams']);
		$this->tmpl->assign("league_current_round", $row['current_round']);
		$this->tmpl->assign("coordinator_name", $row['coordinator_name']);
		$this->tmpl->assign("coordinator_id", $row['coordinator_id']);

		if( $row['co_coordinator_id'] > 1 ) {
			$co_name = $DB->getOne("SELECT CONCAT(co.firstname,' ',co.lastname) FROM person co where user_id = ?", array($row['co_coordinator_id'])); 
			$this->tmpl->assign("co_coordinator_name", $co_name);
			$this->tmpl->assign("co_coordinator_id", $row['co_coordinator_id']);
		}

		/* Now, fetch teams */
		$rows = $DB->getAll(
			"SELECT 
				t.team_id AS id,
				t.name,
				t.shirt_colour,
				t.status AS team_status,
				l.status AS league_status
			 FROM
			 	team t,
				leagueteams l
			 WHERE
			 	t.team_id = l.team_id
				AND l.league_id = ?
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

?>
