<?php

/*
 * Handlers for dealing with fields
 */

function field_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'edit':
			return new FieldEdit;
		case 'view':
			return new FieldView;
		case 'assign':
			return new FieldAssign;
		case 'unassign':
			return new FieldUnassign;
	}
	return null;
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
			'admin_sufficient',
			'deny'
		);

		$this->title = "Edit Field";
		return true;
	}

	function process ()
	{
		$id = arg(2);
		
		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$field = field_load( array('field_id' => $id) );
				$rc = $this->generateConfirm( $id, $field, $edit );
				break;
			case 'perform':
				$this->perform( &$id, $edit );
				local_redirect("field/view/$id");
				break;
			default:
				$edit = $this->getFormData( $id );
				$rc = $this->generateForm( $edit );
		}
		
		$this->setLocation(array($edit['name']  => "field/view/$id", $this->title => 0));
		return $rc;
	}

	function getFormData( $id )
	{
		return field_load( array('field_id' => $id) );
	}

	function generateForm ( $field )
	{
		$output .= form_hidden('edit[step]', 'confirm');
		
		$days_available = strlen($field->availability) ? split(",", $field->availability) : array();
		$field->availability = array(
			'Sunday' => false,
			'Monday' => false,
			'Tuesday' => false,
			'Wednesday' => false,
			'Thursday' => false,
			'Friday' => false,
			'Saturday' => false,
		);
		while(list(,$day) = each($days_available)) {
			$field->availability[$day] = true;
		}

		$rows = array();
		$rows[] = array('Site Name:', "$field->name ($field->code)");
		$rows[] = array('Field Number:', 
			form_textfield('', 'edit[num]', $field->num, 2, 2, "Number for this field at the given site"));
			
		$rows[] = array('Field Status:', 
			form_select('', 'edit[status]', $field->status, getOptionsFromEnum('field','status'), "Is this field open for scheduling, or not?"));

		$availability = '';
		while(list($day,$isAvailable) = each($field->availability)) {
			$availability .= form_checkbox($day,'edit[availability][]', $day, $isAvailable);
		}

		$rows[] = array('Availability:',  $availability);
		$output .= "<div class='pairtable'>". table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		return form($output);
	}
	
	function generateConfirm ( $id, $site, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$edit['availability'] = is_array($edit['availability']) ? join(",", $edit['availability']) : "";

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");

		$output .= form_hidden('edit[step]', 'perform');

		$rows[] = array();
		$rows[] = array('Site Name:', "$site->name ($site->code)");
		$rows[] = array('Field Number:', 
			form_hidden('edit[num]', $edit['num']) . $edit['num']);
		$rows[] = array('Field Status:', 
			form_hidden('edit[status]', $edit['status']) . $edit['status']);
		$rows[] = array('Availability:', 
			form_hidden('edit[availability]', $edit['availability']) . $edit['availability']);
			
		$output .= "<div class='pairtable'>". table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));

		return form($output);
	}

	function perform ( $id, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$rc = db_query("UPDATE field SET 
			num = %d, 
			status = '%s',
			availability = '%s' 
			WHERE field_id = %d",
			$edit['num'], $edit['status'], $edit['availability'], $id
		);


		return ($rc != false);
	}

	function isDataInvalid ( $edit )
	{
		$errors = "";
		
		if( !validate_number($edit['num']) ) {
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
		$this->title = 'View Field';
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'allow',
		);
		$this->_permissions = array(
			'field_edit'		=> false,
			'field_assign'		=> false,
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
		$site_id = arg(2);
		$field_num = arg(3);

		$site = site_load( array('site_id' => $site_id) );
		if(!$site) {
			$this->error_exit("That site does not exist");
		}
		if( !validate_number($field_num) ) {
			$this->error_exit("That field does not exist");
		}
		
		$this->setLocation(array(
			"$site->name $field_num" => "field/view/$site_id/$field_num",
			$this->title => 0
		));

		$result = db_query("SELECT 
			g.*
			FROM gameslot g
			WHERE site_id = %d AND field_num = %d ORDER BY g.game_date, g.game_start", $site->site_id, $field_num);

		$header = array("Date","Start Time","End Time","Booking", "Actions");
		$rows = array();
		while($slot = db_fetch_object($result)) {
			$booking = '';
			$actions = array(
				l('availability', "slot/availability/$slot->slot_id")
			);
			if($slot->game_id) {
				$game = game_load( array('game_id' => $slot->game_id) );
				$booking = l($game->game_id,"game/view/$game->game_id");
			}
			$rows[] = array($slot->game_date, $slot->game_start, $slot->game_end, $booking, theme_links($actions));
		}

		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";

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
			'admin_sufficient',
			'volunteer_sufficient',
			'deny'
		);
		return true;
	}
	
	function process ()
	{
		$id = arg(2);
		if( !validate_number($id) ) {
			$this->error_exit("Field ID is missing");
		}
		$day = arg(3);
		if( !validate_nonblank($day) ) {
			$this->error_exit("Day is missing");
		}

		$edit = $_POST['edit'];
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($id, $day, $edit);
				break;
			case 'perform':
				db_query("INSERT INTO field_assignment VALUES(%d,%d,'%s')", $edit['league_id'], $id, $day);
				if( 1 != db_affected_rows() ) {
					return false;
				}
				local_redirect(url("field/view/$id"));
				break;
			default:
				$rc = $this->generateForm($id, $day);
		}
		
		return $rc;
	}

	function generateForm( $id, $day ) {

		$field = field_load( array( 'field_id' => $id) );
		if(!$field) {
			$this->error_exit("You must provide a valid field ID");
		}
		$this->setLocation(array(
			"$field->fullname ($field->abbrev)" => "field/view/$id",
			$this->title => 0
		));

		$output = form_hidden('edit[step]', 'confirm');
		
		$leagues = getOptionsFromQuery("SELECT l.league_id AS theKey, IF(l.tier,CONCAT(l.name, ' Tier ', l.tier), l.name) AS theValue FROM league l WHERE l.allow_schedule = 'Y' AND (FIND_IN_SET('%s', l.day) > 0)", array($day));

		$output .= para("Select a league to assign field <b>$field->fullname</b> to for <b>$day </b>")
			. form_select('', 'edit[league_id]', '', $leagues);

		$output .= form_submit("Submit");

		return form($output);
	}

	function generateConfirm ( $id, $day, $edit )
	{
		$league = league_load( array('league_id'=> $edit['league_id'], 'allow_schedule' => 'Y', '_extra' => "(FIND_IN_SET('" . check_query($day). "',l.day) > 0)") );
		
		if( ! $league ) {
			$this->error_exit("You must provide a valid league ID");
		}
	
		$field = field_load( array( 'field_id' => $id) );
		if(!$field) {
			$this->error_exit("You must provide a valid field ID");
		}
		$this->setLocation(array(
			"$field->fullname ($field->abbrev)" => "field/view/$id",
			$this->title => 0
		));
		
		$output .= form_hidden('edit[step]', 'perform');
		$output .= form_hidden('edit[league_id]', $edit['league_id']);

		$output .= para("You have chosen to assign field <b>$field_name</b> to <b>$league->fullname</b> for <b>$day</b>")
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
			'admin_sufficient',
			'volunteer_sufficient',
			'deny',
		);
		return true;
	}
	
	function process ()
	{
		$id = arg(2);
		if( !validate_number($id) ) {
			$this->error_exit("Field ID is missing");
		}
		$day = arg(3);
		if( !validate_nonblank($day) ) {
			$this->error_exit("Day is missing");
		}
		$leagueID = arg(4);
		
		$league = league_load( array('league_id' => $leagueID) );
		if( !$league ) {
			$this->error_exit("That league does not exist.");
		}
		
		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			default:
			case 'confirm':
				$field = field_load( array( 'field_id' => $id) );
				if(!$field) {
					$this->error_exit("You must provide a valid field ID");
				}
				$this->setLocation(array(
					"$field->fullname ($field->abbrev)" => "field/view/$id",
					$this->title => 0
				));

				$output = form_hidden('edit[step]', 'perform');
				$output .= para("You have chosen to remove the assignment of <b>$field_name</b> from <b>$league->fullname</b> for <b>$day</b>")
					. para("If this is correct, please click 'Submit' below to proceed");
				$output .= form_submit("Submit");
				$rc = form($output);
				break;
			case 'perform':
				db_query("DELETE FROM field_assignment WHERE league_id = %d AND field_id = %d AND day = '%s'",$leagueID, $id, $day);

				local_redirect("field/view/$id");
				break;
		}
		
		return $rc;
	}
}

/**
 * Load a single field object from the database using the supplied query
 * data.  If more than one field matches, we will return only the first one.
 * If fewer than one matches, we return null.
 *
 * @param	mixed 	$array key-value pairs that identify the field to be loaded.
 */
function field_load ( $array = array() )
{
	$query = array();

	foreach ($array as $key => $value) {
		if($key == '_extra') {
			/* Just slap on any extra query fields desired */
			$query[] = $value;
		} else {
			$query[] = "f.$key = '" . check_query($value) . "'";
		}
	}
	
	$result = db_query_range("SELECT 
		f.*, s.name, s.code
	 	FROM field f LEFT JOIN site s ON (s.site_id = f.site_id)
		WHERE " . implode(' AND ',$query),0,1);

	/* TODO: we may want to abort here instead */
	if(1 != db_num_rows($result)) {
		return null;
	}

	$field = db_fetch_object($result);

	/* set derived attributes */
	$field->fullname = "$field->name $field->num";
	$field->abbrev = "$field->code $field->num";

	return $field;
}
?>
