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
		$this->title = "{$this->league->fullname} &raquo; Scores";

		$this->template_name = 'pages/league/headtohead.tpl';

		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}

		// TODO: do we need to handle multiple rounds differently?
		list($order, $season, $round) = $this->league->calculate_standings();
		$teams = array();
		foreach ($order as $tid) {
			$row = array();

			// grab schedule information
			$games = Game::load_many( array( 'either_team' => $tid, '_order' => 'g.game_date,g.game_start,g.game_id') );
			$gameentry = array();
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
					$row[] = array('data'=>'&nbsp;', 'class'=>'impossible');
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
					$row[] = array('data' => $thiscell);
				}
			}

			$season[$tid]->headtohead = $row;

			$teams[] = $season[$tid];
		}

		$this->smarty->assign('teams', $teams);
	}
}

?>
