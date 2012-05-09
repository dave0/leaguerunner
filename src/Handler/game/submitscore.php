<?php
require_once('Handler/GameHandler.php');

class game_submitscore extends GameHandler
{
	private $team;

	function __construct( $game_id, $team_id )
	{
		parent::__construct( $game_id );

		$this->team = Team::load( array('team_id' => $team_id ) );
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

		$this->spirit = new Spirit;

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

		$step = $edit['step'];
		unset($edit['step']);

		switch($step) {
			case 'save':
				$this->template_name = 'pages/game/submitscore/result.tpl';
				$rc = $this->perform($edit, $opponent, $spirit);
				break;
			case 'confirm':
				$this->template_name = 'pages/game/submitscore/step4.tpl';
				$rc = $this->generateConfirm($edit, $opponent, $spirit);
				break;
			case 'spirit':
				$this->template_name = 'pages/game/submitscore/step3.tpl';
				$rc = $this->generateSpiritForm($edit, $opponent);
				break;
			case 'fieldreport';
				$this->template_name = 'pages/game/submitscore/step2.tpl';
				$rc = $this->generateFieldReportForm($edit, $opponent);
				break;
			default:
				$this->template_name = 'pages/game/submitscore/step1.tpl';
				$this->smarty->assign('game', $this->game);
				$this->smarty->assign('default_winning_score', variable_get('default_winning_score', 6));
				$this->smarty->assign('default_losing_score', variable_get('default_losing_score', 6));
				$this->smarty->assign('team', $this->team);
				$this->smarty->assign('opponent', $opponent);
				$this->smarty->assign('opponent_entry', $this->game->get_score_entry( $opponent->team_id ) );
				$rc = true;
		}

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
		global $lr_session;

		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = $this->spirit->as_formbuilder();
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
			if( !$this->spirit->store_spirit_entry( $this->game, $opponent->team_id, $lr_session->attr_get('user_id'), $questions->bulk_get_answers()) ) {
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
			$this->smarty->assign('have_opponent_entry', false);
		} else {
			$this->smarty->assign('have_opponent_entry', true);
			$this->smarty->assign('finalized', $this->game->finalize());
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
			$this->smarty->assign('next_step', 'confirm');
		} else {
			// Force a non-default to display correctly
			$edit['defaulted'] = 'no';
			$this->smarty->assign('next_step', 'spirit');
		}

		$this->smarty->assign('interim_game_result', $this->interim_game_result($edit, $opponent));
		$this->smarty->assign('league_name', variable_get('app_org_short_name', 'the league'));
		$this->smarty->assign('hidden_fields', $edit);

		return true;

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

		$this->smarty->assign('interim_game_result', $this->interim_game_result($edit, $opponent));
		$this->smarty->assign('hidden_fields', $edit);

		# TODO smartyification?
		$questions = $this->spirit->as_formbuilder();
		$questions->bulk_set_answers( $this->spirit->default_spirit_answers() );
		$this->smarty->assign('spirit_form_questions', $questions->render_editable( true ));

		return true;
	}

	function generateConfirm ($edit, $opponent, $spirit = null )
	{
		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = $this->spirit->as_formbuilder();
			$questions->bulk_set_answers( $spirit );
		} else {
			$questions = null;
		}

		$dataInvalid = $this->isDataInvalid( $edit, $questions);
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$this->smarty->assign('interim_game_result', $this->interim_game_result($edit, $opponent));
		$this->smarty->assign('edit_hidden_fields', $edit);
		$this->smarty->assign('spirit_hidden_fields', $spirit);

		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$this->smarty->assign('spirit_answers', $questions->render_viewable());
		}

		return true;
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
			if( ! $this->game->score_entries_agree( $edit, (array)$opponent_entry ) ) {
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
}

?>
