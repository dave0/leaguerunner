<?php
require_once('Handler/LeagueHandler.php');
class league_standings extends LeagueHandler
{
	private $teamid;

	function __construct ( $id, $teamid = null )
	{
		parent::__construct( $id );
		$this->teamid  = $teamid;
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view', $this->league->league_id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "{$this->league->fullname} &raquo; Standings";

		$this->template_name = 'pages/league/standings.tpl';

		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}

		$s = new Spirit;
		$s->entry_type           = $this->league->enter_sotg;

		$round = $_GET['round'];
		if(! isset($round) ) {
			$round = $this->league->current_round;
		}
		// check to see if this league is on round 2 or higher...
		// if so, set the $current_round so that the standings table is split up
		if ($round > 1) {
			$current_round = $round;
		}

		// TODO: calculate_standings should set the ->round_XXX values on each team object
		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $current_round ));

		$teams = array();
		$seed = 1;
		while(list(, $tid) = each($order)) {

			$team = $season[$tid];
			$team->seed = $seed++;

			// Don't need the current round for a ladder schedule.
			if ($this->league->schedule_type == "roundrobin") {
				if($current_round) {
					$team->round_win = $round[$tid]->win;
					$team->round_loss = $round[$tid]->loss;
					$team->round_tie = $round[$tid]->tie;
					$team->round_defaults_against = $round[$tid]->defaults_against;
					$team->round_points_for = $round[$tid]->points_for;
					$team->round_points_against = $round[$tid]->points_against;
				}
			}

			// TODO: should be a helper on the Team object
			if( count($team->streak) > 1 ) {
				$team->display_streak = count($team->streak) . $team->streak[0];
			} else {
				$team->display_streak = '-';
			}

			$team->sotg_average = $s->average_sotg( $team->spirit, false);
			$team->sotg_image   = $s->full_spirit_symbol_html( $team->sotg_average );

			$teams[] = $team;
		}

		$this->smarty->assign('league', $this->league);
		$this->smarty->assign('teams', $teams);
		$this->smarty->assign('highlight_team', $this->teamid);

		return true;
	}
}

?>
