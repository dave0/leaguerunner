<?php
/* 
 * Handle operations specific to an entire season
 */

function season_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'standings':
			return new SeasonStandings;
	}
	return null;
}

/**
 * Export season standings as plain text.
 */
class SeasonStandings extends Handler
{
	function initialize ()
	{
		$this->title = "Standings";
		$this->_required_perms = array(
			'allow',
		);

		return true;
	}
	
	function process ()
	{
		$season = arg(2);
		if( !$season ) {
			$season = variable_get('current_season','summer');
		}
		header( "Content-type: text/plain\n\n");

		$year = variable_get('current_year', '2004');

		print "OCUA $season League $year Standings\n\n";
		
		$result = db_query("SELECT distinct league_id from league where season = '%s'", $season);
		while( $foo = db_fetch_array($result)) {
			$id = $foo['league_id'];
			$league = league_load( array('league_id' => $id) );
			
			if($league->schedule_type == 'none') {
				continue;
			}

			$days = $league->day;
			$days = str_replace(',','/', $days);
			print "OCUA Fall $days Division\n";
			
			print $this->generate_standings($league, 0);

			print "\n";
		}

		# To prevent HTML output.
		exit(0);
	}

	/**
	 * TODO: this should be split into:
	 * 	1) loading data into $season/$round data structures
	 * 	2) sorting
	 * 	3) displaying
	 * as this will allow us to create multiple sort modules
	 */
	function generate_standings ($league, $current_round = 0)
	{
		$league->load_teams();

		if( count($league->teams) < 1 ) {
			$this->error_exit("Cannot generate standings for a league with no teams");
		}

		while(list($id,) = each($league->teams)) {
			$league->teams[$id]->points_for = 0;
			$league->teams[$id]->points_against = 0;
			$league->teams[$id]->spirit = 0;
			$league->teams[$id]->win = 0;
			$league->teams[$id]->loss = 0;
			$league->teams[$id]->tie = 0;
			$league->teams[$id]->defaults_for = 0;
			$league->teams[$id]->defaults_against = 0;
			$league->teams[$id]->games = 0;
			$league->teams[$id]->vs = array();
		}

               
		$season = $league->teams;
		$round  = $league->teams;

		/* Now, fetch the schedule.  Get all games played by anyone who is
		 * currently in this league, regardless of whether or not their
		 * opponents are still here
		 */
		// TODO: I'd like to use game_load_many here, but it's too slow.
		$result = db_query(
			"SELECT DISTINCT s.*, 
				s.home_team AS home_id, 
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			FROM schedule s, leagueteams t
			LEFT JOIN team h ON (h.team_id = s.home_team) 
			LEFT JOIN team a ON (a.team_id = s.away_team)
			WHERE t.league_id = %d 
				AND NOT ISNULL(s.home_score) AND NOT ISNULL(s.away_score) AND (s.home_team = t.team_id OR s.away_team = t.team_id) ORDER BY s.game_id", $league->league_id);
		while( $ary = db_fetch_array( $result) ) {
			$g = new Game;
			$g->load_from_query_result($ary);
			$this->record_game($season, $g);
			if($current_round == $g->round) {
				$this->record_game($round, $g);
			}
		}

		/* HACK: Before we sort everything, we've gotta copy the 
		 * $season's spirit and games values into the $round array 
		 * because otherwise, in any round after the first we're 
		 * only sorting on the spirit scores received in the current 
		 * round.
		 */
		while(list($team_id,$info) = each($season))
		{
			$round[$team_id]->spirit = $info->spirit;
			$round[$team_id]->games = $info->games;
		}
		reset($season);
		
		/* Now, sort it all */
		uasort($season, array($this, 'sort_standings_by_rank'));	
		$sorted_order = &$season;
		
		$output .= sprintf("%4.4s\t%-30.30s\t%2.2s\t%2.2s\t%2.2s\t%4.4s\t%3.3s\t%3.3s\t%3.3s\t%5.5s\n",
			"Rank","Name","W","L","T","Dfl","PF","PA","+/-","SOTG");
		
        reset($sorted_order);
		while(list(, $data) = each($sorted_order)) {
			$id = $data->team_id;
			
			$name = clean_name($data->name);
			
			// initialize the sotg to dashes!
      	    $sotg = "---";
			if($season[$id]->games < 3 && !($this->_permissions['administer_league'])) {
				 $sotg = "---";
			} else if ($season[$id]->games > 0) {
				$sotg = sprintf("%.2f", ($season[$id]->spirit / $season[$id]->games));
			}
			$output .= sprintf("% 4d\t%-30.30s\t% 2d\t% 2d\t% 2d\t% 4d\t% 3d\t% 3d\t% 3d\t%5.5s\n",
				$season[$id]->rank, 
				$name,
				$season[$id]->win,
				$season[$id]->loss,
				$season[$id]->tie,
				$season[$id]->defaults_against,
				$season[$id]->points_for,
				$season[$id]->points_against,
				$season[$id]->points_for - $season[$id]->points_against,
				$sotg);
		}
		
		return $output;
	}
	
	function record_game(&$season, &$game)
	{

		$game->home_spirit = $game->get_spirit_numeric( $game->home_team );
		$game->away_spirit = $game->get_spirit_numeric( $game->away_team );
		if(isset($season[$game->home_team])) {
			$team = &$season[$game->home_team];
			
			$team->games++;
			$team->points_for += $game->home_score;
			$team->points_against += $game->away_score;
			$team->spirit += $game->home_spirit;

			/* Need to initialize if not set */
			if(!isset($team->vs[$game->away_team])) {
				$team->vs[$game->away_team] = 0;
			}
			
			if($game->status == 'home_default') {
				$team->defaults_against++;
			} else if($game->status == 'away_default') {
				$team->defaults_for++;
			}

			if($game->home_score == $game->away_score) {
				$team->tie++;
				$team->vs[$game->away_team]++;
			} else if($game->home_score > $game->away_score) {
				$team->win++;
				$team->vs[$game->away_team] += 2;
			} else {
				$team->loss++;
				$team->vs[$game->away_team] += 0;
			}
		}
		if(isset($season[$game->away_team])) {
			$team = &$season[$game->away_team];
			
			$team->games++;
			$team->points_for += $game->away_score;
			$team->points_against += $game->home_score;
			$team->spirit += $game->away_spirit;

			/* Need to initialize if not set */
			if(!isset($team->vs[$game->home_team])) {
				$team->vs[$game->home_team] = 0;
			}
			
			if($game->status == 'away_default') {
				$team->defaults_against++;
			} else if($game->status == 'home_default') {
				$team->defaults_for++;
			}

			if($game->away_score == $game->home_score) {
				$team->tie++;
				$team->vs[$game->home_team]++;
			} else if($game->away_score > $game->home_score) {
				$team->win++;
				$team->vs[$game->home_team] += 2;
			} else {
				$team->loss++;
				$team->vs[$game->home_team] += 0;
			}
		}
	}

	function sort_standings_by_rank (&$a, &$b) 
        {

          if ($a->rank == $b->rank) {
            return 0;
          }
          return ($a->rank < $b->rank) ? -1 : 1;

        }

	function sort_standings (&$a, &$b) 
	{

		/* First, order by wins */
		$b_points = (( 2 * $b->win ) + $b->tie);
		$a_points = (( 2 * $a->win ) + $a->tie);
		if( $a_points > $b_points ) {
			return 0;
		} else if( $a_points < $b_points ) {
			return 1;
		}
		
		/* Then, check head-to-head wins */
		if(isset($b->vs[$a['id']]) && isset($a->vs[$b['id']])) {
			if( $b->vs[$a['id']] > $a->vs[$b['id']]) {
				return 0;
			} else if( $b->vs[$a['id']] < $a->vs[$b['id']]) {
				return 1;
			}
		}

		/* Check SOTG */
		if($a->games > 0 && $b->games > 0) {
			if( ($a->spirit / $a->games) > ($b->spirit / $b->games)) {
				return 0;
			} else if( ($a->spirit / $a->games) < ($b->spirit / $b->games)) {
				return 1;
			}
		}
		
		/* Next, check +/- */
		if( ($b->points_for - $b->points_against) > ($a->points_for - $a->points_against) ) {
			return 0;
		} else if( ($b->points_for - $b->points_against) > ($a->points_for - $a->points_against) ) {
			return 1;
		}
		
		/* 
		 * Finally, check losses.  This ensures that teams with no record
		 * appear above teams who have losses.
		 */
		if( $a->loss < $b->loss ) {
			return 0;
		} else if( $a->loss > $b->loss ) {
			return 1;
		}
	}
}

function clean_name ( $name )
{
	$expr = array(
		'/\(fall\)\s*$/i',
		'/\(?-?\s*fall\s*(league\s*)?(2004\s*)?\)?$/i',
	);
	
	$name = preg_replace($expr,"",$name);
	return $name;
}

?>
