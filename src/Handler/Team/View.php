<?php
register_page_handler('team_view', 'TeamView');

/**
 * Team viewing handler
 *
 * @package Leaguerunner
 * @version $Id $
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class TeamView extends Handler
{

	/* Permissions bits for various items of interest */
	var $_permissions;
	
	/** 
	 * Initializer for PlayerView class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "View Team";
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
			while(list($key,) = each($this->_permissions)) {
				$this->_permissions[$key] = true;
			}
			return true;
		}

		$sth = $DB->prepare( "SELECT captain_id, assistant_id FROM team where team_id = ?");
		$res = $DB->execute($sth,$id);
		if(DB::isError($res)) {
		 	/* TODO: Handle database error */
			return false;
		}
		$row = $res->fetchRow(DB_FETCHMODE_ASSOC, 0);
		$res->free();
		if( ($session->attr_get('user_id') == $res['captain_id'])
			|| ($session->attr_get('user_id') == $res['assistant_id'])) {
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
		
		$sth = $DB->prepare("
			SELECT 
				t.team_id, 
				t.name AS team_name, 
				t.website AS team_website, 
				t.captain_id, 
				CONCAT(p.firstname,' ',p.lastname) AS captain_name, 
				t.assistant_id, 
				t.status AS team_status, 
				l.name AS league_name, 
				l.tier AS league_tier, 
				l.league_id,
				t.shirt_colour
			FROM 
				team t, 
				person p, 
				league l,
				leagueteams s 
			WHERE 
				p.user_id = t.captain_id 
				AND s.team_id = t.team_id 
				AND l.league_id = s.league_id 
				AND t.team_id = ?
		");
		$res = $DB->execute($sth,$id);
		if(DB::isError($res)) {
		 	/* TODO: Handle database error */
			$this->error_text = gettext("Database error");
			return false;
		}
		$row = $res->fetchRow(DB_FETCHMODE_ASSOC, 0);
		$res->free();

		if(!isset($row)) {
			$this->error_text = gettext("The team [$id] does not exist");
			return false;
		}

		$this->tmpl->assign("team_name", $row['team_name']);
		$this->tmpl->assign("team_id", $id);
		$this->tmpl->assign("team_website", $row['team_website']);
		$this->tmpl->assign("team_status", $row['team_status']);
		$this->tmpl->assign("shirt_colour", $row['shirt_colour']);
		
		$this->tmpl->assign("captain_id", $row['captain_id']);
		$this->tmpl->assign("captain_name", $row['captain_name']);
		$this->tmpl->assign("assistant_id", $row['assistant_id']);
		
		$this->tmpl->assign("league_name", $row['league_name']);
		$this->tmpl->assign("league_id", $row['league_id']);
		$this->tmpl->assign("league_tier", $row['league_tier']);

		return true;
	}
}

?>
