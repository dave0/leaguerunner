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
		return true;
	}
	
	function perform ()
	{
		global $DB, $session;
		
		if(! $this->validate_data()) {
			$this->error_text .= "<br>Please use your back button to return to the form, fix these errors, and try again";
			return false;
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
				return $this->perform();
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

	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=site_view&id=". $this->_id);
		}
		return parent::display();
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

		$this->tmpl->assign("site", $row);
		
		$this->tmpl->assign("id", $this->_id);
		return true;
	}

	function generate_confirm ()
	{
		global $DB;

		if(! $this->validate_data()) {
			$this->error_text .= "<br>Please use your back button to return to the form, fix these errors, and try again";
			return false;
		}

		$this->tmpl->assign("site", var_from_getorpost('site'));
		$this->tmpl->assign("id", $this->_id);

		return true;
	}

	function perform ()
	{
		global $DB;

		if(! $this->validate_data()) {
			$this->error_text .= "<br>Please use your back button to return to the form, fix these errors, and try again";
			return false;
		}

		$site = var_from_getorpost('site');
		
		$res = $DB->query("UPDATE site SET 
			name = ?, 
			code = ?, 
			location_url = ?, 
			layout_url = ?, 
			directions = ?, 
			instructions = ? 
			WHERE site_id = ?",
			array(
				$site['name'],
				$site['code'],
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

	function validate_data ()
	{
		$rc = true;

		$site = var_from_getorpost('site');
		
		if( !validate_nonhtml($site['name'] ) ) {
			$this->error_text .= "<li>Name cannot be left blank, and cannot contain HTML";
			$rc = false;
		}
		if( !validate_nonhtml($site['code'] ) ) {
			$this->error_text .= "<li>Code cannot be left blank and cannot contain HTML";
			$rc = false;
		}
		
		if(validate_nonblank($site['location_url'])) {
			if( ! validate_nonhtml($site['location_url']) ) {
				$this->error_text .= "<li>If you provide a location URL, it must be valid.";
				$rc = false;
			}
		}
		
		if(validate_nonblank($site['layout_url'])) {
			if( ! validate_nonhtml($site['layout_url']) ) {
				$this->error_text .= "<li>If you provide a site layout URL, it must be valid.";
				$rc = false;
			}
		}
		
		return $rc;
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
				name AS value, 
				site_id AS id_val 
			 FROM site",
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

		$this->set_template_file("Site/view.tmpl");
		
		$site = $DB->getRow("SELECT * FROM site WHERE site_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		if(!isset($site)) {
			$this->error_text = "The site [$id] does not exist";
			return false;
		}
	
		$this->set_title("View Site: " . $row['name']);

		/* and list fields at this site */
		$site['fields'] = $DB->getAll("SELECT * FROM field WHERE site_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($site['fields'])) {
			return false;
		}

		$this->tmpl->assign("site", $site);
		$this->tmpl->assign("id", $id);

		/* ... and set permissions flags */
		reset($this->_permissions);
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return true;
	}
}

?>
