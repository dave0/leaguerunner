<?php
register_page_handler('menu','Menu');
/**
 * Handler for the menu operation
 *
 * @package Leaguerunner
 * @access public
 * @author Dave O'Neill <dmo@acm.org>
 * @copyright GPL
 */
class Menu extends Handler
{
	/**
	 * Initializes the template for this handler. 
	 */
	function initialize ()
	{
		$this->set_title("Main Menu");

		$this->_permissions = array(
			"league_admin"  => false,
			"league_create" => false,
			"user_admin"    => false,
			"field_admin"    => false,
		);
		
		return true;
	}

	/**
	 * Check if the logged-in user has permission to view the menu
	 *
	 * This checks whether or not the user has authorization to view the
	 * menu.  At present, everyone with a valid session can view the menu.
	 * 
	 * @access public
	 * @return boolean True if current session is valid, false otherwise.
	 */
	function has_permission()
	{	
		global $session;
		
		/* Anyone with a valid session id has permission */
		if(!$session->is_valid()) {
			$this->error_text = gettext("Your session has expired.  Please log in again");
			return false;
		}

		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
		}
		reset($this->_permissions);
		
		return true;
	}

	/**
	 * Generate the menu
	 *
	 * This generates the menu.  Each menu category is generated with
	 * its own function, which checks if the current user session 
	 * has permission for those options.  
	 *
	 * @access public
	 * @return boolean success or failure.
	 */
	function process ()
	{
		global $session, $DB;

		$id =  $session->attr_get("user_id");
		
		$this->set_template_file("Menu.tmpl");
		
		$this->tmpl->assign("user_name", join(" ",array(
			$session->attr_get("firstname"),
			$session->attr_get("lastname")
			)));
		$this->tmpl->assign("user_id", $id);

		/* Fetch team info */
		$teams = get_teams_for_user($id);
		if($this->is_database_error($teams)) {
			return false;
		}
		$this->tmpl->assign("teams", $teams);

		/* Fetch leagues coordinated */
		$leagues = $DB->getAll("
			SELECT 
				league_id AS id,
				name,
				allow_schedule,
				tier
		  	FROM 
				league 
		    WHERE 
				coordinator_id = ? OR (alternate_id = ? AND NOT alternate_id = 1)
		  	ORDER BY name, tier",
			array($id,$id), 
			DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($leagues)) {
			return false;
		}
			
		if(count($leagues) > 0) {
			$this->_permissions['league_admin'] = true;
			$this->tmpl->assign("leagues", $leagues);
		}
		
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
