<?php
require_once('Handler/LeagueHandler.php');
class schedule_view extends LeagueHandler
{
	function has_permission ()
	{
		return true;
	}

	function process ()
	{
		global $lr_session;
		$this->title = "{$this->league->fullname} &raquo; Schedule";
		$this->template_name = 'pages/schedule/view.tpl';

		// TODO: do load_many() and query using 'published'
		$sth = Game::query ( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date, g.game_start, field_code') );

		$games = array();
		while( $game = $sth->fetchObject('Game') ) {

			if( ! ($game->published || $lr_session->has_permission('league','edit schedule', $this->league->league_id) ) ) {
				continue;
			}

			$games[] = $game;
		}

		$this->smarty->assign('can_edit', $lr_session->has_permission('league','edit schedule', $this->league->league_id));
		$this->smarty->assign('games', $games);
		return true;
	}
}

?>
