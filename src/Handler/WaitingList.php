<?php
/*
 * Handlers for waiting list
 */

function wlist_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new WaitingListCreate; // TODO
		case 'edit':
			return new WaitingListEdit; // TODO
		case 'view':
			return new WaitingListView; // TODO
		case 'list':
		case '':
			return new WaitingListList; // TODO
		case 'viewperson':
			return new WaitingListViewPerson; // TODO
		case 'join':
			return new WaitingListJoin; // TODO
		case 'quit':
			return new WaitingListQuit; // TODO
	}
	return null;
}


class WaitingListEdit extends Handler
{
	function initialize ()
	{
		$this->title = "Edit Waiting List";
		$this->_required_perms = array(	
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'deny'
		);
		$this->op = 'wlist_edit';
		$this->section = 'admin';
		return true;
	}
	
	function process ()
	{
		$id = var_from_getorpost('id');
		$step = var_from_getorpost('step');
		
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				$this->perform( &$id );
				local_redirect("op=wlist_view&id=$id");
				break;
			default:
				$formData = $this->getFormData( $id );
				$rc = $this->generateForm($id, $formData);
		}
		return $rc;
	}
	
	function getFormData ( $id )
	{
		/* TODO: waitinglist_load() */
		return db_fetch_array(db_query( "SELECT * FROM waitinglist WHERE wlist_id = %d", $id));
	}
	
	function generateForm ($id, $formData)
	{
		$output = form_hidden("op", $this->op);
		$output .= form_hidden("step", 'confirm');
		$output .= form_hidden("id", $id);

		$rows = array();
		$rows[] = array("Waiting List Name:", form_textfield('', 'edit[name]', $formData['name'], 35,200, "The title for this waiting list.  Should describe what it's for."));
		$rows[] = array("Description:", 
			form_textarea("", 'edit[description]', $formData['description'], 50, 5, "Information about this particular league waitinglist"));
		
		$rows[] = array("Selection process:", 
			form_select("", "edit[selection]", $formData['selection'], getOptionsFromEnum('waitinglist','selection'), "What type of selection process will be used?"));
		$rows[] = array("Max Male Players:", form_textfield('', 'edit[max_male]', $formData['max_male'], 4,4, "Total number of male players that will be accepted"));
		$rows[] = array("Max Female Players:", form_textfield('', 'edit[max_female]', $formData['max_female'], 4,4, "Total number of female players that will be accepted"));
		$rows[] = array("Allow Couples Registration:", 
			form_select("", "edit[allow_couples_registration]", $formData['allow_couples_registration'], getOptionsFromEnum('waitinglist','allow_couples_registration'), "Can registrants request to be paired with another person?"));

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		if($formData['name']) {
			$this->setLocation(array(
				$formData['name']  => "op=wlist_view&id=$id",
				$this->title => 0));
		} else {
			$this->setLocation(array( $this->title => "op=" . $this->op));
		}

		return form($output);
	}
	
	function generateConfirm ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$edit = var_from_getorpost('edit');

		$output = para("Confirm that the data below is correct and click 'Submit'  to make your changes");
		$output .= form_hidden("op", $this->op);
		$output .= form_hidden("step", 'perform');
		$output .= form_hidden("id", $id);
	
		$rows = array();
		$rows[] = array("Waiting List Name:", form_hidden('edit[name]',$edit['name']) .  $edit['name']);
		$rows[] = array("Description:", 
			form_hidden('edit[description]', $edit['description']) . $edit['description'] );
		$rows[] = array("Selection Process:", form_hidden('edit[selection]',$edit['selection']) .  $edit['selection']);
		$rows[] = array("Max Male Players:", form_hidden('edit[max_male]',$edit['max_male']) .  $edit['max_male']);
		$rows[] = array("Max Female Players:", form_hidden('edit[max_female]',$edit['max_female']) .  $edit['max_female']);
		$rows[] = array("Allow Couples Registration:", form_hidden('edit[allow_couples_registration]',$edit['allow_couples_registration']) .  $edit['allow_couples_registration']);
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));
		
		if($team_name) {
			$this->setLocation(array(
				$team_name  => "op=wlist_view&id=$id",
				$this->title => 0));
		} else {
			$this->setLocation(array( $this->title => "op=" . $this->op));
		}
		
		return form($output);
	}

	
	function perform ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$edit = var_from_getorpost('edit');

		db_query("UPDATE waitinglist SET name = '%s', description = '%s', selection = %d, max_male = %d, max_female = %d, allow_couples_registration = '%s' WHERE wlist_id = %d",
			$edit['name'],
			$edit['description'],
			$edit['selection'],
			$edit['max_male'],
			$edit['max_female'],
			$edit['allow_couples_registration'],
			$id
		);

		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		return true;
	}

	function isDataInvalid ()
	{
		$errors = "";

		$edit = var_from_getorpost('edit');

		if( !validate_nonhtml($edit['name']) ) {
			$errors .= "<li>You must enter a valid name";
		}
		
		if( !validate_number($edit['max_male']) ) {
			$errors .= "<li>Maximum number of male players cannot be left blank";
		}
		
		if( !validate_number($edit['max_female']) ) {
			$errors .= "<li>Maximum number of female players cannot be left blank";
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

class WaitingListCreate extends WaitingListEdit
{
	function initialize ()
	{
		$this->title = "Create Waiting List";
		$this->_required_perms = array(	
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		$this->op = 'wlist_create';
		$this->section = 'admin';
		return true;
	}
	
	function getFormData ( $id )
	{
		return array();
	}

	function perform ( $id )
	{
		global $session;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$edit = var_from_getorpost('edit');
		$name = trim($edit['name']);
	
		db_query("INSERT into waitinglist (name) VALUES ('%s')", $name);
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		$id = db_result(db_query("SELECT LAST_INSERT_ID() from waitinglist"));
		return parent::perform( $id );
	}
}

class WaitingListList extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'delete' => false,
			'edit' => false,
			'create' => false,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'allow',
		);
		$this->op = 'wlist_list';
		$this->section = 'admin';
		$this->setLocation(array("Waiting Lists" => 'op=' . $this->op));
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
		$query = "SELECT name AS value, wlist_id AS id FROM waitinglist ORDER BY name";
		
		$ops = array(
			array(
				'name' => 'view',
				'target' => 'op=wlist_view&id='
			),
		);
		if($this->_permissions['edit']) {
			$ops[] = array(
				'name' => 'edit',
				'target' => 'op=wlist_edit&id='
			);
		}
		if($this->_permissions['delete']) {
			$ops[] = array(
				'name' => 'delete',
				'target' => 'op=wlist_delete&id='
			);
		}
		$output = "";
		if($this->_permissions['create']) {
			$output .= l("Create New Waiting List", "op=wlist_create");
		}
		
		$output .= $this->generateSingleList($query, $ops);
		return $output;
	}
}

class WaitingListView extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'edit' => false,
			'viewmembers' => false,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'allow'
		);
		$this->title = 'View Waiting List';
		$this->section = 'admin';
		$this->op = 'wlist_view';

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
		$id = var_from_getorpost('id');

		/* TODO: waitinglist_load() */
		$data = db_fetch_array(db_query(
			"SELECT * FROM waitinglist WHERE wlist_id = %d",$id));

		if(!$data) {
			$this->error_exit("That is not a valid waitinglist ID");
		}
	
		$output = '';
		
		if($this->_permissions['edit']) {
			$output .= l('edit',"op=wlist_edit&id=$id");
		}

		$rows = array();
		$rows[] = array("Waiting List Name:", $data['name']);
		$rows[] = array("Description:", $data['description']);
		$rows[] = array("Selection Process:", $data['selection']);
		$rows[] = array("Max Male Players:", $data['max_male']);
		$rows[] = array("Max Female Players:", $data['max_female']);
		$rows[] = array("Allow Couples Registration:", $data['allow_couples_registration']);
		
		$listRows = array();
		$result = db_query(
			"SELECT p.firstname,p.lastname,p.gender,p.skill_level,p.height,m.*,partner.firstname as partner_firstname, partner.lastname as partner_lastname, partner.user_id AS partner_id
			 FROM waitinglistmembers m
			   LEFT JOIN person p ON (p.user_id = m.user_id)
			   LEFT JOIN person partner ON (partner.member_id = m.paired_with)
			 WHERE m.wlist_id = %d 
			 ORDER BY m.date_registered", $id);
				 
		$position = 1;
		$genderCount = array(
			'male' => 0,
			'female' => 0,
		);
		while($player = db_fetch_object($result)) {

			$lcGender = strtolower($player->gender);
			if(++$genderCount[$lcGender] <= $data["max_$lcGender"]) {
				$class = 'highlight';	
			} else {
				$class = 'light';
			}
		
			if( isset($player->partner_id) ) {
				$partnerInfo = l("$player->partner_firstname $player->partner_lastname", "op=wlist_viewperson&id=$player->partner_id");
			} else {
				$partnerInfo = '';
			}
				
			$listRows[] = array(
				array('data' => l("$player->firstname $player->lastname", "op=wlist_viewperson&id=$player->user_id"), 'class' => $class),
				array('data' => $player->preference, 'class' => $class),
				array('data' => $player->date_registered, 'class' => $class),
				array('data' => $player->gender, 'class' => $class),
				array('data' => $player->skill_level, 'class' => $class),
				array('data' => $player->height, 'class' => $class),
				array('data' => $partnerInfo, 'class' => $class),
			);
			$position++;
		}
		$rows[] = array('Current Males Registered:', $genderCount['male']);
		$rows[] = array('Current Females Registered:', $genderCount['female']);
		if($this->_permissions['viewmembers']) {
			$header = array( 'Name', 'Preference', 'Date Registered', 'Gender', 'Skill', 'Height', 'Partner');
			$rows[] = array('Waitlist Members:', 
				"<div class='listtable'>" . table($header, $listRows) . "</div>");
		}
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		
		$this->setLocation(array(
			$data['name'] => "op=wlist_view&id=$id",
			$this->title => 0));

		return $output;
	}
}

class WaitingListJoin extends Handler
{
	function initialize ()
	{
		$this->title = "Join Waiting List";
		$this->_required_perms = array(	
			'require_valid_session',
			'allow'
		);

		/* If this is set to a nonzero value, the user will be given
		 * this many choices for registering their preference.  
		 * otherwise, they will be allowed to rank all.
		 */
		$this->max_preference = 0;
		
		$this->op = 'wlist_join';
		$this->section = 'person';
		return true;
	}
	
	function process ()
	{
		global $session;
		
		$step = var_from_getorpost('step');

		/* First, make sure this person isn't on any lists yet */
		$numLists = db_result(db_query("SELECT COUNT(*) from waitinglistmembers WHERE user_id = %d", $session->attr_get('user_id')));
		
		if($numLists) {
			return para("You have already made your waiting list selections.")
				. para("You can view your selections " . l('here',"op=wlist_viewperson&id=" . $session->attr_get('user_id')));
		}
		
		/* TODO: This should be a config option!  It duplicates info in
		 * Menu.php */
		$signupTime = mktime(9,0,0,10,22,2003);
		if(	time() < $signupTime) {
			return para("You may not make any selections until registration opens.")
				. para("Indoor Signup opens " . date("F j Y h:i A", $signupTime));
		}
		
		$signupTime = mktime(9,0,0,10,31,2003);
		if(	time() > $signupTime) {
			return para("Online indoor signup is now closed.")
				. para("If you still wish to sign up, please email <a href='mailto:spenceitup@hotmail.com'>spenceitup@hotmail.com</a>.");
		}

		
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( );
				break;
			case 'perform':
				$rc = $this->perform( );
				break;
			default:
				$rc = $this->generateForm();
		}
		return $rc;
	}
	
	function generateForm ( )
	{
		$result = db_query("SELECT * from waitinglist");

		if( ! $this->max_preference) {
			$this->max_preference = db_num_rows($result);
		}
		
		$output = form_hidden("op", $this->op);
		$output .= form_hidden("step", 'confirm');

		$output .= para(
				"Enter your preferences for the following waiting lists.  You may
				select up to " . $this->max_preference . ", ranked in order of
				preference.  Where space and other restrictions allow, you may be
				given the opportunity to play on more than one night.")
			. para("Note that you do not need to rank all nights of play if you do not wish to be considered for all of them.  You may either leave columns blank for 3rd, 4th, etc, preference, or select \"No selection\" for that column.")
			. para("After you have made your selection, you will be given an opportunity to confirm it.  Once you have confirmed your selections, they will be final, and cannot be changed without losing your priority.")
			. para("If you are willing and able to be a team captain for any of the divisions you have registered for, please contact the indoor coordinator at <a href='mailto:spenceitup@hotmail.com'>spenceitup@hotmail.com</a> with your name and contact info after completing your registration.")
			. para("For lesser-known players wishing to join the Tuesday Advanced Division, you may also wish to mail the indoor coordinator at <a href='mailto:spenceitup@hotmail.com'>spenceitup@hotmail.com</a> with the name of a player or two who can provide a recommendation.");
		
		$header = array(
			array( "Choices", 'colspan'=>$this->max_preference ),
			array( "Name", 'rowspan' => 2 ),
			array( "Partner Member Number (optional)", 'rowspan' => 2)
		);
		
		$rows = array();
		for($i=1; $i <= $this->max_preference; $i++) {
			$prefColumns[] = numberToOrdinal($i);
		}
		$rows[] = array( $prefColumns );
		
		for($i=1;$i <= $this->max_preference; $i++) {
			$buttonColumns[] = form_radio('', "edit[preference][$i]", 0);
		}
		$buttonColumns[] = array("No selection", 'colspan' => 2);
		$rows[] = array( $buttonColumns );
		
		$rowCount = 0;
		while($list = db_fetch_object($result)) {
			$buttonColumns = array();
			for($i=1;$i <= $this->max_preference; $i++) {
				$buttonColumns[] = form_radio('', "edit[preference][$i]", $list->wlist_id);
			}
			$buttonColumns[] = "$list->name (" . l('view', "op=wlist_view&id=$list->wlist_id") . ")";
			
			if($list->allow_couples_registration == 'Y') {
				$buttonColumns[] = form_textfield('', "edit[$list->wlist_id][paired_with]", '', 8,8);
			} else {
				$buttonColumns[] = '';
			}
			$rowCount++;
			$rows[] = array ($buttonColumns);
		}
		
		$output .= "<div class='waitlist'>" . array($header, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		$this->setLocation(array( $this->title => "op=$this->op"));

		return form($output);
	}
	
	function generateConfirm ( )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$edit = var_from_getorpost('edit');

		$output = para("Confirm that the data below is correct and click 'Submit' if you are satisfied.  Be advised that you <b>cannot</b> change this information later without losing your priority!");
		
		$output .= form_hidden("op", $this->op);
		$output .= form_hidden("step", 'perform');
		
		$rows = array();
		while(list($preference,$wlist_id) = each($edit['preference'])) {

			if($wlist_id == 0) {
				continue;
			}

			$list = db_fetch_object(db_query("SELECT * from waitinglist WHERE wlist_id = %d", $wlist_id));
			if(!$list) {
				$this->error_exit("An invalid waitinglist was specified; please go back and try again");		
			}
			
			$rows[] = array(
				array( 'data' => $list->name, 'class' => 'subtitle', 'colspan' => 2)
			);
			
			$rows[] = array("Registration Preference:", form_hidden("edit[preference][$preference]", $wlist_id) . numberToOrdinal($preference));

			$paired_with = $edit[$wlist_id]['paired_with'];
			if($paired_with && $list->allow_couples_registration == 'Y') {
				$partnerName = db_result(db_query("SELECT CONCAT(firstname,' ',lastname) FROM person WHERE member_id = %d", $paired_with));
				
				if(!$partnerName) {
					$this->error_exit("An invalid partner member ID was specified; please go back and try again");		
				}
				$rows[] = array(
					array("Partner:",
						form_hidden("edit[$wlist_id][paired_with]", $paired_with) . "$partnerName (OCUA Member Number $paired_with)")
				);
			}
		}
		
		$output .= "<div class=listtable'>" . table(null,$rows) . "</div>";
		$output .= para(form_submit("submit"));
		
		$this->setLocation(array( $this->title => "op=" . $this->op));
		
		return form($output);
	}


	function isDataInvalid ()
	{
		global $session;
		$errors = "";

		$edit = var_from_getorpost('edit');
	
		$seenPreference = array();
		$seenWlist = array();
		while(list($preference,$wlist_id) = each($edit['preference'])) {
			if( $wlist_id == 0 ) {
				continue;
			}
			
			if( $wlist_id < 0 ) {
				$errors .= "<li>An invalid waitlist ID was supplied";
			}
			
			if(array_key_exists($wlist_id, $seenWlist)) {
				$errors .= "<li>You cannot specify the same waitlist more than once";
			}
			$seenWlist[$wlist_id] = true;
			
			if( $preference < 0) {
				$errors .= "<li>An invalid preference was supplied: $preference";
			}
			
			if(($preference != 0) && array_key_exists($preference, $seenPreference)) {
				$errors .= "<li>You cannot specify the same preference for more than one waitlist";
			}
			$seenPreference[$preference] = true;

			if(isset($edit[$wlist_id]['paired_with'])) {
				if($edit[$wlist_id]['paired_with'] == $session->attr_get('member_id')) {
					$errors .= "<li>You cannot specify yourself as a partner!";
				}
	#			if($edit[$wlist_id]['paired_with'] == 0) {
	#				$errors .= "<li>Do not enter 0 as a partner ID.  If you do not wish to register as a couple, leave this field blank";
	#			}
			}
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
	
	function perform (  )
	{
		global $session;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$edit = var_from_getorpost('edit');
	
		while(list($preference,$wlist_id) = each($edit['preference'])) {

			if($wlist_id == 0) {
				continue;
			}

			db_query("INSERT INTO waitinglistmembers (wlist_id,user_id,paired_with,preference,date_registered) VALUES(%d,%d,%d,%d,NOW())",
				$wlist_id,
				$session->attr_get('user_id'),
				$edit[$wlist_id]['paired_with'],
				$preference
			);
			
			if(1 != db_affected_rows() ) {
				return false;
			}
		}

		$output = para("Your preferences have been recorded.  Please note that you need to send in a separate cheque for each night you have registered for.  DO NOT send in a single cheque covering all nights or you may lose your position on the waiting list.  Remember to write your OCUA member number clearly on the front or back of the cheque.");
		$output .= para("If you are willing and able to be a team captain for any of the divisions you have registered for, please contact the indoor coordinator at <a href='mailto:spenceitup@hotmail.com'>spenceitup@hotmail.com</a> with your name and contact info.");
		$output .= para("For lesser-known players wishing to join the Tuesday Advanced Division, you may also wish to mail the indoor coordinator at <a href='mailto:spenceitup@hotmail.com'>spenceitup@hotmail.com</a> with the name of a player or two who can provide a recommendation");
		$output .= para("Make cheques payable to \"Ottawa-Carleton Ultimate Association\" and mail them to<br />Ottawa Carleton Ultimate Association<br />PO Box 120, 410 Bank St<br />Ottawa, Ontario<br /> K2P 1Y8.");
		$output .= para("Cheques must be received by Nov 7th or you will lose your spot.");

		return $output;
	}
}

class WaitingListQuit extends Handler
{
	function initialize ()
	{
		$this->title = "Quit Waiting List";
		$this->_required_perms = array(	
			'require_valid_session',
			'require_var:id',
			'require_var:user',
			'admin_sufficient',
			'self_sufficient:user',
			'deny'
		);

		$this->op = 'wlist_quit';
		$this->section = 'person';
		return true;
	}
	
	function process ()
	{
		$step = var_from_getorpost('step');
		
		$id = var_from_getorpost('id');
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				$this->perform( $id );
				break;
			default:
				$this->error_exit("You cannot perform that operation");
		}
		return $rc;
	}
	
	function generateConfirm ( $id )
	{
		global $session;

		if($session->is_admin()) {
			$user = var_from_getorpost('user');
			
			$info = db_fetch_array(db_query("SELECT firstname, lastname FROM person WHERE user_id = %d", $user));

			if( !$info ) {
				$this->error_exit("That is not a valid user");
			}
			$fullName = $info['firstname'] . " " . $info['lastname'];
		} else {
			$user = $session->attr_get('user_id');
			$fullName = $session->attr_get('firstname') . " " . $session->attr_get('lastname');
		}
		
		$this->setLocation(
			array( $fullName => "person/view/$user", $this->title => 0,));
		
		$listName = db_result(db_query("SELECT name from waitinglist WHERE wlist_id = %d", $id));

		if( !$listName ) {
			$this->error_exit("That is not a valid waiting list.");
		}

		$output = para("Confirm that you wish to remove $fullName from the $listName waiting list.  Note that this will result in a loss of any priority for this list and that you cannot be re-added."
		);
		
		$output .= form_hidden("op", $this->op);
		$output .= form_hidden("step", 'perform');
		$output .= form_hidden("id", $id);
		$output .= form_hidden("user", $user);
		
		$output .= para(form_submit("submit"));
		
		return form($output);
	}

	function perform ( $id )
	{
		global $session;

		if($session->is_admin()) {
			$user_id = var_from_getorpost('user');
		} else {
			$user_id = $session->attr_get('user_id');
		}

		db_query("DELETE 
			 FROM waitinglistmembers 
			 WHERE wlist_id = %d
			   AND user_id = %d", $id, $user_id);
			
		if( 1 != db_affected_rows() ) {
			return false;
		}
		local_redirect("op=wlist_viewperson&id=$user_id");
	}
}

class WaitingListViewPerson extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'self_sufficient',
			'deny'
		);
		$this->title = 'Waiting Lists';
		$this->section = 'person';
		$this->op = 'wlist_viewperson';

		return true;
	}

	function process ()
	{
		global $session;


		if($session->is_admin()) {
			$id = var_from_getorpost('id');
			$info = db_fetch_object(db_query("SELECT firstname, lastname, gender FROM person WHERE user_id = %d", $id));

			if( !$info ) {
				$this->error_exit("That is not a valid user");
			}
			$lcGender = strtolower($info->gender);
			$fullName = $info->firstname . " " . $info->lastname;
		} else {
			$id = $session->attr_get('user_id');
			$lcGender = strtolower($session->attr_get('gender'));
			$fullName = $session->attr_get('firstname') . " " . $session->attr_get('lastname');
		}
		
		$this->setLocation(array( $fullName => "person/view/$id", $this->title => 0));

		$result = db_query(
			"SELECT w.*, m.preference,m.paired_with
			 FROM waitinglist w, waitinglistmembers m
			 WHERE w.wlist_id = m.wlist_id 
			   AND m.user_id = %d
			 ORDER BY m.preference",$id);

		if(db_num_rows($result) < 1) {
			$signupTime = mktime(9,0,0,10,31,2003);
			if(	time() > $signupTime) {
				return para("Online indoor signup is now closed.")
					. para("If you still wish to sign up, please email <a href='mailto:spenceitup@hotmail.com'>spenceitup@hotmail.com</a>.");
			}

			// TODO: This belongs in a config file or as a data resource
			$output = <<<EOM
<p>
Welcome to OCUA's Winter Indoor Registration.  Please read everything
carefully before you proceed to make sure you do not make a mistake.
Once you submit your registration you may not change your preferences
without losing your priority ranking on the waiting lists.  You are able
to remove yourself from waiting lists.  If you remove yourself from all
waiting lists you can start registration with a clean slate.
</p>
<p>
If you want to register as a couple you must know your partner/friend's
OCUA member number.
</p>
<p>
Instructions for cheque submission will be displayed after you submit
your registration.  You will need to provide OCUA with a separate cheque
for each division in which you register.  If you are not accepted into a
particular division your cheque for that division will be destroyed.
</p>
<p>
The lists of players attempting to register for a division is called a
waiting list. That's just a label. Everyone is signing up for the
waiting lists. Then, for the Open, Rec, and Daytime divisions the people
who get in are selected from the top of the waiting list. After all the
spots have been filled those who didn't make it in are on a traditional
waiting list. Any subsequent openings within divisions will be filled
from the top of the traditional waiting list.  Players for Tuesday
Advanced will be selected from the entire waiting list.  Order of
submission does not apply for the Tuesday Advanced division.
</p>
<p>
Registration for the Wednesday Competitive division is not being done in
Leaguerunner.  An announcement about the Wednesday Competitive
registration procedure should be made on OCUA's website shortly.
Remember: You cannot play in both Wednesday Competitive and Tuesdays
Advanced.  If you are interested in playing Competitive but might not be
selected it is in your best interest to apply for Tuesdays Advanced as
well.
</p>
<p>
Incomplete registration submissions will be rejected.  The onus is on
you, the user, to complete your registration in its entirety.  We (the
Webteam, Coordinating team, and OCUA Management) accept no
responsibility for user error.
</p>
EOM;
			$output .= para("You are not currently on any waiting lists.  Click <b>" . l('here','op=wlist_join') . "</b> to sign up for one or more divisions.");
			return $output;
		}

		$output = para("Your waiting list registration choices are below.  Note that
			waiting list merely guarantees your order of consideration for a
			playing slot and does <b>not</b> guarantee your selection.")
			. para("Selection for each night will be made based on all
			appropriate criteria, (including experience and skill level for
			some nights) however every attempt will be made to ensure that as
			many people as possible get their first choice for at
			least one night of play.");
		$output = para("Please note that you need to send in a separate cheque for each night you have registered for.  DO NOT send in a single cheque covering all nights or you may lose your position on the waiting list.  Remember to write your OCUA member number clearly on the front or back of the cheque.");
		$output .= para("If you are willing and able to be a team captain for any of the divisions you have registered for, please contact the indoor coordinator at <a href='mailto:spenceitup@hotmail.com'>spenceitup@hotmail.com</a> with your name and contact info.");
		$output .= para("For lesser-known players wishing to join the Tuesday Advanced Division, you may also wish to mail the indoor coordinator at <a href='mailto:spenceitup@hotmail.com'>spenceitup@hotmail.com</a> with the name of a player or two who can provide a recommendation");
		$output .= para("Make cheques payable to \"Ottawa-Carleton Ultimate Association\" and mail them to<br />Ottawa Carleton Ultimate Association<br />PO Box 120, 410 Bank St<br />Ottawa, Ontario<br /> K2P 1Y8.");
		$output .= para("Cheques must be received by Nov 7th or you will lose your spot.");

		$rows = array();
		while($data = db_fetch_object($result)) {
			$rows[] = array(
				array('data' => $data->name, 'class' => 'subtitle'),
				array('data' => l("remove from this waitlist", "op=wlist_quit&step=confirm&id=$data->wlist_id &user=$id"), 'class' => 'subtitle')
			);

			$rows[] = array("Registration Preference:", $data->preference);

			/* The following is a very ugly way to find this person's
			 * position in the waiting list; however there doesn't
			 * appear to be a nicer way to do it
			 */
			$posResult = db_query(
				"SELECT w.user_id, p.gender
				 FROM waitinglistmembers w, person p
				 WHERE p.user_id = w.user_id
				   AND wlist_id = %d
				 ORDER BY date_registered", array($data->wlist_id));
				 
			$totalRows = db_num_rows($posResult);
			$position = array(
				'male' => 1,
				'female' => 1 
			);
			while($row = db_fetch_array($posResult)) {
				if($row['user_id'] == $id) {
					break;
				}
				$position[ strtolower($row['gender']) ]++;
			}

			$waitlistPosition = "Currently ".numberToOrdinal($position[$lcGender]) . " $lcGender of $totalRows total registrants.<br/>";

			if($position[$lcGender] < $data["max_$lcGender"]) {
				$waitlistPosition .= "Under consideration, pending payment and approval";
			} else {
				$waitlistPosition .= "Waiting for an available spot";
			}
			$waitlistPosition .= " (limit is $data->max_male men, $data->max_female women)";
			
			$rows[] = array("Waitlist Position:", $waitlistPosition);
			if($data->paired_with) {
				$partnerName = db_result(db_query("SELECT CONCAT(firstname,' ',lastname) FROM person WHERE member_id = %d", $data->paired_with));
				if(! $partnerName ) {
					$this->error_exit("An invalid partner member ID was specified; please go back and try again");		
				}
				
				$rows[] = array("Partner:", 
					form_hidden("edit[$wlist_id][paired_with]", $data->paired_with) . "$partnerName (OCUA Member Number $data->paired_with)");
			}
		}

		$output .= "<div class='listtable'>" . table(null,$rows) . "</div>";
		
		return $output;
	}
}

/**
 * Convert a numeric number to an English ordinal number for ease of reading.
 * Rule of thumb is:  If the 'tens' digit is 1, then add 'th'.  Otherwise,
 * check the last digit to determine which suffix to use.  1 == 'st', 2 ==
 * 'nd', 3 == 'rd', 4 through 0 == 'th'
 */
function numberToOrdinal ( $num )
{
	
	if( (floor( $num / 10 ) % 10) == 1 ) {
		return $num . "th";
	}

	switch($num % 10) {
		case 1:
			return $num . "st";
		case 2:
			return $num . "nd";
		case 3:
			return $num . "rd";
		default:
			return $num . "th";
	}

}

?>
