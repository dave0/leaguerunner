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

		$this->title = "Game Ratings Table &raquo; Game {$this->game->game_id}";

		$this->template_name = 'pages/game/ratings.tpl';

		$rating_home = $_GET['rating_home'];
		$rating_away = $_GET['rating_away'];

		if (is_null($rating_home)) {
			$rating_home = $this->game->get_home_team_object()->rating;
		}

		if (is_null($rating_away)) {
			$rating_away = $this->game->get_away_team_object()->rating;
		}

		$this->smarty->assign('game', $this->game);
		$this->smarty->assign('rating_home', $rating_home);
		$this->smarty->assign('rating_away', $rating_away);
		$this->smarty->assign('ratings_table',
			$this->game->get_ratings_table( $rating_home, $rating_away) );

		return true;
	}

}

?>
