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
		menu_add_child($site->name, "$site->name/create", "add field", array('link' => "field/create/$site->site_id", 'weight' => 2));
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
		$id = -1;
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($id, $edit);
				break;
			case 'perform':
				$this->perform(&$id, $edit);
				local_redirect(url("site/view/$id"));
				break;
			default:
				$edit = $this->getFormData( $id );
				$rc = $this->generateForm($id, $edit);
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}
	
	function perform ( $id, $edit )
	{
		db_query("INSERT into site (name,code) VALUES ('%s','%s')", $edit['name'], $edit['code']);
		if(1 != db_affected_rows() ) {
			return false;
		}
	
		$result = db_query("SELECT LAST_INSERT_ID() from site");
		if( !db_num_rows($result) ) {
			return false;
		}
		$id = db_result($result);
	
		return parent::perform($id, $edit);
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

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($id, $edit);
				break;
			case 'perform':
				$this->perform($id, $edit);
				local_redirect(url("site/view/$id"));
				break;
			default:
				$edit = $this->getFormData( $id );
				$rc = $this->generateForm( $edit );
		}
		$this->setLocation(array($edit['name']  => "site/view/$id", $this->title => 0));
		return $rc;
	}
	
	function getFormData( $id ) 
	{
		$site = site_load( array( 'site_id' => $id ) );
		return object2array($site);
	}

	function generateForm( $data = array() )
	{
		$output = form_hidden("edit[step]", "confirm");
		
		$rows = array();
		$rows[] = array( "Site Name:", form_textfield("", 'edit[name]', $data['name'], 35, 35, "Name of field site"));
			
		$rows[] = array( "Site Code:", form_textfield("", 'edit[code]', $data['code'], 3, 3, "Three-letter abbreviation for field site"));
			
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

	function generateConfirm ( $id, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = form_hidden("edit[step]", "perform");
		
		$rows = array();
		$rows[] = array( "Site Name:", form_hidden('edit[name]', $edit['name']) . check_form($edit['name']));
		$rows[] = array( "Site Code:", form_hidden('edit[code]', $edit['code']) . check_form($edit['code']));
		$rows[] = array( "Site Region:", form_hidden('edit[region]', $edit['region']) . check_form($edit['region']));
		$rows[] = array( "City Ward:", form_hidden('edit[ward_id]', $edit['ward_id']) .  getWardName($edit['ward_id']));
		$rows[] = array( "Site Location Map:", form_hidden('edit[location_url]', $edit['location_url']) . check_form($edit['location_url']));
		$rows[] = array( "Site Layout Map:", form_hidden('edit[layout_url]', $edit['layout_url']) . check_form($edit['layout_url']));
		$rows[] = array( "Directions:", form_hidden('edit[directions]', $edit['directions']) . check_form($edit['directions']));
		$rows[] = array( "Special Instructions:", form_hidden('edit[instructions]', $edit['instructions']) . check_form($edit['instructions']));
		$rows[] = array( form_submit('Submit'), "");
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		
		return form($output);
	}

	function perform ( $id, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		db_query("UPDATE site SET 
			name = '%s', code = '%s', 
			region = '%s', ward_id = %d,
			location_url = '%s', layout_url = '%s', 
			directions = '%s', 
			instructions = '%s' 
			WHERE site_id = %d",
			array(
				$edit['name'],
				$edit['code'],
				$edit['region'],
				($edit['ward_id'] == 0) ? NULL : $edit['ward_id'],
				$edit['location_url'],
				$edit['layout_url'],
				$edit['directions'],
				$edit['instructions'],
				$this->id,
			)
		);
	
		if( 1 != db_affected_rows() ) {
			return false;
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

		$result = db_query( "SELECT s.site_id, s.name, s.region FROM site s ORDER BY s.region,s.name");

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
			$this->error_exit("The site [$id] does not exist");
		}
	
		/* and list fields at this site */
		$result = db_query("SELECT * FROM field WHERE site_id = %d ORDER BY num", $id);

		$fieldRows = array();
		$header = array("Field","Status","&nbsp;");
		while($field = db_fetch_object($result)) {
			$fieldOps = array( 
				l("view", "field/view/$field->field_id", array('title' => "View field entry"))
			);
			if($this->_permissions["site_edit"]) {
				$fieldOps[] = l("edit", "field/edit/$field->field_id", array('title' => "Edit field entry"));
			}
			$fieldRows[] = array(
				"$site->code $field->num",
				$field->status,
				theme_links($fieldOps)
			);
		}
		
		$rows = array();
		$rows[] = array("Site Name:", $site->name);
		$rows[] = array("Site Code:", $site->code);
		$rows[] = array("Site Region:", $site->region);
		$rows[] = array("City Ward:", l(getWardName($site->ward_id), "ward/view/$site->ward_id"));
		$rows[] = array("Site Location Map:", 
			$site->location_url ? l("Click for map in new window", $site->location_url, array('target' => '_new'))
				: "No Map");
		$rows[] = array("Field Layout Map:", 
			$site->layout_url ? l("Click for map in new window", $site->layout_url, array('target' => '_new'))
				: "No Map");
		$rows[] = array("Directions:", $site->directions);
		$rows[] = array("Special Instrutions:", $site->instructions);
		
		$rows[] = array("Fields:", "<div class='listtable'>" . table($header,$fieldRows) . "</div>");
		
		$this->setLocation(array(
			$site->name => "site/view/$site->site_id",
			$this->title => 0
		));
	
		site_add_to_menu($site);
		return "<div class='pairtable'>" . table(null, $rows) . "</div>";
	}
}

/**
 * Load a single site object from the database using the supplied query
 * data.  If more than one site matches, we will return only the first one.
 * If fewer than one matches, we return null.
 *
 * @param	mixed 	$array key-value pairs that identify the site to be loaded.
 */
function site_load ( $array = array() )
{
	$query = array();

	foreach ($array as $key => $value) {
		if($key == '_extra') {
			/* Just slap on any extra query fields desired */
			$query[] = $value;
		} else {
			$query[] = "s.$key = '" . check_query($value) . "'";
		}
	}
	
	$result = db_query_range("SELECT 
		s.*
	 	FROM site s
		WHERE " . implode(' AND ',$query),0,1);

	/* TODO: we may want to abort here instead */
	if(1 != db_num_rows($result)) {
		return null;
	}

	return db_fetch_object($result);
}
?>
