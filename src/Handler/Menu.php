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
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'allow'
		);
		return true;
	}
	
	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} 
	}

	/**
	 * Generate the menu
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
				coordinator_id = ? OR (alternate_id <> 1 AND alternate_id = ?)
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
		
		if($session->is_admin()) {
			/* Fetch count of pending new users */
			$new_users = $DB->getOne("SELECT COUNT(*) FROM person WHERE class = 'new'");
			if($this->is_database_error($new_users)) {
				return false;
			}

			$this->tmpl->assign("new_user_count", $new_users);
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
