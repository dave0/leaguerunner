<?php
require_once('Handler/LeagueHandler.php');
class league_spiritdownload extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league', 'download', $this->league->league_id, 'spirit');
	}

	function process ()
	{
		global $dbh;

		$games = Game::load_many( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date,g.game_id') );

		if( !is_array($games) ) {
			error_exit("There are no games scheduled for this league");
		}

		$s = new Spirit;
		$s->entry_type = $this->league->enter_sotg;
		$s->display_numeric_sotg = $this->league->display_numeric_sotg();

		// Start the output, let the browser know what type it is
		header('Content-type: text/x-csv');
		header("Content-Disposition: attachment; filename=\"spirit{$this->league_id}.csv\"");
		$out = fopen('php://output', 'w');

		$header = array_merge(
			array(
				'Game #',
				'Date',
				'Giver Name',
				'Giver ID',
				'Given To',
				'Given To ID',
				'SOTG Total',
			),
			(array)$s->question_headings(),
			array(
				'Comments',
			)
		);
		fputcsv($out, $header);

		while(list(,$game) = each($games)) {

			$teams = array(
				$game->home_team => $game->home_name,
				$game->away_team => $game->away_name
			);
			while( list($giver,$giver_name) = each ($teams)) {

				$recipient = $game->get_opponent_id ($giver);

				# Fetch spirit answers for games
				$entry = $game->get_spirit_entry( $recipient );
				if( !$entry ) {
					$entry = array(
						comments => 'Team did not submit a spirit rating',
					);
				} else {
					if( ! $entry['entered_sotg'] ) {
						$entry['entered_sotg'] = (
							$entry['timeliness'] + $entry['rules_knowledge'] + $entry['sportsmanship'] + $entry['rating_overall'] + $entry['score_entry_penalty']
						);
					}
				}

				$thisrow = array(
					$game->game_id,
					strftime('%a %b %d %Y', $game->timestamp),
					$giver_name,
					$giver,
					$teams[$recipient],
					$recipient,
					$entry['entered_sotg'],
					$entry['timeliness'],
					$entry['rules_knowledge'],
					$entry['sportsmanship'],
					$entry['rating_overall'],
					$entry['score_entry_penalty'],
					$entry['comments'],
				);

				fputcsv($out, $thisrow);
			}
		}
		fclose($out);

		// Returning would cause the Leaguerunner menus to be added
		exit;
	}
}

?>
