<?php
register_page_handler('field_view', 'FieldView');

/**
 * Field viewing handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class FieldView extends Handler
{
	/** 
	 * Initializer for FieldView class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->_permissions = array(
			'field_assign' => false,
			'field_edit'   => false,
		);

		return true;
	}

	/**
	 * Check if the current session has permission to view this field.
	 *
	 * @access public
	 * @return boolean success/fail
	 */
	function has_permission ()
	{
		global $DB, $session, $id;
		
		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide an ID to view");
			return false;
		}

		/* Administrator can do anything */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}

		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->set_template_file("Field/view.tmpl");
		
		$row = $DB->getRow("
			SELECT 
				name,
				url
			FROM 
				field_info
			WHERE 
				field_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		if(!isset($row)) {
			$this->error_text = gettext("The field [$id] does not exist");
			return false;
		}
	
		$this->set_title("View Field: " . $row['name']);
		$this->tmpl->assign("field_name", $row['name']);
		$this->tmpl->assign("field_id", $id);
		$this->tmpl->assign("field_website", $row['url']);
	
		/* and, grab bookings */
		$rows = $DB->getAll("
			SELECT 
				a.league_id,
				IF(l.tier,CONCAT(l.season,' ',l.name, ' Tier ',l.tier),CONCAT(l.season,' ',l.name)) AS name,
				a.day
		  	FROM 
				field_assignment a,
				league l
		  	WHERE 
				a.field_id = ?
				AND a.league_id = l.league_id",
			array($id),
			DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($rows)) {
			return false;
		}
		$daynum = array( 'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6);
		$assignments = array(
			array( 'day' => "Sunday", 'leagues' => array()),
			array( 'day' => "Monday", 'leagues' => array()),
			array( 'day' => "Tuesday", 'leagues' => array()),
			array( 'day' => "Wednesday", 'leagues' => array()),
			array( 'day' => "Thursday", 'leagues' => array()),
			array( 'day' => "Friday", 'leagues' => array()),
			array( 'day' => "Saturday", 'leagues' => array()),
		);
		
		while(list(,$booking) = each($rows)) {
			$num = $daynum[$booking['day']];
			$assignments[$num]['leagues'][] = $booking;
		}

		/* Argh.  Need to resort array */
		ksort($assignments);
		
		$this->tmpl->assign("field_assignments", $assignments);

		/* ... and set permissions flags */
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return true;
	}
}

?>
