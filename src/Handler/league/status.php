<?php
require_once('Handler/LeagueHandler.php');
class league_status extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit', $this->league->league_id);
	}

	function process ()
	{
		$this->title = "{$this->league->fullname} &raquo; Status Report";

		$this->template_name = 'pages/league/status.tpl';

		// make sure the teams are loaded
		$this->league->load_teams();

		// TODO: we calculate_standings() here, but it's probably not necessary
		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));

		$fields = array();
		$sth = Field::query( array( '_extra' => '1 = 1', '_order' => 'f.code') );
		while( $field = $sth->fetchObject('Field') ) {
			$fields[$field->code] = $field->region;
		}

		// Parse the schedule and accumulate per-team stats
		$sth = Game::query ( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date, g.game_start, field_code') );
		while($g = $sth->fetchObject('Game') ) {

			$season[$g->home_team]->game_count++;
			$season[$g->away_team]->game_count++;

			list($code, $num) = explode(' ', $g->field_code);
			$season[$g->home_team]->region_game_counts[$fields[$code]]++;
			$season[$g->away_team]->region_game_counts[$fields[$code]]++;

			$season[$g->home_team]->home_game_count++;

			$season[$g->home_team]->opponent_counts[$g->away_name]++;
			$season[$g->away_team]->opponent_counts[$g->home_name]++;
		}

		$teams = array();
		while(list(, $tid) = each($order)) {
			$team = $season[$tid];
			$ratio = sprintf("%.3f", $team->preferred_field_ratio());

			$check_ratio = $ratio;
			if( $team->game_count % 2 ) {
				$check_ratio = ( ($ratio * $team->game_count)+1)/($team->game_count +1);
			}

			list($team->preferred_ratio, $team->preferred_ratio_bad) = array($ratio, ($check_ratio < 0.5) );

			list($team->home_game_ratio, $team->home_game_ratio_bad) = _ratio_helper( $team->home_game_count, $team->game_count);

			$teams[] = $team;
		}

		$this->smarty->assign('teams', $teams);

		return true;
	}
}

/*
 * Calculate ratio to 3 decimal places, and flag if we're below 0.500.
 *
 * For an odd value of $total, flag is not triggered if we're one value of
 * $count below 0.500, so that we do not warn if 0.500 is not possible.
 */
function _ratio_helper( $count, $total )
{
	$ratio = 0;
	if( $total > 0 ) {
		$ratio = $count / $total;
	}
	$ratio = sprintf("%.3f", $ratio);

	$check_ratio = $ratio;
	if( $total % 2 ) {
		$check_ratio = ($count+1)/($total+1);
	}

	return array($ratio, ($check_ratio < 0.5) );
}


?>
