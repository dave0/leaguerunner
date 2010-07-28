<?php
require_once('Handler/GameHandler.php');
######################################################################
# TONY added this GameDelete, which is pretty much a copy of GameEdit.
# It can use some work to be nicer, but I don't have time.
# For now, you can't delete a game that is already finalized.
# TODO: needs cleanup (as above)
#
class game_delete extends GameHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('game','delete', $this->game);
	}

	function process ()
	{
		global $lr_session;
		if(!$this->game) {
			error_exit("That game does not exist");
		}

		$this->title = "{$this->league->fullname} &raquo; Delete Game";

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
		if($game->approved_by) {
			error_exit("Finalized games cannot be deleted at this time.");
		} else {
			$score_group .= form_item('',"Score not yet finalized");
			$score_group .= form_item("Score as entered", $this->score_entry_display());
		}

		$output .= form_group("Scoring", $score_group);

		$output .= "<p><font color='red'>If you click <b>submit</b>, you will <b>delete</b> this game!</font></p>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		return form($output);
	}

	function generateConfirm ( $game, $edit )
	{
		$output = para( "You have requested to <b>delete</b> the game: <b>$game->game_date $game->game_start between $game->home_name and $game->away_name</b>.  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page.");

		$output .= form_hidden('edit[step]', 'perform');

		$output .= para(form_submit('submit'));

		return form($output);
	}


	function perform ( $edit )
	{
		global $lr_session;

		if ( ! $this->game->delete() ) {
			error_exit("Could not successfully delete the game");
		}

		return true;
	}

}

?>
