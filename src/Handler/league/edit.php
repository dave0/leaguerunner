<?php
require_once('Handler/LeagueHandler.php');
class league_edit extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit',$this->league->league_id);
	}

	function process ()
	{

		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->perform($edit);
				local_redirect(url("league/view/" . $this->league->league_id));
				break;
			default:
				$edit = $this->getFormData( $this->league );
				$rc = $this->generateForm( $edit );
		}

		$this->title = "{$edit['name']} &raquo; Edit";

		return $rc;
	}

	function getFormData ( &$league )
	{
		/* Deal with multiple days and start times */
		if(strpos($league->day, ",")) {
			$league->day = explode(',',$league->day);
		}
		return object2array($league);
	}

	function generateForm ( &$formData )
	{
		$output .= form_hidden('edit[step]', 'confirm');

		$rows = array();
		$rows[] = array('League Name:', form_textfield('', 'edit[name]', $formData['name'], 35,200, 'The full name of the league.  Tier numbering will be automatically appended.'));

		$rows[] = array('Status:',
			form_select('', 'edit[status]', $formData['status'], getOptionsFromEnum('league','status'), 'Teams in closed leagues are locked and can be viewed only in historical modes'));

		$rows[] = array('Year:', form_textfield('', 'edit[year]', $formData['year'], 4,4, 'Year of play.'));

		$rows[] = array('Season:',
			form_select('', 'edit[season]', $formData['season'], getOptionsFromEnum('league','season'), "Season of play for this league. Choose 'none' for administrative groupings and comp teams."));

		$rows[] = array('Day(s) of play:',
			form_select('', 'edit[day]', $formData['day'], getOptionsFromEnum('league','day'), 'Day, or days, on which this league will play.', 0, true));

		$thisYear = strftime('%Y', time());
		$rows[] = array('Roster deadline:',
			form_select_date('', 'edit[roster_deadline]', $formData['roster_deadline'], ($thisYear - 1), ($thisYear + 1), 'The date after which teams are no longer allowed to edit their rosters.'));

		/* TODO: 10 is a magic number.  Make it a config variable */
		$rows[] = array('Tier:',
			form_select('', 'edit[tier]', $formData['tier'], getOptionsFromRange(0, 10), 'Tier number.  Choose 0 to not have numbered tiers.'));

		$rows[] = array('Gender Ratio:',
			form_select('', 'edit[ratio]', $formData['ratio'], getOptionsFromEnum('league','ratio'), 'Gender format for the league.'));

		/* TODO: 5 is a magic number.  Make it a config variable */
		$rows[] = array('Current Round:',
			form_select('', 'edit[current_round]', $formData['current_round'], getOptionsFromRange(1, 5), 'New games will be scheduled in this round by default.'));

		$rows[] = array('Scheduling Type:',
			form_select('', 'edit[schedule_type]', $formData['schedule_type'], getOptionsFromEnum('league','schedule_type'), 'What type of scheduling to use.  This affects how games are scheduled and standings displayed.'));

		$rows[] = array('Ratings - Games Before Repeat:',
			form_select('', 'edit[games_before_repeat]', $formData['games_before_repeat'], getOptionsFromRange(0,9), 'The number of games before two teams can be scheduled to play each other again (FOR PYRAMID/RATINGS LADDER SCHEDULING ONLY).'));

		$rows[] = array('How to enter SOTG?',
			form_select('', 'edit[enter_sotg]', $formData['enter_sotg'], getOptionsFromEnum('league','enter_sotg'), 'Control SOTG entry.  "both" uses the survey and allows numeric input.  "numeric_only" turns off the survey for spirit.  "survey_only" uses only the survey questions to gather SOTG info.'));

		$rows[] = array('How to display SOTG?',
			form_select('', 'edit[display_sotg]', $formData['display_sotg'], getOptionsFromEnum('league','display_sotg'), 'Control SOTG display.  "all" shows numeric scores and survey answers to any player.  "symbols_only" shows only star, check, and X, with no numeric values attached.  "coordinator_only" restricts viewing of any per-game information to coordinators only.'));

		$rows[] = array('League Coordinator Email List:', form_textfield('', 'edit[coord_list]', $formData['coord_list'], 35,200, 'An email alias for all coordinators of this league (can be a comma separated list of individual email addresses)'));

		$rows[] = array('League Captain Email List:', form_textfield('', 'edit[capt_list]', $formData['capt_list'], 35,200, 'An email alias for all captains of this league'));

		$rows[] = array('Allow exclusion of teams during scheduling?',
			form_select('', 'edit[excludeTeams]', $formData['excludeTeams'], getOptionsFromEnum('league','excludeTeams'), 'Allows coordinators to exclude teams from schedule generation.'));

		$rows[] = array('Scoring reminder delay:', form_textfield('', 'edit[email_after]', $formData['email_after'], 5, 5, 'Email captains who haven\'t scored games after this many hours, no reminder if 0'));

		$rows[] = array('Game finalization delay:', form_textfield('', 'edit[finalize_after]', $formData['finalize_after'], 5, 5, 'Games which haven\'t been scored will be automatically finalized after this many hours, no finalization if 0'));

		$output .= '<div class="pairtable">' . table(null, $rows) . '</div>';
		$output .= para(form_submit('submit') . form_reset('reset'));

		return form($output);
	}

	function generateConfirm ( $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if(is_array($edit['day'])) {
			$edit['day'] = join(",",$edit['day']);
		}

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");
		$output .= form_hidden("edit[step]", 'perform');

		$rows = array();
		$rows[] = array("League Name:",
			form_hidden('edit[name]', $edit['name']) . $edit['name']);

		$rows[] = array("Status:",
			form_hidden('edit[status]', $edit['status']) . $edit['status']);

		$rows[] = array("Year:",
			form_hidden('edit[year]', $edit['year']) . $edit['year']);

		$rows[] = array("Season:",
			form_hidden('edit[season]', $edit['season']) . $edit['season']);

		$rows[] = array("Day(s) of play:",
			form_hidden('edit[day]',$edit['day']) . $edit['day']);

		$rows[] = array("Roster deadline:",
			form_hidden('edit[roster_deadline][year]',$edit['roster_deadline']['year'])
			. form_hidden('edit[roster_deadline][month]',$edit['roster_deadline']['month'])
			. form_hidden('edit[roster_deadline][day]',$edit['roster_deadline']['day'])
			. $edit['roster_deadline']['year'] . '/' . $edit['roster_deadline']['month'] . '/' . $edit['roster_deadline']['day']);

		$rows[] = array("Tier:",
			form_hidden('edit[tier]', $edit['tier']) . $edit['tier']);

		$rows[] = array("Gender Ratio:",
			form_hidden('edit[ratio]', $edit['ratio']) . $edit['ratio']);

		$rows[] = array("Current Round:",
			form_hidden('edit[current_round]', $edit['current_round']) . $edit['current_round']);

		$rows[] = array("Scheduling Type:",
			form_hidden('edit[schedule_type]', $edit['schedule_type']) . $edit['schedule_type']);

		if (   $edit['schedule_type'] == 'ratings_ladder'
		    || $edit['schedule_type'] == 'ratings_wager_ladder') {
			$rows[] = array("Ratings - Games Before Repeat:",
				form_hidden('edit[games_before_repeat]', $edit['games_before_repeat']) . $edit['games_before_repeat']);
		}
		$rows[] = array("How to enter SOTG?",
			form_hidden('edit[enter_sotg]', $edit['enter_sotg']) . $edit['enter_sotg']);

		$rows[] = array("How to display SOTG?",
			form_hidden('edit[display_sotg]', $edit['display_sotg']) . $edit['display_sotg']);

		$rows[] = array("League Coordinator Email List:",
			form_hidden('edit[coord_list]', $edit['coord_list']) . $edit['coord_list']);

		$rows[] = array("League Captain Email List:",
			form_hidden('edit[capt_list]', $edit['capt_list']) . $edit['capt_list']);

		$rows[] = array("Allow exclusion of teams during scheduling?",
			form_hidden('edit[excludeTeams]', $edit['excludeTeams']) . $edit['excludeTeams']);

		$rows[] = array('Scoring reminder delay:',
			form_hidden('edit[email_after]', $edit['email_after']) . $edit['email_after']);

		$rows[] = array('Game finalization delay:',
			form_hidden('edit[finalize_after]', $edit['finalize_after']) . $edit['finalize_after']);

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));

		return form($output);
	}

	function perform ( $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$this->league->set('name', $edit['name']);
		$this->league->set('status', $edit['status']);
		$this->league->set('day', $edit['day']);
		$this->league->set('year', $edit['year']);
		$this->league->set('season', $edit['season']);
		$this->league->set('roster_deadline', join('-',array(
								$edit['roster_deadline']['year'],
								$edit['roster_deadline']['month'],
								$edit['roster_deadline']['day'])));
		$this->league->set('tier', $edit['tier']);
		$this->league->set('ratio', $edit['ratio']);
		$this->league->set('current_round', $edit['current_round']);
		$this->league->set('schedule_type', $edit['schedule_type']);

		if (   $edit['schedule_type'] == 'ratings_ladder'
		    || $edit['schedule_type'] == 'ratings_wager_ladder') {
			$this->league->set('games_before_repeat', $edit['games_before_repeat']);
		}

		$this->league->set('enter_sotg', $edit['enter_sotg']);
		$this->league->set('display_sotg', $edit['display_sotg']);
		$this->league->set('coord_list', $edit['coord_list']);
		$this->league->set('capt_list', $edit['capt_list']);
		$this->league->set('excludeTeams', $edit['excludeTeams']);

		$this->league->set('email_after', $edit['email_after']);
		$this->league->set('finalize_after', $edit['finalize_after']);

		if( !$this->league->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	/* TODO: Properly validate other data */
	function isDataInvalid ( $edit )
	{
		$errors = "";

		if ( ! validate_nonhtml($edit['name'])) {
			$errors .= "<li>A valid league name must be entered";
		}

		if( !validate_date_input($edit['roster_deadline']['year'], $edit['roster_deadline']['month'], $edit['roster_deadline']['day']) )
		{
			$errors .= '<li>You must provide a valid roster deadline';
		}

		switch($edit['schedule_type']) {
			case 'none':
			case 'roundrobin':
				break;
			case 'ratings_ladder':
			case 'ratings_wager_ladder':
				if ($edit['games_before_repeat'] == null || $edit['games_before_repeat'] == 0) {
					$errors .= "<li>Invalid 'Games Before Repeat' specified!";
				}
				break;
			default:
				$errors .= "<li>Values for allow schedule are none, roundrobin, ratings_ladder, and ratings_wager_ladder";
		}

		if($edit['schedule_type'] != 'none') {
			if( !$edit['day'] ) {
				$errors .= "<li>One or more days of play must be selected";
			}
		}

		if ( ! validate_number($edit['email_after']) || $edit['email_after'] < 0 ) {
			$errors .= "<li>A valid number must be entered for the scoring reminder delay";
		}

		if ( ! validate_number($edit['finalize_after']) || $edit['finalize_after'] < 0 ) {
			$errors .= "<li>A valid number must be entered for the game finalization delay";
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

?>
