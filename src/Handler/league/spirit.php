<?php
require_once('Handler/LeagueHandler.php');
class league_spirit extends LeagueHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view', $this->league->league_id, 'spirit');
	}

	function process ()
	{
		global $dbh, $CONFIG;
		$this->title = "{$this->league->fullname} Spirit";

		/*
		 * Grab schedule info
		 */
		$games = Game::load_many( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date,g.game_id') );

		if( !is_array($games) ) {
			error_exit("There are no games scheduled for this league");
		}

		$s = new Spirit;
		$s->entry_type = $this->league->enter_sotg;
		$s->display_numeric_sotg = $this->league->display_numeric_sotg();

		/*
		 * Show overall league spirit
		 */
		$rows   = $s->league_sotg( $this->league );
		$rows[] = $s->league_sotg_averages( $this->league );
		$rows[] = $s->league_sotg_std_dev( $this->league );
		$output = h2('Team spirit summary')
			. table(
				array_merge(
					array(
						'Team',
						'Average',
					),
					(array)$s->question_headings()
				),
				$rows,
				array('alternate-colours' => true)
			);

		$output .= h2('Distribution of team average spirit scores')
			. table(
				array(
					'Spirit score',
					'Number of teams',
					'Percentage of league'
				),
				$s->league_sotg_distribution( $this->league )
			)
			. "\n";


		/*
		 * Show every game
		 */
		$header = array_merge(
			array(
				'Game',
				'Entry By',
				'Given To',
				'Score',
			),
			(array)$s->question_headings()
		);
		$rows = array();
		$question_column_count = count($s->question_headings());
		while(list(,$game) = each($games)) {

			$teams = array(
				$game->home_team => $game->home_name,
				$game->away_team => $game->away_name
			);
			while( list($giver,$giver_name) = each ($teams)) {

				$recipient = $game->get_opponent_id ($giver);

				$thisrow = array(
					l($game->game_id, "game/view/$game->game_id")
						. " " .  strftime('%a %b %d %Y', $game->timestamp),
					l($giver_name, "team/view/$giver"),
					l($teams[$recipient], "team/view/$recipient")
				);

				# Fetch spirit answers for games
				$entry = $game->get_spirit_entry( $recipient );
				if( !$entry ) {
					$thisrow[] = array(
						'data'    => 'Team did not submit a spirit rating',
						'colspan' => $question_column_count + 1,
					);
					$rows[] = $thisrow;
					continue;
				}

				$thisrow = array_merge(
					$thisrow,
					(array)$s->render_game_spirit( $entry )
				);

				$rows[] = $thisrow;
				if( $entry['comments'] != '' ) {
					$rows[] = array(
						array(
							'colspan' => 2,
							'data' => '<b>Comment for entry above:</b>'
						),
						array(
							'colspan' => count($header) - 2,
							'data'    => $entry['comments'],
						)
					);
				}
			}
		}

		$style = '#main table td { font-size: 80% }';
		$output .= h2('Spirit reports per game');
		$output .= "<style>$style</style>" . table($header,$rows, array('alternate-colours' => true) );

		return $output;
	}
}

?>
