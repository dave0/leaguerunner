<?php
register_page_handler('league_create', 'LeagueCreate');

/**
 * Create handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class LeagueCreate extends LeagueEdit
{
	function initialize ()
	{
		if(parent::initialize() == false) {
			return false;
		}
		$this->set_title("Create New League");
		return true;
	}
	
	/**
	 * Check if the current session has permission to create a league
	 *
	 * check that the session is valid (return false if not)
	 * check if the session user is the system admin  (return true)
	 * else return false.
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
		
		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}

		$this->error_text = gettext("You do not have permission to perform that operation");
		return false;
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
		global $DB, $id, $session;
		$league_name = trim(var_from_getorpost("league_name"));
		
		$res = $DB->query("INSERT into league (name,coordinator_id) VALUES (?,?)", array($league_name, $session->data['user_id']));
		if($this->is_database_error($res)) {
			return false;
		}
		
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from league");
		if($this->is_database_error($id)) {
			return false;
		}
		
		return parent::perform();
	}

	function validate_data ()
	{
		global $_POST, $session;
		$err = true;
		
		$league_name = trim(var_from_getorpost("league_name"));
		if(0 == strlen($league_name)) {
			$this->error_text .= gettext("League name cannot be left blank") . "<br>";
			$err = false;
		}

		return $err;
	}

}

?>
