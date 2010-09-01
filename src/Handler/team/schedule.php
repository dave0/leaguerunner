<?php
require_once('Handler/TeamHandler.php');
class team_schedule extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view schedule', $this->team->team_id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "{$this->team->name} &raquo; Schedule";
		$this->template_name = 'pages/team/schedule.tpl';

		$games = Game::load_many( array( 'either_team' => $this->team->team_id, 'published' => 1, '_order' => 'g.game_date,g.game_start,g.game_id') );

		if( !count($games) ) {
			error_exit('This team does not yet have any games scheduled');
		}

		foreach($games as $g) {
			if($g->is_finalized()) {
				/* Already entered */
				$g->score_type = 'final';
			} else {
				/* Not finalized yet, so we will either:
				*   - display entered score if present
				*   - display score entry link if game date has passed
				*   - display a blank otherwise
				*/
				$entered = $g->get_score_entry( $this->team->team_id );
				if($entered) {
					$g->score_type = 'unofficial';
					if( $g->home_id == $this->team->team_id) {
						$g->home_score = $entered->score_for;
						$g->away_score = $entered->score_against;
					} else {
						$g->away_score = $entered->score_for;
						$g->home_score = $entered->score_against;
					}
				} else if($lr_session->has_permission('game','submit score', $g, $this->team)
					&& ($g->timestamp < time()) ) {
						$g->score_type = l("submit score", "game/submitscore/$g->game_id/" . $this->team->team_id);
				}
			}
		}



		$this->smarty->assign('can_edit', false);
		$this->smarty->assign('games', $games);
		$this->smarty->assign('team', $this->team);

		return true;
	}
}
?>
