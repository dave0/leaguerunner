<?php
register_page_handler('wlist_create', 'WaitingListCreate');
register_page_handler('wlist_edit', 'WaitingListEdit');
register_page_handler('wlist_view', 'WaitingListView');
register_page_handler('wlist_viewperson', 'WaitingListViewPerson');
#register_page_handler('wlist_delete', 'WaitingListDelete');
register_page_handler('wlist_list', 'WaitingListList');
register_page_handler('wlist_join', 'WaitingListJoin');
register_page_handler('wlist_quit', 'WaitingListQuit');

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
		global $DB;
		
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
		global $DB;

		$data = $DB->getRow(
			"SELECT * FROM waitinglist WHERE wlist_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($data)) {
			return false;
		}

		return $data;
	}
	
	function generateForm ($id, $formData)
	{
		$output = form_hidden("op", $this->op);
		$output .= form_hidden("step", 'confirm');
		$output .= form_hidden("id", $id);
		$output .= "<table border='0'>";
		$output .= simple_row("Waiting List Name:", form_textfield('', 'edit[name]', $formData['name'], 35,200, "The title for this waiting list.  Should describe what it's for."));
		$output .= simple_row("Description:", 
			form_textarea("", 'edit[description]', $formData['description'], 50, 5, "Information about this particular league waitinglist"));
		
		$output .= simple_row("Selection process:", 
			form_select("", "edit[selection]", $formData['selection'], getOptionsFromEnum('waitinglist','selection'), "What type of selection process will be used?"));
		$output .= simple_row("Max Male Players:", form_textfield('', 'edit[max_male]', $formData['max_male'], 4,4, "Total number of male players that will be accepted"));
		$output .= simple_row("Max Female Players:", form_textfield('', 'edit[max_female]', $formData['max_female'], 4,4, "Total number of female players that will be accepted"));
		$output .= simple_row("Allow Couples Registration:", 
			form_select("", "edit[allow_couples_registration]", $formData['allow_couples_registration'], getOptionsFromEnum('waitinglist','allow_couples_registration'), "Can registrants request to be paired with another person?"));

		$output .= "</table>";
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
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$edit = var_from_getorpost('edit');

		$output = para("Confirm that the data below is correct and click 'Submit'  to make your changes");
		$output .= form_hidden("op", $this->op);
		$output .= form_hidden("step", 'perform');
		$output .= form_hidden("id", $id);
		
		$output .= "<table border='0'>";
		$output .= simple_row("Waiting List Name:", form_hidden('edit[name]',$edit['name']) .  $edit['name']);
		$output .= simple_row("Description:", 
			form_hidden('edit[description]', $edit['description']) . $edit['description'] );
		$output .= simple_row("Selection Process:", form_hidden('edit[selection]',$edit['selection']) .  $edit['selection']);
		$output .= simple_row("Max Male Players:", form_hidden('edit[max_male]',$edit['max_male']) .  $edit['max_male']);
		$output .= simple_row("Max Female Players:", form_hidden('edit[max_female]',$edit['max_female']) .  $edit['max_female']);
		$output .= simple_row("Allow Couples Registration:", form_hidden('edit[allow_couples_registration]',$edit['allow_couples_registration']) .  $edit['allow_couples_registration']);
		$output .= "</table>";
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
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$edit = var_from_getorpost('edit');

		$res = $DB->query("UPDATE waitinglist SET name = ?, description = ?, selection = ?, max_male = ?, max_female = ?, allow_couples_registration = ? WHERE wlist_id = ?",
			array(
				$edit['name'],
				$edit['description'],
				$edit['selection'],
				$edit['max_male'],
				$edit['max_female'],
				$edit['allow_couples_registration'],
				$id,
			)
		);
		
		$err = isDatabaseError($res);
		if($err != false) {
			if(strstr($err,"uplicate entry ")) {
				$err = "A team with that name already exists; please go back and try again";
			}
			$this->error_exit($err);
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
		global $DB, $session;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$edit = var_from_getorpost('edit');
		$name = trim($edit['name']);
	
		$res = $DB->query("INSERT into waitinglist (name) VALUES (?)", array($name));
		$err = isDatabaseError($res);
		if($err != false) {
			if(strstr($err,"already exists: INSERT into")) {
				$err = "A waiting list with that name already exists; please go back and try again";
			}
			$this->error_exit($err);
		}
		
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from waitinglist");
		if($this->is_database_error($id)) {
			return false;
		}

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
		global $DB;

		$query = $DB->prepare("SELECT 
			name AS value, wlist_id AS id
			FROM waitinglist ORDER BY name");
		
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
			$output .= blockquote(l("Create New Waiting List", "op=wlist_create"));
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
		global $DB;

		$id = var_from_getorpost('id');

		$data = $DB->getRow(
			"SELECT * FROM waitinglist WHERE wlist_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($data)) {
			return false;
		}

		if(!isset($data)) {
			$this->error_exit("That is not a valid waitinglist ID");
		}
	
		$output = '';
		
		if($this->_permissions['edit']) {
			$output .= blockquote(l('edit',"op=wlist_edit&id=$id"));
		}
	
		$output .= "<table border='0'>";
		$output .= simple_row("Waiting List Name:", $data['name']);
		$output .= simple_row("Description:", $data['description']);
		$output .= simple_row("Selection Process:", $data['selection']);
		$output .= simple_row("Max Male Players:", $data['max_male']);
		$output .= simple_row("Max Female Players:", $data['max_female']);
		$output .= simple_row("Allow Couples Registration:", $data['allow_couples_registration']);
		$output .= "</table>";
		if($this->_permissions['viewmembers']) {
			$listContents .= "<div class='waitlist'><table border='0'>";
			$listContents .= tr(
				th( 'Name' )
				. th( 'Preference' )
				. th( 'Date Registered' )
				. th( 'Gender' )
				. th( 'Skill' )
				. th( 'Height' )
				. th( 'Partner' )
			);
			$listMembers = $DB->query(
				"SELECT p.firstname,p.lastname,p.gender,p.skill_level,p.height,m.*,partner.firstname as partner_firstname, partner.lastname as partner_lastname, partner.user_id AS partner_id
				 FROM waitinglistmembers m
				   LEFT JOIN person p ON (p.user_id = m.user_id)
				   LEFT JOIN person partner ON (partner.member_id = m.paired_with)
				 WHERE m.wlist_id = ? 
				 ORDER BY m.date_registered", array($id));
				 
			if($this->is_database_error($listMembers)) {
				return false;
			}
		
			$position = 1;
			$genderCount = array(
				'male' => 0,
				'female' => 0,
			);
			while($row = $listMembers->fetchRow(DB_FETCHMODE_ASSOC)) {

				$lcGender = strtolower($row['gender']);
				if(++$genderCount[$lcGender] <= $data["max_$lcGender"]) {
					$style = array('class' => 'highlight');	
				} else {
					$style = array();
				}
			
				if( isset($row['partner_id']) ) {
					$partnerInfo = l($row['partner_firstname'] . ' ' . $row['partner_lastname'], 'op=wlist_viewperson&id='.$row['partner_id']);
				} else {
					$partnerInfo = '';
				}
				$listContents .= tr(
					td(l($row['firstname'] . ' ' . $row['lastname'], 'op=wlist_viewperson&id='.$row['user_id']), $style)
					. td($row['preference'], $style)
					. td($row['date_registered'], $style)
					. td($row['gender'], $style )
					. td($row['skill_level'], $style )
					. td($row['height'], $style )
					. td($partnerInfo, $style)
				);
				$position++;
				
			}
			$listMembers->free();
			$listContents .= "</table></div>";
			$output .= simple_row('Waitlist Members:', $listContents);
		}
		
		$this->setLocation(array(
			$data['name'] => "op=wlist_view&id=$id",
			$this->title => 0));
		$output .= "</table></div>";

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
		global $DB, $session;
		
		$step = var_from_getorpost('step');

		/* First, make sure this person isn't on any lists yet */
		$numLists = $DB->getOne("SELECT COUNT(*) from waitinglistmembers WHERE user_id = ?", array($session->attr_get('user_id')));
		if($this->is_database_error($numLists)) {
			return false;
		}
		if($numLists > 0) {
			return blockquote(para("You have already made your waiting list selections.")
				. para("You can view your selections " . l('here',"op=wlist_viewperson&id=" . $session->attr_get('user_id')))
			);
		}
		
		/* TODO: This should be a config option!  It duplicates info in
		 * Menu.php */
		$signupTime = time();#mktime(9,0,0,10,22,2003);
		if(	time() < $signupTime) {
			return blockquote(para("You may not make any selections until registration opens.")
				. para("Indoor Signup opens " . date("F j Y h:i A", $signupTime)));
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
		global $DB;
		
		$dbResult = $DB->query("SELECT * from waitinglist");
		if($this->is_database_error($dbResult)) {
			return false;
		}

		if( ! $this->max_preference) {
			$this->max_preference = $dbResult->numRows();
		}
		
		$output = form_hidden("op", $this->op);
		$output .= form_hidden("step", 'confirm');

		$output .= blockquote(
			para(
				"Enter your preferences for the following waiting lists.  You may
				select up to " . $this->max_preference . ", ranked in order of
				preference.  Where space and other restrictions allow, you may be
				given the opportunity to play on more than one night.")
			. para("Note that you do not need to rank all nights of play if you do not wish to be considered for all of them.  You may either leave columns blank for 3rd, 4th, etc, preference, or select \"No selection\" for that column.")
			. para("After you have made your selection, you will be given an opportunity to confirm it.  Once you have confirmed your selections, they will be final, and cannot be changed without losing your priority.")
		);
		
		$output .= "<div class='waitlist'><table border='0'>";

		$output .= tr(
			th( "Choices", array('colspan'=>$this->max_preference ) )
			. th( "Name", array('rowspan' => 2 ))
			. th( "Partner Member Number (optional)", array('rowspan' => 2) )
		);
		for($i=1; $i <= $this->max_preference; $i++) {
			$prefColumns .= th( numberToOrdinal($i));
		}
		$output .= tr( $prefColumns );
		
		$output .= "<tr>";
		for($i=1;$i <= $this->max_preference; $i++) {
			$output .= td(
				form_radio('', "edit[preference][$i]", 0));
		}
		$output .= td("No selection", array('colspan' => 2));
		$output .= "</tr>";
		
		$rowCount = 0;
		while($list = $dbResult->fetchRow(DB_FETCHMODE_ASSOC)) {
			$output .= "<tr>";
			for($i=1;$i <= $this->max_preference; $i++) {
				$output .= td(
					form_radio('', "edit[preference][$i]", $list['wlist_id']));
				
			}
			$output .= td( $list['name'] . " (" . l('view', 'op=wlist_view&id=' . $list['wlist_id']) . ")");
			if($list['allow_couples_registration'] == 'Y') {
				$output .= td(form_textfield('', 'edit[' . $list['wlist_id'] . '][paired_with]', '', 8,8));
			} else {
				$output .= td('');
			}
			
			$rowCount++;
			$output .= "</tr>";
		}
		$dbResult->free();
		
		$output .= "</table></div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		$this->setLocation(array( $this->title => "op=" . $this->op));

		return form($output);
	}
	
	function generateConfirm ( )
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$edit = var_from_getorpost('edit');

		$output = para("Confirm that the data below is correct and click 'Submit' if you are satisfied.  Be advised that you <b>cannot</b> change this information later without losing your priority!");
		
		$output .= form_hidden("op", $this->op);
		$output .= form_hidden("step", 'perform');
		
		$output .= "<div class='waitlist'><table border='0'>";
		while(list($preference,$wlist_id) = each($edit['preference'])) {

			if($wlist_id == 0) {
				continue;
			}

			$list = $DB->getRow("SELECT * from waitinglist WHERE wlist_id = ?", array($wlist_id), DB_FETCHMODE_ASSOC);
			if($this->is_database_error($list)) {
				return false;
			}

			$output .= tr(
				th( $list['name'], array('colspan' => 2) )
			);
			
			$output .= simple_row("Registration Preference:", form_hidden("edit[preference][$preference]", $wlist_id) . numberToOrdinal($preference));

			$paired_with = $edit[$wlist_id]['paired_with'];
			if($paired_with && $list['allow_couples_registration'] == 'Y') {
				$partnerName = $DB->getOne("SELECT CONCAT(firstname,' ',lastname) FROM person WHERE member_id = ?", array($paired_with));
				if($this->is_database_error($partnerName)) {
					return false;
				}
				
				if(!isset($partnerName)) {
					$this->error_exit("An invalid partner member ID was specified; please go back and try again");		
				}
				
				$output .= simple_row("Partner:", 
					form_hidden("edit[$wlist_id][paired_with]", $paired_with) . "$partnerName (OCUA Member Number $paired_with)");
			}
		}
		
		$output .= "</table></div>";
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

			if($edit[$wlist_id]['paired_with']) {
				if($edit[$wlist_id]['paired_with'] == $session->attr_get('member_id')) {
					$errors .= "<li>You cannot specify yourself as a partner!";
				}
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
		global $DB, $session;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$edit = var_from_getorpost('edit');
	
		while(list($preference,$wlist_id) = each($edit['preference'])) {

			if($wlist_id == 0) {
				continue;
			}

			$result = $DB->query("INSERT INTO waitinglistmembers (wlist_id,user_id,paired_with,preference,date_registered) VALUES(?,?,?,?,NOW())",
				array(
					$wlist_id,
					$session->attr_get('user_id'),
					$edit[$wlist_id]['paired_with'],
					$preference
				)
			);
			
			if($this->is_database_error($result)) {
				return false;
			}
		}

		$output = para("Your preferences have been recorded.  Please note that you need to send in a separate cheque for each night you have registered for.  DO NOT send in a single cheque covering all nights or you may lose your position on the waiting list.");
		$output .= para("Make cheques payable to \"Ottawa-Carleton Ultimate Association\" and mail them to TODO: what address?!");

		return blockquote($output);
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
		global $DB;
		
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
		global $DB, $session;

		if($session->is_admin()) {
			$user = var_from_getorpost('user');
			$info = $DB->getRow("SELECT firstname, lastname FROM person WHERE user_id = ?", array($user), DB_FETCHMODE_ASSOC);
			if($this->is_database_error($info)) {
				return false;
			}
			if(is_null($info)) {
				$this->error_exit("That is not a valid user");
			}
			$fullName = $info['firstname'] . " " . $info['lastname'];
		} else {
			$user = $session->attr_get('user_id');
			$fullName = $session->attr_get('firstname') . " " . $session->attr_get('lastname');
		}
		
		$this->setLocation(
			array( $fullName => "op=person_view&id=$user", $this->title => 0,));
		
		$list = $DB->getRow("SELECT * from waitinglist WHERE wlist_id = ?", array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($list)) {
			return false;
		}
		if(is_null($list)) {
			$this->error_exit("That is not a valid waiting list.");
		}

		$output = para("Confirm that you wish to remove $fullName from the " 
			. $list['name'] 
			. " waiting list.  Note that this will result in a loss of any priority for this list and that you cannot be re-added."
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
		global $DB, $session;

		if($session->is_admin()) {
			$user = var_from_getorpost('user');
		} else {
			$user = $session->attr_get('user_id');
		}

		$result = $DB->query(
			"DELETE 
			 FROM waitinglistmembers 
			 WHERE wlist_id = ?
			   AND user_id = ?",
			array( $id, $user));
			
		if($this->is_database_error($result)) {
			return false;
		}
		local_redirect("op=wlist_viewperson&id=$user");
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
		global $DB, $session;


		if($session->is_admin()) {
			$id = var_from_getorpost('id');
			$info = $DB->getRow("SELECT firstname, lastname, gender FROM person WHERE user_id = ?", array($id), DB_FETCHMODE_ASSOC);
			if($this->is_database_error($info)) {
				return false;
			}
			if(is_null($info)) {
				$this->error_exit("That is not a valid user");
			}
			$lcGender = strtolower($info['gender']);
			$fullName = $info['firstname'] . " " . $info['lastname'];
		} else {
			$id = $session->attr_get('user_id');
			$lcGender = strtolower($session->attr_get('gender'));
			$fullName = $session->attr_get('firstname') . " " . $session->attr_get('lastname');
		}
		
		$this->setLocation(array( $fullName => "op=person_view&id=$id", $this->title => 0));


		$dbResult = $DB->query(
			"SELECT w.*, m.preference,m.paired_with
			 FROM waitinglist w, waitinglistmembers m
			 WHERE w.wlist_id = m.wlist_id 
			   AND m.user_id = ?
			 ORDER BY m.preference",array($id));

		if($this->is_database_error($dbResult)) {
			return false;
		}

		if($dbResult->numRows() < 1) {
			$output = blockquote(
			    para("TODO: Text describing registration can go here")
				. para("You are not currently on any waiting lists.  Click " . l('here','op=wlist_join') . " to sign up for one or more days.")
			);
			return $output;
		}

		$output = blockquote(
			para("Your waiting list registration choices are below.  Note that
			waiting list merely guarantees your order of consideration for a
			playing slot and does <b>not</b> guarantee your selection.")
			. para("Selection for each night will be made based on all
			appropriate criteria, (including experience and skill level for
			some nights) however every attempt will be made to ensure that as
			many people as possible get their first choice for at
			least one night of play.")
		);

		$output .= "<div class='waitlist'><table border='0'>";
		while($data = $dbResult->fetchRow(DB_FETCHMODE_ASSOC)) {

			$output .= tr(
				th( $data['name'] )
				. th( l("remove from this waitlist", "op=wlist_quit&step=confirm&id=" . $data['wlist_id'] . "&user=$id"), array('class' => 'thlinks') )
			);

			$output .= simple_row("Registration Preference:", $data['preference']);

			/* The following is a very ugly way to find this person's
			 * position in the waiting list; however there doesn't
			 * appear to be a nicer way to do it
			 */
			$findPosResult = $DB->query(
				"SELECT w.user_id, p.gender
				 FROM waitinglistmembers w, person p
				 WHERE p.user_id = w.user_id
				   AND wlist_id = ? 
				 ORDER BY date_registered", array($data['wlist_id']));
			if($this->is_database_error($findPosResult)) {
				return false;
			}
			$totalRows = $findPosResult->numRows();
			$position = array(
				'male' => 1,
				'female' => 1 
			);
			while($row = $findPosResult->fetchRow(DB_FETCHMODE_ASSOC)) {
				if($row['user_id'] == $id) {
					break;
				}
				$position[ strtolower($row['gender']) ]++;
			}
			$findPosResult->free();

			$waitlistPosition = "Currently ".numberToOrdinal($position[$lcGender]) . " $lcGender of $totalRows total registrants.<br/>";

			if($position[$lcGender] < $data["max_$lcGender"]) {
				$waitlistPosition .= "Under consideration, pending payment and approval";
			} else {
				$waitlistPosition .= "Waiting for an available spot";
			}
			$waitlistPosition .= " (limit is " . $data['max_male'] . " men, ". $data['max_female'] . " women)";
			
			$output .= simple_row("Waitlist Position:", $waitlistPosition);
			if($data['paired_with']) {
				$partnerName = $DB->getOne("SELECT CONCAT(firstname,' ',lastname) FROM person WHERE member_id = ?", array($data['paired_with']));
				if($this->is_database_error($partnerName)) {
					return false;
				}
				
				if(!isset($partnerName)) {
					$this->error_exit("An invalid partner member ID was specified; please go back and try again");		
				}
				
				$output .= simple_row("Partner:", 
					form_hidden("edit[$wlist_id][paired_with]", $data['paired_with']) . "$partnerName (OCUA Member Number " . $data['paired_with'] . ")");
			}
		}
		$dbResult->free();

		$output .= "</table></div>";
		
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
