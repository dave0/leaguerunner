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
		$this->set_title("Create New Field");
		$this->op = 'field_create';
		return true;
	}

	function generateForm ( $id )
	{
		global $DB;
		
		$site_id = var_from_getorpost('site_id');
		if(! validate_number($site_id) ) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}

		$field = $DB->getRow("SELECT name as site_name, code as site_code FROM site where site_id = ?", array($site_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($field)) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}

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

		$output .= "<table border='0'>";

		$output .= simple_row('Site Name:', $field['site_name'] . ' (' . $field['site_code'] . ')');
		$output .= simple_row('Field Number:', 
			form_textfield('', 'field[num]', '', 2, 2, "Number for this field at the given site"));
			
		$output .= simple_row('Field Status:', 
			form_select('', 'field[status]', '', getOptionsFromEnum('field','status'), "Is this field open for scheduling, or not?"));

		$availability = '';
		while(list($day,$isAvailable) = each($field['availability'])) {
			$availability .= form_checkbox($day,'field[availability][]', $day, $isAvailable);
		}

		$output .= simple_row('Availability:',  $availability);
		$output .= "</table>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		
		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;
	}
	
	function generateConfirm ( $id )
	{
		global $DB;
		
		$site_id = var_from_getorpost('site_id');
		if(! validate_number($site_id) ) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$site = $DB->getRow("SELECT name as site_name, code as site_code FROM site where site_id = ?", array($site_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($site)) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}

		$field = var_from_getorpost('field');

		$field['availability'] = is_array($field['availability']) ? join(",", $field['availability']) : "";

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('site_id', $site_id);

		$output .= "<table border='0'>";

		$output .= simple_row('Site Name:', $site['site_name'] . ' (' . $site['site_code'] . ')');
		$output .= simple_row('Field Number:', 
			form_hidden('field[num]', $field['num']) . $field['num']);
		$output .= simple_row('Field Status:', 
			form_hidden('field[status]', $field['status']) . $field['status']);
		$output .= simple_row('Availability:', 
			form_hidden('field[availability]', $field['availability']) . $field['availability']);
			
		$output .= "</table>";
		$output .= para(form_submit("submit"));
		
		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;
	}
	
	function perform ( $id )
	{
		global $DB, $session;
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$site_id = var_from_getorpost('site_id');
		if(! validate_number($site_id) ) {
			$this->error_exit("You cannot add a field to an invalid site.");
		}

		$field = var_from_getorpost("field");
		
		$res = $DB->query("INSERT into field (site_id,num) VALUES (?,?)", array($site_id,$field['num']));
		if($this->is_database_error($res)) {
			return false;
		}
	
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from field");
		if($this->is_database_error($id)) {
			return false;
		}
		
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

		$this->set_title("Edit Field");
		$this->op = 'field_edit';
		return true;
	}

	function process ()
	{
		global $DB;

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
		global $DB;

		$field = $DB->getRow(
			"SELECT 
				f.field_id, f.site_id, f.num, f.status, f.availability, s.name as site_name, s.code as site_code
			 FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
			 WHERE f.field_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($field)) {
			return false;
		}
		
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

		$output .= "<table border='0'>";

		$output .= simple_row('Site Name:', $field['site_name'] . ' (' . $field['site_code'] . ')');
		$output .= simple_row('Field Number:', 
			form_textfield('', 'field[num]', $field['num'], 2, 2, "Number for this field at the given site"));
			
		$output .= simple_row('Field Status:', 
			form_select('', 'field[status]', $field['status'], getOptionsFromEnum('field','status'), "Is this field open for scheduling, or not?"));

		$availability = '';
		while(list($day,$isAvailable) = each($field['availability'])) {
			$availability .= form_checkbox($day,'field[availability][]', $day, $isAvailable);
		}

		$output .= simple_row('Availability:',  $availability);
		$output .= "</table>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		$this->set_title($this->title . " &raquo; " . $field['site_name'] . " " . $field['num']);
		
		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;
	}

	function generateConfirm ( $id )
	{
		global $DB;
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$site = $DB->getRow(
			"SELECT 
				f.site_id, s.name as site_name, s.code as site_code
			 FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
			 WHERE f.field_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($site)) {
			return false;
		}

		$field = var_from_getorpost('field');

		$field['availability'] = is_array($field['availability']) ? join(",", $field['availability']) : "";

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);

		$output .= "<table border='0'>";

		$output .= simple_row('Site Name:', $site['site_name'] . ' (' . $site['site_code'] . ')');
		$output .= simple_row('Field Number:', 
			form_hidden('field[num]', $field['num']) . $field['num']);
		$output .= simple_row('Field Status:', 
			form_hidden('field[status]', $field['status']) . $field['status']);
		$output .= simple_row('Availability:', 
			form_hidden('field[availability]', $field['availability']) . $field['availability']);
			
		$output .= "</table>";
		$output .= para(form_submit("submit"));

		
		$this->set_title($this->title . " &raquo; " . $site['site_name'] . " " . $field['num']);

		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;
	}

	function perform ( $id )
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
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
				$id,
			)
		);
		
		if($this->is_database_error($res)) {
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
		$this->op = 'field_view';	
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
		
		$id = var_from_getorpost('id');

		$field = $DB->getRow(
			"SELECT 
				f.*, s.name, s.code
			 FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
			 WHERE f.field_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($field)) {
			return false;
		}

		if(!isset($field)) {
			$this->error_exit("That field does not exist");
		}
		
		$this->set_title("View Field &raquo; " . $field['name'] . " " . $field['num']);

		$daysAvailable = strlen($field['availability']) ? split(",", $field['availability']) : array();
		
		$allDays = array_values( getOptionsFromEnum('field_assignment', 'day') );
		$bookings = "<table cellpadding='3' cellspacing='0' width='100%'>";
		$bookings .= tr(
			td("Day", array('class' => 'booking_title'))
			. td("League", array('class' => 'booking_title'))
			. td("&nbsp;", array('colspan' => 2, 'class' => 'booking_title'))
		);
		foreach($allDays as $curDay) {
			$bookings .= "<tr>";
			$bookings .= td($curDay, array('valign' => 'top',  'class' => 'booking_item'));
			if(in_array($curDay, $daysAvailable)) {
				$result = $DB->query("SELECT 
					a.league_id, l.name, l.tier
				FROM 
					field_assignment a, league l
				WHERE 
					a.day = ? AND a.field_id = ? AND a.league_id = l.league_id ORDER BY l.ratio, l.tier", 
					array($curDay, $id));
				if($this->is_database_error($result)) {
					return false;
				}
				$bookings .= "<td class='booking_item'>";
				while($ass = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
					$bookings .= "&raquo;&nbsp;" . $ass['name'];
					if($ass['tier']) {
						$bookings .= " Tier " . $ass['tier'];
					}
					$bookings .= "&nbsp;[&nbsp;" 
						. l("view league", "op=league_view&id=" . $ass['league_id']);
					if($this->_permissions['field_assign']) {
						$bookings .= "&nbsp;|&nbsp;" 
							. l("delete booking", "op=field_unassign&id=$id&day=$curDay&league_id=" . $ass['league_id']);
					}
					$bookings .= "&nbsp;]<br />";
				}
				$bookings .= "&nbsp;</td>";
				if($this->_permissions['field_assign']) {
					$bookings .= td(l("add new booking", "op=field_assign&id=$id&day=$curDay"), array('class' => 'booking_item', 'valign' => 'top'));
				}
			} else {
				$bookings .= td("Unavailable", array('class' => 'booking_item', 'colspan' => 2));
			}
			
			$bookings .= "</tr>";
		}
		$bookings .= "</table>";

		$links = array();
		if($this->_permissions['field_edit']) {
			$links[] = l("edit field", "op=field_edit&id=$id");
		}

		$output = "<table border='0' width='100%'>";
		$output .= simple_row("Site:", 
			$field['name'] 
			. " (" . $field['code'] . ")&nbsp;[&nbsp;" 
			. l("view", "op=site_view&id=" . $field['site_id']) . "&nbsp;]");
		$output .= simple_row("Status:", $field['status']);
		$output .= simple_row("Assignments:", $bookings);
		
		$output .= "<table>";

		print $this->get_header();
		print h1($this->title);
		print blockquote(theme_links($links));
		print $output;
		print $this->get_footer();

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
		$this->op = 'field_assign';
		return true;
	}
	
	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		$day = var_from_getorpost('day');
		$league_id = var_from_getorpost('league_id');

		if($step != 'confirm' && $step != 'perform') {
			$rc = $this->generateForm($id, $day);
		} else {
			$league = $DB->getRow("SELECT league_id, name, tier FROM league WHERE allow_schedule = 'Y' AND (FIND_IN_SET(?,day) > 0) AND league_id = ?", array($day, $league_id), DB_FETCHMODE_ASSOC);
			if($this->is_database_error($league)) {
				return false;
			}
			if(sizeof($league) < 1) {
				$this->error_exit("You must provide a valid league ID");
				return false;
			}
		
			switch($step) {
				case 'confirm':
					$rc = $this->generateConfirm($id, $league, $day);
					break;
				case 'perform':
					$res = $DB->query("INSERT INTO field_assignment VALUES(?,?,?)", array($league_id, $id, $day));
					if($this->is_database_error($res)) {
						return false;
					}
					local_redirect("op=field_view&id=$id");
					break;
			}
		}
		
		return $rc;
	}

	function generateForm( $id, $day ) {
		global $DB;
		
		$field_name = get_field_name($id);
		$this->set_title("Assign Field &raquo; $field_name");

		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'confirm');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('day', $day);
		
		$leagues = getOptionsFromQuery("SELECT league_id, IF(tier,CONCAT(name, ' Tier ', tier), name) FROM league WHERE allow_schedule = 'Y' AND (FIND_IN_SET(?, day) > 0)", array($day));

		$output .= blockquote( 
			para("Select a league to assign field <b>"
				. $field_name . "</b> to for <b>"
				. $day . "</b>")
			. form_select('', 'league_id', '', $leagues)
		);

		$output .= form_submit("Submit");

		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;	
	}

	function generateConfirm ( $id, $league, $day )
	{
		global $DB;
		
		$league_name = $league['name'];
		if($league['tier']) {
			$league_name .= " Tier " . $league_info['tier'];
		}
		
		$field_name = get_field_name($id);
		$this->set_title("Assign Field &raquo; $field_name");
		
		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('league_id', $league['league_id']);
		$output .= form_hidden('day', $day);

		$output .= blockquote( 
			para("You have chosen to assign field <b>"
				. $field_name . "</b> to <b>"
				. $league_name. "</b> for <b>"
				. $day . "</b>")
			. para("If this is correct, please click 'Submit' below to proceed")
		);

		$output .= form_submit("Submit");

		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;	
	}
}

/**
 * Un-assign a field
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
		$this->op = 'field_unassign';
		return true;
	}
	
	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		$league_id = var_from_getorpost('league_id');
		$day = var_from_getorpost('day');
		
		$league = $DB->getRow("SELECT name, tier, league_id FROM league WHERE allow_schedule = 'Y' AND (FIND_IN_SET(?,day) > 0) AND league_id = ?", array($day, $league_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league)) {
			return false;
		}
		if(sizeof($league) < 1) {
			$this->error_exit("You must provide a valid league ID");
		}
		
		switch($step) {
			default:
			case 'confirm':
				$rc = $this->generateConfirm( $id, $league, $day );
				break;
			case 'perform':
				$res = $DB->query("DELETE FROM field_assignment WHERE league_id = ? AND field_id = ? AND day = ?", array($league['league_id'], $id, $day));
				if($this->is_database_error($res)) {
					return false;
				}
				local_redirect("op=field_view&id=$id");
				break;
		}
		
		return $rc;
	}

	function generateConfirm ( $id, $league, $day )
	{
		global $DB;
		
		$league_name = $league['name'];
		if($league['tier']) {
			$league_name .= " Tier " . $league_info['tier'];
		}
		$field_name = get_field_name($id);
		$this->set_title("Unassign Field &raquo; $field_name");

		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('league_id', $league['league_id']);
		$output .= form_hidden('day', $day);

		$output .= blockquote( 
			para("You have chosen to remove the assignment of <b>"
				. $field_name . "</b> from <b>"
				. $league_name. "</b> for <b>"
				. $day . "</b>")
			. para("If this is correct, please click 'Submit' below to proceed")
		);

		$output .= form_submit("Submit");

		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;	
	}
}

?>
