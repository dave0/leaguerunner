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
		global $lr_session;

		$this->title = "{$this->league->fullname} &raquo; Spirit";
		$this->template_name = 'pages/league/spirit.tpl';

		$s = new Spirit;
		$s->display_numeric_sotg = $this->league->display_numeric_sotg();

		$this->smarty->assign('question_headings', $s->question_headings() );
		$this->smarty->assign('spirit_summary', $s->league_sotg( $this->league ) );
		$this->smarty->assign('spirit_avg',     $s->league_sotg_averages( $this->league ) );
		$this->smarty->assign('spirit_dev',     $s->league_sotg_std_dev( $this->league ) );


		if( ! $lr_session->is_coordinator_of( $this->league ) ) {
			return true;
		}

		$games = Game::load_many( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date,g.game_id') );

		if( !is_array($games) ) {
			error_exit("There are no games scheduled for this league");
		}

		$this->smarty->assign('question_keys',  array_merge( array('full'), $s->question_keys(), array('score_entry_penalty') ));
		$this->smarty->assign('num_spirit_columns', count($s->question_headings()) + 1);
		$this->smarty->assign('num_comment_columns', count($s->question_headings()) + 2);

		$rows = array();
		foreach($games as $game) {

			$teams = array(
				$game->home_team => $game->home_name,
				$game->away_team => $game->away_name
			);
			while( list($giver,) = each ($teams)) {
				$recipient = $game->get_opponent_id ($giver);

				$thisrow = array(
					'game_id' => $game->game_id,
					'day_id' => $game->day_id,
					'given_by_id' => $giver,
					'given_by_name' => $teams[$giver],
					'given_to_id' => $recipient,
					'given_to_name' => $teams[$recipient],
					'has_entry'     => 0,
				);

				# Fetch spirit answers for games
				$entry = $game->get_spirit_entry( $recipient );
				if( !$entry ) {
					$rows[] = $thisrow;
					continue;
				}
				$thisrow['has_entry'] = 1;

				$thisrow = array_merge(
					$thisrow,
					(array)$s->fetch_game_spirit_items_html( $entry )
				);
				$thisrow['comments'] = $entry['comments'];

				$rows[] = $thisrow;
			}
		}
		$this->smarty->assign('spirit_detail', $rows );

		return true;
	}
}

?>
