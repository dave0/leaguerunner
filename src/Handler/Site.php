<?php

/*
 * Handlers for dealing with field sites
 */
function site_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new SiteCreate;
		case 'edit':
			return new SiteEdit;
		case 'view':
			return new SiteView;
		case 'list':
			return new SiteList;
	}
	return null;
}

function site_menu()
{
	global $session;
	menu_add_child('_root','site','Field Sites');
	menu_add_child('site','site/list','list fields', array('link' => 'site/list') );

	
	if($session->is_admin()) {
		menu_add_child('site','site/create','create field site', array('weight' => 5, 'link' => 'site/create') );
	}
}

/**
 * Add view/edit/delete links to the menu for the given site
 * TODO: fix ugly evil things like SiteEdit so that this can be called to add
 * site being edited to the menu.
 */
function site_add_to_menu( &$site, $parent = 'site' ) 
{
	global $session;
	
	menu_add_child($parent, $site->name, $site->name, array('weight' => -10, 'link' => "site/view/$site->site_id"));
	
	if($session->is_admin()) {
		menu_add_child($site->name, "$site->name/edit",'edit site', array('weight' => 1, 'link' => "site/edit/$site->site_id"));
	}
}	

class SiteCreate extends SiteEdit
{
	function initialize ()
	{
		$this->title = "Create Field Site";
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
				$site = new Site;
				$this->perform($site, $edit);
				local_redirect(url("site/view/$site->site_id"));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm($edit);
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}
}

class SiteEdit extends Handler
{
	function initialize ()
	{
		$this->title = "Edit Field Site";
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
		$site = site_load( array('site_id' => $id) );
		if (!$site) {
			$this->error_exit("That site does not exist");
		}

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$this->perform($site, $edit);
				local_redirect(url("site/view/$id"));
				break;
			default:
				$edit = object2array($site);
				$rc = $this->generateForm( $edit );
		}

		site_add_to_menu($site);
		$this->setLocation(array($edit['name']  => "site/view/$id", $this->title => 0));
		return $rc;
	}
	
	function generateForm( $data = array() )
	{
		$output = form_hidden("edit[step]", "confirm");

		$rows = array();
		$rows[] = array( "Site Name:", form_textfield("", 'edit[name]', $data['name'], 35, 35, "Name of field site"));

		$rows[] = array( "Site Code:", form_textfield("", 'edit[code]', $data['code'], 3, 3, "Three-letter abbreviation for field site"));

		$rows[] = array( "Number of Fields:", form_textfield("", 'edit[num_fields]', $data['num_fields'], 3, 3, "Number of fields at this site"));

		$rows[] = array( "Site Region:", form_select("", 'edit[region]', $data['region'], getOptionsFromEnum('site', 'region'), "Area of city this site is located in"));

		$rows[] = array( "City Ward:", form_select("", 'edit[ward_id]', $data['ward_id'],
			getOptionsFromQuery("SELECT ward_id as theKey, CONCAT(name, ' (', city, ' Ward ', num, ')') as theValue FROM ward ORDER BY ward_id"),
			"Official city ward this site is located in"));

		$rows[] = array( "Site Location Map:", form_textfield("", 'edit[location_url]', $data['location_url'],50, 255, "URL for image that shows how to reach the site"));

		$rows[] = array( "Site Layout Map:", form_textfield("", 'edit[layout_url]', $data['layout_url'], 50, 255, "URL for image that shows how to set up fields at the site"));

		$rows[] = array( "Directions:", form_textarea("", 'edit[directions]', $data['directions'], 60, 5, "Directions to field site.  Please ensure that bus and bike directions are also provided if practical."));

		$rows[] = array( "Special Instructions:", form_textarea("", 'edit[instructions]', $data['instructions'], 60, 5, "Specific instructions for this site (parking, other restrictions)"));
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
		
		$ward = ward_load( array('ward_id' => $edit['ward_id']) );
		
		$rows = array();
		$rows[] = array( "Site Name:", form_hidden('edit[name]', $edit['name']) . check_form($edit['name']));
		$rows[] = array( "Site Code:", form_hidden('edit[code]', $edit['code']) . check_form($edit['code']));
		$rows[] = array( "Number of Fields:", form_hidden('edit[num_fields]', $edit['num_fields']) . check_form($edit['num_fields']));
		$rows[] = array( "Site Region:", form_hidden('edit[region]', $edit['region']) . check_form($edit['region']));
		$rows[] = array( "City Ward:", form_hidden('edit[ward_id]', $edit['ward_id']) .  "$ward->name ($ward->city Ward $ward->num)");
		$rows[] = array( "Site Location Map:", form_hidden('edit[location_url]', $edit['location_url']) . check_form($edit['location_url']));
		$rows[] = array( "Site Layout Map:", form_hidden('edit[layout_url]', $edit['layout_url']) . check_form($edit['layout_url']));
		$rows[] = array( "Directions:", form_hidden('edit[directions]', $edit['directions']) . check_form($edit['directions']));
		$rows[] = array( "Special Instructions:", form_hidden('edit[instructions]', $edit['instructions']) . check_form($edit['instructions']));
		$rows[] = array( form_submit('Submit'), "");
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		
		return form($output);
	}

	function perform ( &$site, &$edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$site->set('name', $edit['name']);
		$site->set('code', $edit['code']);
		$site->set('num_fields', $edit['num_fields']);
		$site->set('region', $edit['region']);
		$site->set('ward_id', $edit['ward_id']);
		$site->set('location_url', $edit['location_url']);
		$site->set('layout_url', $edit['layout_url']);
		$site->set('directions', $edit['directions']);
		$site->set('instructions', $edit['instructions']);

		if( !$site->save() ) {
			$this->error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = "";

		if( !validate_nonhtml($edit['name'] ) ) {
			$errors .= "<li>Name cannot be left blank, and cannot contain HTML";
		}
		if( !validate_nonhtml($edit['code'] ) ) {
			$errors .= "<li>Code cannot be left blank and cannot contain HTML";
		}
		
		if( ! validate_number($edit['num_fields']) ) {
			$errors .= "<li>Number of fields must be provided";
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
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

class SiteList extends Handler
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
		$this->setLocation(array('List Field Sites' => 'field/list'));
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

		$result = site_query( array( '_order' => 's.region,s.name') );

		$fieldsByRegion = array();
		while($field = db_fetch_object($result)) {
			if(! array_key_exists( $field->region, $fieldsByRegion) ) {
				$fieldsByRegion[$field->region] = "";
			}
			$fieldsByRegion[$field->region] 
				.= l($field->name, "site/view/$field->site_id") . "<br />";
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

class SiteView extends Handler
{
	function initialize ()
	{
		$this->title= "View Field Site";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'allow',
		);
		$this->_permissions = array(
			'site_edit'			=> false,
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

		$site = site_load( array('site_id' => $id) );
		if(!$site) {
			$this->error_exit("That site does not exist");
		}
	
		$rows = array();
		$rows[] = array("Site Name:", $site->name);
		$rows[] = array("Site Code:", $site->code);
		$rows[] = array("Number of Fields:", $site->num_fields);
		$rows[] = array("Site Region:", $site->region);
		$rows[] = array("City Ward:", l("$site->ward_name ($site->ward_city Ward $site->ward_num)", "ward/view/$site->ward_id"));
		$rows[] = array("Site Location Map:", 
			$site->location_url ? l("Click for map in new window", $site->location_url, array('target' => '_new'))
				: "No Map");
		$rows[] = array("Field Layout Map:", 
			$site->layout_url ? l("Click for map in new window", $site->layout_url, array('target' => '_new'))
				: "No Map");
		$rows[] = array("Directions:", $site->directions);
		$rows[] = array("Special Instrutions:", $site->instructions);
		
		/* and list fields at this site */
		$result = db_query("SELECT * FROM field WHERE site_id = %d ORDER BY num", $id);

		$fieldRows = array();
		$header = array("Field","&nbsp;");
		for( $field_num = 1; $field_num <= $site->num_fields; $field_num++) {
			$fieldRows[] = array(
				"$site->code $field_num",
				l("view field", "field/view/$site->site_id/$field_num", array('title' => "View field details"))
			);
		}
		
		$rows[] = array("Fields:", "<div class='listtable'>" . table($header,$fieldRows) . "</div>");
		
		$this->setLocation(array(
			$site->name => "site/view/$site->site_id",
			$this->title => 0
		));
	
		site_add_to_menu($site);
		return "<div class='pairtable'>" . table(null, $rows) . "</div>";
	}
}
?>
