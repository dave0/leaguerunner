<?php
register_page_handler('league_edit', 'LeagueEdit');

/**
 * League edit handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class LeagueEdit extends Handler
{
	/** 
	 * @access public
	 */
	function initialize ()
	{
	
		$this->set_title("Edit League");

		$this->_permissions = array(
			'edit_info'			=> false,
			'edit_coordinator'		=> false,
			'edit_flags'		=> false,
		);
		return true;
	}

	/**
	 * Check if the current session has permission to edit the league
	 *
	 * check that the session is valid (return false if not)
	 * check if the session user is the system admin  (return true)
	 * check if the user is coordinator (return true)
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
			$this->error_text = gettext("You must provide a league ID");
			return false;
		}
		
		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}

		/* League coordinator or assistant can edit */
		if($session->is_coordinator_of($id)) { 
			$this->_permissions['edit_info'] = true;
			$this->_permissions['edit_flags'] = true;
			return true;	
		}

		$this->error_text = gettext("You do not have permission to perform that operation");
		return false;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		switch($step) {
			case 'confirm':
				$this->set_template_file("League/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
			default:
				$this->set_template_file("League/edit_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
		}
		$this->tmpl->assign("page_op", var_from_getorpost('op'));
		
		/* ... and set permissions flags */
		reset($this->_permissions);
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return $rc;
	}

	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		global $id;
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=league_view&id=$id");
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB, $id;

		$row = $DB->getRow(
			"SELECT 
				l.name as league_name,
				l.day  as league_day,
				l.season as league_season,
				l.tier as league_tier,
				l.ratio as league_ratio,
				l.max_teams as max_teams,
				l.coordinator_id,
				l.alternate_id,
				l.stats_display as stats_display,
				l.current_round as current_round,
				l.year,
				l.start_time as league_start_time
			FROM league l WHERE l.league_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		if($this->populate_pulldowns() == false) {
			return false;
		}

		/* Deal with multiple days */
		if(strpos($row['league_day'], ",")) {
			$row['league_day'] = split(",",$row['league_day']);
		}

		$this->tmpl->assign($row);
		$this->tmpl->assign("id", $id);
		
		return true;
	}

	function populate_pulldowns ( )
	{
		global $DB;
		$volunteers = $DB->getAll(
			"SELECT
				p.user_id AS value,
				CONCAT(p.firstname,' ',p.lastname) AS output
			 FROM
			 	person p
			 WHERE
			 	p.class = 'volunteer'
				OR p.class = 'administrator'
			 ORDER BY p.lastname",
			DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($volunteers)) {
			return false;
		}
		/* Pop in a --- element */
		array_unshift($volunteers, array('value' => 0, 'output' => '---'));
		$this->tmpl->assign("volunteers", $volunteers);

		$days = $this->get_enum_options('league','day');
		if(is_bool($days)) {
			return $days;
		}
		$this->tmpl->assign("days", $days);
		
		$ratios = $this->get_enum_options('league','ratio');
		if(is_bool($ratios)) {
			return $ratios;
		}
		$this->tmpl->assign("ratios", $ratios);
		
		$seasons = $this->get_enum_options('league','season');
		if(is_bool($seasons)) {
			return $seasons;
		}
		$this->tmpl->assign("seasons", $seasons);

		/* TODO: 10 is a magic number.  Make it a config variable */
		$this->tmpl->assign("tiers", $this->get_numeric_options(0,10));
		/* TODO: 4 is a magic number.  Make it a config variable */
		$this->tmpl->assign("rounds", $this->get_numeric_options(1,4));
	
		return true;
	}

	function generate_confirm ()
	{
		global $DB, $id;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("League/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}
		
		$this->tmpl->assign("id", $id);

		$this->tmpl->assign("league_name", var_from_getorpost('league_name'));
		$this->tmpl->assign("league_season", var_from_getorpost('league_season'));
		$this->tmpl->assign("league_day", join(",",var_from_getorpost('league_day')));
		$this->tmpl->assign("league_tier", var_from_getorpost('league_tier'));
		$this->tmpl->assign("league_round", var_from_getorpost('league_round'));
		$this->tmpl->assign("league_ratio", var_from_getorpost('league_ratio'));
		$this->tmpl->assign("league_start_time_Hour", var_from_getorpost('league_start_time_Hour'));
		$this->tmpl->assign("league_start_time_Minute", var_from_getorpost('league_start_time_Minute'));
		
		$c_id = var_from_getorpost('coordinator_id');
		$c_name = $DB->getOne("SELECT CONCAT(p.firstname,' ',p.lastname) FROM person p WHERE p.user_id = ?",array($c_id));
		$this->tmpl->assign("coordinator_id",   $c_id);
		$this->tmpl->assign("coordinator_name", $c_name);
	
		$a_id = var_from_getorpost('alternate_id');
		if($a_id > 0) {
			$a_name = $DB->getOne("SELECT CONCAT(p.firstname,' ',p.lastname) FROM person p WHERE p.user_id = ?",array($a_id));
			$this->tmpl->assign("alternate_id",   $a_id);
			$this->tmpl->assign("alternate_name", $a_name);
		} else {
			$this->tmpl->assign("alternate_id",   $a_id);
			$this->tmpl->assign("alternate_name", "N/A");
		}

		return true;
	}

	function perform ()
	{
		global $DB, $id;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("League/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}
		
		$fields      = array();
		$fields_data = array();

		if($this->_permissions['edit_info']) {
			$fields[] = "name = ?";
			$fields_data[] = var_from_getorpost("league_name");
			$fields[] = "day = ?";
			$fields_data[] = var_from_getorpost("league_day");
			$fields[] = "season = ?";
			$fields_data[] = var_from_getorpost("league_season");
			$fields[] = "tier = ?";
			$fields_data[] = var_from_getorpost("league_tier");
			$fields[] = "ratio = ?";
			$fields_data[] = var_from_getorpost("league_ratio");
			$fields[] = "start_time = ?";
			$fields_data[] = var_from_getorpost("league_start_time_Hour") . ":" . var_from_getorpost("league_start_time_Minute");
		}
		
		if($this->_permissions['edit_coordinator']) {
			$fields[] = "coordinator_id = ?";
			$fields_data[] = var_from_getorpost("coordinator_id");
			$fields[] = "alternate_id = ?";
			$fields_data[] = var_from_getorpost("alternate_id");
		}
			
		$sql = "UPDATE league SET ";
		$sql .= join(",", $fields);	
		$sql .= "WHERE league_id = ?";

		$sth = $DB->prepare($sql);
		
		$fields_data[] = $id;
		$res = $DB->execute($sth, $fields_data);

		if($this->is_database_error($res)) {
			return false;
		}

		return true;
	}

	/* TODO: Properly validate other data */
	function validate_data ()
	{
		$err = true;
		
		$league_name = trim(var_from_getorpost("league_name"));
		if(0 == strlen($league_name)) {
			$this->error_text .= gettext("Name cannot be left blank") . "<br>";
			$err = false;
		}

		$coord_id = var_from_getorpost("coordinator_id");
		if($coord_id <= 0) {
			$this->error_text .= gettext("A coordinator must be selected") . "<br>";
			$err = false;
		}

		$league_day = var_from_getorpost("league_day");
		if( !isset($league_day) ) {
			$this->error_text .= gettext("One or more days of play must be selected") . "<br>";
			$err = false;
		}
		
		return $err;
	}

}

?>
