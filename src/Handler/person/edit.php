<?php

require_once('Handler/PersonHandler.php');

class person_edit extends PersonHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('person','edit', $this->person->user_id);
	}

	function process ()
	{
		global $lr_session;
		$edit = $_POST['edit'];
		$this->title = 'Edit';

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $this->person->user_id, $edit );
				break;
			case 'perform':
				$this->perform( $this->person, $edit );
				if($this->person->is_active()) {
					local_redirect('person/view/' . $this->person->user_id);
				}
				else {
					local_redirect('');
				}
				break;
			default:
				$edit = object2array($this->person);
				$rc = $this->generateForm($this->person->user_id, $edit, "Edit any of the following fields and click 'Submit' when done.");
		}

		return $rc;
	}

	function generateForm ( $id, &$formData, $instructions = "")
	{
		global $lr_session, $CONFIG;
		$output = <<<END_TEXT
<script language="JavaScript" type="text/javascript">
<!--
function popup(url)
{
	newwindow=window.open(url,'Leaguerunner Skill Rating Form','height=350,width=400,resizable=yes,scrollbars=yes')
	if (window.focus) {newwindow.focus()}
	return false;
}

function doNothing() {}

// -->
// </script>
END_TEXT;
		$output .= form_hidden('edit[step]', 'confirm');

		$output .= para($instructions);
		$output .= para(
			"Note that email and phone publish settings below only apply to regular players.  "
			. "Captains will always have access to view the phone numbers and email addresses of their confirmed players.  "
			. "All Team Captains will also have their email address viewable by other players"
		);
		$privacy = variable_get('privacy_policy', 'http://www.ocua.ca/node/17');
		if($privacy) {
			$output .= para(
					'If you have concerns about the data ' . variable_get('app_org_short_name', 'the league') . ' collects, please see our '
					. "<b><a href=\"$privacy\" target=\"_new\">Privacy Policy</a>.</b>"
			);
		}

		if($lr_session->has_permission('person', 'edit', $id, 'name') ) {
			$group .= form_textfield('First Name', 'edit[firstname]', $formData['firstname'], 25,100, 'First (and, if desired, middle) name.');

			$group .= form_textfield('Last Name', 'edit[lastname]', $formData['lastname'], 25,100);
		} else {
			$group .= form_item('Full Name', $formData['firstname'] . ' ' . $formData['lastname']);
		}

		if($lr_session->has_permission('person', 'edit', $id, 'username') ) {
			$group .= form_textfield('System Username', 'edit[username]', $formData['username'], 25,100, 'Desired login name.');
		} else {
			$group .= form_item('System Username', $formData['username'], 'Desired login name.');
		}
		
		if($lr_session->has_permission('person', 'edit', $id, 'password') ) {
			$group .= form_password('Password', 'edit[password_once]', '', 25,100, 'Enter your desired password.');
			$group .= form_password('Re-enter Password', 'edit[password_twice]', '', 25,100, 'Enter your desired password a second time to confirm it.');
		}

		$group .= form_select('Gender', 'edit[gender]', $formData['gender'], getOptionsFromEnum( 'person', 'gender'), 'Select your gender');

		$output .= form_group('Identity', $group);

		$group = form_textfield('Email Address', 'edit[email]', $formData['email'], 25, 100, 'Enter your preferred email address.  This will be used by ' . variable_get('app_org_short_name', 'the league') . ' to correspond with you on league matters.');
		$group .= form_checkbox('Allow other players to view my email address','edit[allow_publish_email]','Y',($formData['allow_publish_email'] == 'Y'));

		$output .= form_group('Online Contact', $group);

		$group = form_textfield('Street and Number','edit[addr_street]',$formData['addr_street'], 25, 100, 'Number, street name, and apartment number if necessary');
		$group .= form_textfield('City','edit[addr_city]',$formData['addr_city'], 25, 100, 'Name of city');

		/* TODO: evil.  Need to allow Americans to use this at some point in
		 * time... */
		$group .= form_select('Province', 'edit[addr_prov]', $formData['addr_prov'], getProvinceNames(), 'Select a province/state from the list');

		$group .= form_select('Country', 'edit[addr_country]', $formData['addr_country'], getCountryNames(), 'Select a country from the list');

		$group .= form_textfield('Postal Code', 'edit[addr_postalcode]', $formData['addr_postalcode'], 8, 7, 'Please enter a correct postal code matching the address above. ' . variable_get('app_org_short_name', 'the league') . ' uses this information to help locate new fields near its members.');

		$output .= form_group('Street Address', $group);


		$group = form_textfield('Home', 'edit[home_phone]', $formData['home_phone'], 25, 100, 'Enter your home telephone number');
		$group .= form_checkbox('Allow other players to view home number','edit[publish_home_phone]','Y',($formData['publish_home_phone'] == 'Y'));
		$group .= form_textfield('Work', 'edit[work_phone]', $formData['work_phone'], 25, 100, 'Enter your work telephone number (optional)');
		$group .= form_checkbox('Allow other players to view work number','edit[publish_work_phone]','Y',($formData['publish_work_phone'] == 'Y'));
		$group .= form_textfield('Mobile', 'edit[mobile_phone]', $formData['mobile_phone'], 25, 100, 'Enter your cell or pager number (optional)');
		$group .= form_checkbox('Allow other players to view mobile number','edit[publish_mobile_phone]','Y',($formData['publish_mobile_phone'] == 'Y'));
		$output .= form_group('Telephone Numbers', $group);

		$player_classes = array(
			'player' => 'Player',
			'visitor' => 'Non-player account');

		if(! $formData['class'] ) {
			$formData['class'] = 'visitor';
		}

		if($lr_session->has_permission('person', 'edit', $id, 'class') ) {
			$player_classes['administrator'] = 'Leaguerunner administrator';
			$player_classes['volunteer'] = 'Volunteer';
		}

		# Volunteers can unset themselves as volunteer if they wish.
		if( $formData['class'] == 'volunteer' ) {
			$player_classes['volunteer'] = 'Volunteer';
		}

		$group = form_radiogroup('Account Type', 'edit[class]', $formData['class'], $player_classes );
		if($lr_session->has_permission('person', 'edit', $id, 'status') ) {
			$group .= form_select('Account Status','edit[status]', $formData['status'], getOptionsFromEnum('person','status'));
		}

		$output .= form_group('Account Information', $group);

		$group = form_select('Skill Level', 'edit[skill_level]', $formData['skill_level'], 
				getOptionsFromRange(1, 10), 
				"Please use the questionnaire to <a href=\"" . $CONFIG['paths']['base_url'] . "/data/rating.html\" target='_new'>calculate your rating</a>"
		);

		$thisYear = strftime('%Y', time());
		$group .= form_select('Year Started', 'edit[year_started]', $formData['year_started'], 
				getOptionsFromRange(1986, $thisYear, 'reverse'), 'The year you started playing Ultimate in this league.');

		$group .= form_select_date('Birthdate', 'edit[birth]', $formData['birthdate'], ($thisYear - 75), ($thisYear - 5), 'Please enter a correct birthdate; having accurate information is important for insurance purposes');

		$group .= form_textfield('Height','edit[height]',$formData['height'], 4, 4, 'Please enter your height in inches (5 feet is 60 inches; 6 feet is 72 inches).  This is used to help generate even teams for hat leagues.');

		$group .= form_select('Shirt Size','edit[shirtsize]', $formData['shirtsize'], getShirtSizes());

		if (variable_get('dog_questions', 1)) {
			$group .= form_radiogroup('Has Dog', 'edit[has_dog]', $formData['has_dog'], array(
				'Y' => 'Yes, I have a dog I will be bringing to games',
				'N' => 'No, I will not be bringing a dog to games'));
		}

		$group .= form_radiogroup('Can ' . variable_get('app_org_short_name', 'the league') . ' contact you with a survey about volunteering?', 'edit[willing_to_volunteer]', $formData['willing_to_volunteer'], array(
			'Y' => 'Yes',
			'N' => 'No'));

		$group .= form_radiogroup('From time to time, ' . variable_get('app_org_short_name', 'the league') .
					' would like to contact members with information on our programs and to solicit feedback. ' .
					'Can ' . variable_get('app_org_short_name', 'the league') . ' contact you in this regard? ',
					'edit[contact_for_feedback]', $formData['contact_for_feedback'],
					array('Y' => 'Yes',
							'N' => 'No'));

		$output .= form_group('Player and Skill Information', $group);

		$this->setLocation(array(
			$formData['fullname'] => "person/view/$id",
			$this->title => 0));

		$output .= para(form_submit('submit') . form_reset('reset'));

		return form($output, 'post', null, 'id="player_form"');
	}

	function generateConfirm ( $id, $edit = array() )
	{
		global $lr_session;
		$dataInvalid = $this->isDataInvalid( $id, $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");
		$output .= form_hidden('edit[step]', 'perform');

		$group = '';
		if($lr_session->has_permission('person', 'edit', $id, 'name') ) {
			$group .= form_item('First Name',
				form_hidden('edit[firstname]',$edit['firstname']) . $edit['firstname']);
			$group .= form_item('Last Name',
				form_hidden('edit[lastname]',$edit['lastname']) . $edit['lastname']);
		}

		if($lr_session->has_permission('person', 'edit', $id, 'username') ) {
			$group .= form_item('System Username',
				form_hidden('edit[username]',$edit['username']) . $edit['username']);
		}

		if($lr_session->has_permission('person', 'edit', $id, 'password') ) {
			$group .= form_item('Password',
				form_hidden('edit[password_once]', $edit['password_once'])
				. form_hidden('edit[password_twice]', $edit['password_twice'])
				. '<i>(entered)</i>');
		}
		$group .=  form_item('Gender', form_hidden('edit[gender]',$edit['gender']) . $edit['gender']);

		$output .= form_group('Identity', $group);

		$group = form_item('Email Address',
			form_hidden('edit[email]',$edit['email']) . $edit['email']);

		$group .= form_item('Show email to other players',
			form_hidden('edit[allow_publish_email]',$edit['allow_publish_email']) . $edit['allow_publish_email']);

		$output .= form_group('Online Contact', $group);

		$group = form_item('',
			form_hidden('edit[addr_street]',$edit['addr_street'])
			. form_hidden('edit[addr_city]',$edit['addr_city'])
			. form_hidden('edit[addr_prov]',$edit['addr_prov'])
			. form_hidden('edit[addr_country]',$edit['addr_country'])
			. form_hidden('edit[addr_postalcode]',$edit['addr_postalcode'])
			. "{$edit['addr_street']}<br>{$edit['addr_city']}, {$edit['addr_prov']}, {$edit['addr_country']}<br>{$edit['addr_postalcode']}");

		$output .= form_group('Street Address', $group);

		$group = '';
		foreach( array('home','work','mobile') as $location) {
			if($edit["${location}_phone"]) {
				$group .= form_item(ucfirst($location),
					form_hidden("edit[${location}_phone]", $edit["${location}_phone"])
					. $edit["${location}_phone"]);

				if($edit["publish_${location}_phone"] == 'Y') {
					$publish_info = "yes";
					$publish_info .= form_hidden("edit[publish_${location}_phone]", 'Y');
				} else {
					$publish_info = "no";
				}
				$group .= form_item("Allow other players to view $location number", $publish_info);
			}
		}
		$output .= form_group('Telephone Numbers', $group);


		$group = form_item("Account Class", form_hidden('edit[class]',$edit['class']) . $edit['class']);

		if($lr_session->has_permission('person', 'edit', $id, 'status') ) {
			$group .= form_item("Account Status", form_hidden('edit[status]',$edit['status']) . $edit['status']);
		}

		$output .= form_group('Account Information', $group);

		$levels = getOptionsForSkill();
		$group = form_item("Skill Level", form_hidden('edit[skill_level]',$edit['skill_level']) . $levels[$edit['skill_level']]);

		$group .= form_item("Year Started", form_hidden('edit[year_started]',$edit['year_started']) . $edit['year_started']);

		$group .= form_item("Birthdate", 
			form_hidden('edit[birth][year]',$edit['birth']['year']) 
			. form_hidden('edit[birth][month]',$edit['birth']['month']) 
			. form_hidden('edit[birth][day]',$edit['birth']['day']) 
			. $edit['birth']['year'] . " / " . $edit['birth']['month'] . " / " . $edit['birth']['day']);

		if($edit['height']) {
			$group .= form_item("Height", form_hidden('edit[height]',$edit['height']) . $edit['height'] . " inches");
		}
		$group .= form_item("Shirt Size", form_hidden('edit[shirtsize]',$edit['shirtsize']) . $edit['shirtsize']);

		if (variable_get('dog_questions', 1)) {
			$group .= form_item("Has dog", form_hidden('edit[has_dog]',$edit['has_dog']) . $edit['has_dog']);
		}

		$group .= form_item('Can ' . variable_get('app_org_short_name', 'the league') . ' contact you with a survey about volunteering?', form_hidden('edit[willing_to_volunteer]',$edit['willing_to_volunteer']) . $edit['willing_to_volunteer']);

		$group .= form_item('From time to time, ' . variable_get('app_org_short_name', 'the league') .
					' would like to contact members with information on our programs and to solicit feedback. ' .
					'Can ' . variable_get('app_org_short_name', 'the league') . ' contact you in this regard? ',
					form_hidden('edit[contact_for_feedback]',$edit['contact_for_feedback']) . $edit['contact_for_feedback']);

		$output .= form_group('Player and Skill Information', $group);

		$output .= para(form_submit('submit') . form_reset('reset'));

		$this->setLocation(array(
			$edit['firstname'] . " " . $edit['lastname'] => "person/view/$id",
			$this->title => 0));

		return form($output);
	}

	function perform ( $person, $edit = array() )
	{
		global $lr_session;

		$dataInvalid = $this->isDataInvalid( $person->user_id, $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if($edit['username'] && $lr_session->has_permission('person', 'edit', $this->person->user_id, 'username') ) {
			$person->set('username', $edit['username']);
		}

		/* EVIL HACK
		 * If this person is currently a 'visitor', it does not have a
		 * member number, so if we move it to another class, it needs
		 * to be given one.  We do this by forcing its status to 'new' and
		 * requiring it be reapproved.  Ugly hack, but since
		 * we're likely to scrutinize non-player accounts less than player
		 * accounts, it's necessary.
		 */
		if( ($person->class == 'visitor') && ($edit['class'] == 'player') ) {
			$person->set('status','new');
			$person->set('class','player');
			$status_changed = true;
		}

		if($edit['class'] && $lr_session->has_permission('person', 'edit', $this->person->user_id, 'class') ) {
			$person->set('class', $edit['class']);
		}

		if($edit['status'] && $lr_session->has_permission('person', 'edit', $this->person->user_id, 'status') ) {
			$person->set('status',$edit['status']);
		}

		$person->set('email', $edit['email']);
		$person->set('allow_publish_email', $edit['allow_publish_email']);

		foreach(array('home_phone','work_phone','mobile_phone') as $type) {
			$num = $edit[$type];
			if(strlen($num)) {
				$person->set($type, clean_telephone_number($num));
			} else {
				$person->set($type, null);
			}

			$person->set('publish_' . $type, $edit['publish_' . $type] ? 'Y' : 'N');
		}

		if($lr_session->has_permission('person', 'edit', $this->person->user_id, 'name') ) {
			$person->set('firstname', $edit['firstname']);
			$person->set('lastname', $edit['lastname']);
		}

		$person->set('addr_street', $edit['addr_street']);
		$person->set('addr_city', $edit['addr_city']);
		$person->set('addr_prov', $edit['addr_prov']);
		$person->set('addr_country', $edit['addr_country']);

		$postcode = $edit['addr_postalcode'];
		if(strlen($postcode) == 6) {
			$foo = substr($postcode,0,3) . " " . substr($postcode,3);
			$postcode = $foo;
		}
		$person->set('addr_postalcode', $edit['addr_postalcode']);

		$person->set('birthdate', join("-",array(
			$edit['birth']['year'],
			$edit['birth']['month'],
			$edit['birth']['day'])));

		if($edit['height']) {
			$person->set('height', $edit['height']);
		}
		$person->set('shirtsize', $edit['shirtsize']);

		$person->set('gender', $edit['gender']);

		$person->set('skill_level', $edit['skill_level']);
		$person->set('year_started', $edit['year_started']);

		if (variable_get('dog_questions', 1)) {
			$person->set('has_dog', $edit['has_dog']);
		}

		$person->set('willing_to_volunteer', $edit['willing_to_volunteer']);
		$person->set('contact_for_feedback', $edit['contact_for_feedback']);

		if( ! $person->save() ) {
			error_exit("Internal error: couldn't save changes");
		} else {
			/* EVIL HACK
			 * If a user changes their own status from visitor to player, they
			 * will get logged out, so we need to warn them of this fact.
			 */
			if($status_changed) {
				$result = para(
					"You have requested to change your account status to 'Player'.  As such, your account is now being held for one of the administrators to approve.  "
					. 'Once your account is approved, you will receive an email informing you of your new ' . variable_get('app_org_short_name', 'League') . ' member number. '
					. 'You will then be able to log in once again with your username and password.');
				$this->smarty->assign('title', 'Edit Account');
				$this->smarty->assign('menu', menu_render('_root') );
				$this->smarty->assign('content', $result);
				$this->smarty->display( 'backwards_compatible.tpl' );
				exit;
			}
		}
		return true;
	}

	function isDataInvalid ( $id, $edit = array() )
	{
		global $lr_session;
		$errors = "";

		if($lr_session->has_permission('person','edit',$id, 'name')) {
			if( ! validate_name_input($edit['firstname']) || ! validate_name_input($edit['lastname'])) {
				$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in first and last names";
			}
		}

		if($lr_session->has_permission('person','edit',$id, 'username')) {
			if( ! validate_name_input($edit['username']) ) {
				$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
			}
			$user = person_load( array('username' => $edit['username']) );
			# TODO: BUG: need to check that $user->user_id != current id
			if( $user && !$lr_session->is_admin()) {
				error_exit("A user with that username already exists; please go back and try again");
			}
		}

		if ( ! validate_email_input($edit['email']) ) {
			$errors .= "\n<li>You must supply a valid email address";
		}

		if( !validate_nonblank($edit['home_phone']) &&
			!validate_nonblank($edit['work_phone']) &&
			!validate_nonblank($edit['mobile_phone']) ) {
			$errors .= "\n<li>You must supply at least one valid telephone number.  Please supply area code, number and (if any) extension.";
		}
		if(validate_nonblank($edit['home_phone']) && !validate_telephone_input($edit['home_phone'])) {
			$errors .= "\n<li>Home telephone number is not valid.  Please supply area code, number and (if any) extension.";
		}
		if(validate_nonblank($edit['work_phone']) && !validate_telephone_input($edit['work_phone'])) {
			$errors .= "\n<li>Work telephone number is not valid.  Please supply area code, number and (if any) extension.";
		}
		if(validate_nonblank($edit['mobile_phone']) && !validate_telephone_input($edit['mobile_phone'])) {
			$errors .= "\n<li>Mobile telephone number is not valid.  Please supply area code, number and (if any) extension.";
		}

		$address_errors = validate_address( 
			$edit['addr_street'],
			$edit['addr_city'],
			$edit['addr_prov'],
			$edit['addr_postalcode'],
			$edit['addr_country']);

		if( count($address_errors) > 0) {
			$errors .= "\n<li>" . join("\n<li>", $address_errors);
		}

		if( !preg_match("/^[mf]/i",$edit['gender'] ) ) {
			$errors .= "\n<li>You must select either male or female for gender.";
		}

		if( !validate_date_input($edit['birth']['year'], $edit['birth']['month'], $edit['birth']['day']) ) {
			$errors .= "\n<li>You must provide a valid birthdate";
		}

		if( validate_nonblank($edit['height']) ) {
			if( !$lr_session->is_admin() && ( ($edit['height'] < 36) || ($edit['height'] > 84) )) {
				$errors .= "\n<li>Please enter a reasonable and valid value for your height.";
			}
		}

		if( $edit['skill_level'] < 1 || $edit['skill_level'] > 10 ) {
			$errors .= "\n<li>You must select a skill level between 1 and 10. You entered " .  $edit['skill_level'];
		}

		$current = localtime(time(),1);
		$this_year = $current['tm_year'] + 1900;
		if( $edit['year_started'] > $this_year ) {
			$errors .= "\n<li>Year started must be before current year.";
		}

		if( $edit['year_started'] < 1986 ) {
			$errors .= "\n<li>Year started must be after 1986.  For the number of people who started playing before then, I don't think it matters if you're listed as having played 17 years or 20, you're still old. :)";
		}
		$yearDiff = $edit['year_started'] - $edit['birth']['year'];
		if( $yearDiff < 8) {
			$errors .= "\n<li>You can't have started playing when you were $yearDiff years old!  Please correct your birthdate, or your starting year";
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

?>
