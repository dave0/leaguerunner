<?php

/*
 * Handlers for dealing with fields
 */
register_page_handler('field_create', 'FieldCreate');
register_page_handler('field_edit', 'FieldEdit');
register_page_handler('field_view', 'FieldView');
register_page_handler('field_assign', 'FieldAssign');
register_page_handler('field_unassign', 'FieldUnassign');

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
			'require_var:site_id',
			'admin_sufficient',
			'deny'
		);
		return true;
	}
	
	function generate_form () 
	{
		global $DB;

		$field['site_id'] = var_from_getorpost('site_id');
		if(! validate_number($field['site_id']) ) {
			$this->error_text .= "You cannot add a field to an invalid site.";
			return false;
		}

		$field['site_name'] = $DB->getOne("SELECT name FROM site where site_id = ?", array($field['site_id']));
		if($this->is_database_error($field['site_name'])) {
			$this->error_text .= "You cannot add a field to an invalid site.";
			return false;
		}

		$field['availability'] = array();

		$this->tmpl->assign("field", $field);

		return true;
	}
	
	function perform ()
	{
		global $DB, $session;
		
		if(! $this->validate_data()) {
			$this->error_text .= "<br>Please use your back button to return to the form, fix these errors, and try again";
			return false;
		}

		$field = var_from_getorpost("field");
		
		$res = $DB->query("INSERT into field (site_id,num) VALUES (?,?)", array($field['site_id'],$field['num']));
		if($this->is_database_error($res)) {
			return false;
		}
	
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from field");
		if($this->is_database_error($id)) {
			return false;
		}
		
		$this->_id = $id;
		
		return parent::perform();
	}

}

/**
 * Field edit handler
 */
class FieldEdit extends Handler
{

	var $_id;

	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'deny'
		);
		return true;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		$this->_id = var_from_getorpost('id');
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

		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		return $rc;
	}

	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=field_view&id=". $this->_id);
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB;

		$field = $DB->getRow(
			"SELECT 
				f.field_id, f.site_id, f.num, f.status, f.availability, s.name as site_name, s.code as site_code
			 FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
			 WHERE f.field_id = ?", 
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($field)) {
			return false;
		}

		$field['availability'] = split(",", $field['availability']);
		$this->set_title("Edit Field: " . $field['site_name'] . " " . $field['num']);

		$this->tmpl->assign("field", $field);
		$this->tmpl->assign("id", $this->_id);
		return true;
	}

	function generate_confirm ()
	{
		global $DB;

		if(! $this->validate_data()) {
			$this->error_text .= "<br>Please use your back button to return to the form, fix these errors, and try again";
			return false;
		}

		$site = $DB->getRow(
			"SELECT 
				f.site_id, s.name as site_name, s.code as site_code
			 FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
			 WHERE f.field_id = ?", 
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($site)) {
			return false;
		}

		$field = var_from_getorpost('field');

		$field['availability'] = join(",", $field['availability']);
		$field['site_name'] = $site['site_name'];
		$field['site_code'] = $site['site_code'];
		$this->set_title("Edit Field: " . $field['site_name'] . " " . $field['num']);

		$this->tmpl->assign("field", $field);
		$this->tmpl->assign("id", $this->_id);
		return true;
	}

	function perform ()
	{
		global $DB;

		if(! $this->validate_data()) {
			$this->error_text .= "<br>Please use your back button to return to the form, fix these errors, and try again";
			return false;
		}
		$field = var_from_getorpost('field');

		
		
		$res = $DB->query("UPDATE field SET 
			num = ?, 
			status = ?,
			availability = ?
			WHERE field_id = ?",
			array(
				$field['num'],
				$field['status'],
				$field['availability'],
				$this->_id,
			)
		);
		
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}

	function validate_data ()
	{
		$rc = true;
		
		$field = var_from_getorpost("field");
		if( !validate_number($field['num']) ) {
			$this->error_text .= "<li>Field number cannot be left blank";
			$rc = false;
		}
		
		return $rc;
	}
}

/**
 * Field viewing handler
 */
class FieldView extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'field_edit'		=> false,
			'field_assign'		=> false,
		);
		
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'allow',
		);
		
		return true;
	}
	
	function set_permission_flags($type) 
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		}
	}
	
	function process ()
	{
		global $DB;
		
		$this->_id = var_from_getorpost('id');
		$this->set_template_file("Field/view.tmpl");

		$field = $DB->getRow(
			"SELECT 
				f.field_id, f.site_id, f.num, f.status, f.availability, s.name as site_name, s.code as site_code
			 FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
			 WHERE f.field_id = ?", 
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($field)) {
			return false;
		}

		if(!isset($field)) {
			$this->error_text = "The field [$id] does not exist";
			return false;
		}
		
		$this->set_title("View Field: " . $field['site_name'] . " " . $field['num']);

		$field['availability'] = split(",", $field['availability']);

		$this->tmpl->assign("field", $field);
		$this->tmpl->assign("id", $this->_id);

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
			array($this->_id),
			DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($rows)) {
			return false;
		}
		
		$daynum = array( 'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6);

		$assignments = array(
			array( 'day' => "Sunday", 'leagues' => array(), 'avail' => false),
			array( 'day' => "Monday", 'leagues' => array(), 'avail' => false),
			array( 'day' => "Tuesday", 'leagues' => array(), 'avail' => false),
			array( 'day' => "Wednesday", 'leagues' => array(), 'avail' => false),
			array( 'day' => "Thursday", 'leagues' => array(), 'avail' => false),
			array( 'day' => "Friday", 'leagues' => array(), 'avail' => false),
			array( 'day' => "Saturday", 'leagues' => array(), 'avail' => false),
		);

		/* Now, show only available days on booking list */
		while(list(,$day) = each($field['availability'])) {
			$assignments[$daynum[$day]]['avail'] = true;
		}
		
		while(list(,$booking) = each($rows)) {
			$num = $daynum[$booking['day']];
			$assignments[$num]['leagues'][] = $booking;
		}

		/* Argh.  Need to resort array */
		ksort($assignments);
		
		$this->tmpl->assign("field_assignments", $assignments);

		reset($this->_permissions);
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return true;
	}
}


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
			'require_var:id',
			'require_var:day',
			'admin_sufficient',
			'volunteer_sufficient',
			'deny'
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
			$this->error_text = "You must provide a valid league ID";
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
			$this->error_text = "You must provide a valid league ID";
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
			'require_var:id',
			'require_var:day',
			'require_var:league_id',
			'admin_sufficient',
			'volunteer_sufficient',
			'deny',
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
			$this->error_text = "You must provide a valid league ID";
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
			$this->error_text = "You must provide a valid league ID";
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

?>
