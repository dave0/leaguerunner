<?php

/*
 * Handlers for dealing with field sites
 */
register_page_handler('site_create', 'SiteCreate');
register_page_handler('site_edit', 'SiteEdit');
register_page_handler('site_list', 'SiteList');
register_page_handler('site_view', 'SiteView');

class SiteCreate extends SiteEdit
{
	function initialize ()
	{
		$this->set_title("Create New Field Site");
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		return true;
	}
	
	/*
	 * Overridden, as we have no info to put in that form.
	 */
	function generate_form () 
	{
		// TODO: This should be populated from database.
		$this->tmpl->assign("regions", array(
			array( 'value' => "Central", output => "Central" ), 
			array( 'value' => "East", output => "East" ),  
			array( 'value' => "South", output => "South" ),  
			array( 'value' => "West", output => "West" ), 
		));
		return true;
	}
	
	function perform ()
	{
		global $DB, $session;
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$site = var_from_getorpost("site");
		
		$res = $DB->query("INSERT into site (name,code) VALUES (?,?)", array($site['name'], $site['code']));
		if($this->is_database_error($res)) {
			return false;
		}
	
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from site");
		if($this->is_database_error($id)) {
			return false;
		}
		
		$this->_id = $id;
		
		return parent::perform();
	}

}

class SiteEdit extends Handler
{

	var $_id;

	function initialize ()
	{
		$this->set_title("Edit Site");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'deny'
		);
		return true;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		$this->_id = var_from_getorpost('id');
		switch($step) {
			case 'confirm':
				$this->set_template_file("Site/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				$this->perform();
				local_redirect("op=site_view&id=". $this->_id);
				break;
			default:
				$this->set_template_file("Site/edit_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
		}
	
		if($this->_id) {
			$this->set_title("Edit Site: " . $DB->getOne("SELECT name FROM site where site_id = ?", array($this->_id)));
		}

		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		return $rc;
	}

	function generate_form ()
	{
		global $DB;

		$row = $DB->getRow(
			"SELECT *
			FROM site WHERE site_id = ?", 
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		// TODO: This should be populated from database.
		$this->tmpl->assign("regions", array(
			array( 'value' => "Central", output => "Central" ), 
			array( 'value' => "East", output => "East" ),  
			array( 'value' => "South", output => "South" ),  
			array( 'value' => "West", output => "West" ), 
		));

		$this->tmpl->assign("site", $row);
		
		$this->tmpl->assign("id", $this->_id);
		return true;
	}

	function generate_confirm ()
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$this->tmpl->assign("site", var_from_getorpost('site'));
		$this->tmpl->assign("id", $this->_id);

		return true;
	}

	function perform ()
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$site = var_from_getorpost('site');
		
		$res = $DB->query("UPDATE site SET 
			name = ?, 
			code = ?, 
			region = ?, 
			location_url = ?, 
			layout_url = ?, 
			directions = ?, 
			instructions = ? 
			WHERE site_id = ?",
			array(
				$site['name'],
				$site['code'],
				$site['region'],
				$site['location_url'],
				$site['layout_url'],
				$site['directions'],
				$site['instructions'],
				$this->_id,
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
		$this->set_title("List Sites");
		$this->_required_perms = array(
			'allow'		/* Allow everyone */
		);

		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->set_template_file("common/generic_list.tmpl");

		$found = $DB->getAll(
			"SELECT 
				CONCAT(s.name, ' (', COUNT(f.field_id), ' fields)') as value, 
				s.site_id AS id_val from site s LEFT JOIN field f ON (f.site_id = s.site_id) 
			GROUP BY f.site_id ORDER BY s.name",
			array(), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("available_ops", array(
			array(
				'description' => 'view',
				'action' => 'site_view'
			),
		));
		$this->tmpl->assign("page_op", "site_list");
		$this->tmpl->assign("list", $found);
		
		return true;
	}
}

class SiteView extends Handler
{
	function initialize ()
	{
		$this->set_title("View Site");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
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
		

		$page_title = "View Site &raquo; ".$site['name']." (" . $site['code'] . ")";
		$this->set_title($page_title);
		$output = h1($page_title);
		if(count($links) > 0) {
			$output .= simple_tag("blockquote", theme_links($links));
		}
		$output .= "<table border='0' width='100%'>";
		$output .= simple_row("Site Name:", $site['name']);
		$output .= simple_row("Site Code:", $site['code']);
		$output .= simple_row("Site Region:", $site['region']);
		$output .= simple_row("Site Directions:", $site['directions']);
		$output .= simple_row("Site-specific Instrutions:", $site['instructions']);
		$output .= simple_row("Site Location Map:", 
			$site['location_url'] ? l("Click for map in new window", $site['location_url'], array('target' => '_top'))
				: "No Map");
		$output .= simple_row("Field Layout Map:", 
			$site['layout_url'] ? l("Click for map in new window", $site['layout_url'], array('target' => '_top'))
				: "No Map");
		
		$output .= simple_row("Fields:", $field_listing);
		
		$output .= "</table>";
		
		print $this->get_header();
		print $output;
		print $this->get_footer();
		return true;
	}

	function display() 
	{
		return true;  // TODO Remove me after smarty is removed
	}
}

?>
