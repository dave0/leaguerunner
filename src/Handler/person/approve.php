<?php

require_once('Handler/person/view.php');

class person_approve extends person_view
{
	function has_permission()
	{
		global $lr_session;

		return $lr_session->has_permission('person','approve', $this->person->user_id);
	}

	function process ()
	{
		$edit = $_POST['edit'];
		$this->title = "{$this->person->fullname} &raquo; Approve";

		if($edit['step'] == 'perform') {
			/* Actually do the approval on the 'perform' step */
			$this->perform( $edit );
			local_redirect("person/listnew");
		} 

		if($this->person->status != 'new') {
			error_exit("That account has already been approved");
		}

		$dispositions = array(
			'---'	          => '- Select One -',
			'approve_player'  => 'Approved as Player',
			'approve_visitor' => 'Approved as visitor account',
			'delete' 		  => 'Deleted silently',
		);

		$sth = $this->person->find_duplicates();

		$duplicates = '';
		while( $user = $sth->fetchObject('Person', array(LOAD_OBJECT_ONLY)) ) {
			$duplicates .= "<li>$user->firstname $user->lastname";
			$duplicates .= "[&nbsp;" . l("view", "person/view/$user->user_id") . "&nbsp;]";

			$dispositions["delete_duplicate:$user->user_id"] = "Deleted as duplicate of $user->firstname $user->lastname ($user->user_id)";
			$dispositions["merge_duplicate:$user->user_id"] = "Merged backwards into $user->firstname $user->lastname ($user->user_id)";
		}

		$approval_form = 
			form_hidden('edit[step]', 'perform')
			. form_select('This user should be', 'edit[disposition]', '---', $dispositions)
			. form_submit("Submit");


		if( strlen($duplicates) > 0 ) {
			$duplicates = para("<div class='warning'><br>The following users may be duplicates of this account:<ul>\n"
				. $duplicates
				. "</ul></div>");
		}

		return 
			$duplicates
			. form( para($approval_form) )
			. $this->generateView($this->person);
	}

	function perform ( $edit )
	{
		global $lr_session; 

		$disposition = $edit['disposition'];

		if($disposition == '---') {
			error_exit("You must select a disposition for this account");
		}

		list($disposition,$dup_id) = explode(':',$disposition);

		switch($disposition) {
			case 'approve_player':
				$this->person->set('class','player');
				$this->person->set('status','inactive');
				if(! $this->person->generate_member_id() ) {
					error_exit("Couldn't get member ID allocation");
				}

				if( ! $this->person->save() ) {
					error_exit("Couldn't save new member activation");
				}

				$message = _person_mail_text('approved_body_player', array( 
					'%fullname' => $this->person->fullname,
					'%username' => $this->person->username,
					'%memberid' => $this->person->member_id,
					'%url' => url(""),
					'%adminname' => variable_get('app_admin_name', 'Leaguerunner Admin'),
					'%site' => variable_get('app_name','Leaguerunner')));

				$rc = send_mail($this->person,
					false, // from the administrator
					false, // no Cc
					_person_mail_text('approved_subject', array( '%username' => $this->person->username, '%site' => variable_get('app_name','Leaguerunner') )), 
					$message);
				if($rc == false) {
					error_exit("Error sending email to " . $this->person->email);
				}
				return true;

			case 'approve_visitor':
				$this->person->set('class','visitor');
				$this->person->set('status','inactive');
				if( ! $this->person->save() ) {
					error_exit("Couldn't save new member activation");
				}

				$message = _person_mail_text('approved_body_visitor', array( 
					'%fullname' => $this->person->fullname,
					'%username' => $this->person->username,
					'%url' => url(""),
					'%adminname' => variable_get('app_admin_name','Leaguerunner Admin'),
					'%site' => variable_get('app_name','Leaguerunner')));
				$rc = send_mail($this->person,
					false, // from the administrator
					false, // no Cc
					_person_mail_text('approved_subject', array( '%username' => $this->person->username, '%site' => variable_get('app_name','Leaguerunner' ))), 
					$message);
				if($rc == false) {
					error_exit("Error sending email to " . $this->person->email);
				}
				return true;

			case 'delete':
				if( ! $this->person->delete() ) {
					error_exit("Delete of user " . $this->person->fullname . " failed.");
				}
				return true;

			case 'delete_duplicate':
				$existing = Person::load( array('user_id' => $dup_id) );
				$message = _person_mail_text('dup_delete_body', array( 
					'%fullname' => $this->person->fullname,
					'%username' => $this->person->username,
					'%existingusername' => $existing->username,
					'%existingemail' => $existing->email,
					'%passwordurl' => variable_get('password_reset', url('person/forgotpassword')),
					'%adminname' => $lr_session->user->fullname,
					'%site' => variable_get('app_name','Leaguerunner')));

				$to_list = array(
					$this->person,
				);

				if($this->person->email != $existing->email) {
					$to_list[] = $existing;
				}

				if( ! $this->person->delete() ) {
					error_exit("Delete of user " . $this->person->fullname . " failed.");
				}

				$rc = send_mail($to_list,
					false, // from the administrator
					false, // no Cc
					_person_mail_text('dup_delete_subject', array( '%site' => variable_get('app_name', 'Leaguerunner') )),
					$message);

				if($rc == false) {
					error_exit("Error sending email to " . $this->person->email);
				}
				return true;

			// This is basically the same as the delete duplicate, except
			// that some old information (e.g. user ID) is preserved
			case 'merge_duplicate':
				$existing = Person::load( array('user_id' => $dup_id) );
				$message = _person_mail_text('dup_merge_body', array( 
					'%fullname' => $this->person->fullname,
					'%username' => $this->person->username,
					'%existingusername' => $existing->username,
					'%existingemail' => $existing->email,
					'%passwordurl' => variable_get('password_reset', url('person/forgotpassword')),
					'%adminname' => variable_get('app_admin_name','Leaguerunner Admin'),
					'%site' => variable_get('app_name','Leaguerunner')));

				$to_list = array(
					$this->person,
				);

				if($this->person->email != $existing->email) {
					$to_list[] = $existing;
				}

				// Copy over almost all of the new data; there must be a better way
				$existing->set('status', 'active');
				$existing->set('username', $this->person->username);
				$existing->set('password', $this->person->password);
				$existing->set('firstname', $this->person->firstname);
				$existing->set('lastname', $this->person->lastname);
				$existing->set('email', $this->person->email);
				$existing->set('allow_publish_email', $this->person->allow_publish_email);
				$existing->set('home_phone', $this->person->home_phone);
				$existing->set('publish_home_phone', $this->person->publish_home_phone);
				$existing->set('work_phone', $this->person->work_phone);
				$existing->set('publish_work_phone', $this->person->publish_work_phone);
				$existing->set('mobile_phone', $this->person->mobile_phone);
				$existing->set('publish_mobile_phone', $this->person->publish_mobile_phone);
				$existing->set('addr_street', $this->person->addr_street);
				$existing->set('addr_city', $this->person->addr_city);
				$existing->set('addr_prov', $this->person->addr_prov);
				$existing->set('addr_country', $this->person->addr_country);
				$existing->set('addr_postalcode', $this->person->addr_postalcode);
				$existing->set('gender', $this->person->gender);
				$existing->set('birthdate', $this->person->birthdate);
				$existing->set('height', $this->person->height);
				$existing->set('skill_level', $this->person->skill_level);
				$existing->set('year_started', $this->person->year_started);
				$existing->set('shirtsize', $this->person->shirtsize);
				$existing->set('session_cookie', $this->person->session_cookie);
				$existing->set('has_dog', $this->person->has_dog);
				$existing->set('survey_completed', $this->person->survey_completed);
				$existing->set('willing_to_volunteer', $this->person->willing_to_volunteer);
				$existing->set('contact_for_feedback', $this->person->contact_for_feedback);
				$existing->set('last_login', $this->person->last_login);
				$existing->set('client_ip', $this->person->client_ip);

				if( !$existing->member_id) {
					$existing->generate_member_id();
				}

				if( ! $this->person->delete() ) {
					error_exit("Delete of user " . $this->person->fullname . " failed.");
				}

				if( ! $existing->save() ) {
					error_exit("Couldn't save new member information");
				}

				$rc = send_mail($to_list,
					false, // from the administrator
					false, // no Cc
					_person_mail_text('dup_merge_subject', array( '%site' => variable_get('app_name', 'Leaguerunner') )),
					$message);

				if($rc == false) {
					error_exit("Error sending email to " . $this->person->email);
				}
				return true;

			default:
				error_exit("You must select a disposition for this account");

		}
	}
}


?>
