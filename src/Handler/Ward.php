<?php

/*
 * Handlers for dealing with city ward data
 */
register_page_handler('ward_create', 'WardCreate');
register_page_handler('ward_edit', 'WardEdit');
register_page_handler('ward_list', 'WardList');
register_page_handler('ward_view', 'WardView');

class WardCreate extends WardEdit
{
	function initialize ()
	{
		$this->set_title("Create New Ward");
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		$this->op = "ward_create";
		return true;
	}
	
	function perform ()
	{
		global $DB;

		$edit = var_from_getorpost("edit");
		
		$res = $DB->query("INSERT into ward (name,num) VALUES (?,?)", array($edit['name'], $edit['num']));
		if($this->is_database_error($res)) {
			return false;
		}
	
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from ward");
		if($this->is_database_error($id)) {
			return false;
		}
		
		$this->id = $id;
		
		return parent::perform();
	}

}

class WardEdit extends Handler
{
	var $id;

	function initialize ()
	{
		$this->set_title("Edit Ward");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'deny'
		);
		$this->op = "ward_edit";
		return true;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		$edit = var_from_getorpost('edit');
		$this->id = var_from_getorpost('id');

		switch($step) {
			case 'confirm':
				$dataInvalid = $this->isDataInvalid();
				if($dataInvalid) {
					$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
				}
				
				$this->set_title($this->title . " &raquo; ". $edit['name']);
				print $this->get_header();
				print h1($this->title);
				print $this->generateConfirm($edit);
				print $this->get_footer();
				exit;
				break;
			case 'perform':
				$dataInvalid = $this->isDataInvalid();
				if($dataInvalid) {
					$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
				}
				$this->perform();
				local_redirect("op=ward_view&id=". $this->id);
				break;
			default:
				if($this->id) {
					$row = $DB->getRow(
						"SELECT * FROM ward WHERE ward_id = ?", 
						array($this->id), DB_FETCHMODE_ASSOC);

					if($this->is_database_error($row)) {
						return false;
					}
					$this->set_title($this->title . " &raquo; ". $row['name']);
				} else {
					$row = array();
				}
				print $this->get_header();
				print h1($this->title);
				print $this->generateForm($row);
				print $this->get_footer();
				exit;
		}
	
		return $rc;
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
			"Ward Name:",
			form_textfield("", 'edit[name]', $data['name'], 35, 35, "Name of ward"));
			
		$output .= simple_row(
			"Ward Number:",
			form_textfield("", 'edit[num]', $data['num'], 3, 3, "City's number for this ward"));
			
		$output .= simple_row(
			"Ward Region:",
			form_select("", 'edit[region]', $data['region'], getOptionsFromEnum('ward', 'region'), "Area of city this ward is located in"));
			
		$output .= simple_row(
			"City:",
			form_select("", 'edit[city]', $data['city'],
				getOptionsFromQuery("SELECT city, city FROM ward"),
				"City this ward is located in"));
			
		$output .= simple_row(
			"Ward URL:",
			form_textfield("", 'edit[url]', $data['url'],50, 255, "City's URL for information on this ward"));
			
		$output .= simple_row(
			form_submit('Submit'),
			form_reset('Reset'));
		$output .= "</table>";
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
			"Ward Name:",
			form_hidden('edit[name]', $data['name']) . check_form($data['name']));
			
		$output .= simple_row(
			"Ward Number:",
			form_hidden('edit[num]', $data['num']) . check_form($data['num']));
			
		$output .= simple_row(
			"Ward Region:",
			form_hidden('edit[region]', $data['region']) . check_form($data['region']));
			
		$output .= simple_row(
			"City Ward:",
			form_hidden('edit[city]', $data['city']) . check_form($data['city']));
			
		$output .= simple_row(
			"Ward URL:",
			form_hidden('edit[url]', $data['url']) . check_form($data['url']));
			
		$output .= simple_row( form_submit('Submit'), "");
		$output .= "</table>";
		return form($output);
	}

	function perform ()
	{
		global $DB;

		$edit = var_from_getorpost('edit');
		
		$res = $DB->query("UPDATE ward SET 
			name = ?, 
			num = ?, 
			region = ?, 
			city = ?,
			url = ?
			WHERE ward_id = ?",
			array(
				$edit['name'],
				$edit['num'],
				$edit['region'],
				$edit['city'],
				$edit['url'],
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

		$edit = var_from_getorpost('edit');
		
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
		$this->set_title("List Wards");
		$this->_required_perms = array(
			'allow'		/* Allow everyone */
		);
		$this->op = "ward_list";
		return true;
	}

	function process ()
	{
		global $DB;

		$cities = $DB->getCol("SELECT DISTINCT city FROM ward");
		if($this->is_database_error($cities)) {
			return false;
		}
		$output = "<table border='0' cellpadding='3' cellspacing='0'><tr>";
		foreach($cities as $city) {
			
			$output .= "<td valign='top'><table border='0' cellpadding='3' cellspacing='0'>";
			$output .= tr( td($city, array('colspan' => 6, 'class' => 'ward_title')));
			$result = $DB->query("SELECT * FROM ward WHERE city = ?", array($city));
			if($this->is_database_error($result)) {
				return false;
			}
			while($ward = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
				$fields = $DB->getOne("SELECT COUNT(*) FROM field f, site s WHERE f.site_id = s.site_id AND f.status = 'open' AND s.ward_id = ?", array($ward['ward_id']));
				$players = $DB->getOne("SELECT COUNT(*) FROM person WHERE ward_id = ?", array($ward['ward_id']));
				$output .= tr( 
					td("&nbsp;", array('width' => 10))
					. td("Ward " . $ward['num'], array('class'=>'ward_item'))
					. td($ward['name'], array('class'=>'ward_item'))
					. td($fields . (($fields == 1) ? " field" : " fields"), array('class'=>'ward_item', 'align' => 'right'))
					. td($players . (($players == 1) ? " player" : " players"), array('class'=>'ward_item', 'align' => 'right'))
					. td(l('view', 'op=ward_view&id=' . $ward['ward_id']), array('class'=>'ward_item'))
				);
			}
			$players = $DB->getOne("SELECT COUNT(*) FROM person WHERE ISNULL(ward_id) AND addr_city = ?", array($city));
			$output .= tr( 
				td("&nbsp;", array('width' => 10))
				. td("&nbsp;", array('class'=>'ward_item'))
				. td("Unknown Ward", array('class'=>'ward_item'))
				. td("&nbsp;", array('class'=>'ward_item'))
				. td($players . (($players == 1) ? " player" : " players"), array('class'=>'ward_item', 'align' => 'right'))
				. td("&nbsp;", array('class'=>'ward_item'))
			);
			
			$output .= "</table></td>";
		}
		$output .= "</tr></table>";

		print $this->get_header();
		print h1($this->title);
		print $output;
		print $this->get_footer();
		
		return true;
	}
}

class WardView extends Handler
{
	function initialize ()
	{
		$this->set_title("View Ward");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'allow',
		);
		$this->_permissions = array(
			'ward_edit'			=> false,
		);
		$this->op = "ward_view";
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

		$ward = $DB->getRow("SELECT * FROM ward WHERE ward_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		if(!isset($ward)) {
			$this->error_exit("That ward does not exist");
		}
	
		$links = array();
		if($this->_permissions['ward_edit']) {
			$links[] = l('edit ward', "op=ward_edit&id=$id", array("title" => "Edit this ward"));
		}
		
		/* and list field sites in this ward */
		$sites = $DB->getAll("SELECT * FROM site WHERE ward_id = ? ORDER BY site_id",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($sites)) {
			return false;
		}

		$site_listing = "<ul>";
		foreach ($sites as $site) {
			$field_listing .= "<li>" . $site['name'] . " (" . $site['code'] . ") &nbsp;";
			$field_listing .= l("view", 
				"op=site_view&id=" . $site['site_id'], 
				array('title' => "View site"));
		}
		$field_listing .= "</ul>";
		

		$this->set_title("View Ward &raquo; ".$ward['name']);
		
		$output = h1($this->title);
		$output .= blockquote(theme_links($links));
		$output .= "<table border='0' width='100%'>";
		$output .= simple_row("Ward Name:", $ward['name']);
		$output .= simple_row("Ward Number:", $ward['num']);
		$output .= simple_row("Ward City:", $ward['city']);
		$output .= simple_row("Information URL:", 
			$ward['url'] ? l( $ward['url'], $ward['url'], array('target' => '_new')) . " (opens in new window)"
				: "No Link");
				
		$output .= simple_row("Field Sites:", $field_listing);
		
		$output .= "</table>";
		
		print $this->get_header();
		print $output;
		print $this->get_footer();
		return true;
	}
}
?>
