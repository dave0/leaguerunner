<?php
register_page_handler('team_addplayer', 'TeamAddPlayer');

/**
 * List players for addition to team
 *
 * @todo this is an evil duplication of Person/List.php
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class TeamAddPlayer extends Handler
{
	/** 
	 * Initializer for PersonList class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("Add Player");

		return true;
	}

	function has_permission ()
	{
		global $DB, $session, $id;
		
		/* Anyone with a valid session id has permission */
		if(!$session->is_valid()) {
			return false;
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide a team ID");
			return false;
		}
		if($session->attr_get('class') == 'administrator') {
			return true;
		}
		
		if($session->is_captain_of($id)) {  
			return true;
		}
		
		$this->error_text = gettext("You do not have permission to perform that operation");
		return false;
	}

	function process ()
	{
		global $DB, $id, $op;

		$this->set_template_file("Team/add_player_list.tmpl");

		$letter = var_from_getorpost("letter");
		if(!isset($letter)) {
			$letter = "A";
		}
		$letter = strtoupper($letter);

		$found = $DB->getAll(
			"SELECT 
				CONCAT(lastname,', ',firstname) AS value, 
				user_id AS id_val 
			 FROM person 
			 WHERE lastname LIKE ? ORDER BY lastname",
			array($letter . "%"), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("letter", $letter);
		$this->tmpl->assign("id", $id);
		
		$this->tmpl->assign("page_op", $op);
		$letters = $DB->getCol("select distinct UPPER(SUBSTRING(lastname,1,1)) as letter from person ORDER BY letter asc");
		if($this->is_database_error($letters)) {
			return false;
		}
		
		$this->tmpl->assign("letters", $letters);
		$this->tmpl->assign("list", $found);
		
		return true;
	}
}

?>
