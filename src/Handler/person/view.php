<?php

require_once('Handler/PersonHandler.php');

class person_view extends PersonHandler
{
	function has_permission ()
	{
		global $lr_session;

		return $lr_session->has_permission('person','view', $this->person->user_id);
	}

	function process ()
	{
		$this->title = $this->person->fullname;

		return $this->generateView($this->person);
	}

	function generateView (&$person)
	{
		global $lr_session;

		$rows[] = array("Name:", $person->fullname);

		if( ! ($lr_session->is_player() || ($lr_session->attr_get('user_id') == $person->user_id)) ) {
			return "<div class='pairtable'>" . table(null, $rows) . "</div>";
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'username') ) {
			$rows[] = array("System Username:", $person->username);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'member_id') ) {
			if($person->member_id) {
				$rows[] = array(variable_get('app_org_short_name', 'League') . ' Member ID:', $person->member_id);
			} else {
				$rows[] = array(variable_get('app_org_short_name', 'League') . ' Member ID:', 'Not a member of ' . variable_get('app_org_short_name', 'the league'));
			}
		}

		if($person->allow_publish_email == 'Y') {
			$rows[] = array("Email Address:", l($person->email, "mailto:$person->email") . " (published)");
		} else {
			if($lr_session->has_permission('person','view',$person->user_id, 'email') ) {
				$rows[] = array("Email Address:", l($person->email, "mailto:$person->email") . " (private)");
			}
		}

		foreach(array('home','work','mobile') as $type) {
			$item = "${type}_phone";
			$publish = "publish_$item";
			if($person->$publish == 'Y') {
				$rows[] = array("Phone ($type):", $person->$item . " (published)");
			} else {
				if($lr_session->has_permission('person','view',$person->user_id, $item)  && isset($person->$item) ) {
					$rows[] = array("Phone ($type):", $person->$item . " (private)");
				}
			}
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'address')) {
			$rows[] = array("Address:", 
				format_street_address(
					$person->addr_street,
					$person->addr_city,
					$person->addr_prov,
					$person->addr_country,
					$person->addr_postalcode
				)
			);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'birthdate')) {
			$rows[] = array('Birthdate:', $person->birthdate);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'height')) {
			$rows[] = array('Height:', $person->height ? "$person->height inches" : "Please edit your account to enter your height");
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'gender')) {
			$rows[] = array("Gender:", $person->gender);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'shirtsize')) {
			$rows[] = array('Shirt Size:', $person->shirtsize);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'skill')) {
			$skillAry = getOptionsForSkill();
			$rows[] = array("Skill Level:", $skillAry[$person->skill_level]);
			$rows[] = array("Year Started:", $person->year_started);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'class')) {
			$rows[] = array("Account Class:", $person->class);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'status')) {
			$rows[] = array("Account Status:", $person->status);
		}

		if(variable_get('dog_questions', 1)) {
			if($lr_session->has_permission('person','view',$person->user_id, 'dog')) {
				$rows[] = array("Has Dog:",($person->has_dog == 'Y') ? "yes" : "no");

				if($person->has_dog == 'Y') {
					$rows[] = array("Dog Waiver Signed:",($person->dog_waiver_signed) ? $person->dog_waiver_signed : "Not signed");
				}
			}
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'willing_to_volunteer')) {
			$rows[] = array('Can ' . variable_get('app_org_short_name', 'the league') . ' contact you with a survey about volunteering?',($person->willing_to_volunteer == 'Y') ? 'yes' : 'no');
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'contact_for_feedback')) {
			$rows[] = array('From time to time, ' . variable_get('app_org_short_name', 'the league') .
					' would like to contact members with information on our programs and to solicit feedback. ' .
					'Can ' . variable_get('app_org_short_name', 'the league') . ' contact you in this regard? ',
					($person->contact_for_feedback == 'Y') ? 'yes' : 'no');
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'last_login')) {
			if($person->last_login) {
				$rows[] = array('Last Login:', 
					$person->last_login . ' from ' . $person->client_ip);
			} else {
				$rows[] = array('Last Login:', 'Never logged in');
			}
		}

		$rosterPositions = getRosterPositions();
		$teams = array();
		while(list(,$team) = each($person->teams)) {
			$teams[] = array(
				$rosterPositions[$team->position],
				'on',
				l($team->name, "team/view/$team->id"),
				"(" . l($team->league_name, "league/view/$team->league_id") . ")"
			);
		}
		reset($person->teams);
		$rows[] = array("Teams:", table( null, $teams) );

		if( $person->is_a_coordinator ) {
			$leagues = array();
			foreach( $person->leagues as $league ) {
				$leagues[] = array(
					"Coordinator of",
					l($league->fullname, "league/view/$league->league_id")
				);
			}
			reset($person->leagues);

			$rows[] = array("Leagues:", table( null, $leagues) );
		}

		if( variable_get('registration', 0) ) {
			if($lr_session->has_permission('registration','history',$person->user_id)) {
				$rows[] = array("Registration:", l('View registration history', "registration/history/$person->user_id"));
			}
		}

		return "<div class='pairtable'>" . table(null, $rows) . "</div>";
	}
}
?>
