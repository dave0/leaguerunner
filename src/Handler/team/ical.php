<?php
require_once('Handler/TeamHandler.php');

class team_ical extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view schedule', $this->team->team_id);
	}

	function process ()
	{
		global $CONFIG;

		$this->template_name = 'pages/team/ical.tpl';

		$this->smarty->assign('team', $this->team);
		$this->smarty->assign('short_league_name', variable_get('app_org_short_name', 'League'));
		$this->smarty->assign('timezone', $CONFIG['localization']['local_tz']);

		/*
		 * Grab schedule info
		 */
		$games = game_load_many( array( 'either_team' => $this->team->team_id, 'published' => 1, '_order' => 'g.game_date DESC,g.game_start,g.game_id') );

		// We'll be outputting an ical
		header('Content-type: text/calendar; charset=UTF-8');
		// Prevent caching
		header("Cache-Control: no-cache, must-revalidate");

		// get domain URL for signing games
		$arr = explode('@',variable_get('app_admin_email',"@$short_league_name"));
		$this->smarty->assign('domain_url', $arr[1]);

		// date stamp this file
		// MUST be in UTC
		$this->smarty->assign('now', gmstrftime('%Y%m%dT%H%M%SZ'));

		while(list(,$game) = each($games)) {
			$opponent_id = ($game->home_id == $this->team->team_id) ? $game->away_id : $game->home_id;
			$game->opponent = team_load( array('team_id' => $opponent_id) );

			$game->field = field_load(array('fid' => $game->fid));
		}

		$this->smarty->assign('games', $games);
	}
}
?>
