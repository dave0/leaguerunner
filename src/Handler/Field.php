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
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:site_id',
			'admin_sufficient',
			'deny'
		);
		
		$this->op = 'field_create';
		$this->section = 'field';
		$this->setLocation(array("Create New Field" => 0));
		return true;
	}

	function generateForm ( $id )
	{
		$site_id = var_from_getorpost('site_id');
		if(! validate_number($site_id) ) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}

		$result = db_query("SELECT name as site_name, code as site_code FROM site where site_id = %d", $site_id);
		if( !db_num_rows($result) ) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}
		$field = db_fetch_array($result);

		$field['availability'] = array(
			'Sunday' => false,
			'Monday' => false,
			'Tuesday' => false,
			'Wednesday' => false,
			'Thursday' => false,
			'Friday' => false,
			'Saturday' => false,
		);

		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'confirm');
		$output .= form_hidden('site_id', $site_id);

		$rows = array();

		$rows[] = array('Site Name:', $field['site_name'] . ' (' . $field['site_code'] . ')');
		$rows[] = array('Field Number:', 
			form_textfield('', 'field[num]', '', 2, 2, "Number for this field at the given site"));
			
		$rows[] = array('Field Status:', 
			form_select('', 'field[status]', '', getOptionsFromEnum('field','status'), "Is this field open for scheduling, or not?"));

		$availability = '';
		while(list($day,$isAvailable) = each($field['availability'])) {
			$availability .= form_checkbox($day,'field[availability][]', $day, $isAvailable);
		}

		$rows[] = array('Availability:',  $availability);
		$output .= "<div class='pairtable'>". table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		
		return form($output);
	}
	
	function generateConfirm ( $id )
	{
		$site_id = var_from_getorpost('site_id');
		if(! validate_number($site_id) ) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$result = db_query("SELECT name as site_name, code as site_code FROM site where site_id = %d", $site_id);
		if( !db_num_rows($result) ) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}
		$site = db_fetch_array($result);

		$field = var_from_getorpost('field');

		$field['availability'] = is_array($field['availability']) ? join(",", $field['availability']) : "";

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('site_id', $site_id);

		$rows[] = array();
		$rows[] = array('Site Name:', $site['site_name'] . ' (' . $site['site_code'] . ')');
		$rows[] = array('Field Number:', 
			form_hidden('field[num]', $field['num']) . $field['num']);
		$rows[] = array('Field Status:', 
			form_hidden('field[status]', $field['status']) . $field['status']);
		$rows[] = array('Availability:', 
			form_hidden('field[availability]', $field['availability']) . $field['availability']);
			
		$output .= "<div class='pairtable'>". table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));
		
		return form($output);
	}
	
	function perform ( $id )
	{
		global $session;
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$site_id = var_from_getorpost('site_id');
		if(! validate_number($site_id) ) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}

		$field = var_from_getorpost("field");
	
		db_query("INSERT into field (site_id,num) VALUES (%d,%d)", $site_id,$field['num']);
		if( 1 != db_affected_rows() ) {
			return false;
		}

		$result = db_query("SELECT LAST_INSERT_ID() from field");
		if(1 != db_num_rows($result)) {
			return false;
		}
		$id = db_result($result);
		
		return parent::perform( $id );
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

		$this->title = "Edit Field";
		$this->op = 'field_edit';
		$this->section = 'field';
		return true;
	}

	function process ()
	{
		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				$this->perform( &$id );
				local_redirect("op=field_view&id=$id");
				break;
			default:
				$rc = $this->generateForm($id);
		}

		return $rc;
	}

	function generateForm ( $id )
	{
	
		$result = db_query(
			"SELECT 
				f.field_id, f.site_id, f.num, f.status, f.availability, s.name as site_name, s.code as site_code
			 FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
			 WHERE f.field_id = %d",  $id);
			 
		$field = db_fetch_array($result);
		
		$days_available = strlen($field['availability']) ? split(",", $field['availability']) : array();
		$field['availability'] = array(
			'Sunday' => false,
			'Monday' => false,
			'Tuesday' => false,
			'Wednesday' => false,
			'Thursday' => false,
			'Friday' => false,
			'Saturday' => false,
		);
		while(list(,$day) = each($days_available)) {
			$field['availability'][$day] = true;
		}

		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'confirm');
		$output .= form_hidden('id', $id);

		$rows = array();
		$rows[] = array('Site Name:', $field['site_name'] . ' (' . $field['site_code'] . ')');
		$rows[] = array('Field Number:', 
			form_textfield('', 'field[num]', $field['num'], 2, 2, "Number for this field at the given site"));
			
		$rows[] = array('Field Status:', 
			form_select('', 'field[status]', $field['status'], getOptionsFromEnum('field','status'), "Is this field open for scheduling, or not?"));

		$availability = '';
		while(list($day,$isAvailable) = each($field['availability'])) {
			$availability .= form_checkbox($day,'field[availability][]', $day, $isAvailable);
		}

		$rows[] = array('Availability:',  $availability);
		$output .= "<div class='pairtable'>". table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		$this->setLocation(array(
			$field['site_name'] . " " . $field['num'] => "op=field_view&id=$id",
			$this->title => 0
		));
		
		return form($output);
	}

	function generateConfirm ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$result = db_query(
			"SELECT 
				f.site_id, s.name as site_name, s.code as site_code
			 FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
			 WHERE f.field_id = %d", $id);
		$site = db_fetch_array($result);

		$field = var_from_getorpost('field');

		$field['availability'] = is_array($field['availability']) ? join(",", $field['availability']) : "";

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);

		$rows[] = array();
		$rows[] = array('Site Name:', $site['site_name'] . ' (' . $site['site_code'] . ')');
		$rows[] = array('Field Number:', 
			form_hidden('field[num]', $field['num']) . $field['num']);
		$rows[] = array('Field Status:', 
			form_hidden('field[status]', $field['status']) . $field['status']);
		$rows[] = array('Availability:', 
			form_hidden('field[availability]', $field['availability']) . $field['availability']);
			
		$output .= "<div class='pairtable'>". table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));

		$this->setLocation(array(
			$site['site_name'] . " " . $field['num'] => "op=field_view&id=$id",
			$this->title => 0
		));

		return form($output);
	}

	function perform ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$field = var_from_getorpost('field');
		
		db_query("UPDATE field SET 
			num = %d, 
			status = %s,
			availability = %s 
			WHERE field_id = %d",
			$field['num'], $field['status'], $field['availability'], $id
		);
		
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		return true;
	}

	function isDataInvalid ()
	{
		$errors = "";
		
		$field = var_from_getorpost("field");
		if( !validate_number($field['num']) ) {
			$errors .= "<li>Field number cannot be left blank";
		}
	
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
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
		$this->title = 'View Field';
		$this->op = 'field_view';	
		$this->section = 'field';
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
		$id = var_from_getorpost('id');

		$result = db_query(
			"SELECT 
				f.*, s.name, s.code
			 FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
			 WHERE f.field_id = %d", $id);

		$field = db_fetch_object($result);

		if(!isset($field)) {
			$this->error_exit("That field does not exist");
		}
		
		$this->setLocation(array(
			"$field->name $field->num" => "op=field_view&id=$id",
			$this->title => 0
		));

		$daysAvailable = strlen($field->availability) ? split(",", $field->availability) : array();
		
		$allDays = array_values( getOptionsFromEnum('field_assignment', 'day') );
		
		$header = array("Day","League",array('data' => '&nbsp;', 'colspan' => 2));
		$rows = array();	
		foreach($allDays as $curDay) {
			if($curDay === '---') {
				continue;
			}
			$thisRow = array( $curDay );
			
			if(in_array($curDay, $daysAvailable)) {
				$result = db_query("SELECT 
					a.league_id, l.name, l.tier
				FROM 
					field_assignment a, league l
				WHERE 
					a.day = '%s' AND a.field_id = %d AND a.league_id = l.league_id ORDER BY l.ratio, l.tier",  $curDay, $id);
				
				$booking = "";
				while($ass = db_fetch_object($result)) {
					$booking .= "&raquo;&nbsp;$ass->name";
					if($ass->tier) {
						$booking .= " Tier " . $ass->tier;
					}
					$booking .= "&nbsp;[&nbsp;" 
						. l("view league", "op=league_view&id=$ass->league_id");
					if($this->_permissions['field_assign']) {
						$booking .= "&nbsp;|&nbsp;" 
							. l("delete booking", "op=field_unassign&id=$id&day=$curDay&league_id=$ass->league_id");
					}
					$booking .= "&nbsp;]<br />";
				}
				$thisRow[] = $booking;
				if($this->_permissions['field_assign']) {
					$thisRow[] = l("add new booking", "op=field_assign&id=$id&day=$curDay");
				}
			} else {
				$thisRow[] = array('data' => 'Unavailable', 'colspan' => 2);
			}
			$rows[] = $thisRow;
		}
		$bookings = '<div class="listtable">' . table($header, $rows) . "</div>";

		$links = array();
		if($this->_permissions['field_edit']) {
			$links[] = l("edit field", "op=field_edit&id=$id");
		}

		$output = theme_links($links);
		$output .= "<div class='pairtable'>";
		$output .= table(null, array(
			array("Site:", 
				"$field->name ($field->code)&nbsp;[&nbsp;" 
				. l("view", "op=site_view&id=$field->site_id") . "&nbsp;]"),
			array("Status:", $field->status),
			array("Assignments:", $bookings),
		
		));
		$output .= "</div>";

		return $output;
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
		$this->title = "Assign Field";
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'require_var:day',
			'admin_sufficient',
			'volunteer_sufficient',
			'deny'
		);
		$this->op = 'field_assign';
		$this->section = 'field';
		return true;
	}
	
	function process ()
	{
		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		$day = var_from_getorpost('day');
		$league_id = var_from_getorpost('league_id');

		if($step != 'confirm' && $step != 'perform') {
			$rc = $this->generateForm($id, $day);
		} else {
			$result = db_query("SELECT league_id, name, tier FROM league WHERE allow_schedule = 'Y' AND (FIND_IN_SET('%s',day) > 0) AND league_id = %d", $day, $league_id);
			
			if( db_num_rows($result) < 1) {
				$this->error_exit("You must provide a valid league ID");
				return false;
			}
			
			$league = db_fetch_array($result);
		
			switch($step) {
				case 'confirm':
					$rc = $this->generateConfirm($id, $league, $day);
					break;
				case 'perform':
					db_query("INSERT INTO field_assignment VALUES(%d,%d,'%s')", $league_id, $id, $day);
					if( 1 != db_affected_rows() ) {
						return false;
					}
					local_redirect("op=field_view&id=$id");
					break;
			}
		}
		
		return $rc;
	}

	function generateForm( $id, $day ) {
		$field_name = get_field_name($id);
		$this->setLocation(array(
			$field_name => "op=field_view&id=$id",
			$this->title => 0
		));

		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'confirm');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('day', $day);
		
		$leagues = getOptionsFromQuery("SELECT league_id AS theKey, IF(tier,CONCAT(name, ' Tier ', tier), name) AS theValue FROM league WHERE allow_schedule = 'Y' AND (FIND_IN_SET(?, day) > 0)", array($day));

		$output .= para("Select a league to assign field <b>"
				. $field_name . "</b> to for <b>"
				. $day . "</b>")
			. form_select('', 'league_id', '', $leagues);

		$output .= form_submit("Submit");

		return form($output);
	}

	function generateConfirm ( $id, $league, $day )
	{
		$league_name = $league['name'];
		if($league['tier']) {
			$league_name .= " Tier " . $league['tier'];
		}
		
		$field_name = get_field_name($id);
		$this->setLocation(array(
			$field_name => "op=field_view&id=$id",
			$this->title => 0
		));
		
		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('league_id', $league['league_id']);
		$output .= form_hidden('day', $day);

		$output .= para("You have chosen to assign field <b>"
				. $field_name . "</b> to <b>"
				. $league_name. "</b> for <b>"
				. $day . "</b>")
			. para("If this is correct, please click 'Submit' below to proceed");

		$output .= form_submit("Submit");

		return form($output);
	}
}

/**
 * Un-assign a field
 */
class FieldUnassign extends Handler
{
	function initialize ()
	{
		$this->title = "Unassign Field";
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'require_var:day',
			'require_var:league_id',
			'admin_sufficient',
			'volunteer_sufficient',
			'deny',
		);
		$this->op = 'field_unassign';
		$this->section = 'field';
		return true;
	}
	
	function process ()
	{
		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		$league_id = var_from_getorpost('league_id');
		$day = var_from_getorpost('day');
		
		$result = db_query("SELECT name, tier, league_id FROM league WHERE allow_schedule = 'Y' AND (FIND_IN_SET('%s',day) > 0) AND league_id = %d", $day, $league_id);
		
		if(db_num_rows($result) < 1) {
			$this->error_exit("You must provide a valid league ID");
		}
		
		$league = db_fetch_array($result);
		
		switch($step) {
			default:
			case 'confirm':
				$rc = $this->generateConfirm( $id, $league, $day );
				break;
			case 'perform':
				db_query("DELETE FROM field_assignment WHERE league_id = %d AND field_id = %d AND day = '%s'",$league['league_id'], $id, $day);

				local_redirect("op=field_view&id=$id");
				break;
		}
		
		return $rc;
	}

	function generateConfirm ( $id, $league, $day )
	{
		$league_name = $league['name'];
		if($league['tier']) {
			$league_name .= " Tier " . $league_info['tier'];
		}
		$field_name = get_field_name($id);
		$this->setLocation(array(
			$field_name => "op=field_view&id=$id",
			$this->title => 0
		));

		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('league_id', $league['league_id']);
		$output .= form_hidden('day', $day);

		$output .= para("You have chosen to remove the assignment of <b>"
				. $field_name . "</b> from <b>"
				. $league_name. "</b> for <b>"
				. $day . "</b>")
			. para("If this is correct, please click 'Submit' below to proceed");

		$output .= form_submit("Submit");

		return form($output);
	}
}

?>
