<?php
register_page_handler('field_create', 'FieldCreate');

/**
 * Field create handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class FieldCreate extends FieldEdit
{
	function initialize ()
	{
		$this->set_title("Create New Field");
		$this->_permissions = array(
			'edit_name'			=> true,
			'edit_website' 		=> true,
		);
		return true;
	}
	
	function has_permission ()
	{
		global $DB, $session, $id;

		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}
		
		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			return true;
		}

		/* 
		 * TODO: 
		 * See if we're a volunteer with field edit permission
		 */

		$this->error_text = gettext("You do not have permission to perform that operation");
		return false;
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
			$this->set_template_file("Field/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}
		
		$st = $DB->prepare("INSERT into field_info (name) VALUES ('new field')");
		$res = $DB->execute($st, array($session->data['user_id']));
		if($this->is_database_error($res)) {
			return false;
		}
		
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from field_info");
		if($this->is_database_error($id)) {
			return false;
		}
		
		return parent::perform();
	}

}
