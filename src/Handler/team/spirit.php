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

		// load the league
		$league = League::load( array('league_id' => $this->team->league_id) );

		// if the person doesn't have permission to see this team's spirit, bail out
		if( !$lr_session->has_permission('team', 'view', $this->team->team_id, 'spirit') ) {
			error_exit("You do not have permission to view this team's spirit results");
		}

		if( $league->display_sotg == 'coordinator_only' && ! $lr_session->is_coordinator_of( $league->league_id ) ) {
			error_exit("Spirit results are restricted to coordinator-only");
		}

		$s = new Spirit;
		$s->display_numeric_sotg = $league->display_numeric_sotg();
		$s->entry_type = $league->enter_sotg;

		/*
		 * Grab schedule info
		 */
		$games = Game::load_many( array( 'either_team' => $this->team->team_id, '_order' => 'g.game_date') );

		if( !is_array($games) ) {
			error_exit('There are no games scheduled for this team');
		}

		$questions = $s->question_headings();
		$header = array_merge(
			array(
				'ID',
				'Date',
				'Opponent',
				'Game Avg',
			),
			(array)$questions
		);

		$question_column_count = count($questions);
		if ($lr_session->has_permission('league', 'view', $this->team->league_id, 'spirit') ) {
			$header[] = 'Comments';
			$question_column_count++;
		}

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
				l($game->game_id, "game/view/$game->game_id"),
				strftime('%a %b %d %Y', $game->timestamp),
				l($opponent_name, "team/view/$opponent_id")
			);

			# Fetch spirit answers for games
			$entry = $game->get_spirit_entry( $this->team->team_id );
			if( !$entry ) {
				$thisrow[] = array(
					'data'    => 'Opponent did not submit a spirit rating',
					'colspan' => $question_column_count + 1,
				);
				$rows[] = $thisrow;
				continue;
			}

			$thisrow = array_merge(
				$thisrow,
				(array)$s->render_game_spirit( $entry )
			);

			// can only see comments if you're a coordinator
			if( $lr_session->has_permission('league', 'view', $this->team->league_id, 'spirit') ) {
				$thisrow[] = $entry['comments'];
			}

			$rows[] = $thisrow;
		}

		$rows[] = array_merge(
			array(
				"Average","-","-"
			),
			(array)$s->team_sotg_averages( $this->team ),
			array(
				'-'
			)
		);

		$style = '#main table td { font-size: 80% }';
		return "<style>$style</style>" . table($header,$rows, array('alternate-colours' => true) );
	}
}

?>
