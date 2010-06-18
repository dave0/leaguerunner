<?php
require_once('Handler/LeagueHandler.php');
class league_view extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view',$this->league->league_id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = $this->league->fullname;
		$this->template_name = 'pages/league/view.tpl';

		$this->smarty->assign('league', $this->league);

		$this->league->load_teams();

		if( count($this->league->teams) > 0 ) {
			// TODO: replace with a load_teams_ordered() or maybe a flag to load_teams() ?
			list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));
			$this->smarty->assign('teams', $season);
		}

		return true;
	}
}

?>
