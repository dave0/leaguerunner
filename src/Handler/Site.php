<?php

/*
 * Handlers for dealing with field sites
 */
register_page_handler('field', 'SiteList');

register_page_handler('site_create', 'SiteCreate');
register_page_handler('site_edit', 'SiteEdit');
register_page_handler('site_list', 'SiteList');
register_page_handler('site_view', 'SiteView');

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
		$this->op = "site_create";
		$this->section = 'field';
		return true;
	}
	
	function perform ()
	{
		$site = var_from_getorpost("site");
	
		db_query("INSERT into site (name,code) VALUES ('%s','%s')", $site['name'], $site['code']);
		if(1 != db_affected_rows() ) {
			return false;
		}
	
		/* TODO Make $this->id go away */
		$this->id = db_result(db_query("SELECT LAST_INSERT_ID() from site"));
	
		return parent::perform();
	}

}

class SiteEdit extends Handler
{

	var $id;

	function initialize ()
	{
		$this->title = "Edit Field Site";
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'deny'
		);
		$this->op = "site_edit";
		$this->section = 'field';
		return true;
	}

	function process ()
	{
		$step = var_from_getorpost('step');
		$this->id = var_from_getorpost('id');

		switch($step) {
			case 'confirm':
				$dataInvalid = $this->isDataInvalid();
				if($dataInvalid) {
					$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
				}

				return $this->generateConfirm(var_from_getorpost('site'));
				break;
			case 'perform':
				$dataInvalid = $this->isDataInvalid();
				if($dataInvalid) {
					$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
				}
				$this->perform();
				local_redirect("op=site_view&id=". $this->id);
				break;
			default:
				if($this->id) {
					
					$result = db_query(
						"SELECT * FROM site WHERE site_id = %d", $this->id);
					if( 1 != db_num_rows($result) ) {
						return false;
					}
					$row = db_fetch_array($result);

				} else {
					$row = array();
				}
				return $this->generateForm($row);
		}
	}

	function generateForm( $data = array() )
	{

		$output = form_hidden("op", $this->op);
		$output .= form_hidden("step", "confirm");
		if($this->id) {
			$output .= form_hidden("id", $this->id);
		}
		
		$rows = array();
		$rows[] = array(
			"Site Name:",
			form_textfield("", 'site[name]', $data['name'], 35, 35, "Name of field site"));
			
		$rows[] = array(
			"Site Code:",
			form_textfield("", 'site[code]', $data['code'], 3, 3, "Three-letter abbreviation for field site"));
			
		$rows[] = array(
			"Site Region:",
			form_select("", 'site[region]', $data['region'], getOptionsFromEnum('site', 'region'), "Area of city this site is located in"));
			
		$rows[] = array(
			"City Ward:",
			form_select("", 'site[ward_id]', $data['ward_id'],
				getOptionsFromQuery("SELECT ward_id as theKey, CONCAT(name, ' (', city, ' Ward ', num, ')') as theValue FROM ward ORDER BY ward_id"),
				"Official city ward this site is located in"));
			
		$rows[] = array(
			"Site Location Map:",
			form_textfield("", 'site[location_url]', $data['location_url'],50, 255, "URL for image that shows how to reach the site"));
			
		$rows[] = array(
			"Site Layout Map:",
			form_textfield("", 'site[layout_url]', $data['layout_url'], 50, 255, "URL for image that shows how to set up fields at the site"));
			
		$rows[] = array(
			"Directions:",
			form_textarea("", 'site[directions]', $data['directions'], 60, 5, "Directions to field site.  Please ensure that bus and bike directions are also provided if practical."));
			
		$rows[] = array(
			"Special Instructions:",
			form_textarea("", 'site[instructions]', $data['instructions'], 60, 5, "Specific instructions for this site (parking, other restrictions)"));
		$rows[] = array(
			form_submit('Submit'),
			form_reset('Reset'));

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		
		if($this->id) {
			$this->setLocation(array(
				$data['name'] => "op=site_view&id=" . $this->id,
				$this->title => 0));
		} else {
			$this->setLocation(array( $this->title => 0));
		}
		
		return form($output);	
	}

	function generateConfirm ($data)
	{
		$output = form_hidden("op", $this->op);
		$output .= form_hidden("step", "perform");
		$output .= form_hidden("id", $this->id);
		
		$rows = array();

		$rows[] = array(
			"Site Name:",
			form_hidden('site[name]', $data['name']) . check_form($data['name']));
			
		$rows[] = array(
			"Site Code:",
			form_hidden('site[code]', $data['code']) . check_form($data['code']));
			
		$rows[] = array(
			"Site Region:",
			form_hidden('site[region]', $data['region']) . check_form($data['region']));
			
		$rows[] = array(
			"City Ward:",
			form_hidden('site[ward_id]', $data['ward_id']) . 
				getWardName($data['ward_id']));
			
		$rows[] = array(
			"Site Location Map:",
			form_hidden('site[location_url]', $data['location_url']) . check_form($data['location_url']));
			
		$rows[] = array(
			"Site Layout Map:",
			form_hidden('site[layout_url]', $data['layout_url']) . check_form($data['layout_url']));
			
		$rows[] = array(
			"Directions:",
			form_hidden('site[directions]', $data['directions']) . check_form($data['directions']));
			
		$rows[] = array(
			"Special Instructions:",
			form_hidden('site[instructions]', $data['instructions']) . check_form($data['instructions']));

		$rows[] = array( form_submit('Submit'), "");
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		
		if($this->id) {
			$this->setLocation(array(
				$data['name'] => "op=site_view&id=" . $this->id,
				$this->title => 0));
		} else {
			$this->setLocation(array( $this->title => 0));
		}
		return form($output);
	}

	function perform ()
	{
		$site = var_from_getorpost('site');
		
		db_query("UPDATE site SET 
			name = '%s', code = '%s', 
			region = '%s', ward_id = %d,
			location_url = '%s', layout_url = '%s', 
			directions = '%s', 
			instructions = '%s' 
			WHERE site_id = %d",
			array(
				$site['name'],
				$site['code'],
				$site['region'],
				($site['ward_id'] == 0) ? NULL : $site['ward_id'],
				$site['location_url'],
				$site['layout_url'],
				$site['directions'],
				$site['instructions'],
				$this->id,
			)
		);
	
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		return true;
	}

	function isDataInvalid ()
	{
		$errors = "";

		$site = var_from_getorpost('site');
		
		if( !validate_nonhtml($site['name'] ) ) {
			$errors .= "<li>Name cannot be left blank, and cannot contain HTML";
		}
		if( !validate_nonhtml($site['code'] ) ) {
			$errors .= "<li>Code cannot be left blank and cannot contain HTML";
		}
		
		if( ! validate_number($site['ward_id']) ) {
			$errors .= "<li>Ward must be selected";
		}

		if( ! validate_nonhtml($site['region']) ) {
			$errors .= "<li>Region cannot be left blank and cannot contain HTML";
		}
		
		if(validate_nonblank($site['location_url'])) {
			if( ! validate_nonhtml($site['location_url']) ) {
				$errors .= "<li>If you provide a location URL, it must be valid.";
			}
		}
		
		if(validate_nonblank($site['layout_url'])) {
			if( ! validate_nonhtml($site['layout_url']) ) {
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
		$this->op = "site_list";
		$this->section = 'field';
		$this->setLocation(array("List Field Sites" => 'op=' . $this->op));
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
		$links = array();
		
		if($this->_permissions['field_admin']) {
			$links[] = l("create field site", "op=site_create");
		}
		
		$output = theme_links( $links );

		ob_start();
		$retval = @readfile("data/field_caution.html");
		if (false !== $retval) {
			$output .= ob_get_contents();
		}           
		ob_end_clean();

		$result = db_query( "SELECT s.site_id, s.name, s.region FROM site s ORDER BY s.region,s.name");

		$fieldsByRegion = array();
		while($field = db_fetch_object($result)) {
			if(! array_key_exists( $field->region, $fieldsByRegion) ) {
				$fieldsByRegion[$field->region] = "";
			}
			$fieldsByRegion[$field->region] 
				.= l($field->name, "op=site_view&id=$field->site_id") . "<br />";
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
			'require_var:id',
			'admin_sufficient',
			'allow',
		);
		$this->_permissions = array(
			'site_edit'			=> false,
			'field_create'		=> false,
		);
		$this->op = "site_view";
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
		global $session;

		$id = var_from_getorpost('id');

		/* TODO: site_load() ? */
		$site = db_fetch_object(db_query("SELECT * FROM site WHERE site_id = %d", $id));

		if(!$site) {
			$this->error_exit("The site [$id] does not exist");
		}
	
		$links = array();
		if($this->_permissions['site_edit']) {
			$links[] = l('edit site', "op=site_edit&id=$id", array("title" => "Edit this field site"));
		}
		if($this->_permissions['field_create']) {
			$links[] = l('add field', "op=field_create&site_id=$id", array("title" => "Add a new field to this site"));
		}
		
		/* and list fields at this site */
		$result = db_query("SELECT * FROM field WHERE site_id = %d ORDER BY num", $id);

		$field_listing = "<ul>";
		while($field = db_fetch_object($result)) {
			$field_listing .= "<li>$site->code $field->num ($field->status)";
			$field_listing .= l("view", 
				"op=field_view&id=$field->field_id", 
				array('title' => "View field entry"));
			if($this->_permissions["site_edit"]) {
				$field_listing .= " | " . l("edit", 
					"op=field_edit&id=$field->field_id", 
					array('title' => "Edit field entry"));
			}
		}
		$field_listing .= "</ul>";
		

		$rows = array();
		$rows[] = array("Site Name:", $site->name);
		$rows[] = array("Site Code:", $site->code);
		$rows[] = array("Site Region:", $site->region);
		$rows[] = array("City Ward:", l(getWardName($site->ward_id), "op=ward_view&id=$site->ward_id"));
		$rows[] = array("Site Location Map:", 
			$site->location_url ? l("Click for map in new window", $site->location_url, array('target' => '_new'))
				: "No Map");
		$rows[] = array("Field Layout Map:", 
			$site->layout_url ? l("Click for map in new window", $site->layout_url, array('target' => '_new'))
				: "No Map");
		$rows[] = array("Directions:", $site->directions);
		$rows[] = array("Special Instrutions:", $site->instructions);
		
		$rows[] = array("Fields:", $field_listing);
		
		
		$this->setLocation(array(
			$site->name => "op=site_view&id=$site->site_id",
			$this->title => 0
		));
		
		$output = theme_links($links);
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		
		return $output;
	}
}

?>
