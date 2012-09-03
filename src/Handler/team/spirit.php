<?php
require_once('Handler/TeamHandler.php');

class team_spirit extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view', $this->team->team_id);
	}

	function process ()
	{
		global $lr_session;
		$this->title = "{$this->team->name} &raquo; Spirit";
		$this->template_name = 'pages/team/spirit.tpl';

		// load the league
		$league = League::load( array('league_id' => $this->team->league_id) );

		// if the person doesn't have permission to see this team's spirit, bail out
		if( !$lr_session->has_permission('team', 'view', $this->team->team_id, 'spirit') ) {
			info_exit("You do not have permission to view this team's spirit results");
		}

		if( $league->display_sotg == 'coordinator_only' && ! $lr_session->is_coordinator_of( $league->league_id ) ) {
			error_exit("Spirit results are restricted to coordinator-only");
		}

		$s = new Spirit;
		$s->display_numeric_sotg = $league->display_numeric_sotg();

		/*
		 * Grab schedule info
		 */
		$games = Game::load_many( array( 'either_team' => $this->team->team_id, '_order' => 'g.game_date') );

		if( !is_array($games) ) {
			info_exit('There are no games scheduled for this team');
		}
		$this->smarty->assign('question_keys',  array_merge( array('full'), $s->question_keys(), array('score_entry_penalty') ));
		$this->smarty->assign('question_headings', $s->question_headings() );
		$this->smarty->assign('num_spirit_columns', count($s->question_headings()) + 1);
		$this->smarty->assign('num_comment_columns', count($s->question_headings()) + 2);

		$rows = array();
		foreach($games as $game) {

			if( ! $game->is_finalized() ) {
				continue;
			}

			if($game->home_id == $this->team->team_id) {
				$opponent_id = $game->away_id;
				$opponent_name = $game->away_name;
				$home_away = '(home)';
			} else {
				$opponent_id = $game->home_id;
				$opponent_name = $game->home_name;
				$home_away = '(away)';
			}
			$thisrow = array(
				'game_id' => $game->game_id,
				'day_id' => $game->day_id,
				'given_by_id' => $opponent_id,
				'given_by_name' => $opponent_name,
				'has_entry'     => 0,
			);

			# Fetch spirit answers for games
			$entry = $game->get_spirit_entry( $this->team->team_id );
			if( !$entry ) {
				$rows[] = $thisrow;
				continue;
			}
			$thisrow['has_entry'] = 1;

			$thisrow = array_merge(
				$thisrow,
				(array)$s->fetch_game_spirit_items_html( $entry )
			);

			// can only see comments if you're a coordinator
			if( $lr_session->has_permission('league', 'view', $this->team->league_id, 'spirit') ) {
				$thisrow[] = $entry['comments'];
			}

			$rows[] = $thisrow;
		}
		$this->smarty->assign('spirit_detail', $rows );

		return true;
	}
}

?>
