<?php

class sportsml extends Handler
{
	private $league;

	private $need_schedule;
	private $need_standings;

	function __construct ( $what, $id )
	{
		$this->league = league_load( array('league_id' => $id) );
		if( ! $this->league ){
			error_exit("That league does not exist");
		}

		if( $what == 'schedule' || $what == 'combined' ) {
			$this->need_schedule = true;
		}

		if( $what == 'standings' || $what == 'combined' ) {
			$this->need_standings = true;
		}
	}

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
		if( $this->need_standings ) {
			$this->render_standings();
		}
		if( $this->need_schedule ) {
			$this->render_schedule();
		}
		$this->render_footer();
		exit(); // To prevent header/footer being displayed.
	}

	function render_header( $type = 'html' )
	{
		global $CONFIG;

		header("Content-type: text/xml");
		print '<?';
?>
xml version="1.0" encoding="ISO-8859-1"?>
<?php
		print '<?xml-stylesheet type="text/xsl" href="';
		if( $type == 'text') {
			print $CONFIG['paths']['base_url'] . "/data/ocuasportsml2text.xsl";
		} else {
			print $CONFIG['paths']['base_url'] . "/data/ocuasportsml2html.xsl";
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
    <sports-title><?php print htmlspecialchars($this->league->fullname) ?></sports-title>
  </sports-metadata>
<?php
	}

	function render_footer()
	{
		print  "\n</sports-content>\n";
	}

	function render_standings()
	{
		$s = new Spirit;
		$s->entry_type = $this->league->enter_sotg;

		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}

		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $current_round ));
?>
  <standing content-label="<?php print htmlspecialchars($this->league->fullname) ?>">
    <standing-metadata date-coverage-type="season-regular" date-coverage-value="<?php print $this->league->year ?>" />
<?php
		while(list(,$id) = each($order) ) {
			$team = &$season[$id];

			switch( $this->league->schedule_type ) {
				case 'ratings_ladder':
				case 'ratings_wager_ladder':
					$team->rank = $team->rating;
					break;
				default:
					$team->rank = ++$rank;
			}
?>
    <team>
        <team-metadata>
            <name full="<?php print htmlspecialchars($team->name) ?>" />
        </team-metadata>
        <team-stats standing-points="<?php print (2 * $team->win) + $team->tie ?>">
            <outcome-totals wins="<?php print $team->win ?>" losses="<?php print $team->loss ?>" ties="<?php print $team->tie ?>" points-scored-for="<?php print $team->points_for ?>" points-scored-against="<?php print $team->points_against ?>" />
            <team-stats-ultimate>
<?php if( $this->league->display_numeric_sotg() ) { ?>
                <stats-ultimate-spirit value="<?php if( $team->games > 3 ) { printf("%.2f", $s->average_sotg($team->spirit)); } ?>" />
<?php } ?>
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
  <schedule content-label="<?php print htmlspecialchars($this->league->fullname) ?>">
    <schedule-metadata team-coverage-type="multi-team" date-coverage-type='season-regular' date-coverage-value="<?php print $this->league->year ?>" />
<?php
		$sth = game_query ( array( 'league_id' => $this->league->league_id, 'published' => 1, '_order' => 'g.game_date, g.game_start, field_code') );

		$currentTime = time();
		while( $game = $sth->fetchObject('Game') ) {
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
            	<name full="<?php print htmlspecialchars($game->home_name) ?>" />
        	</team-metadata>
        	<team-stats score="<?php print $game->home_score ?>" />
		</team>
		<team>
        	<team-metadata alignment="away">
            	<name full="<?php print htmlspecialchars($game->away_name) ?>" />
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
?>
