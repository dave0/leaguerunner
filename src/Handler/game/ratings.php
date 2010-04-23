<?php
require_once('Handler/GameHandler.php');
class game_ratings extends GameHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('game','view', $this->game);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "Game Ratings Table";

		$this->setLocation(array(
			"$this->title &raquo; Game " . $this->game->game_id => 0));

		$rc = $this->generateForm( );

		return $rc;
	}

	function generateForm ( )
	{
		global $lr_session;

		$rating_home = arg(3);
		$rating_away = arg(4);
		$whatifratings = true;

		# Alias, to avoid typing.  Bleh.
		$game = &$this->game;
		$league = $this->get_league();

		$game->load_score_entries();

		$teams = $league->load_teams();
		$teams = $league->teams;

		$home_team = null;
		$away_team = null;
		foreach ($teams as $team) {
			if ($team->team_id == $game->home_id) {
				$home_team = $team;
			} else if ($team->team_id == $game->away_id) {
				$away_team = $team;
			}
		}

		if ($rating_home == null || $rating_away == null) {
			$rating_home = $home_team->rating;
			$rating_away = $away_team->rating;
			$whatifratings = false;
		}

      $output = para("The number of rating points transferred depends on several factors:" .
      		"<br>- the total score" .
      		"<br>- the difference in score" .
      		"<br>- and the current rating of both teams");

      $output .= para("How to read the table below:" .
      		"<br>- Find the 'home' team's score along the left." .
      		"<br>- Find the 'away' team's score along the top." .
      		"<br>- The points shown in the table where these two scores intersect are the number of rating points that will be transfered from the losing team to the winning team.");

		$output .= para("A tie does not necessarily mean 0 rating points will be transfered... " .
				"Unless the two team's rating scores are very close, one team is expected to win. " .
				"If that team doesn't win, they will lose rating points. " .
				"The opposite is also true: if a team is expected to lose, but they tie, they will gain some rating points.");

		$output .= para("Ties are shown from the home team's perspective.  So, a negative value indicates " .
				"that in the event of a tie, the home team will lose rating points (and the away team will gain them).");

		$home = $game->home_name;
		$away = $game->away_name;

		if ($whatifratings) {
      	$output .= para("HOME: <b>$home</b>, 'what if' rating of <b>$rating_home</b> ".
      			"<br>AWAY: <b>$away</b>, 'what if' rating of <b>$rating_away</b>");
		} else {
      	$output .= para("HOME: <b>$home</b>, current rating of <b>$rating_home</b> ".
      			"<br>AWAY: <b>$away</b>, current rating of <b>$rating_away</b>");
		}

		$ratings_table = $game->get_ratings_table( $rating_home, $rating_away, true );

		return $output . $ratings_table ;
	}

}

?>
