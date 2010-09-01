<?php
require_once('Handler/GameHandler.php');
class game_edit extends GameHandler
{
	protected $can_edit;

	function __construct ( $id )
	{
		global $lr_session;
		parent::__construct($id);
		if( $lr_session->is_admin() ) {
			$this->can_edit = true;
		}
	}

	function has_permission ()
	{
		global $lr_session;

		if( $lr_session->is_admin() ) {
			$this->can_edit = true;
		}

		if( $lr_session->is_coordinator_of($this->game->league_id)) {
			$this->can_edit = true;
		}

		return $lr_session->has_permission('game','view', $this->game);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "Game {$this->game->game_id}";

		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $this->game, $edit );
				break;
			case 'perform':
				$this->perform( $edit );
				local_redirect(url("game/view/" . $this->game->game_id));
				break;
			default:
				$rc = $this->generateForm( );
		}

		return $rc;
	}

	function generateForm ( )
	{
		global $lr_session, $dbh;
		# Alias, to avoid typing.  Bleh.
		$game = &$this->game;
		$league = &$this->league;

		$game->load_score_entries();

		$output = form_hidden('edit[step]', 'confirm');

		$teams = $league->teams_as_array();
		/* Now, since teams may not be in league any longer, we need to force
		 * them to appear in the pulldown
		 */
		$teams[$game->home_id] = $game->home_name;
		$teams[$game->away_id] = $game->away_name;

		$output .= form_item("League/Division", l($league->fullname, "league/view/$league->league_id"));

		$output .= form_item( "Home Team", l($game->home_name,"team/view/$game->home_id"));
		$output .= form_item( "Away Team", l($game->away_name,"team/view/$game->away_id"));

		$output .= form_item("Date and Time", "$game->game_date, $game->game_start until " . $game->display_game_end() . $note);

		$field = Field::load( array('fid' => $game->fid) );
		$output .= form_item("Location",
			l("$field->fullname ($game->field_code)", "field/view/$game->fid"), $note);

		$output .= form_item("Game Status", $game->status);

		if( isset( $game->round ) ) {
			$output .= form_item("Round", $game->round);
		}

		$spirit_group = '';
		$score_group = '';
		/*
		 * Now, for scores and spirit info.  Possibilities:
		 *  - game has been finalized:
		 *  	- everyone can see scores
		 *  	- coordinator can edit scores/spirit
		 *  - game has not been finalized
		 *  	- players only see "not yet submitted"
		 *  	- captains can see submitted scores
		 *  	- coordinator can see everything, edit final scores/spirit
		 */

		if($game->approved_by) {
			// Game has been finalized

			if( ! $this->can_edit ) {
				// If we're not editing, display score.  If we are,
				// it will show up below.
				switch($game->status) {
					case 'home_default':
						$home_status = " (defaulted)";
						break;
					case 'away_default':
						$away_status = " (defaulted)";
						break;
					case 'forfeit':
						$home_status = " (forfeit)";
						$away_status = " (forfeit)";
						break;
				}
				$score_group .= form_item("Home ($game->home_name [rated: $game->rating_home]) Score", "$game->home_score $home_status");
				$score_group .= form_item("Away ($game->away_name [rated: $game->rating_away]) Score", "$game->away_score $away_status");
			}

			if ($game->home_score == $game->away_score && $game->rating_points == 0){
				$score_group .= form_item("Rating Points", "No points were transferred between teams");
			}
			else {
				if ($game->home_score >= $game->away_score) {
					$winner = l($game->home_name,"team/view/$game->home_id");
					$loser = l($game->away_name,"team/view/$game->away_id");
				}
				elseif ($game->home_score < $game->away_score) {
					$winner = l($game->away_name,"team/view/$game->away_id");
					$loser = l($game->home_name,"team/view/$game->home_id");

				}
				$score_group .= form_item("Rating Points", $game->rating_points , $winner." gain " .$game->rating_points. " points and " .$loser. " lose " .$game->rating_points. " points");
			}

			switch($game->approved_by) {
				case APPROVAL_AUTOMATIC:
					$approver = 'automatic approval';
					break;
				case APPROVAL_AUTOMATIC_HOME:
					$approver = 'automatic approval using home submission';
					break;
				case APPROVAL_AUTOMATIC_AWAY:
					$approver = 'automatic approval using away submission';
					break;
				case APPROVAL_AUTOMATIC_FORFEIT:
					$approver = 'game automatically forfeited due to lack of score submission';
					break;
				default:
					$approver = Person::load( array('user_id' => $game->approved_by));
					$approver = l($approver->fullname, "person/view/$approver->user_id");
			}
			$score_group .= form_item("Score Approved By", $approver);

		} else {
			/*
			 * Otherwise, scores are still pending.
			 */
			if( $lr_session->is_coordinator_of($game->league_id)) {
				$list = player_rfc2822_address_list($game->get_captains(), true);
				$output .= para( l('Click here to send an email', "mailto:$list") . ' to all captains.' );
			}

			$stats_group = '';
			/* Use our ratings to try and predict the game outcome */
			$homePct = $game->home_expected_win();
			$awayPct = $game->away_expected_win();

			$stats_group .= form_item("Chance to win", table(null, array(
				array($game->home_name, sprintf("%0.1f%%", (100 * $homePct))),
				array($game->away_name, sprintf("%0.1f%%", (100 * $awayPct))),
				array("View the " . l('Ratings Table', "game/ratings/$game->game_id") . " for this game." ))
				));
			$output .= form_group("Statistics", $stats_group);


			$score_group .= form_item('',"Score not yet finalized");
			if( $lr_session->has_permission('game','view', $game, 'submission') ) {
				$score_group .= form_item("Score as entered", $this->score_entry_display());

			}
		}

		// Now, we always want to display this edit code if we have
		// permission to edit.
		if( $this->can_edit ) {
			$score_group .= form_select('Game Status','edit[status]', $game->status, getOptionsFromEnum('schedule','status'), "To mark a game as defaulted, select the appropriate option here.  Appropriate scores will automatically be entered.");
			$score_group .= form_textfield( "Home ($game->home_name [rated: $game->rating_home]) score", 'edit[home_score]',$game->home_score,2,2);
			$score_group .= form_textfield( "Away ($game->away_name [rated: $game->rating_away]) score",'edit[away_score]',$game->away_score,2,2);
		}

		$output .= form_group("Scoring", $score_group);

		if( $lr_session->has_permission('game','view',$game,'spirit') ) {
			$ary = $game->get_spirit_entry( $game->home_id );

			$s = new Spirit;
			$s->entry_type = $this->league->enter_sotg;
			$formbuilder = $s->as_formbuilder();
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );

				// TODO: when not editable, display viewable tabular format with symbols
				$home_spirit_group = $this->can_edit
					?  $formbuilder->render_editable( true, 'home' )
					:  $formbuilder->render_viewable( true, 'home' );
			} else {
				$formbuilder->bulk_set_answers( $s->default_spirit_answers() );
				$home_spirit_group = $this->can_edit
					?  $formbuilder->render_editable( true, 'home' )
					:  'Not entered';
			}

			$formbuilder->clear_answers();
			$ary = $game->get_spirit_entry( $game->away_id );
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );
				$away_spirit_group = $this->can_edit
					?  $formbuilder->render_editable( true, 'away' )
					:  $formbuilder->render_viewable( true, 'away' );
			} else {
				$formbuilder->bulk_set_answers( $s->default_spirit_answers() );
				$away_spirit_group = $this->can_edit
					?  $formbuilder->render_editable( true, 'away' )
					:  'Not entered';
			}

			$output .= form_group("Spirit assigned TO home ($game->home_name)", $home_spirit_group);
			$output .= form_group("Spirit assigned TO away ($game->away_name)", $away_spirit_group);
		}

		if( $lr_session->has_permission('field', 'view reports')) {

			$sth = FieldReport::query(array('game_id' => $this->game->game_id ));
			$header = array("Date Reported","Reported By","Report");
			while( $r = $sth->fetchObject('FieldReport') ) {
				$rows[] = array(
					$r->created,
					l( $r->reporting_user_fullname,  url("person/view/" . $r->reporting_user_id)),
					$r->report_text,
				);
			}
			$output .= form_group("This game's field reports for ". $field->fullname,
				"<div class='listtable'>" . table($header, $rows ) . "</div>\n"
			);
		}

		if( $this->can_edit ) {
			$output .= para(form_submit("submit") . form_reset("reset"));
		}
		return $script . form($output, 'post', null, 'id="score_form"');
	}

	function generateConfirm ( $game, $edit )
	{
		if( ! $this->can_edit ) {
			error_exit("You do not have permission to edit this game");
		}

		$dataInvalid = $this->isDataInvalid( $edit );

		$s = new Spirit;
		$s->entry_type = $this->league->enter_sotg;
		$home_spirit = $s->as_formbuilder();
		$away_spirit = $s->as_formbuilder();

		$win = variable_get('default_winning_score', 6);
		$lose = variable_get('default_losing_score', 0);

		switch($edit['status']) {
			case 'home_default':
				$edit['home_score'] = "$lose (defaulted)";
				$edit['away_score'] = $win;
				break;
			case 'away_default':
				$edit['home_score'] = $win;
				$edit['away_score'] = "$lose (defaulted)";
				break;
			case 'forfeit':
				$edit['home_score'] = '0 (forfeit)';
				$edit['away_score'] = '0 (forfeit)';
				break;
			case 'normal':
			default:
				$home_spirit->bulk_set_answers( $_POST['spirit_home'] );
				$away_spirit->bulk_set_answers( $_POST['spirit_away'] );
				$dataInvalid .= $home_spirit->answers_invalid();
				$dataInvalid .= $away_spirit->answers_invalid();
				break;
		}

		if( $dataInvalid ) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para( "You have made the changes below for the $game->game_date $game->game_start game between $game->home_name and $game->away_name.  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the score.");

		$output .= form_hidden('edit[step]', 'perform');
		$output .= form_hidden('edit[status]', $edit['status']);
		$output .= form_hidden('edit[home_score]', $edit['home_score']);
		$output .= form_hidden('edit[away_score]', $edit['away_score']);
		$score_group .= form_item("Home ($game->home_name [rated: $game->rating_home]) Score",$edit['home_score']);
		$score_group .= form_item("Away ($game->away_name [rated: $game->rating_away]) Score", $edit['away_score']);

		$output .= form_group("Scoring", $score_group);
		if( $edit['status'] == 'normal' ) {
			$output .= form_group("Spirit assigned to home ($game->home_name)", $home_spirit->render_viewable());
			$output .= $home_spirit->render_hidden('home');

			$output .= form_group("Spirit assigned to away ($game->away_name)", $away_spirit->render_viewable());
			$output .= $away_spirit->render_hidden('away');
		}

		$output .= para(form_submit('submit'));

		return form($output);
	}


	function perform ( $edit )
	{
		global $lr_session;

		if( ! $this->can_edit ) {
			error_exit("You do not have permission to edit this game");
		}

		$s = new Spirit;
		$s->entry_type = $this->league->enter_sotg;
		$home_spirit = $s->as_formbuilder();
		$away_spirit = $s->as_formbuilder();
		if( $_POST['spirit_home'] ) {
			$home_spirit->bulk_set_answers( $_POST[ 'spirit_home'] );
		}
		if( $_POST['spirit_away'] ) {
			$away_spirit->bulk_set_answers( $_POST[ 'spirit_away'] );
		}

		$dataInvalid = $this->isDataInvalid( $edit );

		if($edit['status'] == 'normal') {
			$dataInvalid .= $home_spirit->answers_invalid();
			$dataInvalid .= $away_spirit->answers_invalid();
		}
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		// store the old info:
		$oldgameresults['home_score'] = $this->game->home_score;
		$oldgameresults['away_score'] = $this->game->away_score;

		// Now, finalize score.
		$this->game->set('home_score', $edit['home_score']);
		$this->game->set('away_score', $edit['away_score']);
		$this->game->set('status', $edit['status']);
		$this->game->set('approved_by', $lr_session->attr_get('user_id'));

		// For a normal game, save spirit entries.
		if( $edit['status'] == 'normal' ) {
			# TODO: don't need to do this unless there are changes.
			if( ! $s->store_spirit_entry( $this->game, $this->game->home_id, $lr_session->attr_get('user_id'), $home_spirit->bulk_get_answers() ) ) {
				error_exit("Error saving spirit entry for " . $this->game->home_name);
			}
			if( ! $s->store_spirit_entry( $this->game, $this->game->away_id, $lr_session->attr_get('user_id'), $away_spirit->bulk_get_answers() ) ) {
				error_exit("Error saving spirit entry for " . $this->game->away_name);
			}
		}
		switch( $edit['status'] ) {
			// for defaults, have to prepare both home and away spirit scores!
			case 'normal':
			default:
				break;
		}

		// load the teams in order to be able to save their current rating
		$home_team = Team::load( array('team_id' => $this->game->home_id) );
		$away_team = Team::load( array('team_id' => $this->game->away_id) );

		// only save the current team ratings if we didn't already save them
		if ($this->game->rating_home == null || $this->game->rating_home == "" &&
		    $this->game->rating_away == null || $this->game->rating_away == "") {
			// save the current snapshot of each team's rating:
			$this->game->set('rating_home', $home_team->rating);
			$this->game->set('rating_away', $away_team->rating);
		}

		if ( ! $this->game->save() ) {
			error_exit("Could not successfully save game results");
		}

		return true;
	}

	function isDataInvalid( $edit )
	{
		$errors = "";

		if($edit['status'] == 'normal') {
			if( !validate_score_value($edit['home_score']) ) {
				$errors .= '<br>You must enter a valid number for the home score.';
			}
			if( !validate_score_value($edit['away_score']) ) {
				$errors .= '<br>You must enter a valid number for the away score.';
			}
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

?>
