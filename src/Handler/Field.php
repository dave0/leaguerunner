<?php

/*
 * Handlers for dealing with fields
 */
function field_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new FieldCreate;
		case 'edit':
			return new FieldEdit;
		case 'view':
			return new FieldView;
		case 'list':
			return new FieldList;
		case 'bookings':
			return new FieldBooking;
	}
	return null;
}

function field_menu()
{
	global $session;
	menu_add_child('_root','field','Fields');
	menu_add_child('field','field/list','list fields', array('link' => 'field/list') );

	
	if($session->is_admin()) {
		menu_add_child('field','field/create','create field', array('weight' => 5, 'link' => 'field/create') );
	}
}

/**
 * Add view/edit/delete links to the menu for the given field
 * TODO: fix ugly evil things like FieldEdit so that this can be called to add
 * site being edited to the menu.
 */
function field_add_to_menu( &$field, $parent = 'field' ) 
{
	global $session;
	
	menu_add_child($parent, $field->fullname, $field->fullname, array('weight' => -10, 'link' => "field/view/$field->fid"));
	menu_add_child($field->fullname, "$field->fullname bookings", "view bookings", array('link' => "field/bookings/$field->fid"));

	
	if($session->is_admin()) {
		menu_add_child($field->fullname, "$field->fullname/edit",'edit field', array('weight' => 1, 'link' => "field/edit/$field->fid"));
		menu_add_child($field->fullname, "$field->fullname gameslot", 'new gameslot', array('link' => "slot/create/$field->fid"));
	}
}	

class FieldCreate extends FieldEdit
{
	function initialize ()
	{
		$this->title = "Create Field";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		return true;
	}
	
	function process ()
	{
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$field = new Field;
				$this->perform($field, $edit);
				local_redirect(url("field/view/$field->fid"));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm($edit);
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}
}

class FieldEdit extends Handler
{
	function initialize ()
	{
		$this->title = "Edit Field";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		return true;
	}
	
	function process ()
	{
		$id = arg(2);
		$edit = $_POST['edit'];
		$field = field_load( array('fid' => $id) );
		if (!$field) {
			$this->error_exit("That field does not exist");
		}

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$this->perform($field, $edit);
				local_redirect(url("field/view/$id"));
				break;
			default:
				$edit = object2array($field);
				$rc = $this->generateForm( $edit );
		}

		field_add_to_menu($field);
		$this->setLocation(array($edit['name']  => "field/view/$id", $this->title => 0));
		return $rc;
	}
	
	function generateForm( $data = array() )
	{
		$output = form_hidden("edit[step]", "confirm");

		$rows = array();

		$rows[] = array( "Field Number:", form_textfield("", 'edit[num]', $data['num'], 3, 3, "Number of this field at the given site"));

		
		// if( ! $edit['is_parent'] ) {
		$result = field_query( array('_extra' => 'ISNULL(parent_fid)', '_order' => 'f.name,f.num') );
		$parents = array();
		$parents[0] = "---";
		while($p = db_fetch_object($result)) {
			$parents[$p->fid] = $p->fullname;
		}

		$rows[] = array( "Parent Field", form_select("", 'edit[parent_fid]', $data['parent_fid'], $parents, "Inherit location and name from other field"));
		
		$rows[] = array( "Field Name:", form_textfield("", 'edit[name]', $data['name'], 35, 35, "Name of field (do not append number)"));

		$rows[] = array( "Field Code:", form_textfield("", 'edit[code]', $data['code'], 3, 3, "Three-letter abbreviation for field site"));

		$rows[] = array( "Region:", form_select("", 'edit[region]', $data['region'], getOptionsFromEnum('field', 'region'), "Area of city this field is located in"));

		$rows[] = array( "City Ward:", form_select("", 'edit[ward_id]', $data['ward_id'],
			getOptionsFromQuery("SELECT ward_id as theKey, CONCAT(name, ' (', city, ' Ward ', num, ')') as theValue FROM ward ORDER BY ward_id"),
			"Official city ward this field is located in"));

		$rows[] = array( "Location Map:", form_textfield("", 'edit[location_url]', $data['location_url'],50, 255, "URL for image that shows how to reach the field"));

		$rows[] = array( "Layout Map:", form_textfield("", 'edit[layout_url]', $data['layout_url'], 50, 255, "URL for image that shows how to set up fields at the site"));
		
		$rows[] = array( "Field Permit:", form_textfield("", 'edit[permit_url]', $data['permit_url'], 50, 255, "URL for field permit (if required)"));

		$rows[] = array( "Directions:", form_textarea("", 'edit[site_directions]', $data['site_directions'], 60, 5, "Directions to field.  Please ensure that bus and bike directions are also provided if practical."));

		$rows[] = array( "Special Instructions:", form_textarea("", 'edit[site_instructions]', $data['site_instructions'], 60, 5, "Specific instructions for this site (parking, other restrictions)"));
		$rows[] = array(
			form_submit('Submit'),
			form_reset('Reset'));

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";

		return form($output);	
	}

	function generateConfirm ( $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = form_hidden("edit[step]", "perform");

		if ( $edit['parent_fid'] ) {
			$parent = field_load( array('fid' => $edit['parent_fid']));
			$rows = array();
			$rows[] = array( "Name:", $parent->name );
			$rows[] = array( "Number:", form_hidden('edit[num]', $edit['num']) . check_form($edit['num']));
			$rows[] = array( "Parent Field:", form_hidden('edit[parent_fid]', $edit['parent_fid']) . $parent->fullname);
		} else {
			$ward = ward_load( array('ward_id' => $edit['ward_id']) );
			
			$rows = array();
			$rows[] = array( "Name:", form_hidden('edit[name]', $edit['name']) . check_form($edit['name']));
			$rows[] = array( "Code:", form_hidden('edit[code]', $edit['code']) . check_form($edit['code']));
			$rows[] = array( "Number:", form_hidden('edit[num]', $edit['num']) . check_form($edit['num']));
			$rows[] = array( "Region:", form_hidden('edit[region]', $edit['region']) . check_form($edit['region']));
			$rows[] = array( "City Ward:", form_hidden('edit[ward_id]', $edit['ward_id']) .  "$ward->name ($ward->city Ward $ward->num)");
			$rows[] = array( "Location Map:", form_hidden('edit[location_url]', $edit['location_url']) . check_form($edit['location_url']));
			$rows[] = array( "Layout Map:", form_hidden('edit[layout_url]', $edit['layout_url']) . check_form($edit['layout_url']));
			$rows[] = array( "Field Permit:", form_hidden('edit[permit_url]', $edit['permit_url']) . check_form($edit['permit_url']));
			$rows[] = array( "Directions:", form_hidden('edit[site_directions]', $edit['site_directions']) . check_form($edit['site_directions']));
			$rows[] = array( "Special Instructions:", form_hidden('edit[site_instructions]', $edit['site_instructions']) . check_form($edit['site_instructions']));
		}
		
		$rows[] = array( form_submit('Submit'), "");
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		
		return form($output);
	}

	function perform ( &$field, &$edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		
		$field->set('num', $edit['num']);

		if( isset($edit['parent_fid']) ) {
			$field->set('parent_fid', $edit['parent_fid']);
		}
		
		if( $edit['parent_fid'] == 0 ) {
			$field->set('parent_fid', '' );
			$field->set('name', $edit['name']);
			$field->set('code', $edit['code']);
			$field->set('region', $edit['region']);
			$field->set('ward_id', $edit['ward_id']);
			$field->set('location_url', $edit['location_url']);
			$field->set('layout_url', $edit['layout_url']);
			$field->set('permit_url', $edit['permit_url']);
			$field->set('site_directions', $edit['site_directions']);
			$field->set('site_instructions', $edit['site_instructions']);
		}


		if( !$field->save() ) {
			$this->error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = "";
		
		if( ! validate_number($edit['num']) ) {
			$errors .= "<li>Number of field must be provided";
		}

		if( $edit['parent_fid'] > 0 ) {
			if( ! validate_number($edit['parent_fid']) ) {
				$errors .= "<li>Parent must be a valid value";
				return $errors;
			}

			return false;
		}

		if( !validate_nonhtml($edit['name'] ) ) {
			$errors .= "<li>Name cannot be left blank, and cannot contain HTML";
		}
		if( !validate_nonhtml($edit['code'] ) ) {
			$errors .= "<li>Code cannot be left blank and cannot contain HTML";
		}
		
		if( ! validate_number($edit['ward_id']) ) {
			$errors .= "<li>Ward must be selected";
		}

		if( ! validate_nonhtml($edit['region']) ) {
			$errors .= "<li>Region cannot be left blank and cannot contain HTML";
		}
		
		if(validate_nonblank($edit['location_url'])) {
			if( ! validate_nonhtml($edit['location_url']) ) {
				$errors .= "<li>If you provide a location URL, it must be valid.";
			}
		}
		
		if(validate_nonblank($edit['layout_url'])) {
			if( ! validate_nonhtml($edit['layout_url']) ) {
				$errors .= "<li>If you provide a site layout URL, it must be valid.";
			}
		}
		
		if(validate_nonblank($edit['permit_url'])) {
			if( ! validate_nonhtml($edit['permit_url']) ) {
				$errors .= "<li>If you provide a permit URL, it must be valid.";
			}
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

class FieldList extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			"field_admin"    => false,
		);
		
		$this->_required_perms = array(
			'admin_sufficient',
			'volunteer_sufficient',
			'allow'		/* Allow everyone */
		);
		$this->setLocation(array('List Fields' => 'field/list'));
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

		ob_start();
		$retval = @readfile("data/field_caution.html");
		if (false !== $retval) {
			$output = ob_get_contents();
		}           
		ob_end_clean();

		$result = field_query( array( '_extra' => 'ISNULL(parent_fid)', '_order' => 'f.region,f.name') );

		$fieldsByRegion = array();
		while($field = db_fetch_object($result)) {
			if(! array_key_exists( $field->region, $fieldsByRegion) ) {
				$fieldsByRegion[$field->region] = "";
			}
			$fieldsByRegion[$field->region] 
				.= l($field->name, "field/view/$field->fid") . "<br />";
		}

		$fieldColumns = array();
		$header = array();
		while(list($region,$fields) = each($fieldsByRegion)) {
			$fieldColumns[] = $fields;	
			$header[] = ucfirst($region);
		}
		$output .= "<div class='fieldlist'>" . table($header, array( $fieldColumns) ) . "</div>";

		return $output;
	}
}

class FieldView extends Handler
{
	function initialize ()
	{
		$this->title= "View Field";
		$this->_required_perms = array(
			'admin_sufficient',
			'allow',
		);
		$this->_permissions = array(
			'field_edit'		=> false,
			'field_create'		=> false,
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
		$id = arg(2);

		$field = field_load( array('fid' => $id) );
		if(!$field) {
			$this->error_exit("That field does not exist");
		}
	
		$rows = array();
		$rows[] = array("Field Name:", $field->name);
		$rows[] = array("Field Code:", $field->code);
		$rows[] = array("Number:", $field->num);
		$rows[] = array("Field Region:", $field->region);

		$ward = ward_load( array('ward_id' => $field->ward_id) );
		
		$rows[] = array("City Ward:", l("$ward->name ($ward->city Ward $ward->num)", "ward/view/$field->ward_id"));
		$rows[] = array("Location Map:", 
			$field->location_url ? l("Click for map in new window", $field->location_url, array('target' => '_new'))
				: "N/A");
		$rows[] = array("Layout Map:", 
			$field->layout_url ? l("Click for map in new window", $field->layout_url, array('target' => '_new'))
				: "N/A");
		$rows[] = array("Field Permit:", 
			$field->permit_url ? l("Click for permit in new window", $field->permit_url, array('target' => '_new'))
				: "N/A");
		$rows[] = array("Directions:", $field->site_directions);
		$rows[] = array("Special Instrutions:", $field->site_instructions);
		
		/* TODO: list other fields at this site */
		if( $field->parent_fid ) {
			$result = db_query("SELECT * FROM field WHERE parent_fid = %d OR fid = %d ORDER BY num", $field->parent_fid, $field->parent_fid);
		} else {
			$result = db_query("SELECT * FROM field WHERE parent_fid = %d OR fid = %d ORDER BY num", $field->fid, $field->fid);
		}

		$fieldRows = array();
		$header = array("Fields","&nbsp;");
		while( $related = db_fetch_object( $result ) ) {
			$fieldRows[] = array(
				"$field->code $related->num",
				l("view field", "field/view/$related->fid", array('title' => "View field details"))
			);
		}
		
		$rows[] = array("Fields at this site:", "<div class='listtable'>" . table($header,$fieldRows) . "</div>");
		
		$this->setLocation(array(
			$field->fullname => "field/view/$field->fid",
			$this->title => 0
		));
	
		field_add_to_menu($field);
		return "<div class='pairtable'>" . table(null, $rows) . "</div>";
	}
}

/**
 * Field viewing handler
 */
class FieldBooking extends Handler
{
	function initialize ()
	{
		$this->title = 'View Field Booking';
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'allow',
		);
		$this->_permissions = array(
			'field_admin'		=> false,
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

		global $session;

		$fid = arg(2);

		$field = field_load( array('fid' => $fid) );
		if(!$field) {
			$this->error_exit("That field does not exist");
		}
		
		$this->setLocation(array(
			$field->fullname => "field/view/$fid",
			$this->title => 0
		));

		$result = db_query("SELECT 
			g.*
			FROM gameslot g
			WHERE fid = %d ORDER BY g.game_date, g.game_start", $field->fid);

		$header = array("Date","Start Time","End Time","Booking", "Actions");
		$rows = array();
		while($slot = db_fetch_object($result)) {
			$booking = '';
			if( $this->_permissions['field_admin'] ) {
				$actions = array(
					l('change avail', "slot/availability/$slot->slot_id"),
					l('delete', "slot/delete/$slot->slot_id")
				);
			} else {
				$actions = array();
			}
			if($slot->game_id) {
				$game = game_load( array('game_id' => $slot->game_id) );
				$booking = l($game->league_name,"game/view/$game->game_id");
				if( $session->is_coordinator_of($game->league_id) ) {
					$actions[] = l('reschedule/move', "game/reschedule/$game->game_id");
				}
			}
			$rows[] = array($slot->game_date, $slot->game_start, $slot->game_end, $booking, theme_links($actions));
		}

		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";

		field_add_to_menu($field);
		return $output;
	}
}
?>
