<?php
/*
 * Handlers for dealing with city ward data
 */

function ward_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new WardCreate;
		case 'edit':
			return new WardEdit;
		case 'view':
			return new WardView;
		case 'list':
		case '':
			return new WardList;
	}
	return null;
}

function ward_menu()
{
	global $session;
	if($session->is_admin()) {
		menu("ward", "City Wards", 0,1);
		menu("ward/list", "list wards");
	}
}

class WardCreate extends WardEdit
{
	function initialize ()
	{
		$this->title = "Create Ward";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		$this->section = 'admin';
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
				local_redirect(url("ward/view/$id"));
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
		/* TODO: should use a sequence table here instead of LAST_INSERT_ID()
		 */
	
		db_query("INSERT into ward (name,num) VALUES ('%s',%d)", $edit['name'], $edit['num']);

		if(1 != db_affected_rows() ) {
			return false;
		}
		
		$result = "SELECT LAST_INSERT_ID() from ward";
		if( !db_num_rows($result) ) {
			return false;
		}
		$id = db_result($result);
		
		return parent::perform($id, $edit);
	}

}

class WardEdit extends Handler
{
	function initialize ()
	{
		$this->title = "Edit Ward";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		$this->section = 'admin';
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
				local_redirect(url("ward/view/$id"));
				break;
			default:
				$edit = $this->getFormData( $id );
				$rc = $this->generateForm( $edit );
		}
		$this->setLocation(array($edit['name']  => "ward/view/$id", $this->title => 0));
		return $rc;
	}

	function getFormData( $id ) 
	{
		/* TODO: ward_load() */
		return db_fetch_array(db_query("SELECT * FROM ward WHERE ward_id = %d", $id));
	}

	function generateForm( $data = array() )
	{
		$output .= form_hidden("edit[step]", "confirm");
		
		$rows = array();
		$rows[] = array( "Ward Name:", form_textfield("", 'edit[name]', $data['name'], 35, 35, "Name of ward"));
		$rows[] = array( "Ward Number:", form_textfield("", 'edit[num]', $data['num'], 3, 3, "City's number for this ward"));
		$rows[] = array( "Ward Region:", form_select("", 'edit[region]', $data['region'], getOptionsFromEnum('ward', 'region'), "Area of city this ward is located in"));
		$rows[] = array( "City:", form_select("", 'edit[city]', $data['city'],
				getOptionsFromQuery("SELECT city AS theKey, city AS theValue FROM ward"),
				"City this ward is located in"));
		$rows[] = array( "Ward URL:", form_textfield("", 'edit[url]', $data['url'],50, 255, "City's URL for information on this ward"));
		$rows[] = array( form_submit('Submit'), form_reset('Reset'));

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		return form($output);	
	}

	function generateConfirm ($id, $edit)
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		$output .= form_hidden("edit[step]", "perform");
		
		$rows = array();
		$rows[] = array(
			"Ward Name:",
			form_hidden('edit[name]', $edit['name']) . check_form($edit['name']));
			
		$rows[] = array(
			"Ward Number:",
			form_hidden('edit[num]', $edit['num']) . check_form($edit['num']));
			
		$rows[] = array(
			"Ward Region:",
			form_hidden('edit[region]', $edit['region']) . check_form($edit['region']));
			
		$rows[] = array(
			"City Ward:",
			form_hidden('edit[city]', $edit['city']) . check_form($edit['city']));
			
		$rows[] = array(
			"Ward URL:",
			form_hidden('edit[url]', $edit['url']) . check_form($edit['url']));
			
		$rows[] = array( form_submit('Submit'), "");
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		
		return form($output);
	}

	function perform ($id, $edit)
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$res = db_query("UPDATE ward SET 
			name = '%s', 
			num = %d, 
			region = '%s', 
			city = '%s',
			url = '%s'
			WHERE ward_id = %d", $edit['name'], $edit['num'], $edit['region'], $edit['city'], $edit['url'], $id);
		
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
		if( !validate_number($edit['num'] ) ) {
			$errors .= "<li>Ward number must be numeric";
		}
		if( !validate_nonhtml($edit['city'] ) ) {
			$errors .= "<li>City cannot be left blank";
		}
		
		if( ! validate_nonhtml($edit['region']) ) {
			$errors .= "<li>Region cannot be left blank and cannot contain HTML";
		}
		
		if(validate_nonblank($edit['url'])) {
			if( ! validate_nonhtml($edit['url']) ) {
				$errors .= "<li>If you provide a URL, it must be valid.";
			}
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

class WardList extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'allow'		/* Allow everyone */
		);
		$this->section = 'admin';
		$this->setLocation(array('List Wards' => 'ward/list'));
		return true;
	}

	function process ()
	{
		$cities = array('Ottawa','Gatineau');
		$columns = array();
		foreach($cities as $city) {
			
			$header = array( array('data' => $city, 'colspan' => 6));
			$rows = array();
			
			$result = db_query("SELECT w.*, COUNT(*) as players FROM ward w LEFT JOIN person p ON (p.ward_id = w.ward_id) WHERE w.city = '%s' GROUP BY w.ward_id", $city);
			
			while($ward = db_fetch_object($result) ) {
			
				$fieldQuery = db_query("SELECT COUNT(*) FROM field f, site s WHERE f.site_id = s.site_id AND f.status = 'open' AND s.ward_id = %d", $ward->ward_id);
				$fields = db_result($fieldQuery);
				$rows[] = array(
					array("&nbsp;", 'width' => 10),
					"Ward $ward->num",
					$ward->name,
					array('data' => $fields . (($fields == 1) ? " field" : " fields"), 'align' => 'right'),
					array('data' => $ward->players . (($ward->players == 1) ? " player" : " players"), 'align' => 'right'),
					l('view', "ward/view/$ward->ward_id")
				);
			}
			$playerQuery = db_query("SELECT COUNT(*) FROM person WHERE ISNULL(ward_id) AND addr_city = '%s'", $city);
			$players = db_result($playerQuery);
			$rows[] = array( 
				array('data' => "&nbsp;", 'width' => 10),
				"&nbsp;",
				"Unknown Ward",
				"&nbsp;",
				array('data' => $players . (($players == 1) ? " player" : " players"), 'align' => 'right'),
				"&nbsp;"
			);
			
			$columns[] = "<div class='listtable'>" . table( $header, $rows ) . "</div>";
		}
		return table(null, array( $columns ) );
	}
}

class WardView extends Handler
{
	function initialize ()
	{
		$this->title = "View Ward";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'allow',
		);
		$this->_permissions = array(
			'ward_edit'			=> false,
		);
		$this->section = 'admin';
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

		/* TODO: ward_load() */
		$result = db_query("SELECT * FROM ward WHERE ward_id = %d", $id);
		if( 1 != db_num_rows($result)) {
			$this->error_exit("That ward does not exist");
		}
		$ward = db_fetch_object($result);

		$links = array();
		if($this->_permissions['ward_edit']) {
			$links[] = l('edit ward', "ward/edit/$id", array("title" => "Edit this ward"));
		}
		
		/* and list field sites in this ward */
		$fieldSites = db_query("SELECT * FROM site WHERE ward_id = %d ORDER BY site_id", $id);

		$site_listing = "<ul>";
		while($site = db_fetch_object($fieldSites)) {
			$field_listing .= "<li>$site->name ($site->code) &nbsp;";
			$field_listing .= l("view", "site/view/$site->site_id", array('title' => "View site"));
		}
		$field_listing .= "</ul>";
		
		$this->setLocation(array( $ward->name => "ward/view/$id", $this->title => 0));
		
		$output = theme_links($links);
		
		$rows[] = array("Ward Name:", $ward->name);
		$rows[] = array("Ward Number:", $ward->num);
		$rows[] = array("Ward City:", $ward->city);
		$rows[] = array("Information URL:", 
			$ward->url ? l( $ward->url, $ward->url, array('target' => '_new')) . " (opens in new window)"
				: "No Link");
		$rows[] = array("Field Sites:", $field_listing);
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		return $output;
	}
}
?>
