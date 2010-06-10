<?php
require_once('Handler/LeagueHandler.php');
/*
 * RK: tabular report of scores for all league games
 */
class league_scores extends LeagueHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view', $this->league->league_id);
	}

	function process ()
	{
		$id = $this->league->league_id;

		$this->title = "{$this->league->fullname} &raquo; Scores";

		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}

		// TODO: do we need to handle multiple rounds differently?

		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $current_round ));

		$this->league->load_teams();
		if( $this->league->teams <= 0 ) {
			return para('This league has no teams.');
		}

		$header = array('');
		$seed = 0;
		foreach ($order as $tid) {
			$seed++;
			$short_name = $season[$tid]->name;
			$header[] = l($short_name, "team/view/$tid",
						  array('title' => htmlspecialchars($season[$tid]->name)
								." Rank:$seed Rating:".$season[$tid]->rating));
		}
		$header[] = '';

		$rows = array($header);

		$seed = 0;
		foreach ($order as $tid) {
			$seed++;
			$row = array();
			$row[] = l($season[$tid]->name, "team/schedule/$tid",
					   array('title'=>"Rank:$seed Rating:".$season[$tid]->rating));

			// grab schedule information
			$games = game_load_many( array( 'either_team' => $tid,
											'_order' => 'g.game_date,g.game_start,g.game_id') );
			$gameentry = array();
			//while(list(,$game) = each($games)) {
			foreach ($games as &$game) {
				if($game->home_id == $tid) {
					$opponent_id = $game->away_id;
				} else {
					$opponent_id = $game->home_id;
				}
				// if score finalized, save game for printing
				if($game->is_finalized()) {
					$gameentry[$opponent_id][] = $game;
				}
			}

			// output game results row
			foreach ($order as $opponent_id) {
				if ($opponent_id == $tid) {
					// no games against my own team
					$row[] = array('data'=>'&nbsp;', 'bgcolor'=>'gray');
					continue;
				}
				if( ! array_key_exists($opponent_id, $gameentry) ) {
					// no games against this team
					$row[] = array('data'=>'&nbsp;');
					continue;
				}

				$results = array();
				$wins = $losses = 0;
				foreach ($gameentry[$opponent_id] as &$game) {
					$game_score = '';
					$game_result = "";
					switch($game->status) {
					case 'home_default':
						$game_score = "(default)";
						$game_result = "$game->home_name defaulted";
						break;
					case 'away_default':
						$game_score = "(default)";
						$game_result = "$game->away_name defaulted";
						break;
					case 'forfeit':
						$game_score = "(forfeit)";
						$game_result = "forfeit";
						break;
					default: //normal finalized game
						if($game->home_id == $tid) {
							$opponent_name = $game->away_name;
							$game_score = "$game->home_score-$game->away_score";
							if ($game->home_score > $game->away_score) {
								$wins++;
							} else if ($game->home_score < $game->away_score) {
								$losses++;
							}
						} else {
							$opponent_name = $game->home_name;
							$game_score = "$game->away_score-$game->home_score";
							if ($game->away_score > $game->home_score) {
								$wins++;
							} else if ($game->away_score < $game->home_score) {
								$losses++;
							}
						}
						if ($game->home_score > $game->away_score) {
							$game_result = "$game->home_name defeated $game->away_name"
								." $game->home_score-$game->away_score";
						} else if ($game->home_score < $game->away_score) {
							$game_result = "$game->away_name defeated $game->home_name"
								." $game->away_score-$game->home_score";
						} else {
							$game_result = "$game->home_name and $game->away_name tied $game_score";
						}
						$game_result .= " ($game->rating_points rating points transferred)";
					}

					$popup = strftime('%a %b %d', $game->timestamp)." at $game->field_code: $game_result";

					$results[] = l($game_score, "game/view/$game->game_id",
								   array('title' => htmlspecialchars($popup)));
				}
				$thiscell = implode('<br />', $results);
				if ($thiscell == '') {
					$thiscell = '&nbsp;';
				}
				if ($wins > $losses) {
					/* $row[] = array('data'=>$thiscell, 'bgcolor'=>'#A0FFA0'); */
					$row[] = array('data'=>$thiscell, 'class'=>'winning');
				} else if ($wins < $losses) {
					$row[] = array('data'=>$thiscell, 'class'=>'losing');
				} else {
					$row[] = $thiscell;
				}
			}

			// repeat team name
			$row[] = l($season[$tid]->name, "team/schedule/$tid",
					   array('title'=>"Rank:$seed Rating:".$season[$tid]->rating));
			$rows[] = $row;
		}

		//return "<div class='pairtable'>" . table(null, $rows, array('border'=>'1')) . "</div>"
		return "<div class='scoretable'>" . table(null, $rows, array('class'=>'scoretable')) . "</div>"
			. para("Scores are listed with the first score belonging the team whose name appears on the left.<br />"
			. "Green backgrounds means row team is winning season series, red means column team is winning series. Defaulted games are not counted.");
		//return "<div class='pairtable'>" . table(null, $rows, array('style'=>'border: 1px solid gray;')) . "</div>";
		//return "<div class='listtable'>" . table(null, $rows) . "</div>";
	}
}

?>
