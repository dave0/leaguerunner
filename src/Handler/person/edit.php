<?php

require_once('Handler/PersonHandler.php');

class person_edit extends PersonHandler
{
	function __construct ( $id )
	{
		parent::__construct( $id );
		$this->title = "{$this->person->fullname} &raquo; Edit";
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('person','edit', $this->person->user_id);
	}

	function process ()
	{
		$edit = $_POST['edit'];

		$this->smarty->assign('instructions', "Edit any of the following fields and click 'Submit' when done.");
		$this->template_name = 'pages/person/edit.tpl';

		$this->generateForm( $edit );
		$this->smarty->assign('person', $this->person);

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->perform($edit);
			local_redirect('person/view/' . $this->person->user_id);
		} else {
			$this->smarty->assign('edit', (array)$this->person);
		}

		return true;
	}

	function generateForm ()
	{
		global $lr_session, $CONFIG;

		$this->smarty->assign('privacy_url', variable_get('privacy_policy', ''));
		$this->smarty->assign('app_org_short_name', variable_get('app_org_short_name', 'the league'));

		$this->smarty->assign('province_names', getProvinceStateNames());
		$this->smarty->assign('country_names',  getCountryNames());

		$player_classes = array(
			'player' => 'Player',
			'visitor' => 'Non-player account');

		if($lr_session->has_permission('person', 'edit', $this->person->id, 'class') ) {
			$player_classes['administrator'] = 'Leaguerunner administrator';
			$player_classes['volunteer'] = 'Volunteer';
		}

		# Volunteers can unset themselves as volunteer if they wish.
		if( $this->person->class == 'volunteer' ) {
			$player_classes['volunteer'] = 'Volunteer';
		}

		$this->smarty->assign('player_classes',  $player_classes);
		$this->smarty->assign('player_statuses', getOptionsFromEnum('person','status'));


		$this->smarty->assign('skill_levels', getOptionsFromRange(1,10));
		$this->smarty->assign('start_years', getOptionsFromRange(1986, strftime('%Y', time()), 'reverse') );
		$this->smarty->assign('shirt_sizes', getShirtSizes());

		$this->smarty->assign('dog_questions', variable_get('dog_questions', 1));

		return true;
	}

	function perform ( $edit = array() )
	{
		global $lr_session;

		$person = $this->person;

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

		$person->set('birthdate', $edit['birthdate']);

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
		$person->set('show_gravatar', $edit['show_gravatar']);

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
				$this->smarty->assign('title', $this->title);
				$this->smarty->assign('menu', menu_render('_root') );
				$this->smarty->assign('content', $result);
				$this->smarty->display( 'backwards_compatible.tpl' );
				exit;
			}
		}
		return true;
	}

	function check_input_errors ( $edit = array() )
	{
		global $lr_session;

		$errors = array();

		if($lr_session->has_permission('person','edit',$this->person->user_id, 'name')) {
			if( ! validate_name_input($edit['firstname']) || ! validate_name_input($edit['lastname'])) {
				$errors[] = "You can only use letters, numbers, spaces, and the characters - ' and . in first and last names";
			}
		}

		if($lr_session->has_permission('person','edit',$this->person->user_id, 'username')) {
			if( ! validate_name_input($edit['username']) ) {
				$errors[] = "You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
			}
			$user = Person::load( array('username' => $edit['username']) );
			# TODO: BUG: need to check that $user->user_id != current id
			if( $user && !$lr_session->is_admin()) {
				$errors[] = "A user with that username already exists; please choose another";
			}
		}

		if ( ! validate_email_input($edit['email']) ) {
			$errors[] = "You must supply a valid email address";
		}

		if( !validate_nonblank($edit['home_phone']) &&
			!validate_nonblank($edit['work_phone']) &&
			!validate_nonblank($edit['mobile_phone']) ) {
			$errors[] = "You must supply at least one valid telephone number.  Please supply area code, number and (if any) extension.";
		}
		if(validate_nonblank($edit['home_phone']) && !validate_telephone_input($edit['home_phone'])) {
			$errors[] = "Home telephone number is not valid.  Please supply area code, number and (if any) extension.";
		}
		if(validate_nonblank($edit['work_phone']) && !validate_telephone_input($edit['work_phone'])) {
			$errors[] = "Work telephone number is not valid.  Please supply area code, number and (if any) extension.";
		}
		if(validate_nonblank($edit['mobile_phone']) && !validate_telephone_input($edit['mobile_phone'])) {
			$errors[] = "Mobile telephone number is not valid.  Please supply area code, number and (if any) extension.";
		}

		$address_errors = validate_address(
			$edit['addr_street'],
			$edit['addr_city'],
			$edit['addr_prov'],
			$edit['addr_postalcode'],
			$edit['addr_country']);

		if( count($address_errors) > 0) {
			$errors = array_merge( $errors, $address_errors);
		}

		if( !preg_match("/^[mf]/i",$edit['gender'] ) ) {
			$errors[] = "You must select either male or female for gender.";
		}

		if( !validate_yyyymmdd_input( $edit['birthdate'] ) ) {
			$errors[] = "You must provide a valid birthdate";
		}

		if( validate_nonblank($edit['height']) ) {
			if( !$lr_session->is_admin() && ( ($edit['height'] < 36) || ($edit['height'] > 84) )) {
				$errors[] = "Please enter a reasonable and valid value for your height.";
			}
		}

		if( $edit['skill_level'] < 1 || $edit['skill_level'] > 10 ) {
			$errors[] = "You must select a skill level between 1 and 10. You entered " .  $edit['skill_level'];
		}

		$current = localtime(time(),1);
		$this_year = $current['tm_year'] + 1900;
		if( $edit['year_started'] > $this_year ) {
			$errors[] = "Year started must be before current year.";
		}

		if( $edit['year_started'] < 1986 ) {
			$errors[] = "Year started must be after 1986.  For the number of people who started playing before then, I don't think it matters if you're listed as having played 17 years or 20, you're still old. :)";
		}

		$birth_year = substr($edit['birthdate'], 0, 4);

		$yearDiff = $edit['year_started'] - $birth_year;
		if( $yearDiff < 8) {
			$errors[] = "You can't have started playing when you were $yearDiff years old!  Please correct your birthdate, or your starting year";
		}

		return $errors;
	}
}

?>
