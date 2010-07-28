<?php
require_once('Handler/GameHandler.php');
######################################################################
# To remove just the results of a game... ie: teams enter wrong scores
# This will UNDO any change to rank, ratings, wins/losses/ties, goals for
#  goals against, SOTG, etc.....
# After this, the game can be re-entered since the game itself is not deleted.
#
class game_removeresults extends GameHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('game','edit', $this->game);
	}

	function process ()
	{
		global $lr_session;

		$this->get_league();

		$this->title = "Game {$this->game->game_id} &raquo; Remove Results";

		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $this->game, $edit );
				break;
			case 'perform':
				$this->perform( $edit );
				local_redirect(url("schedule/view/" . $this->league->league_id));
				break;
			default:
				$rc = $this->generateForm( );
		}

		return $rc;
	}

	function generateForm ( )
	{
		global $lr_session;
		# Alias, to avoid typing.  Bleh.
		$game = &$this->game;
		$league = &$this->league;

		$game->load_score_entries();

		$output = form_hidden('edit[step]', 'confirm');

		$output .= form_item("Game ID", $game->game_id);

		$output .= form_item("League/Division", l($league->fullname, "league/view/$league->league_id"));

		$output .= form_item( "Home Team", l($game->home_name,"team/view/$game->home_id"));
		$output .= form_item( "Away Team", l($game->away_name,"team/view/$game->away_id"));

		$output .= form_item("Date and Time", "$game->game_date, $game->game_start until " . $game->display_game_end(), $note);
		$field = Field::load( array('fid' => $game->fid) );
		$output .= form_item("Location",
			l("$field->fullname ($game->field_code)", "field/view/$game->fid"), $note);

		$output .= form_item("Game Status", $game->status);

		$output .= form_item("Round", $game->round);

		$score_group = '';

		if( ! $game->approved_by) {
			error_exit("The game is not finalized, and so results cannot be removed at this time.");
		}

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

		$score_group .= form_item("Rating Points", $game->rating_points,"Rating points transferred to winning team from losing team");

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

		$output .= form_group("Scoring", $score_group);

		$output .= "<p><font color='red'>If you click <b>submit</b>, you will <b>remove all results</b> for this game!</font></p>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		return form($output);
	}

	function generateConfirm ( $game, $edit )
	{
		$output = para( "You have requested to <b>remove results</b> for the game: <b>$game->game_date $game->game_start between $game->home_name and $game->away_name</b>.  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page.");

		$output .= form_hidden('edit[step]', 'perform');

		$output .= para(form_submit('submit'));

		return form($output);
	}


	function perform ( $edit )
	{
		global $lr_session;

		if ( ! $this->game->removeresults() ) {
			error_exit("Could not successfully remove results for the game");
		}

		return true;
	}

}

?>
