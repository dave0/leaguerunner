<?php
register_page_handler('person_edit', 'PersonEdit');

/**
 * Player edit handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonEdit extends Handler
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "Edit Person";
		$this->_permissions = array(
			'edit_email'		=> false,
			'edit_phone'		=> false,
			'edit_username'		=> false,
			'edit_name' 		=> false,
			'edit_birthdate'	=> false,
			'edit_address'		=> false,
			'edit_gender'		=> false,
			'edit_skill' 		=> false,
		);

		return true;
	}

	/**
	 * Check if the current session has permission to view this player.
	 *
	 * check that the session is valid (return false if not)
	 * check if the session user is the target player (return true)
	 * check if the session user is the system admin  (return true)
	 * Now, check permissions of session to view this user
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
			$this->error_text = gettext("You must provide a user ID");
			return false;
		}

		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}

		/* Can always edit most self things */
		if($session->attr_get('user_id') == $id) {
			$this->_permissions['edit_email'] 		= true;
			$this->_permissions['edit_phone']		= true;
			$this->_permissions['edit_name'] 		= true;
			$this->_permissions['edit_birthdate']	= true;
			$this->_permissions['edit_address']		= true;
			$this->_permissions['edit_gender']		= true;
			$this->_permissions['edit_skill'] 		= true;
			return true;
		}

		/* 
		 * TODO: 
		 * See if we're a volunteer with user edit permission
		 */

		$this->error_text = gettext("You do not have permission to perform that operation");
		return false;
	}

	function process ()
	{
		$step = var_from_getorpost('step');
		switch($step) {
			case 'confirm':
				$this->set_template_file("Person/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				$rc = $this->perform();
				break;
			default:
				$this->set_template_file("Person/edit_form.tmpl");
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
			return $this->output_redirect("op=person_view;id=$id");
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB, $id;

		$row = $DB->getRow(
			"SELECT 
				firstname,
				lastname, 
				class,
				allow_publish_email, 
				allow_publish_phone,
				username, 
				email, 
				gender, 
				telephone, 
				birthdate, 
				skill_level, 
				year_started, 
				addr_street, 
				addr_city, 
				addr_prov, 
				addr_postalcode, 
				last_login 
			FROM person WHERE user_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign("firstname", $row['firstname']);
		$this->tmpl->assign("lastname", $row['lastname']);
		$this->tmpl->assign("id", $id);

		$this->tmpl->assign("username", $row['username']);
		
		$this->tmpl->assign("email", $row['email']);
		
		$ary = explode(" ", $row['telephone']);
		$this->tmpl->assign("phone_areacode", $ary[0]);
		$this->tmpl->assign("phone_prefix", $ary[1]);
		$this->tmpl->assign("phone_number", $ary[2]);
		if(isset($ary[3])) {
			$this->tmpl->assign("phone_extension", $ary[3]);
		}
		
		$this->tmpl->assign("addr_street", $row['addr_street']);
		$this->tmpl->assign("addr_city", $row['addr_city']);
		$this->tmpl->assign("addr_prov", $row['addr_prov']);
		$this->tmpl->assign("addr_postalcode", $row['addr_postalcode']);

		/* And fill provinces array */
		/* TODO: evil.  Need to allow Americans to use this at some point in
		 * time... */
		$this->tmpl->assign("provinces",
			array_map(
				array($this, "map_callback"), 
				array('Ontario','Quebec','Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland','Northwest Territories','Nunavut','Nova Scotia','Prince Edward Island','Saskatchewan','Yukon'))
		);

/* Here */

		$this->tmpl->assign("gender", $row['gender']);
		$this->tmpl->assign("gender_list",
			array_map(
				array($this, "map_callback"),
				array('Male','Female'))
		);
		
		$this->tmpl->assign("skill_level", $row['skill_level']);
		$this->tmpl->assign("skill_list", 
			array_map(
				array($this, "map_callback"),
				array(1,2,3,4,5))
		);

		$this->tmpl->assign("started_year", $row['year_started'] . "-00-00");
		
		$this->tmpl->assign("birthdate",  $row['birthdate']);
		
		$this->tmpl->assign("allow_publish_email",  ($row['allow_publish_email'] == 'Y'));
		$this->tmpl->assign("allow_publish_phone",  ($row['allow_publish_phone'] == 'Y'));

		return true;
	}

	function generate_confirm ()
	{
		global $DB, $id;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Person/edit_form.tmpl");
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$this->tmpl->assign("id", $id);

		$this->tmpl->assign("firstname", var_from_getorpost('firstname'));
		$this->tmpl->assign("lastname", var_from_getorpost('lastname'));
		
		$this->tmpl->assign("username", var_from_getorpost('username'));
		
		$this->tmpl->assign("email", var_from_getorpost('email'));
		
		$this->tmpl->assign("addr_street", var_from_getorpost('addr_street'));
		$this->tmpl->assign("addr_city", var_from_getorpost('addr_city'));
		$this->tmpl->assign("addr_prov", var_from_getorpost('addr_prov'));
		$this->tmpl->assign("addr_postalcode", var_from_getorpost('addr_postalcode'));

		$this->tmpl->assign("phone_areacode", var_from_getorpost('phone_areacode'));
		$this->tmpl->assign("phone_prefix", var_from_getorpost('phone_prefix'));
		$this->tmpl->assign("phone_number", var_from_getorpost('phone_number'));
		$this->tmpl->assign("phone_extension", var_from_getorpost('phone_extension'));

		$this->tmpl->assign("gender", var_from_getorpost('gender'));
		$this->tmpl->assign("skill_level", var_from_getorpost('skill_level'));
		
		$this->tmpl->assign("started_Year", var_from_getorpost('started_Year'));
		
		$this->tmpl->assign("birth_Year", var_from_getorpost('birth_Year'));
		$this->tmpl->assign("birth_Month", var_from_getorpost('birth_Month'));
		$this->tmpl->assign("birth_Day", var_from_getorpost('birth_Day'));
		
		$this->tmpl->assign("allow_publish_email", var_from_getorpost('allow_publish_email'));
		$this->tmpl->assign("allow_publish_phone", var_from_getorpost('allow_publish_phone'));

		return true;
	}

	function perform ()
	{
		global $DB, $id;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Person/edit_form.tmpl");
			$this->tmpl->assign("error", 'TODO: Real error goes here');
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$fields      = array();
		$fields_data = array();

		if($this->_permissions['edit_username']) {
			$fields[] = "username = ?";
			$fields_data[] = var_from_getorpost('username');
		}
		
		if($this->_permissions['edit_email']) {
			$fields[] = "email = ?";
			$fields_data[] = var_from_getorpost('email');
		}
		
		if($this->_permissions['edit_phone']) {
			$fields[] = "telephone = ?";
			$fields_data[] = join(" ",array(
				var_from_getorpost('phone_areacode'),
				var_from_getorpost('phone_prefix'),
				var_from_getorpost('phone_number'),
				var_from_getorpost('phone_extension'))
			);
		}
		
		if($this->_permissions['edit_name']) {
			$fields[] = "firstname = ?";
			$fields_data[] = var_from_getorpost('firstname');
			
			$fields[] = "lastname = ?";
			$fields_data[] = var_from_getorpost('lastname');
		}
		
		if($this->_permissions['edit_address']) {
			$fields[] = "addr_street = ?";
			$fields_data[] = var_from_getorpost('addr_street');
			
			$fields[] = "addr_city = ?";
			$fields_data[] = var_from_getorpost('addr_city');
			
			$fields[] = "addr_prov = ?";
			$fields_data[] = var_from_getorpost('addr_prov');
			
			$fields[] = "addr_postalcode = ?";
			$fields_data[] = var_from_getorpost('addr_postalcode');
		}
		
		if($this->_permissions['edit_birthdate']) {
			$fields[] = "birthdate = ?";
			$fields_data[] = join("-",array(
				var_from_getorpost('birth_Year'),
				var_from_getorpost('birth_Month'),
				var_from_getorpost('birth_Day')));
				
		}
		
		if($this->_permissions['edit_gender']) {
			$fields[] = "gender = ?";
			$fields_data[] = var_from_getorpost('gender');
		}
		
		if($this->_permissions['edit_skill']) {
			$fields[] = "skill_level = ?";
			$fields_data[] = var_from_getorpost('skill_level');
			$fields[] = "year_started = ?";
			$fields_data[] = var_from_getorpost('started_Year');
		}

		if(count($fields_data) != count($fields)) {
			$this->error_text = gettext("Internal error: Incorrect number of fields set");
			return false;
		}
		
		if(count($fields) <= 0) {
			$this->error_text = gettext("You have no permission to edit");
			return false;
		}
		
		$sql = "UPDATE person SET ";
		$sql .= join(",", $fields);	
		$sql .= "WHERE user_id = ?";

		$sth = $DB->prepare($sql);
		
		$fields_data[] = $id;
		$res = $DB->execute($sth, $fields_data);
		
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}

	function validate_data ()
	{
		/* TODO: Actually validate some of our data! */
		return true;
/*
	## TODO:
	## Check each form field for appropriateness of data. 
	## If something is wrong, set $validity to 0, and append an HTML
	## error to $error_string.

	## Names can only have letters, numbers spaces, and '-. in them.
	if (($q->param('firstname') !~ m/^[\w][\d\w-\. ']+$/) || ($q->param('lastname') !~ m/^[\w][\d\w-\. ']+$/)) {
		$validity = 0;
		$error_string .= "\n<br>You can only use letters, numbers, spaces, and the characters - ' and . in your name.";
	}

	## username
	##	- only allow [\d\w-_. ]
	if ($q->param('username') !~ m/^[\d\w][\d\w-_\. ]+$/) {
		$validity = 0;
		$error_string .= "\n<br>You can only use letters, numbers, spaces, and the characters - _ and . in your username.  Also, it must start with a letter or a number.";
	}

	## email
	##	- in format user@domain, where domain contains at least one . 
	##    character.  We may also want to check for valid toplevel domains.
	if ($q->param('primary_email') !~ m/^[\d\w-_\.]+\@([\d\w-_]+\.)+[\d\w-_]+$/) {
		$validity = 0;
		$error_string .= "\n<br>You must supply a valid email address";
	}

	## phone
	if (!$accept_short && $q->param('primary_areacode') !~ m/^\d{3}$/) {
		$validity = 0;
		$error_string .= "\n<br>You must supply a 3-digit area code.";
			
	}
	if (!$accept_short && ($q->param('primary_prefix') !~ m/^\d{3}$/ ||
	   $q->param('primary_number') !~ m/^\d{4}$/)) {
		$validity = 0;
		$error_string .= "\n<br>You must supply a valid phone number.";
			
	}

	## address (addr_street, addr_city, addr_prov, addr_postalcode)
	if (!defined($q->param('addr_street')) || ($q->param('addr_street') =~ m/^\s*$/)) {
		$validity = 0;
		$error_string .= "\n<br>Your street address cannot be blank.";
	}
	if (!defined($q->param('addr_city')) || ($q->param('addr_city') =~ m/^\s*$/)) {
		$validity = 0;
		$error_string .= "\n<br>Your city cannot be blank.";
	}
	if (!defined($q->param('addr_prov')) || ($q->param('addr_prov') =~ m/^\s*$/)) {
		$validity = 0;
		$error_string .= "\n<br>Your province cannot be blank.";
	}
	if (!$accept_short && $q->param('addr_postalcode') !~ m/^[a-zA-z]\d[a-zA-z]\d[a-zA-z]\d$/i) {
		$validity = 0;
		$error_string .= "\n<br>Postal code must be in X0X0X0 format (no spaces).";
	}

	## gender
	##	- must be either 'male' or 'female'
	if ($q->param('gender') !~ m/^[MmFf]/) {
		$validity = 0;
		$error_string .= "\n<br>Gender must be either male or female";
	}
	
	## skill
	##  - should be between 0 and 5
#	if (!$accept_short && $q->param('skill_level') !~ m/^[012345]/) {
#		$validity = 0;
#		$error_string .= "\n<br>Skill level must be between 0 and 5";
#	}

	## allow_publish
	
	## birthyear		
	##	- should be four digits
	if (!$accept_short && $q->param('birthyear') !~ m/^\d{4}$/) {
		$validity = 0;
		$error_string .= "\n<br>Birth year must be in YYYY format.";
	}
	if (!$accept_short && $q->param('birthmonth') !~ m/^\d{1,2}$/)  {
		$validity = 0;
		$error_string .= "\n<br>Birth month must be in MM format.";
	}
	if (!$accept_short && $q->param('birthday') !~ m/^\d{1,2}$/) {
		$validity = 0;
		$error_string .= "\n<br>Birth day must be in DD format.";
	}

	return($validity, $error_string);
*/
	}

	function map_callback($item)
	{
		return array("output" => $item, "value" => $item);
	}
}

?>
