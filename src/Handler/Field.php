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
			$obj = new FieldList;
			$obj->closed = ( isset( $id ) && $id == 'closed' );
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
			// Only admin can create
			break;
		case 'edit':
			// Admin and "volunteer" can edit
			if($user && $user->class == 'volunteer') {
				return true;
			}
			break;
		case 'view':
			// Everyone can view, but valid users get extra info
			if($user && $user->is_active()) {
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
			// Everyone can list open fields, only admins can list closed; $fid here is the "closed" bool
			if (!$fid) {
				return true;
			}
			break;
		case 'view bookings':
			// Only valid users can view bookings
			if($user && $user->is_active()) {
				return true;
			}
	}

	return false;
}

function field_menu()
{
	global $lr_session;
	menu_add_child('_root','field','Fields');
	menu_add_child('field','field/list','list fields', array('link' => 'field/list') );

	if( $lr_session->has_permission('field','edit') ) {
		menu_add_child('field','field/list/closed','list closed fields', array('weight' => 3, 'link' => 'field/list/closed') );
	}

	if( $lr_session->has_permission('field','create') ) {
		menu_add_child('field','field/create','create field', array('weight' => 5, 'link' => 'field/create') );
	}
}

/**
 * Add view/edit/delete links to the menu for the given field
 */
function field_add_to_menu( &$field ) 
{
	global $lr_session;

	menu_add_child('field', $field->fullname, $field->fullname, array('weight' => -10, 'link' => "field/view/$field->fid"));

	if($lr_session->has_permission('field','view bookings', $field->fid) ) {
		menu_add_child($field->fullname, "$field->fullname bookings", "view bookings", array('link' => "field/bookings/$field->fid"));
	}

	if($lr_session->has_permission('field','edit', $field->fid) ) {
		menu_add_child($field->fullname, "$field->fullname/edit",'edit field', array('weight' => 1, 'link' => "field/edit/$field->fid"));
	} 

	if($lr_session->has_permission('gameslot','create', $field->fid) ) {
		menu_add_child($field->fullname, "$field->fullname gameslot", 'new gameslot', array('link' => "slot/create/$field->fid"));
	}
}

class FieldCreate extends FieldEdit
{
	var $field;

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','create');
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
		global $lr_session;
		if (!$this->field) {
			error_exit("That field does not exist");
		}
		return $lr_session->has_permission('field','edit', $this->field->fid);
	}

	function process ()
	{
		$this->title = "Edit Field";
		$this->setLocation(array($this->field->fullname  => "field/view/".$this->field->fid, $this->title => 0));
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

		return $rc;
	}

	function generateForm( $data = array() )
	{
		$output = form_hidden("edit[step]", "confirm");

		$output .= form_textfield("Field Identification", 'edit[num]', $data['num'], 15, 15, "Location of this field at the given site; cannot be 0");

		$output .= form_select("Field Status", 'edit[status]', $data['status'], array('open' => 'open', 'closed' => 'closed'));

		$output .= form_select("Field Rating", 'edit[rating]', $data['rating'], field_rating_values(), "Rate this field on the scale provided");

		$result = field_query( array('_extra' => 'ISNULL(parent_fid)', '_order' => 'f.name,f.num') );
		$parents = array();
		$parents[0] = "---";
		while($p = db_fetch_object($result)) {
			$parents[$p->fid] = $p->fullname;
		}

		$output .= form_select("Parent Field", 'edit[parent_fid]', $data['parent_fid'], $parents, "Inherit location and name from other field");

		if( ! $data['parent_fid'] )  {

			$output .= form_textfield("Field Name", 'edit[name]', $data['name'], 35, 35, "Name of field (do not append number)");

			$output .= form_textfield("Field Code", 'edit[code]', $data['code'], 3, 3, "Three-letter abbreviation for field site");

			$output .= form_select("Region", 'edit[region]', $data['region'], getOptionsFromEnum('field', 'region'), "Area of city this field is located in");

			$output .= form_textfield('Street and Number','edit[location_street]',$data['location_street'], 25, 100);

			$output .= form_textfield('City','edit[location_city]',$data['location_city'], 25, 100, 'Name of city');

			$output .= form_select('Province', 'edit[location_province]', $data['location_province'], getProvinceNames(), 'Select a province from the list');
			$output .= form_textfield("Latitude", 'edit[latitude]', $data['latitude'], 12,12, "Latitude of field site");
			$output .= form_textfield("Longitude", 'edit[longitude]', $data['longitude'], 12,12, "Longitude of field site");

			$output .= form_textfield("Location Map", 'edit[location_url]', $data['location_url'],50, 255, "URL for image that shows how to reach the field");

			$output .= form_textfield("Layout Map", 'edit[layout_url]', $data['layout_url'], 50, 255, "URL for image that shows how to set up fields at the site");

			$output .= form_textarea("Driving Directions", 'edit[driving_directions]', $data['driving_directions'], 60, 5, "");

			$output .= form_textarea("Parking Details", 'edit[parking_details]', $data['parking_details'], 60, 5, "");

			$output .= form_textarea("Transit Directions", 'edit[transit_directions]', $data['transit_directions'], 60, 5, "");

			$output .= form_textarea("Biking Directions", 'edit[biking_directions]', $data['biking_directions'], 60, 5, "");

			$output .= form_textarea("Public Washrooms", 'edit[washrooms]', $data['washrooms'], 60, 5, "");

			$output .= form_textarea("Special Instructions", 'edit[site_instructions]', $data['site_instructions'], 60, 5, "Specific instructions for this site that don't fit any other category.");

			$output .= form_textarea("Sponsorship", 'edit[sponsor]', $data['sponsor'], 60, 5, "");
		}
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
			$rows[] = array( "Status:", form_hidden('edit[status]', $edit['status']) . check_form($edit['status']));
			$rows[] = array( "Number:", form_hidden('edit[num]', $edit['num']) . check_form($edit['num']));
			$rows[] = array("Field&nbsp;Rating:", form_hidden('edit[rating]', $edit['rating']) . $ratings[$edit['rating']]);
			$rows[] = array("Parent&nbsp;Field:", form_hidden('edit[parent_fid]', $edit['parent_fid']) . $parent->fullname);
		} else {

			$rows = array();
			$rows[] = array( "Name:", form_hidden('edit[name]', $edit['name']) . check_form($edit['name']));
			$rows[] = array( "Status:", form_hidden('edit[status]', $edit['status']) . check_form($edit['status']));
			$rows[] = array( "Number:", form_hidden('edit[num]', $edit['num']) . check_form($edit['num']));
			$rows[] = array("Field&nbsp;Rating:", form_hidden('edit[rating]', $edit['rating']) . $ratings[$edit['rating']]);
			$rows[] = array( "Code:", form_hidden('edit[code]', $edit['code']) . check_form($edit['code']));
			$rows[] = array( "Region:", form_hidden('edit[region]', $edit['region']) . check_form($edit['region']));

			$rows[] = array( "Street:", form_hidden('edit[location_street]', $edit['location_street']) . check_form($edit['location_street']));
			$rows[] = array( "City:", form_hidden('edit[location_city]', $edit['location_city']) . check_form($edit['location_city']));
			$rows[] = array( "Province:", form_hidden('edit[location_province]', $edit['location_province']) . check_form($edit['location_province']));
			$rows[] = array( "Latitude:", form_hidden('edit[latitude]', $edit['latitude']) . check_form($edit['latitude']));
			$rows[] = array( "Longitude:", form_hidden('edit[longitude]', $edit['longitude']) . check_form($edit['longitude']));

			$rows[] = array( "Location&nbsp;Map:", form_hidden('edit[location_url]', $edit['location_url']) . check_form($edit['location_url']));
			$rows[] = array( "Layout&nbsp;Map:", form_hidden('edit[layout_url]', $edit['layout_url']) . check_form($edit['layout_url']));
			$rows[] = array( "Driving Directions:", form_hidden('edit[driving_directions]', $edit['driving_directions']) . check_form($edit['driving_directions']));
			$rows[] = array( "Parking Details:", form_hidden('edit[parking_details]', $edit['parking_details']) . check_form($edit['parking_details']));
			$rows[] = array( "Transit Directions:", form_hidden('edit[transit_directions]', $edit['transit_directions']) . check_form($edit['transit_directions']));
			$rows[] = array( "Biking Directions:", form_hidden('edit[biking_directions]', $edit['biking_directions']) . check_form($edit['biking_directions']));
			$rows[] = array( "Public Washrooms:", form_hidden('edit[washrooms]', $edit['washrooms']) . check_form($edit['washrooms']));
			$rows[] = array( "Special Instructions:", form_hidden('edit[site_instructions]', $edit['site_instructions']) . check_form($edit['site_instructions']));
			$rows[] = array( "Sponsorship:", form_hidden('edit[sponsor]', $edit['sponsor']) . check_form($edit['sponsor']));
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
		$field->set('status', $edit['status']);
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
			$field->set('location_url', $edit['location_url']);
			$field->set('layout_url', $edit['layout_url']);
			$field->set('driving_directions', $edit['driving_directions']);
			$field->set('transit_directions', $edit['transit_directions']);
			$field->set('biking_directions', $edit['biking_directions']);
			$field->set('parking_details', $edit['parking_details']);
			$field->set('washrooms', $edit['washrooms']);
			$field->set('site_instructions', $edit['site_instructions']);
			$field->set('sponsor', $edit['sponsor']);
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
		global $lr_session;
		return $lr_session->has_permission('field','list',$this->closed);
	}

	function process ()
	{
		$output = '';
		if( $this->closed ) {
			$this->setLocation(array('List Closed Fields' => 'field/list'));
		} else {
			$this->setLocation(array('List Fields' => 'field/list'));

			ob_start();
			$retval = @readfile("data/field_caution.html");
			if (false !== $retval) {
				$output .= ob_get_contents();
			}           
			ob_end_clean();
		}

		if( $this->closed ) {
			$status = "AND status = 'closed'";
		} else {
			$status = "AND (status = 'open' OR ISNULL(status))";
		}
		$result = field_query( array( '_extra' => "ISNULL(parent_fid) $status", '_order' => 'f.region,f.name') );

		$fieldsByRegion = array();
		while($field = db_fetch_object($result)) {
			if(! array_key_exists( $field->region, $fieldsByRegion) ) {
				$fieldsByRegion[$field->region] = "";
			}
			$fieldsByRegion[$field->region] 
				.= li( l($field->name, "field/view/$field->fid") );
		}

		$fieldColumns = array();
		$header = array();

		while(list($region,$fields) = each($fieldsByRegion)) {
			$fieldColumns[] = ul( $fields );
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
		global $lr_session;
		if (!$this->field) {
			error_exit("That field does not exist");
		}
		return $lr_session->has_permission('field','view', $this->field->fid);
	}

	function process ()
	{
		global $lr_session;
		$this->title= "View Field";

		$rows = array();
		$rows[] = array("Field&nbsp;Name:", $this->field->name);
		$rows[] = array("Field&nbsp;Code:", $this->field->code);
		$rows[] = array("Field&nbsp;Status:", $this->field->status);

		$ratings = field_rating_values();
		$rows[] = array("Field&nbsp;Rating:", $ratings[$this->field->rating]);
		
		$rows[] = array("Number:", $this->field->num);
		$rows[] = array("Field&nbsp;Region:", $this->field->region);

		if( $this->field->location_street ) {
			$rows[] = array("Address:", 
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

		$rows[] = array("Map:", 
			$this->field->location_url ? l("Click for map in new window", $this->field->location_url, array('target' => '_new'))
				: "N/A");
		$rows[] = array("Layout:", 
			$this->field->layout_url ? l("Click for field layout diagram in new window", $this->field->layout_url, array('target' => '_new'))
				: "N/A");

		if( $this->field->permit_url ) {
			$rows[] = array("Field&nbsp;Permit:", $this->field->permit_url);
		}
		$rows[] = array('Driving Directions:', $this->field->driving_directions);
		if( $this->field->parking_details ) {
			$rows[] = array('Parking Details:', "<div class='parking'>{$this->field->parking_details}</div>");
		}
		if( $this->field->transit_directions ) {
			$rows[] = array('Transit Directions:', "<div class='transit'>{$this->field->transit_directions}</div>");
		}
		if( $this->field->biking_directions ) {
			$rows[] = array('Biking Directions:', "<div class='biking'>{$this->field->biking_directions}</div>");
		}
		if( $this->field->washrooms ) {
			$rows[] = array('Public Washrooms:', "<div class='washrooms'>{$this->field->washrooms}</div>");
		}
		if( $this->field->site_instructions ) {
			if( $lr_session->has_permission('field','view', $this->field->fid, 'site_instructions') ) {
				$rows[] = array("Special Instructions:", $this->field->site_instructions);
			} else {
				$rows[] = array("Special Instructions:", "You must be logged in to see the special instructions for this site.");
			}
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

		// Add sponsorship details
		$sponsor = '';
		if( $this->field->sponsor ) {
			$sponsor = "<div class='sponsor'>{$this->field->sponsor}</div>";
		}

		return "<div class='pairtable'>" . table(null, $rows, array('alternate-colours' => true)) . "</div>\n$sponsor";
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
		global $lr_session;
		if (!$this->field) {
			error_exit("That field does not exist");
		}
		if ($this->field->status != 'open') {
			error_exit("That field is closed");
		}
		return $lr_session->has_permission('field','view', $this->field->fid);
	}

	function process ()
	{
		global $lr_session;

		$this->setLocation(array(
			'Availability and Bookings' => "field/view/" . $this->field->fid,
			$this->field->fullname => 0
		));

		$result = slot_query( array('fid' => $this->field->fid,
									'_extra' => 'YEAR(g.game_date) = YEAR(NOW())',
									'_order' => 'g.game_date, g.game_start'));

		$header = array("Date","Start Time","End Time","Booking", "Actions");
		$rows = array();
		while($slot = db_fetch_object($result)) {
			$booking = '';
			$actions = array();
			if( $lr_session->has_permission('gameslot','edit', $slot->slot_id)) {
				$actions[] = l('change avail', "slot/availability/$slot->slot_id");
			}
			if( $lr_session->has_permission('gameslot','delete', $slot->slot_id)) {
				$actions[] = l('delete', "slot/delete/$slot->slot_id");
			}
			if($slot->game_id) {
				$game = game_load( array('game_id' => $slot->game_id) );
				$booking = l($game->league_name,"game/view/$slot->game_id");
				if( $lr_session->has_permission('game','reschedule', $slot->game_id)) {
					$actions[] = l('reschedule/move', "game/reschedule/$slot->game_id");
				}
			}
			$rows[] = array($slot->game_date, $slot->game_start, $slot->game_end, $booking, theme_links($actions));
		}

		return "<div class='listtable'>" . table($header, $rows) . "</div>";
	}
}
?>
