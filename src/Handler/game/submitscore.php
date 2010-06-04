<?php
require_once('Handler/GameHandler.php');

class game_submitscore extends GameHandler
{
	private $team;

	function __construct( $game_id, $team_id )
	{
		parent::__construct( $game_id );

		$this->team = team_load( array('team_id' => $team_id ) );
		team_add_to_menu( $this->team );
	}

	function has_permission ()
	{
		global $lr_session;

		return $lr_session->has_permission('game','submit score', $this->game, $this->team);
	}

	function process ()
	{
		$this->title = "Submit Game Results";

		$this->get_league();

		if( $this->team->team_id != $this->game->home_id && $this->team->team_id != $this->game->away_id ) {
			error_exit("That team did not play in that game!");
		}

		if( $this->game->timestamp > time() ) {
			error_exit("That game has not yet occurred!");
		}

		if( $this->game->is_finalized() ) {
			error_exit("The score for that game has already been submitted.");
		}

		$this->game->load_score_entries();

		if ( $this->game->get_score_entry( $this->team->team_id ) ) {
			error_exit("The score for your team has already been entered.");
		}

		if($this->game->home_id == $this->team->team_id) {
			$opponent->name = $this->game->away_name;
			$opponent->team_id = $this->game->away_id;
		} else {
			$opponent->name = $this->game->home_name;
			$opponent->team_id = $this->game->home_id;
		}

		$edit = $_POST['edit'];
		$spirit = null;

		if (array_key_exists ('spirit', $_POST))
			$spirit = $_POST['spirit'];

		switch($edit['step']) {
			case 'save':
				$rc = $this->perform($edit, $opponent, $spirit);
				break;
			case 'confirm':
				$rc = $this->generateConfirm($edit, $opponent, $spirit);
				break;
			case 'spirit':
				$rc = $this->generateSpiritForm($edit, $opponent);
				break;
			case 'fieldreport';
				$rc = $this->generateFieldReportForm($edit, $opponent);
				break;
			default:
				$rc = $this->generateForm( $opponent );
		}

		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	// We don't know what path we may take through the submission, so we don't
	// know what data may be present at any particular step. This checks for
	// any possible data errors.
	function isDataInvalid ($edit, $questions = null) {
		$errors = '';

		if( $edit['defaulted'] ) {
			switch($defaulted) {
				case 'us':
				case 'them':
				case '':
					return false;  // Ignore other data in cases of default.
				default:
					return 'An invalid value was specified for default.';
			}
		}

		if( !validate_score_value($edit['score_for']) ) {
			$errors .= '<br>You must enter a valid number for your score.';
		}

		if( !validate_score_value($edit['score_against']) ) {
			$errors .= '<br>You must enter a valid number for your opponent\'s score.';
		}

		if ($questions != null) {
			$invalid_answers = $questions->answers_invalid();
			if ($invalid_answers) {
				$errors .= $invalid_answers;
			}
		}

		if( $edit['field_report'] ) {
			if( preg_match("/</", $edit['field_report'] ) ) {
				$errors .= '<br>Please do not use the &gt; or &lt; characters in your comment';
			}
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}

	function perform ($edit, $opponent, $spirit)
	{
		global $lr_session, $dbh;

		$s = new Spirit;
		$s->entry_type = $this->league->enter_sotg;

		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = $s->as_formbuilder();
			$questions->bulk_set_answers( $spirit );
		} else {
			$questions = null;
		}

		$dataInvalid = $this->isDataInvalid( $edit, $questions);
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			// Save the spirit entry if non-default
			if( !$s->store_spirit_entry( $this->game, $opponent->team_id, $lr_session->attr_get('user_id'), $questions->bulk_get_answers()) ) {
				error_exit("Error saving spirit entry for " . $this->team->team_id);
			}
		}

		if( $edit['field_report'] ) {
			$fr = new FieldReport;
			$fr->set('field_id', $this->game->fid);
			$fr->set('game_id', $this->game->game_id);
			$fr->set('reporting_user_id', $lr_session->attr_get('user_id'));
			$fr->set('report_text', $edit['field_report']);

			if( ! $fr->save() ) {
				error_exit('Error saving field report for game ' . $this->game->game_id);
			}
		}

		// Now, we know we haven't finalized the game, so we first
		// save this team's score entry, as there isn't one already.
		if( !$this->game->save_score_entry( $this->team->team_id, $lr_session->attr_get('user_id'), $edit['score_for'],$edit['score_against'],$edit['defaulted'], $edit['sotg'] ) ) {
			error_exit("Error saving score entry for " . $this->team->team_id);
		}

		// now, check if the opponent has an entry
		if( ! $this->game->get_score_entry( $opponent->team_id ) ) {
			// No, so we just mention that it's been saved and move on
			$resultMessage = para('This score has been saved.  Once your opponent has entered their score, it will be officially posted.');
		} else {
			// Otherwise, both teams have an entry.  So, attempt to finalize using
			// this information.
			if( $this->game->finalize() ) {
				$resultMessage = para('This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.');
			} else {
				// Or, we have a disagreement.  Since we've already saved the
				// score, just say so, and continue.
				$resultMessage = para('This score doesn\'t agree with the one your opponent submitted.  Because of this, the score will not be posted until your coordinator approves it.');
			}
		}

		return $resultMessage;
	}

	function generateFieldReportForm ($edit, $opponent )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		if( $edit['defaulted'] == 'us' || $edit['defaulted'] == 'them' ) {
			// If it's a default, short-circuit the spirit-entry form and skip
			// straight to the confirmation.
			return $this->generateConfirm($edit, $opponent);
		} else {
			// Force a non-default to display correctly
			$edit['defaulted'] = 'no';
		}

		$output = $this->interim_game_result($edit, $opponent);
		$edit['step'] = 'spirit';
		$output .= $this->hidden_fields ('edit', $edit);

		$short_name = variable_get('app_org_short_name', 'the league');
		$output .= para("Since $short_name is unable to do daily inspections of all of the fields it uses, we need your feedback.  Do there appear to be any changes to the field (damage, water etc) that $short_name should be aware of?");

		$output .= form_textarea('','edit[field_report]', '', 70, 5, 'Please enter a description of any issues, or leave blank if there is nothing to report');

		$output .= para(form_submit("Next Step", "submit") . form_reset("reset"));

		return form($output, 'post');
	}

	function generateSpiritForm ($edit, $opponent )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		if( $edit['defaulted'] == 'us' || $edit['defaulted'] == 'them' ) {
			// If it's a default, short-circuit the spirit-entry form and skip
			// straight to the confirmation.
			return $this->generateConfirm($edit, $opponent);
		} else {
			// Force a non-default to display correctly
			$edit['defaulted'] = 'no';
		}

		$output = $this->interim_game_result($edit, $opponent);
		$edit['step'] = 'confirm';
		$output .= $this->hidden_fields ('edit', $edit);

		$output .= para("Now you must rate your opponent's Spirit of the Game.");
		$s = new Spirit;
		$s->entry_type = $this->league->enter_sotg;
		$output .= para("Please fill out the questions below.");
		$questions = $s->as_formbuilder();
		$questions->bulk_set_answers( $s->default_spirit_answers() );
		$output .= $questions->render_editable( true );
		$output .= para(form_submit("Next Step", "submit") . form_reset("reset"));

		return form($output, 'post', null, 'id="score_form"');
	}

	function generateConfirm ($edit, $opponent, $spirit = null )
	{
		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$s = new Spirit;
			$s->entry_type = $this->league->enter_sotg;
			$questions = $s->as_formbuilder();
			$questions->bulk_set_answers( $spirit );
		} else {
			$questions = null;
		}

		$dataInvalid = $this->isDataInvalid( $edit, $questions);
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$output = $this->interim_game_result($edit, $opponent);

		$edit['step'] = 'save';
		$output .= $this->hidden_fields ('edit', $edit);
		$output .= $this->hidden_fields ('spirit', $spirit);

		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$output .= para('The following answers will be shown to your coordinator:');
			$output .= $questions->render_viewable();
		}

		$output .= para("If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the problems.");

		$output .= para(form_submit('Submit'));

		return form($output);
	}

	function generateForm ( $opponent )
	{
		$output = para( "Submit the score for the "
			. $this->game->sprintf('short')
			. " between " . $this->team->name . " and $opponent->name.");
		$output .= para("If your opponent has already entered a score, it will be displayed below.  If the score you enter does not agree with this score, posting of the score will be delayed until your coordinator can confirm the correct score.");

		$output .= form_hidden('edit[step]', 'fieldreport');

		$opponent_entry = $this->game->get_score_entry( $opponent->team_id );

		if($opponent_entry) {
			if($opponent_entry->defaulted == 'us') {
				$opponent_entry->score_for .= " (defaulted)";
			} else if ($opponent_entry->defaulted == 'them') {
				$opponent_entry->score_against .= " (defaulted)";
			}

		} else {
			$opponent_entry->score_for = "not yet entered";
			$opponent_entry->score_against = "not yet entered";
		}

		$rows = array();
		$header = array( "Team Name", "Defaulted?", "Your Score Entry", "Opponent's Score Entry");

		$rows[] = array(
			$this->team->name,
			"<input type='checkbox' name='edit[defaulted]' value='us' onclick='defaultCheckboxChanged()'>",
			form_textfield("","edit[score_for]","",2,2),
			$opponent_entry->score_against
		);

		$rows[] = array(
			$opponent->name,
			"<input type='checkbox' name='edit[defaulted]' value='them' onclick='defaultCheckboxChanged()'>",
			form_textfield("","edit[score_against]","",2,2),
			$opponent_entry->score_for
		);

		$output .= '<div class="listtable">' . table($header, $rows) . "</div>";
		$output .= para(form_submit("Next Step") . form_reset("reset"));

		$win = variable_get('default_winning_score', 6);
		$lose = variable_get('default_losing_score', 0);

		$script = <<<ENDSCRIPT
<script type="text/javascript"> <!--
  function defaultCheckboxChanged() {
	form = document.getElementById('score_form');
    if (form.elements['edit[defaulted]'][0].checked == true) {
        form.elements['edit[score_for]'].value = "$lose";
        form.elements['edit[score_for]'].disabled = true;
        form.elements['edit[score_against]'].value = "$win";
        form.elements['edit[score_against]'].disabled = true;
        form.elements['edit[defaulted]'][1].disabled = true;
    } else if (form.elements['edit[defaulted]'][1].checked == true) {
        form.elements['edit[score_for]'].value = "$win";
        form.elements['edit[score_for]'].disabled = true;
        form.elements['edit[score_against]'].value = "$lose";
        form.elements['edit[score_against]'].disabled = true;
        form.elements['edit[defaulted]'][0].disabled = true;
    } else {
        form.elements['edit[score_for]'].disabled = false;
        form.elements['edit[score_against]'].disabled = false;
        form.elements['edit[defaulted]'][0].disabled = false;
        form.elements['edit[defaulted]'][1].disabled = false;
    }
  }
// -->
</script>
ENDSCRIPT;

		return $script . form($output, 'post', null, 'id="score_form"');
	}

	function interim_game_result( $edit, $opponent )
	{
		$win = variable_get('default_winning_score', 6);
		$lose = variable_get('default_losing_score', 0);

		$output = para( "For the game of " . $this->game->sprintf('short') . " you have entered:");
		$rows = array();
		switch($edit['defaulted']) {
		case 'us':
			$rows[] = array($this->team->name, "$lose (defaulted)");
			$rows[] = array($opponent->name, $win);
			break;
		case 'them':
			$rows[] = array($this->team->name, $win);
			$rows[] = array($opponent->name, "$lose (defaulted)");
			break;
		default:
			$rows[] = array($this->team->name, $edit['score_for']);
			$rows[] = array($opponent->name, $edit['score_against']);
			break;
		}

		$output .= '<div class="pairtable">'
			. table(null, $rows)
			. "</div>";

		// now, check if the opponent has an entry
		$opponent_entry = $this->game->get_score_entry( $opponent->team_id );

		if( $opponent_entry ) {
			if( ! $this->game->score_entries_agree( $edit, object2array($opponent_entry) ) ) {
				$output .= para("<b>Note:</b> this score does NOT agree with the one provided by your opponent, so coordinator approval will be required if you submit it");
			}
		}


		if( $edit['defaulted']== 'them' || ($edit['score_for'] > $edit['score_against']) ) {
			$what = 'win for your team';
		} else if( $edit['defaulted']=='us' || ($edit['score_for'] < $edit['score_against']) ) {
			$what = 'loss for your team';
		} else {
			$what = 'tie game';
		}
		$output .= para("If confirmed, this would be recorded as a <b>$what</b>.");

		if( $edit['field_report'] ) {
			$output .= para("You have also submitted the following field report:<blockquote>" . $edit['field_report'] . "</blockquote>");
		}

		return $output;
	}

	function hidden_fields ($group, $fields)
	{
		$output = '';
		if (is_array($fields) && !empty ($fields)) {
			foreach ($fields as $name => $value)
				$output .= form_hidden("{$group}[$name]", $value);
		}
		return $output;
	}
}

?>
