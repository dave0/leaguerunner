<?php
register_page_handler('field_unassign', 'FieldUnassign');

/**
 * Field assignments
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class FieldUnassign extends Handler
{
	function initialize ()
	{
		$this->set_title("Unassign Field");
		return true;
	}
	
	function has_permission ()
	{
		global $DB, $session, $id, $day, $league_id;

		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide a field ID");
			return false;
		}
		
		$league_id = var_from_getorpost('league_id');
		if(is_null($league_id)) {
			$this->error_text = gettext("You must provide a league ID");
			return false;
		}
		
		$day = var_from_getorpost('day');
		if(is_null($day)) {
			$this->error_text = gettext("You must provide a day of the week");
			return false;
		}
		
		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			return true;
		}

		$this->error_text = gettext("You do not have permission to perform that operation");
		return false;
	}
	
	function process ()
	{
		global $DB, $id;

		$step = var_from_getorpost('step');
		switch($step) {
			default:
			case 'confirm':
				$this->set_template_file("Field/unassign_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
		}
		
		$field_name = $DB->getOne("SELECT name FROM field_info where field_id = ?", array($id));
		if($this->is_database_error($field_name)) {
			return false;
		}
		$this->set_title("Unassign Field: $field_name");
		$this->tmpl->assign("field_name", $field_name);
	
		$this->tmpl->assign("page_op", var_from_getorpost('op'));
		return $rc;
	}

	function display()
	{
		global $id;
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=field_view&id=$id");
		}
		return parent::display();
	}

	function generate_confirm ()
	{
		global $DB, $id, $day, $league_id;
		
		$league_info = $DB->getRow("SELECT name, tier FROM league WHERE allow_schedule = 'Y' AND day = ? AND league_id = ?", array($day, $league_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league_info)) {
			return false;
		}
		if(sizeof($league_info) < 1) {
			$this->error_text = gettext("You must provide a valid league ID");
			return false;
		}

		$league_name = $league_info['name'];
		if($league_info['tier'] > 0) {
			$league_name .= " Tier " . $league_info['tier'];
		}

		$this->tmpl->assign("league_id", $league_id);
		$this->tmpl->assign("league_name", $league_name);
		$this->tmpl->assign("id", $id);
		$this->tmpl->assign("day", $day);
			
		return true;	
	}
	
	function perform ()
	{
		global $DB, $id, $day, $league_id;
		
		$league_info = $DB->getRow("SELECT name, tier FROM league WHERE allow_schedule = 'Y' AND day = ? AND league_id = ?", array($day, $league_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league_info)) {
			return false;
		}
		if(sizeof($league_info) < 1) {
			$this->error_text = gettext("You must provide a valid league ID");
			return false;
		}

		/* Looks like it was valid, so proceed */
		$res = $DB->query("DELETE FROM field_assignment WHERE league_id = ? AND field_id = ? AND day = ?", array($league_id, $id, $day));
		if($this->is_database_error($res)) {
			return false;
		}
		return true;	
	}

}
