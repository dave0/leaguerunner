<?php

/*
 * Handlers for dealing with fields
 */
register_page_handler('field_assign', 'FieldAssign');
register_page_handler('field_create', 'FieldCreate');
register_page_handler('field_edit', 'FieldEdit');
register_page_handler('field_list', 'FieldList');
register_page_handler('field_view', 'FieldView');
register_page_handler('field_unassign', 'FieldUnassign');

/**
 * Assign a field to a league
 * This code is messy and should be combined with the unassign code.
 */
class FieldAssign extends Handler
{
	function initialize ()
	{
		$this->set_title("Assign Field");
		$this->_required_perms = array(
			'require_valid_session',
			'require_admin',
			'require_var:id',
			'require_var:day',
			'allow'
		);
		return true;
	}
	
	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		switch($step) {
			case 'confirm':
				$this->set_template_file("Field/assign_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
			default:
				$this->set_template_file("Field/assign_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
		}
		
		$field_name = $DB->getOne("SELECT name FROM field_info where field_id = ?", array(var_from_getorpost('id')));
		if($this->is_database_error($field_name)) {
			return false;
		}
		$this->set_title("Assign Field: $field_name");
		$this->tmpl->assign("field_name", $field_name);
	
		$this->tmpl->assign("page_op", var_from_getorpost('op'));
		return $rc;
	}

	function display()
	{
		$id = var_from_getorpost('id');
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=field_view&id=$id");
		}
		return parent::display();
	}

	function generate_form()
	{
		global $DB;
		
		$id = var_from_getorpost('id');
		$day = var_from_getorpost('day');
		$leagues = $DB->getAll("SELECT league_id, name, tier FROM league WHERE allow_schedule = 'Y' AND (FIND_IN_SET(?,day) > 0)", array($day), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($leagues)) {
			return false;
		}

		$for_form = array();
		for($i= 0; $i < count($leagues); $i++) {
			$name = $leagues[$i]['name'];
			if($leagues[$i]['tier'] > 0) {
				$name .= " Tier " . $leagues[$i]['tier'];
			}
			$for_form[] = array(
				'value' => $leagues[$i]['league_id'],
				'output' => $name
			);
		}

		$this->tmpl->assign("leagues", $for_form);
		$this->tmpl->assign("id", $id);
		$this->tmpl->assign("day", $day);
			
		return true;	
	}

	function generate_confirm ()
	{
		global $DB;
		
		$id = var_from_getorpost('id');
		$day = var_from_getorpost('day');
		
		$league_id = var_from_getorpost('league_id');
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
		global $DB;
		
		$id = var_from_getorpost('id');
		$day = var_from_getorpost('day');
		
		$league_id = var_from_getorpost('league_id');
		$league_info = $DB->getRow("SELECT name, tier FROM league WHERE allow_schedule = 'Y' AND day = ? AND league_id = ?", array($day, $league_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league_info)) {
			return false;
		}
		if(sizeof($league_info) < 1) {
			$this->error_text = gettext("You must provide a valid league ID");
			return false;
		}

		/* Looks like it was valid, so proceed */
		$res = $DB->query("INSERT INTO field_assignment VALUES(?,?,?)", array($league_id, $id, $day));
		if($this->is_database_error($res)) {
			return false;
		}
		return true;	
	}
}

/**
 * Field create handler
 */
class FieldCreate extends FieldEdit
{
	function initialize ()
	{
		$this->set_title("Create New Field");
		$this->_required_perms = array(
			'require_valid_session',
			'require_admin',
			'allow'
		);
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
		global $DB, $session;
		
		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Field/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}
		
		$field_name = trim(var_from_getorpost("field_name"));
		$res = $DB->query("INSERT into field_info (name) VALUES (?)", array($field_name));
		if($this->is_database_error($res)) {
			return false;
		}
	
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from field_info");
		if($this->is_database_error($id)) {
			return false;
		}
		
		/* Override GET and POST value, so edit() will work */
		set_getandpost('id',$id);	
		
		return parent::perform();
	}

}

/**
 * Field edit handler
 */
class FieldEdit extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_admin',
			'require_var:id',
			'allow'
		);
		return true;
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

		return $rc;
	}

	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		if($step == 'perform') {
			return $this->output_redirect("op=field_view&id=$id");
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB;
		$id = var_from_getorpost('id');

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
		global $DB;

		$id = var_from_getorpost('id');

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
		global $DB;
		$id = var_from_getorpost('id');

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Field/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}
		
		$res = $DB->query("UPDATE field_info SET name = ?, url = ? WHERE field_id = ?",
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
}

/**
 * Field list handler
 */
class FieldList extends Handler
{
	function initialize ()
	{
		$this->set_title("List Fields");
		$this->_required_perms = array(
			'allow'		/* Allow everyone */
		);

		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->set_template_file("common/generic_list.tmpl");

		$found = $DB->getAll(
			"SELECT 
				name AS value, 
				field_id AS id_val 
			 FROM field_info",
			array(), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("available_ops", array(
			array(
				'description' => 'view',
				'action' => 'field_view'
			),
		));
		$this->tmpl->assign("page_op", "field_list");
		$this->tmpl->assign("list", $found);
		
		return true;
	}
}

/**
 * Un-assign a field
 * This code is messy and should be combined with the assign code.
 */
class FieldUnassign extends Handler
{
	function initialize ()
	{
		$this->set_title("Unassign Field");
		$this->_required_perms = array(
			'require_valid_session',
			'require_admin',
			'require_var:id',
			'require_var:day',
			'require_var:league_id',
			'allow',
		);
		return true;
	}
	
	function process ()
	{
		global $DB;

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
		
		$id = var_from_getorpost('id');
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
		$id = var_from_getorpost('id');
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=field_view&id=$id");
		}
		return parent::display();
	}

	function generate_confirm ()
	{
		global $DB;
		
		$id = var_from_getorpost('id');
		$league_id = var_from_getorpost('league_id');
		$day = var_from_getorpost('day');
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
		global $DB;
		
		$id = var_from_getorpost('id');
		$league_id = var_from_getorpost('league_id');
		$day = var_from_getorpost('day');
		
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

/**
 * Field viewing handler
 */
class FieldView extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'allow',
		);
		return true;
	}

	function process ()
	{
		global $session, $DB;

		$id = var_from_getorpost('id');

		$this->set_template_file("Field/view.tmpl");
		
		$row = $DB->getRow("SELECT name, url FROM field_info WHERE field_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		if(!isset($row)) {
			$this->error_text = gettext("The field [$id] does not exist");
			return false;
		}
	
		$this->set_title("View Field: " . $row['name']);
		$this->tmpl->assign("field_name", $row['name']);
		$this->tmpl->assign("field_id", $id);
		$this->tmpl->assign("field_website", $row['url']);
	
		/* and, grab bookings */
		$rows = $DB->getAll("
			SELECT 
				a.league_id,
				IF(l.tier,CONCAT(l.season,' ',l.name, ' Tier ',l.tier),CONCAT(l.season,' ',l.name)) AS name,
				a.day
		  	FROM 
				field_assignment a,
				league l
		  	WHERE 
				a.field_id = ?
				AND a.league_id = l.league_id",
			array($id),
			DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($rows)) {
			return false;
		}
		$daynum = array( 'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6);
		$assignments = array(
			array( 'day' => "Sunday", 'leagues' => array()),
			array( 'day' => "Monday", 'leagues' => array()),
			array( 'day' => "Tuesday", 'leagues' => array()),
			array( 'day' => "Wednesday", 'leagues' => array()),
			array( 'day' => "Thursday", 'leagues' => array()),
			array( 'day' => "Friday", 'leagues' => array()),
			array( 'day' => "Saturday", 'leagues' => array()),
		);
		
		while(list(,$booking) = each($rows)) {
			$num = $daynum[$booking['day']];
			$assignments[$num]['leagues'][] = $booking;
		}

		/* Argh.  Need to resort array */
		ksort($assignments);
		
		$this->tmpl->assign("field_assignments", $assignments);

		/* ... and set permissions flags */
		if($session->is_admin()) {
			$this->tmpl->assign("perm_field_edit", true);
			$this->tmpl->assign("perm_field_assign", true);
		}

		return true;
	}
}

?>
