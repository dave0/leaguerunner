<?php
require_once('Handler/LeagueHandler.php');
class league_approvescores extends LeagueHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','approve scores',$this->league->league_id);
	}

	function process ()
	{
		$this->title = "Approve Scores";

		$this->template_name = 'pages/league/approvescores.tpl';

		$games = Game::load_many(array( 'league_id' => $this->league->league_id, 'game_date_past' => 1, '_extra_table' => 'score_entry se', '_extra' => 'se.game_id = s.game_id' ));

		foreach($games as $game) {
			$home = $game->get_score_entry( $game->home_id );
			if(!$home) {
				$game->home_score_for     = 'not entered';
				$game->home_score_against = 'not entered';
			} else {
				$game->home_score_for     = $home->score_for;
				$game->home_score_against = $home->score_against;
			}

			$away = $game->get_score_entry( $game->away_id );
			if(!$away) {
				$game->away_score_for     = 'not entered';
				$game->away_score_against = 'not entered';
			} else {
				$game->away_score_for     = $away->score_for;
				$game->away_score_against = $away->score_against;
			}

			$game->captains_email_list = player_rfc2822_address_list($game->get_captains(), true);
		}
		$this->smarty->assign('games', $games);
	}
}

?>
