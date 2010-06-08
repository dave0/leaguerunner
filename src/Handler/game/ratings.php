<?php
require_once('Handler/GameHandler.php');
class game_ratings extends GameHandler
{

	private $rating_home;
	private $rating_away;

	function __construct ( $id, $hr = null, $ar = null )
	{
		parent::__construct( $id );

		if( $hr ) {
			$this->rating_home = $hr;
		}
		if( $ar ) {
			$this->rating_away = $ar;
		}
	}

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

		# Alias, to avoid typing.  Bleh.
		$game = &$this->game;

		$home_team = $game->get_home_team_object();
		$away_team = $game->get_away_team_object();

		if ($this->rating_home == null || $this->rating_away == null) {
			$this->rating_home = $home_team->rating;
			$this->rating_away = $away_team->rating;
		}

		$output = para("The number of rating points transferred depends on several factors:" .
			"<ul><li> the total score" .
			"<li> the difference in score" .
			"<li> and the current rating of both teams</ul>");

		$output .= para("How to read the table below:" .
			"<ul><li> Find the 'home' team's score along the left." .
			"<li> Find the 'away' team's score along the top." .
			"<li> The points shown in the table where these two scores intersect are the number of rating points that will be transfered from the losing team to the winning team.</ul>");

		$output .= para("A tie does not necessarily mean 0 rating points will be transfered... " .
			"Unless the two team's rating scores are very close, one team is expected to win. " .
			"If that team doesn't win, they will lose rating points. " .
			"The opposite is also true: if a team is expected to lose, but they tie, they will gain some rating points.");

		$output .= para("Ties are shown from the home team's perspective.  So, a negative value indicates " .
				"that in the event of a tie, the home team will lose rating points (and the away team will gain them).");

		$home = $game->home_name;
		$away = $game->away_name;

		$output .= para("HOME: <b>$home</b> rating of <b>$this->rating_home</b> ".
			"<br>AWAY: <b>$away</b> rating of <b>$this->rating_away</b>");

		$ratings_table = $game->get_ratings_table( $this->rating_home, $this->rating_away, true );

		return $output . $ratings_table ;
	}

}

?>
