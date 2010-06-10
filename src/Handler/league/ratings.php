<?php
require_once('Handler/LeagueHandler.php');
class league_ratings extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','ratings', $this->league->league_id);
	}

	function generateForm ( $data = '' )
	{
		$output = para("Use the links below to adjust a team's ratings for 'better' or for 'worse'.  Alternatively, you can enter a new rating into the box beside each team then click 'Adjust Ratings' below.  Multiple teams can have the same ratings, and likely will at the start of the season.");
		$output .= para("For the rating values, a <b/>HIGHER</b/> numbered rating is <b/>BETTER</b/>, and a <b/>LOWER</b/> numbered rating is <b/>WORSE</b/>.");
		$output .= para("<b/>WARNING: </b/> Adjusting ratings while the league is already under way is possible, but you'd better know what you are doing!!!");

		$header = array( "Rating", "Team Name", "Avg.<br/>Skill", "New Rating",);
		$rows = array();

		$this->league->load_teams();
		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));
		foreach($season as $team) {

			$row = array();
			$row[] = $team->rating;
			$row[] = check_form($team->name);
			$row[] = $team->avg_skill();
			$row[] = "<font size='-4'><a href='#' onClick='document.getElementById(\"ratings_form\").elements[\"edit[$team->team_id]\"].value++; return false'> better </a> " .
				"<input type='text' size='3' name='edit[$team->team_id]' value='$team->rating' />" .
				"<a href='#' onClick='document.getElementById(\"ratings_form\").elements[\"edit[$team->team_id]\"].value--; return false'> worse</a></font>";

			$rows[] = $row;
		}
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		$output .= form_hidden("edit[step]", 'perform');
		$output .= "<input type='reset' />&nbsp;<input type='submit' value='Adjust Ratings' /></div>";

		return form($output, 'post', null, 'id="ratings_form"');
	}

	function process ()
	{
		$this->title = "{$this->league->fullname} &raquo; Ratings Adjustment";

		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				$this->perform($edit);
				local_redirect(url("league/view/" . $this->league->league_id));
				break;
			default:
				$rc = $this->generateForm();
		}

		return $rc;

	}

	function perform ( $edit )
	{
		global $dbh;
		// make sure the teams are loaded
		$this->league->load_teams();

		$sth = $dbh->prepare('UPDATE team SET rating = ? WHERE team_id = ?');
		// go through what was submitted
		foreach ($edit as $team_id => $rating) {
			if (is_numeric($team_id) && is_numeric($rating)) {
				$team = $this->league->teams[$team_id];

				// TODO:  Move this logic to a function inside the league.inc file
				// update the database
				$sth->execute( array( $rating, $team_id ) );
			}
		}

		return true;
	}
}

?>
