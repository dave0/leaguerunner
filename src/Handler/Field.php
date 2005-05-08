<?php

/*
 * Handlers for dealing with fields
 */
function field_dispatch() 
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'create':
			$obj = new FieldCreate;
			break;
		case 'edit':
			$obj = new FieldEdit;
			$obj->field = field_load( array('fid' => $id) );
			break;
		case 'view':
			$obj = new FieldView;
			$obj->field = field_load( array('fid' => $id) );
			break;
		case 'list':
			return new FieldList;
			break;
		case 'bookings':
			$obj = new FieldBooking;
			$obj->field = field_load( array('fid' => $id) );
			break;
		default:
			$obj = null;
	}
	if( $obj->field ) {
		field_add_to_menu( $obj->field );
	}
	return $obj;
}

function field_permissions ( &$user, $action, $fid, $data_field )
{
	$public_data = array();
	$member_data = array( 'site_instructions' );
	
	switch( $action )
	{
		case 'create':
		case 'edit':
			// Only admin can create or edit
			break;
		case 'view':
			// Everyone can view, but valid users get extra info
			if($user->status == 'active') {
				$viewable_data = array_merge($public_data, $member_data);
			} else {
				$viewable_data = $public_data;
			}
			if( $data_field ) {
				return in_array( $data_field, $viewable_data );
			} else {
				return true;
			}
		case 'list':
			// Everyone can list
			return true;
		case 'view bookings':
			// Only valid users can view bookings
			if($user && ($user->status == 'active')) {
				return true;
			}
	}
	
	return false;
}

function field_menu()
{
	global $session;
	menu_add_child('_root','field','Fields');
	menu_add_child('field','field/list','list fields', array('link' => 'field/list') );

	if( $session->has_permission('field','create') ) {
		menu_add_child('field','field/create','create field', array('weight' => 5, 'link' => 'field/create') );
	}
}

/**
 * Add view/edit/delete links to the menu for the given field
 */
function field_add_to_menu( &$field ) 
{
	global $session;
	
	menu_add_child('field', $field->fullname, $field->fullname, array('weight' => -10, 'link' => "field/view/$field->fid"));
	
	if($session->has_permission('field','view bookings', $field->fid) ) {
		menu_add_child($field->fullname, "$field->fullname bookings", "view bookings", array('link' => "field/bookings/$field->fid"));
	}
	
	if($session->has_permission('field','edit', $field->fid) ) {
		menu_add_child($field->fullname, "$field->fullname/edit",'edit field', array('weight' => 1, 'link' => "field/edit/$field->fid"));
	} 
	
	if($session->has_permission('gameslot','create', $field->fid) ) {
		menu_add_child($field->fullname, "$field->fullname gameslot", 'new gameslot', array('link' => "slot/create/$field->fid"));
	}
}	

class FieldCreate extends FieldEdit
{
	var $field;
	
	function has_permission()
	{
		global $session;
		return $session->has_permission('field','create');
	}
	
	function process ()
	{
		$edit = $_POST['edit'];
		$this->title = "Create Field";

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$this->field = new Field;
				$this->perform($this->field, $edit);
				local_redirect(url("field/view/" . $this->field->fid));
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
	var $field;
	
	function has_permission()
	{
		global $session;
		if (!$this->field) {
			error_exit("That field does not exist");
		}
		return $session->has_permission('field','edit', $this->field->fid);
	}
	
	function process ()
	{
		$this->title = "Edit Field";
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$this->perform($this->field, $edit);
				local_redirect(url("field/view/". $this->field->fid));
				break;
			default:
				$edit = object2array($this->field);
				$rc = $this->generateForm( $edit );
		}

		$this->setLocation(array($edit['name']  => "field/view/".$this->field->fid, $this->title => 0));
		return $rc;
	}
	
	function generateForm( $data = array() )
	{
		$output = form_hidden("edit[step]", "confirm");

		$output .= form_textfield("Field Number", 'edit[num]', $data['num'], 3, 3, "Number of this field at the given site");
		
		$output .= form_select("Field Rating", 'edit[rating]', $data['rating'], field_rating_values(), "Rate this field on the scale provided");

		// if( ! $edit['is_parent'] ) {
		$result = field_query( array('_extra' => 'ISNULL(parent_fid)', '_order' => 'f.name,f.num') );
		$parents = array();
		$parents[0] = "---";
		while($p = db_fetch_object($result)) {
			$parents[$p->fid] = $p->fullname;
		}
		
		$output .= form_select("Parent Field", 'edit[parent_fid]', $data['parent_fid'], $parents, "Inherit location and name from other field");
		
		$output .= form_textfield("Field Name", 'edit[name]', $data['name'], 35, 35, "Name of field (do not append number)");

		$output .= form_textfield("Field Code", 'edit[code]', $data['code'], 3, 3, "Three-letter abbreviation for field site");

		$output .= form_select("Region", 'edit[region]', $data['region'], getOptionsFromEnum('field', 'region'), "Area of city this field is located in");
		
		$output .= form_textfield('Street and Number','edit[location_street]',$data['location_street'], 25, 100);
		
		$output .= form_textfield('City','edit[location_city]',$data['location_city'], 25, 100, 'Name of city (Ottawa, Gatineau)');
			
		$output .= form_select('Province', 'edit[location_province]', $data['location_province'], getProvinceNames(), 'Select a province from the list');
		$output .= form_textfield("Latitude", 'edit[latitude]', $data['latitude'], 12,12, "Latitude of field site");
		$output .= form_textfield("Longitude", 'edit[longitude]', $data['longitude'], 12,12, "Longitude of field site");

		$output .= form_select("City Ward", 'edit[ward_id]', $data['ward_id'],
			getOptionsFromQuery("SELECT ward_id as theKey, CONCAT(name, ' (', city, ' Ward ', num, ')') as theValue FROM ward ORDER BY ward_id"),
			"Official city ward this field is located in");

		$output .= form_textfield("Location Map", 'edit[location_url]', $data['location_url'],50, 255, "URL for image that shows how to reach the field");

		$output .= form_textfield("Layout Map", 'edit[layout_url]', $data['layout_url'], 50, 255, "URL for image that shows how to set up fields at the site");
		
		$output .= form_textfield("Field Permit", 'edit[permit_url]', $data['permit_url'], 50, 255, "URL for field permit (if required)");

		$output .= form_textarea("Site Directions", 'edit[site_directions]', $data['site_directions'], 60, 5, "Directions to field.  Please ensure that bus and bike directions are also provided if practical.");

		$output .= form_textarea("Special Instructions", 'edit[site_instructions]', $data['site_instructions'], 60, 5, "Specific instructions for this site (parking, other restrictions)");
		$output .= form_submit('Submit') .  form_reset('Reset');

		return form($output);	
	}

	function generateConfirm ( $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = form_hidden("edit[step]", "perform");

		$ratings = field_rating_values();
		if ( $edit['parent_fid'] ) {
			$parent = field_load( array('fid' => $edit['parent_fid']));
			$rows = array();
			$rows[] = array( "Name:", $parent->name );
			$rows[] = array( "Number:", form_hidden('edit[num]', $edit['num']) . check_form($edit['num']));
			$rows[] = array("Field Rating:", form_hidden('edit[rating]', $edit['rating']) . $ratings[$edit['rating']]);
			$rows[] = array("Parent Field:", form_hidden('edit[parent_fid]', $edit['parent_fid']) . $parent->fullname);
		} else {
			$ward = ward_load( array('ward_id' => $edit['ward_id']) );
			
			$rows = array();
			$rows[] = array( "Name:", form_hidden('edit[name]', $edit['name']) . check_form($edit['name']));
			$rows[] = array( "Number:", form_hidden('edit[num]', $edit['num']) . check_form($edit['num']));
			$rows[] = array("Field Rating:", form_hidden('edit[rating]', $edit['rating']) . $ratings[$edit['rating']]);
			$rows[] = array( "Code:", form_hidden('edit[code]', $edit['code']) . check_form($edit['code']));
			$rows[] = array( "Region:", form_hidden('edit[region]', $edit['region']) . check_form($edit['region']));
			
			$rows[] = array( "Street:", form_hidden('edit[location_street]', $edit['location_street']) . check_form($edit['location_street']));
			$rows[] = array( "City:", form_hidden('edit[location_city]', $edit['location_city']) . check_form($edit['location_city']));
			$rows[] = array( "Province:", form_hidden('edit[location_province]', $edit['location_province']) . check_form($edit['location_province']));
			$rows[] = array( "Latitude:", form_hidden('edit[latitude]', $edit['latitude']) . check_form($edit['latitude']));
			$rows[] = array( "Longitude:", form_hidden('edit[longitude]', $edit['longitude']) . check_form($edit['longitude']));
			
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
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		
		$field->set('num', $edit['num']);
		$field->set('rating', $edit['rating']);

		if( isset($edit['parent_fid']) ) {
			$field->set('parent_fid', $edit['parent_fid']);
		}
		
		if( $edit['parent_fid'] == 0 ) {
			$field->set('parent_fid', '' );
			$field->set('name', $edit['name']);
			$field->set('code', $edit['code']);
			$field->set('location_street', $edit['location_street']);
			$field->set('location_city', $edit['location_city']);
			$field->set('location_province', $edit['location_province']);
			$field->set('latitude', $edit['latitude']);
			$field->set('longitude', $edit['longitude']);
			
			$field->set('region', $edit['region']);
			$field->set('ward_id', $edit['ward_id']);
			$field->set('location_url', $edit['location_url']);
			$field->set('layout_url', $edit['layout_url']);
			$field->set('permit_url', $edit['permit_url']);
			$field->set('site_directions', $edit['site_directions']);
			$field->set('site_instructions', $edit['site_instructions']);
		}


		if( !$field->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = "";
		
		if( ! validate_number($edit['num']) ) {
			$errors .= "<li>Number of field must be provided";
		}
	
		$rating = field_rating_values();
		if( ! array_key_exists($edit['rating'], $rating) ) {
			$errors .= "<li>Rating must be provided";
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
	function has_permission()
	{
		global $session;
		return $session->has_permission('field','list');
	}

	function process ()
	{
	
		$this->setLocation(array('List Fields' => 'field/list'));

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
	var $field;
	
	function has_permission()
	{
		global $session;
		if (!$this->field) {
			error_exit("That field does not exist");
		}
		return $session->has_permission('field','view', $this->field->fid);
	}
	
	function process ()
	{
		global $session;
		$this->title= "View Field";

		$rows = array();
		$rows[] = array("Field Name:", $this->field->name);
		$rows[] = array("Field Code:", $this->field->code);
	
		$ratings = field_rating_values();
		$rows[] = array("Field Rating:", $ratings[$this->field->rating]);
		
		$rows[] = array("Number:", $this->field->num);
		$rows[] = array("Field Region:", $this->field->region);

		if( $this->field->location_street ) {
			$rows[] = array("Street Address:", 
				format_street_address(
					$this->field->location_street,
					$this->field->location_city,
					$this->field->location_province,
					''));
		}
	
		if( $this->field->latitude && $this->field->longitude) {
			$rows[] = array("Latitude:",  $this->field->latitude);
			$rows[] = array("Longitude:",  $this->field->longitude);
		}
	
		if( $this->field->ward_id ) {
			$ward = ward_load( array('ward_id' => $this->field->ward_id) );
			$rows[] = array("City Ward:", l("$ward->name ($ward->city Ward $ward->num)", "ward/view/" . $this->field->ward_id));
		}
		$rows[] = array("Location Map:", 
			$this->field->location_url ? l("Click for map in new window", $this->field->location_url, array('target' => '_new'))
				: "N/A");
		$rows[] = array("Layout Map:", 
			$this->field->layout_url ? l("Click for map in new window", $this->field->layout_url, array('target' => '_new'))
				: "N/A");
		$rows[] = array("Field Permit:", 
			$this->field->permit_url ? l("Click for permit in new window", $this->field->permit_url, array('target' => '_new'))
				: "N/A");
		$rows[] = array("Directions:", $this->field->site_directions);
		if( $session->has_permission('field','view', $this->field->fid, 'site_instructions') ) {
			$rows[] = array("Special Instructions:", $this->field->site_instructions);
		} else {
			$rows[] = array("Special Instructions:", "You must be logged in to see the special instructions for this site.");
		}
		
		// list other fields at this site
		if( $this->field->parent_fid ) {
			$result = db_query("SELECT * FROM field WHERE parent_fid = %d OR fid = %d ORDER BY num", $this->field->parent_fid, $this->field->parent_fid);
		} else {
			$result = db_query("SELECT * FROM field WHERE parent_fid = %d OR fid = %d ORDER BY num", $this->field->fid, $this->field->fid);
		}

		$fieldRows = array();
		$header = array("Fields","&nbsp;");
		while( $related = db_fetch_object( $result ) ) {
			$fieldRows[] = array(
				$this->field->code . " $related->num",
				l("view field", "field/view/$related->fid", array('title' => "View field details"))
			);
		}
		
		$rows[] = array("Fields at this site:", "<div class='listtable'>" . table($header,$fieldRows) . "</div>");
		
		$this->setLocation(array(
			$this->field->fullname => "field/view/" .$this->field->fid,
			$this->title => 0
		));
	
		return "<div class='pairtable'>" . table(null, $rows) . "</div>";
	}
}

/**
 * Field viewing handler
 */
class FieldBooking extends Handler
{
	var $field;

	function has_permission()
	{
		global $session;
		if (!$this->field) {
			error_exit("That field does not exist");
		}
		return $session->has_permission('field','view', $this->field->fid);
	}

	function process ()
	{
		global $session;

		$this->setLocation(array(
			'Availability and Bookings' => "field/view/" . $this->field->fid,
			$this->field->fullname => 0
		));

		$result = slot_query( array('fid' => $this->field->fid, '_order' => 'g.game_date, g.game_start'));

		$header = array("Date","Start Time","End Time","Booking", "Actions");
		$rows = array();
		while($slot = db_fetch_object($result)) {
			$booking = '';
			$actions = array();
			if( $session->has_permission('gameslot','edit', $slot->slot_id)) {
				$actions[] = l('change avail', "slot/availability/$slot->slot_id");
			}
			if( $session->has_permission('gameslot','delete', $slot->slot_id)) {
				$actions[] = l('delete', "slot/delete/$slot->slot_id");
			}
			if($slot->game_id) {
				$game = game_load( array('game_id' => $slot->game_id) );
				$booking = l($game->league_name,"game/view/$game->game_id");
				if( $session->has_permission('game','reschedule', $game->game_id)) {
					$actions[] = l('reschedule/move', "game/reschedule/$game->game_id");
				}
			}
			$rows[] = array($slot->game_date, $slot->game_start, $slot->game_end, $booking, theme_links($actions));
		}

		return "<div class='listtable'>" . table($header, $rows) . "</div>";
	}
}
?>
