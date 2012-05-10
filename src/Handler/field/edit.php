<?php
require_once('Handler/FieldHandler.php');

class field_edit extends FieldHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','edit', $this->field->fid);
	}

	function process ()
	{
		$this->title = "Edit Field: {$this->field->fullname}";
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
				$rc = $this->generateForm( (array)$this->field );
		}

		return $rc;
	}

	function generateForm( $data = array() )
	{
		$output = form_hidden("edit[step]", "confirm");

		$output .= form_textfield("Field Identification", 'edit[num]', $data['num'], 15, 15, "Location of this field at the given site; cannot be 0");

		$output .= form_select("Field Status", 'edit[status]', $data['status'], array('open' => 'open', 'closed' => 'closed'));

		$output .= form_select("Field Rating", 'edit[rating]', $data['rating'], field_rating_values(), "Rate this field on the scale provided");

		// TODO: Should become Field::get_eligible_parents()
		$sth = Field::query( array('_extra' => 'ISNULL(parent_fid)', '_order' => 'f.name,f.num') );
		$parents = array();
		$parents[0] = "---";
		while($p = $sth->fetch(PDO::FETCH_OBJ) ) {
			$parents[$p->fid] = $p->fullname;
		}

		$output .= form_select("Parent Field", 'edit[parent_fid]', $data['parent_fid'], $parents, "Inherit location and name from other field");

		if( ! $data['parent_fid'] )  {

			$output .= form_textfield("Field Name", 'edit[name]', $data['name'], 35, 255, "Name of field (do not append number)");

			$output .= form_textfield("Field Code", 'edit[code]', $data['code'], 3, 3, "Three-letter abbreviation for field site");

			$output .= form_select("Region", 'edit[region]', $data['region'], getOptionsFromEnum('field', 'region'), "Area of city this field is located in");

			$output .= form_select("Is indoor", 'edit[is_indoor]', $data['is_indoor'], array( 0 => 'No', 1 => 'Yes'), "Is this an indoor field");

			$output .= form_textfield('Street and Number','edit[location_street]',$data['location_street'], 25, 100);

			$output .= form_textfield('City','edit[location_city]',$data['location_city'], 25, 100, 'Name of city');

			$output .= form_select('Province/State', 'edit[location_province]', $data['location_province'], getProvinceStateNames(), 'Select a province or state from the list');

			$output .= form_select('Country', 'edit[location_country]', $data['location_country'], getCountryNames(), 'Select a country from the list');

			$output .= form_textfield("Postal Code", 'edit[location_postalcode]', $data['location_postalcode'],25, 50, "Postal code");

			$output .= form_textfield("Location Map", 'edit[location_url]', $data['location_url'],50, 255, "URL for image that shows how to reach the field");

			$output .= form_textfield("Layout Map", 'edit[layout_url]', $data['layout_url'], 50, 255, "URL for image that shows how to set up fields at the site");

			$output .= form_textarea("Driving Directions", 'edit[driving_directions]', $data['driving_directions'], 60, 5, "");

			$output .= form_textarea("Parking Details", 'edit[parking_details]', $data['parking_details'], 60, 5, "");

			$output .= form_textarea("Transit Directions", 'edit[transit_directions]', $data['transit_directions'], 60, 5, "");

			$output .= form_textarea("Biking Directions", 'edit[biking_directions]', $data['biking_directions'], 60, 5, "");

			$output .= form_textarea("Public Washrooms", 'edit[washrooms]', $data['washrooms'], 60, 5, "");

			$output .= form_textarea("Special Instructions", 'edit[public_instructions]', $data['public_instructions'], 60, 5, "Specific instructions for this site that don't fit any other category.");

			$output .= form_textarea("Private Instructions", 'edit[site_instructions]', $data['site_instructions'], 60, 5, "Instructions for this site that should be shown only to logged-in members.");

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
			$parent = Field::load( array('fid' => $edit['parent_fid']));
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
			$rows[] = array("Is&nbsp;indoor:", form_hidden('edit[is_indoor]', $edit['is_indoor']) . ($edit['is_indoor'] ? 'Yes' : 'No'));
			$rows[] = array( "Code:", form_hidden('edit[code]', $edit['code']) . check_form($edit['code']));
			$rows[] = array( "Region:", form_hidden('edit[region]', $edit['region']) . check_form($edit['region']));

			$rows[] = array( "Street:", form_hidden('edit[location_street]', $edit['location_street']) . check_form($edit['location_street']));
			$rows[] = array( "City:", form_hidden('edit[location_city]', $edit['location_city']) . check_form($edit['location_city']));
			$rows[] = array( "Province:", form_hidden('edit[location_province]', $edit['location_province']) . check_form($edit['location_province']));
			$rows[] = array( "Country:", form_hidden('edit[location_country]', $edit['location_country']) . check_form($edit['location_country']));
			$rows[] = array( "Postal&nbsp;Code:", form_hidden('edit[location_postalcode]', $edit['location_postalcode']) . check_form($edit['location_postalcode']));

			$rows[] = array( "Location&nbsp;Map:", form_hidden('edit[location_url]', $edit['location_url']) . check_form($edit['location_url']));
			$rows[] = array( "Layout&nbsp;Map:", form_hidden('edit[layout_url]', $edit['layout_url']) . check_form($edit['layout_url']));
			$rows[] = array( "Driving Directions:", form_hidden('edit[driving_directions]', $edit['driving_directions']) . check_form($edit['driving_directions']));
			$rows[] = array( "Parking Details:", form_hidden('edit[parking_details]', $edit['parking_details']) . check_form($edit['parking_details']));
			$rows[] = array( "Transit Directions:", form_hidden('edit[transit_directions]', $edit['transit_directions']) . check_form($edit['transit_directions']));
			$rows[] = array( "Biking Directions:", form_hidden('edit[biking_directions]', $edit['biking_directions']) . check_form($edit['biking_directions']));
			$rows[] = array( "Public Washrooms:", form_hidden('edit[washrooms]', $edit['washrooms']) . check_form($edit['washrooms']));
			$rows[] = array( "Special Instructions:", form_hidden('edit[public_instructions]', $edit['public_instructions']) . check_form($edit['public_instructions']));
			$rows[] = array( "Private Instructions:", form_hidden('edit[site_instructions]', $edit['site_instructions']) . check_form($edit['site_instructions']));
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
			$field->set('location_country', $edit['location_country']);
			$field->set('location_postalcode', $edit['location_postalcode']);
			$field->set('is_indoor', $edit['is_indoor']);

			$field->set('region', $edit['region']);
			$field->set('location_url', $edit['location_url']);
			$field->set('layout_url', $edit['layout_url']);
			$field->set('driving_directions', $edit['driving_directions']);
			$field->set('transit_directions', $edit['transit_directions']);
			$field->set('biking_directions', $edit['biking_directions']);
			$field->set('parking_details', $edit['parking_details']);
			$field->set('washrooms', $edit['washrooms']);
			$field->set('public_instructions', $edit['public_instructions']);
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
?>
