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
		global $DB;

		$site = var_from_getorpost("site");
		
		$res = $DB->query("INSERT into site (name,code) VALUES (?,?)", array($site['name'], $site['code']));
		if($this->is_database_error($res)) {
			return false;
		}
	
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from site");
		if($this->is_database_error($id)) {
			return false;
		}
	
		/* TODO Make $this->id go away */
		$this->id = $id;
		
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
		global $DB;

		$step = var_from_getorpost('step');
		$this->id = var_from_getorpost('id');

		switch($step) {
			case 'confirm':
				$dataInvalid = $this->isDataInvalid();
				if($dataInvalid) {
					$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
				}
				
				if($this->id) {
					$name = $DB->getOne("SELECT name FROM site where site_id = ?", array($this->id));

					if($this->is_database_error($name)) {
						return false;
					}
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
					$row = $DB->getRow(
						"SELECT * FROM site WHERE site_id = ?", 
						array($this->id), DB_FETCHMODE_ASSOC);

					if($this->is_database_error($row)) {
						return false;
					}
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
		
		$output .= "<table border='0'>";

		$output .= simple_row(
			"Site Name:",
			form_textfield("", 'site[name]', $data['name'], 35, 35, "Name of field site"));
			
		$output .= simple_row(
			"Site Code:",
			form_textfield("", 'site[code]', $data['code'], 3, 3, "Three-letter abbreviation for field site"));
			
		$output .= simple_row(
			"Site Region:",
			form_select("", 'site[region]', $data['region'], getOptionsFromEnum('site', 'region'), "Area of city this site is located in"));
			
		$output .= simple_row(
			"City Ward:",
			form_select("", 'site[ward_id]', $data['ward_id'],
				getOptionsFromQuery("SELECT ward_id, CONCAT(name, ' (', city, ' Ward ', num, ')') FROM ward ORDER BY ward_id"),
				"Official city ward this site is located in"));
			
		$output .= simple_row(
			"Site Location Map:",
			form_textfield("", 'site[location_url]', $data['location_url'],50, 255, "URL for image that shows how to reach the site"));
			
		$output .= simple_row(
			"Site Layout Map:",
			form_textfield("", 'site[layout_url]', $data['layout_url'], 50, 255, "URL for image that shows how to set up fields at the site"));
			
		$output .= simple_row(
			"Directions:",
			form_textarea("", 'site[directions]', $data['directions'], 60, 5, "Directions to field site.  Please ensure that bus and bike directions are also provided if practical."));
			
		$output .= simple_row(
			"Special Instructions:",
			form_textarea("", 'site[instructions]', $data['instructions'], 60, 5, "Specific instructions for this site (parking, other restrictions)"));
		$output .= simple_row(
			form_submit('Submit'),
			form_reset('Reset'));
		$output .= "</table>";
		
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
		global $DB;
		$output = form_hidden("op", $this->op);
		$output .= form_hidden("step", "perform");
		$output .= form_hidden("id", $this->id);
		
		$output .= "<table border='0'>";

		$output .= simple_row(
			"Site Name:",
			form_hidden('site[name]', $data['name']) . check_form($data['name']));
			
		$output .= simple_row(
			"Site Code:",
			form_hidden('site[code]', $data['code']) . check_form($data['code']));
			
		$output .= simple_row(
			"Site Region:",
			form_hidden('site[region]', $data['region']) . check_form($data['region']));
			
		$output .= simple_row(
			"City Ward:",
			form_hidden('site[ward_id]', $data['ward_id']) . 
				getWardName($data['ward_id']));
			
		$output .= simple_row(
			"Site Location Map:",
			form_hidden('site[location_url]', $data['location_url']) . check_form($data['location_url']));
			
		$output .= simple_row(
			"Site Layout Map:",
			form_hidden('site[layout_url]', $data['layout_url']) . check_form($data['layout_url']));
			
		$output .= simple_row(
			"Directions:",
			form_hidden('site[directions]', $data['directions']) . check_form($data['directions']));
			
		$output .= simple_row(
			"Special Instructions:",
			form_hidden('site[instructions]', $data['instructions']) . check_form($data['instructions']));

		$output .= simple_row( form_submit('Submit'), "");
		$output .= "</table>";
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
		global $DB;

		$site = var_from_getorpost('site');
		
		$res = $DB->query("UPDATE site SET 
			name = ?, 
			code = ?, 
			region = ?, 
			ward_id = ?,
			location_url = ?, 
			layout_url = ?, 
			directions = ?, 
			instructions = ? 
			WHERE site_id = ?",
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
		
		if($this->is_database_error($res)) {
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
		global $DB;
		
		$links = array();
		
		if($this->_permissions['field_admin']) {
			$links[] = l("create field site", "op=site_create");
		}
		
		$output = blockquote(
			theme_links( $links ) );

		ob_start();
		$retval = @readfile("data/field_caution.html");
		if (false !== $retval) {
			$output .= blockquote(ob_get_contents());
		}           
		ob_end_clean();

		$query = $DB->prepare(
			"SELECT s.site_id, s.name, s.region FROM site s
			ORDER BY s.region,s.name");
		$result = $DB->execute($query);
		if($this->is_database_error($result)) {
			return false;
		}

		$fieldsByRegion = array();
		while($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
			if(! array_key_exists( $row['region'], $fieldsByRegion) ) {
				$fieldsByRegion[$row['region']] = "";
				$headings .= th(ucfirst($row['region']));
			}
			$fieldsByRegion[$row['region']] 
				.= l($row['name'], 'op=site_view&id=' . $row['site_id']) . " <br />";
		}

		$output .= "<div class='fieldlist'><table>";
		$output .= tr($headings);
		$output .= '<tr>';
		while(list($region,$fields) = each($fieldsByRegion)) {
			$output .= td($fields);	
		}
		$output .= '</tr>';
		$output .= "</table></div>";

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
		global $session, $DB;

		$id = var_from_getorpost('id');

		$site = $DB->getRow("SELECT * FROM site WHERE site_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		if(!isset($site)) {
			$this->error_exit("The site [$id] does not exist");
		}
	
		/* and list fields at this site */
		$fields = $DB->getAll("SELECT * FROM field WHERE site_id = ? ORDER BY num",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($site['fields'])) {
			return false;
		}

		$links = array();
		if($this->_permissions['site_edit']) {
			$links[] = l('edit site', "op=site_edit&id=$id", array("title" => "Edit this field site"));
		}
		if($this->_permissions['field_create']) {
			$links[] = l('add field', "op=field_create&site_id=$id", array("title" => "Add a new field to this site"));
		}

		$field_listing = "<ul>";
		foreach ($fields as $field) {
			$field_listing .= "<li>" . $site['code'] . " " . $field['num'] . " (" . $field['status'] . ")  ";
			$field_listing .= l("view", 
				"op=field_view&id=" . $field['field_id'], 
				array('title' => "View field entry"));
			if($this->_permissions["site_edit"]) {
				$field_listing .= " | " . l("edit", 
					"op=field_edit&id=" . $field['field_id'], 
					array('title' => "Edit field entry"));
			}
		}
		$field_listing .= "</ul>";
		

		$output = blockquote(theme_links($links));
		$output .= "<table border='0' width='100%'>";
		$output .= simple_row("Site Name:", $site['name']);
		$output .= simple_row("Site Code:", $site['code']);
		$output .= simple_row("Site Region:", $site['region']);
		$output .= simple_row("City Ward:", l(getWardName($site['ward_id']), "op=ward_view&id=" . $site['ward_id']));
		$output .= simple_row("Site Location Map:", 
			$site['location_url'] ? l("Click for map in new window", $site['location_url'], array('target' => '_new'))
				: "No Map");
		$output .= simple_row("Field Layout Map:", 
			$site['layout_url'] ? l("Click for map in new window", $site['layout_url'], array('target' => '_new'))
				: "No Map");
		$output .= simple_row("Directions:", $site['directions']);
		$output .= simple_row("Special Instrutions:", $site['instructions']);
		
		$output .= simple_row("Fields:", $field_listing);
		
		$output .= "</table>";
		
		$this->setLocation(array(
			$site['name'] => "op=site_view&id=" . $site['site_id'],
			$this->title => 0
		));
		
		return $output;
	}
}

?>
