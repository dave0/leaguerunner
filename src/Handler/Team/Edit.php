<?php
register_page_handler('team_edit', 'TeamEdit');

/**
 * Team edit handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class TeamEdit extends Handler
{
	/** 
	 * Initializer for TeamEdit class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->_permissions = array(
			'edit_name'			=> true,
			'edit_website'		=> true,
			'edit_shirt'		=> true,
			'edit_captain' 		=> true,
			'edit_assistant'	=> true,
			'edit_status'		=> true,
		);
		return true;
	}

	/**
	 * Check if the current session has permission to edit the team
	 *
	 * check that the session is valid (return false if not)
	 * check if the session user is the system admin  (return true)
	 * check if user is captain or assistant (return true)
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
			$this->error_text = gettext("You must provide a team ID");
			return false;
		}

		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			return true;
		}

		/* TODO: Check for team captain/assistant */
		if($session->is_captain_of($id)) { 
			return true;	
		}

		/* 
		 * TODO: 
		 * See if we're a volunteer with team edit permission
		 */

		$this->error_text = gettext("You do not have permission to perform that operation");
		return false;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		switch($step) {
			case 'confirm':
				$this->set_template_file("Team/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
			default:
				$this->set_template_file("Team/edit_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
		}
	
		if(var_from_getorpost('id')) {
			$this->set_title("Edit Team: ". $DB->getOne("SELECT name FROM team where team_id = ?", array(var_from_getorpost('id'))));
		}
		$this->tmpl->assign("page_op", var_from_getorpost('op'));
		
		/* ... and set permissions flags */
		reset($this->_permissions);
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return $rc;
	}

	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		global $id;
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=team_view;id=$id");
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB, $id;

		$row = $DB->getRow(
			"SELECT 
				t.name          AS team_name, 
				t.website       AS team_website,
				t.shirt_colour  AS shirt_colour,
				t.captain_id,
				t.assistant_id,
				t.status,
				t.established
			FROM team t WHERE t.team_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign("team_name", $row['team_name']);
		$this->tmpl->assign("id", $id);
		
		$this->tmpl->assign("captain_id", $row['captain_id']);
		$this->tmpl->assign("assistant_id", $row['assistant_id']);

		$this->tmpl->assign("team_website", $row['team_website']);
		$this->tmpl->assign("shirt_colour", $row['shirt_colour']);
		$this->tmpl->assign("status", $row['status']);
		$this->tmpl->assign("established", $row['established']);
		
		$team_roster = $DB->getAll(
			"SELECT
				p.user_id AS value,
				CONCAT(p.firstname,' ',p.lastname) AS output
			 FROM
			 	person p, teamroster t
			 WHERE
			 	p.user_id = t.player_id 
			 	AND t.team_id = ?
			 ORDER BY p.lastname",
			array($id), DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($team_roster)) {
			return false;
		}
		
		/* Pop in a --- element */
		array_unshift($team_roster, array('value' => 0, 'output' => '---'));
			
		$this->tmpl->assign("team_roster", $team_roster);

		return true;
	}

	function generate_confirm ()
	{
		global $DB, $id;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Team/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$this->tmpl->assign("team_name", var_from_getorpost('team_name'));
		$this->tmpl->assign("id", $id);
		
		$this->tmpl->assign("captain_id", var_from_getorpost('captain_id'));
		$this->tmpl->assign("assistant_id", var_from_getorpost('assistant_id'));

		$this->tmpl->assign("team_website", var_from_getorpost('team_website'));
		$this->tmpl->assign("shirt_colour", var_from_getorpost('shirt_colour'));
		$this->tmpl->assign("status", var_from_getorpost('status'));
		$this->tmpl->assign("established", var_from_getorpost('established'));
	
		$captain = $DB->getOne(
			"SELECT
				CONCAT(p.firstname,' ',p.lastname) FROM person p
			 WHERE
				p.user_id = ?",
			array(var_from_getorpost('captain_id')));
		if($this->is_database_error($captain)) {
			return false;
		}
		$this->tmpl->assign("captain_id", var_from_getorpost('captain_id'));
		$this->tmpl->assign("captain_name", $captain);
	
		if(var_from_getorpost('assistant_id') != 0) {
			$assistant = $DB->getOne(
				"SELECT
					CONCAT(p.firstname,' ',p.lastname) FROM person p
				 WHERE
					p.user_id = ?",
				array(var_from_getorpost('assistant_id')));
			if($this->is_database_error($assistant)) {
				return false;
			}
			$this->tmpl->assign("assistant_id", var_from_getorpost('assistant_id'));
			$this->tmpl->assign("assistant_name", $assistant);
		} else {
			$assistant = gettext("none");	
		}
		$this->tmpl->assign("assistant_id", var_from_getorpost('assistant_id'));
		$this->tmpl->assign("assistant_name", $assistant);

		return true;
	}

	function perform ()
	{
		global $DB, $id;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Team/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}
		
		$sql = "UPDATE team SET
			name = ?,
			website = ?,
			shirt_colour = ?,
			status = ?,
			captain_id = ?,
			assistant_id = ?
			WHERE team_id = ?
		";

		$sth = $DB->prepare($sql);
		
		$res = $DB->execute($sth, 
			array(
				var_from_getorpost('team_name'),
				var_from_getorpost('team_website'),
				var_from_getorpost('shirt_colour'),
				var_from_getorpost('status'),
				var_from_getorpost('captain_id'),
				(var_from_getorpost('assistant_id') == 0) ? null : var_from_getorpost('assistant_id'),
				$id,
			)
		);
		
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}

	function validate_data ()
	{
		$err = true;
		
		$team_name = trim(var_from_getorpost("team_name"));
		if(0 == strlen($team_name)) {
			$this->error_text .= gettext("Team name cannot be left blank") . "<br>";
			$err = false;
		}
		
		$shirt_colour = trim(var_from_getorpost("shirt_colour"));
		if(0 == strlen($shirt_colour)) {
			$this->error_text .= gettext("Shirt colour cannot be left blank") . "<br>";
			$err = false;
		}

		$captain_id = trim(var_from_getorpost("captain_id"));
		if(is_null($captain_id) || $captain_id == 0) {
			$this->error_text .= gettext("A captain must be selected") . "<br>";
			$err = false;
		}
		
		return $err;
	}

	function map_callback($item)
	{
		return array("output" => $item, "value" => $item);
	}
}

?>
