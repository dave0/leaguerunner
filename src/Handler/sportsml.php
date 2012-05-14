<?php

class sportsml extends Handler
{
	private $league;

	private $need_schedule;
	private $need_standings;

	function __construct ( $what, $id )
	{
		$this->league = League::load( array('league_id' => $id) );
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
		$this->template_name = 'pages/sportsml.tpl';
		$this->smarty->assign('league', $this->league);

		if( $this->need_standings ) {
			$this->smarty->assign('need_standings', true);
			$this->render_standings();
		}
		if( $this->need_schedule ) {
			$this->smarty->assign('need_schedule', true);
			$this->render_schedule();
		}
	}

	function render_standings()
	{
		$s = new Spirit;

		if($this->league->schedule_type == 'none') {
			info_exit("This league does not have a schedule or standings.");
		}

		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $current_round ));
		$teams = array();
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

			$team->standing_points = (2 * $team->win) + $team->tie;
			$team->plusminus       = $team->points_for - $team->points_against;

			if( $this->league->display_numeric_sotg ) {
				if( $team->games > 3 ) {
					$team->numeric_sotg = printf("%.2f", $s->average_sotg( $team->spirit ));
				}
			}

			$teams[] = $team;
		}

		$this->smarty->assign('teams', $teams);
	}

	function render_schedule()
	{

		if($this->league->schedule_type == 'none') {
			info_exit("This league does not have a schedule or standings.");
		}
		$sth = Game::query ( array( 'league_id' => $this->league->league_id, 'published' => 1, '_order' => 'g.game_date, g.game_start, field_code') );

		$currentTime = time();
		$games = array();
		while( $game = $sth->fetchObject('Game') ) {
			$game->event_status = 'pre-event';
			if( $currentTime > $game->timestamp ) {
				$game->event_status = 'post-event';
			}
			$games[] = $game;
		}
		$this->smarty->assign('games', $games);
	}
}
?>
