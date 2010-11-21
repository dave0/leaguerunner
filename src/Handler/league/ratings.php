<?php
require_once('Handler/LeagueHandler.php');
class league_ratings extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','ratings', $this->league->league_id);
	}

	function process ()
	{
		$this->title = "{$this->league->fullname} &raquo; Ratings Adjustment";
		$this->template_name = 'pages/league/ratings.tpl';

		$edit = &$_POST['edit'];

		// make sure the teams are loaded
		$this->league->load_teams();

		if($edit['step'] == 'perform') {
			$this->perform($edit);
			local_redirect(url("league/view/" . $this->league->league_id));
		}

		// TODO: replace with a load_teams_ordered() or maybe a flag to load_teams() ?
		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));
		$this->smarty->assign('teams', $season);

		return true;
	}

	function perform ( $edit )
	{
		global $dbh;

		// TODO:  Move this logic to a function inside the league.inc file
		$sth = $dbh->prepare('UPDATE team SET rating = ? WHERE team_id = ?');
		// go through what was submitted
		foreach ($edit as $team_id => $rating) {
			if (is_numeric($team_id) && is_numeric($rating)) {
				// update the database
				$sth->execute( array( $rating, $team_id ) );
			}
		}

		return true;
	}
}

?>
