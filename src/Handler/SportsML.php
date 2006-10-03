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
		case 'combined':
			$obj = new SportsMLCombined;
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

class SportsMLExporter extends Handler
{
	var $league;
	
	function has_permission()
	{
		return true;
	}

	function render_header( $type = 'html' )
	{
		header("Content-type: text/xml");
		print '<?';
?>
xml version="1.0" encoding="ISO-8859-1"?>
<?php 
		print '<?xml-stylesheet type="text/xsl" href="';
		if( $type == 'text') {
			print '/leaguerunner/data/ocuasportsml2text.xsl';
		} else {
			print '/leaguerunner/data/ocuasportsml2html.xsl';
		}
		print "\" ?>\n";
?>
<sports-content>
<?php
	}

	function render_metadata()
	{
?>
  <sports-metadata>
    <sports-title><?php print $this->league->fullname ?></sports-title>
  </sports-metadata>
<?php
	}

	function render_footer()
	{
		print  "\n</sports-content>\n";
	}

	function render_standings()
	{
		
		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}

		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $current_round ));
?>
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
                <stats-ultimate-spirit value="<?php if( $team->games > 3 ) { printf("%.2f", ($team->spirit / $team->games)); } ?>" />
                <stats-ultimate-miscellaneous defaults="<?php print $team->defaults_against ?>" plusminus="<?php print $team->points_for - $team->points_against ?>" />
            </team-stats-ultimate>
            <rank competition-scope="tier" value="<? print $team->rank ?>" />
        </team-stats>
    </team>
<?php
		}

?>
  </standing>
<?php
	}
	
	function render_schedule()
	{
		
		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}
?>
  <schedule content-label="<?php print $this->league->fullname ?>">
    <schedule-metadata team-coverage-type="multi-team" date-coverage-type='season-regular' date-coverage-value="<?php print $this->league->year ?>" />
<?php
		$result = game_query ( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date, g.game_start, field_code') );

		$currentTime = time();
		while( $ary = db_fetch_array($result) ) {
			$game = new Game;
			$game->load_from_query_result($ary);
			$event_status = 'pre-event';
			if( $currentTime > $game->timestamp ) {
				$event_status = 'post-event';
			}
?>
    <sports-event>
		<event-metadata
			site-name="<?php print $game->field_code; ?>"
			site-id="<?php print $game->field_code; ?>"
			start-date-time="<?php print strftime("%Y-%m-%dT%H:%M", $game->timestamp); ?>"
			event-status="<?php print $event_status ?>"
		/>
		<team>
        	<team-metadata alignment="home">
            	<name full="<?php print $game->home_name ?>" />
        	</team-metadata>
        	<team-stats score="<?php print $game->home_score ?>" />
		</team>
		<team>
        	<team-metadata alignment="away">
            	<name full="<?php print $game->away_name ?>" />
        	</team-metadata>
        	<team-stats score="<?php print $game->away_score ?>" />
		</team>
	</sports-event>
<?php
		}

?>
  </schedule>
<?php
	}
}


class SportsMLStandings extends SportsMLExporter
{
	var $league;
	
	function has_permission()
	{
		return true;
	}

	function process()
	{
		$type = $_GET['type'];
		if ($type != 'text') {
			$type = 'html';
		}
		$this->render_header($type);
		$this->render_metadata();
		$this->render_standings();
		$this->render_footer();
		exit(); // To prevent header/footer being displayed.
	}
}

class SportsMLSchedule extends SportsMLExporter
{
	var $league;
	
	function has_permission()
	{
		return true;
	}
	
	function process()
	{
		$type = $_GET['type'];
		if ($type != 'text') {
			$type = 'html';
		}
		$this->render_header($type);
		$this->render_metadata();
		$this->render_schedule();
		$this->render_footer();
		exit(); // To prevent header/footer being displayed.
	}

}

class SportsMLCombined extends SportsMLExporter
{
	var $league;
	
	function has_permission()
	{
		return true;
	}
	
	function process()
	{
		$type = $_GET['type'];
		if ($type != 'text') {
			$type = 'html';
		}
		$this->render_header($type);
		$this->render_metadata();
		$this->render_standings();
		$this->render_schedule();
		$this->render_footer();
		exit(); // To prevent header/footer being displayed.
	}

}


?>
