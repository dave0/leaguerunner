<?php
register_page_handler('team_create', 'TeamCreate');

/**
 * Team create handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class TeamCreate extends TeamEdit
{
	function initialize ()
	{
		$this->set_title("Create New Team");
		$this->_permissions = array(
			'edit_name'			=> true,
			'edit_website'		=> true,
			'edit_shirt'		=> true,
			'edit_captain' 		=> false,
			'edit_assistant'	=> false,
			'edit_status'		=> true,
		);

		return true;
	}

	function has_permission ()
	{
		global $session;

		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}
		
		/* Anyone with a session can create a new team */
		return true;
	}

	/*
	 * Overridden, as we have no info to put in that form.
	 */
	function generate_form () 
	{
		return true;
	}

	function perform ()
	{
		global $DB, $id, $session;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Team/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$team_name = trim(var_from_getorpost("team_name"));
	
		$res = $DB->query("INSERT into team (name,established) VALUES (?, NOW())", array($team_name));
		if($this->is_database_error($res)) {
			return false;
		}
		
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from team");
		if($this->is_database_error($id)) {
			return false;
		}

		$res = $DB->query("INSERT INTO leagueteams (league_id, team_id, status) VALUES(1, ?, 'requested')", array($id));
		if($this->is_database_error($res)) {
			return false;
		}
		
		$res = $DB->query("INSERT INTO teamroster (team_id, player_id, status, date_joined) VALUES(?, ?, 'captain', NOW())", array($id, $session->data['user_id']));
		if($this->is_database_error($res)) {
			return false;
		}
		
		return parent::perform();
	}
}

?>
