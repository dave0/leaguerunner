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
	function has_permission()
	{
		return true;
	}
	
	function process ()
	{
		$this->title = "Standings";
		$season = arg(2);
		if( !$season ) {
			$season = variable_get('current_season','summer');
		}
		header( "Content-type: text/plain\n\n");

		$year = variable_get('current_year', '2004');

		print variable_get('app_org_short_name', '') . " $season $year Standings\n";
		print "Current as of: " . strftime("%c") . "\n\n";
		
		$result = db_query("SELECT distinct league_id from league where season = '%s'", $season);
		while( $foo = db_fetch_array($result)) {
			$id = $foo['league_id'];
			$league = league_load( array('league_id' => $id) );
			
			if($league->schedule_type == 'none') {
				continue;
			}
	
			$league->load_teams();
			if(count($league->teams) == 0) {
				continue;
			}

			print $league->fullname . "\n";
			
			print $this->generate_standings($league, 0);

			print "\n";
		}

		# To prevent HTML output.
		exit(0);
	}

	function generate_standings ($league)
	{
		global $lr_session;
		$league->load_teams();
		
		list($order, $season, $round) = $league->calculate_standings(array( 'round' => 'all' ));

		$output .= sprintf("%4.4s\t%-30.30s\t%2.2s\t%2.2s\t%2.2s\t%4.4s\t%3.3s\t%3.3s\t%3.3s\t%5.5s\n",
			"Rank","Name","W","L","T","Dfl","PF","PA","+/-","SOTG");
		
		while(list(, $id) = each($order)) {
			$team = &$season[$id];

			if( ! $team->rank ) {
				$team->rank = ++$rank;
			}
			
			if ($team->games > 3) {
				$sotg = sprintf("%.2f", ($team->spirit / $team->games));
			} else {
				$sotg = "---";
			}
			$output .= sprintf("% 4d\t%-30.30s\t% 2d\t% 2d\t% 2d\t% 4d\t% 3d\t% 3d\t% 3d\t%5.5s\n",
				$team->rank, 
				clean_name($team->name),
				$team->win,
				$team->loss,
				$team->tie,
				$team->defaults_against,
				$team->points_for,
				$team->points_against,
				$team->points_for - $team->points_against,
				$sotg);
		}
		
		return $output;
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
