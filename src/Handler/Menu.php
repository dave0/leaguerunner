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
			"user_list"    => false,
			"user_admin"    => false,
			"field_admin"    => false,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'volunteer_sufficient',
			'allow'
		);
		return true;
	}
	
	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} 

		if($type == 'volunteer') {
			$this->_permissions['user_list'] = true;
		}
	}

	function menu_title($title)
	{
		return "<tr><td class='menu_title'>$title</td</tr>";
	}
	function menu_item($value)
	{
		return "<tr><td class='menu_item'>$value</td</tr>";
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
		$output = "";

		$id =  $session->attr_get("user_id");
		
		/* General Account Info */
		$output .= $this->menu_title("My Account");
		$output .= $this->menu_item(
			l("View/Edit My Account", "op=person_view&id=$id"));
		$output .= $this->menu_item(
			l("Change My Password", "op=person_changepassword&id=$id"));
		$output .= $this->menu_item(
			l("View Player Waiver", "op=system_viewfile&file=player_waiver"));
		$output .= $this->menu_item(
			l("View Dog Waiver", "op=system_viewfile&file=dog_waiver"));
		$output .= $this->menu_item( l("Log Out", "op=logout"));

		$output .= $this->menu_title("Teams");
		$teams = get_teams_for_user($id);
		if($this->is_database_error($teams)) {
			return false;
		}
		if(count($teams) > 0) {
			$data = "<table border='0' cellpadding='3' cellspacing='0'>";
			foreach($teams as $team) {
				$data .= "<tr><td class='menu_item'>" . $team['name'] . "</td>";
				$data .= "<td class='menu_item'>(" . $team['position'] . ")</td>";
				$data .= "<td class='menu_item'>" . l("info", "op=team_view&id=" . $team['id']) . "</td>";
				$data .= "<td class='menu_item'>" . l("scores and schedules", "op=team_schedule_view&id=" . $team['id']) . "</td>";
				$data .= "<td class='menu_item'>" . l("standings", "op=team_standings&id=" . $team['id']) . "</td>";
				$data .= "</tr>";
			}
			$data .= "</table>";
			$output .= $this->menu_item( $data );
		}
		
		$output .= $this->menu_item( l("List Other Teams", "op=team_list"));
		$output .= $this->menu_item( l("Create New Team", "op=team_create"));
	

		$output .= $this->menu_title("Leagues");
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
			$data = "<table border='0' cellpadding='3' cellspacing='0'>";
			foreach($leagues as $league) {
				$name = $league['name'];
				if($league['tier']) {
					$name .= " Tier " . $league['tier'];
				}
				$data .= "<tr><td class='menu_item'>$name</td>";
				$data .= "<td class='menu_item'>" . l("view", "op=league_view&id=" . $league['id']) . "</td>";
				if($league['view_schedule'] == 'Y') {
					$data .= "<td class='menu_item'>" . l("schedule", "op=league_schedule_view&id=" . $league['id']) . "</td>";
					$data .= "<td class='menu_item'>" . l("standings", "op=league_standings&id=" . $league['id']) . "</td>";
					$data .= "<td class='menu_item'>" . l("approve scores", "op=league_verifyscores&id=" . $league['id']) . "</td>";
				} else {
					$data .= "<td colspan='3' class='menu_item'>&nbsp;</td>";
				}
				$data .= "</tr>";
			}
			$data .= "</table>";
			$output .= $this->menu_item( $data );
		}
		$output .= $this->menu_item( l("List Leagues", "op=league_list"));
		

		$output .= $this->menu_title("Fields");
		$output .= $this->menu_item(
			l("List Field Sites", "op=site_list"));
		if($this->_permissions['field_admin']) {
			$output .= $this->menu_item( l("Create New Field Site", "op=site_create"));
		}
				
		if($this->_permissions['user_list']) {
			$output .= $this->menu_title("Users");
			$output .= $this->menu_item( l("List Users", "op=person_list"));
			if($this->_permissions['user_admin']) {
				$new_users = $DB->getOne("SELECT COUNT(*) FROM person WHERE class = 'new'");
				if($this->is_database_error($new_users)) {
					return false;
				}
				$output .= $this->menu_item( l("List New Users", "op=person_listnew") . " ($new_users awaiting approval)");
				$output .= $this->menu_item( l("Create New User", "op=person_create"));
			}
		}
		
		print $this->get_header();
		print "<table>$output</table>";
		print $this->get_footer();
		
		return true;
	}
	
	function display ()
	{	
		// DELETEME: Remove this once Smarty is gone.
		return true;
	}
}
?>
