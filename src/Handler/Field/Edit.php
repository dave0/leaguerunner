<?php
register_page_handler('field_edit', 'FieldEdit');

/**
 * Field edit handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class FieldEdit extends Handler
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->_permissions = array(
			'edit_name'			=> true,
			'edit_website' 		=> true,
		);
		return true;
	}

	/**
	 * Check if the current session has permission to edit the field
	 *
	 * check that the session is valid (return false if not)
	 * check if the session user is the system admin  (return true)
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

		/* 
		 * TODO: 
		 * See if we're a volunteer with field edit permission
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
				$this->set_template_file("Field/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
			default:
				$this->set_template_file("Field/edit_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
		}
	
		if(var_from_getorpost('id')) {
			$this->set_title("Edit Field: " . $DB->getOne("SELECT name FROM field_info where field_id = ?", array(var_from_getorpost('id'))));
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
			return $this->output_redirect("op=field_view;id=$id");
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB, $id;

		$row = $DB->getRow(
			"SELECT 
				f.name          AS field_name, 
				f.url           AS field_website
			FROM field_info f  WHERE f.field_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign("field_name", $row['field_name']);
		$this->tmpl->assign("id", $id);
		
		$this->tmpl->assign("field_website", $row['field_website']);
		
		return true;
	}

	function generate_confirm ()
	{
		global $DB, $id;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Field/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$this->tmpl->assign("field_name", var_from_getorpost('field_name'));
		$this->tmpl->assign("id", $id);
		$this->tmpl->assign("field_website", var_from_getorpost('field_website'));

		return true;
	}

	function perform ()
	{
		global $DB, $id;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Field/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}
		
		$sql = "UPDATE field_info SET
			name = ?,
			url = ?
			WHERE field_id = ?
		";

		$sth = $DB->prepare($sql);
		
		$res = $DB->execute($sth, 
			array(
				var_from_getorpost('field_name'),
				var_from_getorpost('field_website'),
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
		
		$field_name = trim(var_from_getorpost("field_name"));
		if(0 == strlen($field_name)) {
			$this->error_text .= gettext("Field name cannot be left blank") . "<br>";
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
