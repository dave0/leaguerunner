<?php

function sportsml_dispatch()
{
	$op = arg(1);
	$id = arg(2);

	switch($op) {
		case 'standings':
			$obj = new SportsMLStandings;
			break;
		case 'schedule':
			$obj = new SportsMLSchedule;
			break;
		default:
			return null;
	}

	$obj->league = league_load( array('league_id' => $id) );
	if( ! $obj->league ){
		error_exit("That league does not exist");
	}

	return $obj;
}

function sportsml_permissions()
{
	return true;
}

function sportsml_cron()
{
	// TODO: possibly auto-generate some export data here
	return true;
}

class SportsMLStandings extends Handler
{
	var $league;
	
	function has_permission()
	{
		return true;
	}

	function process()
	{
		
		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}

		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $current_round ));

		header("Content-type: text/xml");
		print '<?';
?>
xml version="1.0" encoding="ISO-8859-1"?>
<?php print '<?'?>xml-stylesheet type="text/xsl" href="/leaguerunner/data/ocuasportsml2html.xsl"?>
<sports-content>
  <sports-metadata>
    <sports-title><?php print $this->league->fullname ?></sports-title>
  </sports-metadata>
  <standing content-label="<?php print $this->league->fullname ?>">
    <standing-metadata date-coverage-type="season-regular" date-coverage-value="<?php print $this->league->year ?>" />
<?php
		while(list(,$id) = each($order) ) {
			$team = &$season[$id];
			if( ! $team->rank ) {
				$team->rank = ++$rank;
			}
?>
    <team>
        <team-metadata>
            <name full="<?php print $team->name ?>" />
        </team-metadata>
        <team-stats standing-points="<?php print (2 * $team->win) + $team->tie ?>">
            <outcome-totals wins="<?php print $team->win ?>" losses="<?php print $team->loss ?>" ties="<?php print $team->tie ?>" points-scored-for="<?php print $team->points_for ?>" points-scored-against="<?php print $team->points_against ?>" />
            <team-stats-ultimate>
                <stats-ultimate-spirit value="<?php if( $team->games > 0 ) { printf("%.2f", ($team->spirit / $team->games)); } ?>" />
                <stats-ultimate-miscellaneous defaults="<?php print $team->defaults_against ?>" plusminus="<?php print $team->points_for - $team->points_against ?>" />
            </team-stats-ultimate>
            <rank competition-scope="tier" value="<? print $team->rank ?>" />
        </team-stats>
    </team>
<?php
		}

?>
  </standing>
</sports-content>
<?php
		exit(); // To prevent header/footer being displayed.
	}
}

?>
