<?php
register_page_handler('league_standings', 'LeagueStandings');

/**
 * League standings handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class LeagueStandings extends Handler
{
	/** 
	 * Initializer for LeagueStandings class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("View League Standings");
		$this->_permissions = array(
			"view_spirit" => false,
			"view_team" => false,
		);

		return true;
	}

	/**
	 * Check if the current session has permission to view standings
	 *
	 * check that the session is valid (return false if not)
	 *
	 * @access public
	 * @return boolean success/fail
	 */
	function has_permission ()
	{
		global $DB, $session, $id;

		if(!$session->is_valid()) {
			/* Allow not-logged-in users to view standings but not link to the
			 * teams.
			 */
			$this->_permissions['view_team'] = false;
			return true;
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide a league ID");
			return false;
		}
		
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}

		/* TODO: set view_spirit if:
		 * 	- coordinator or co-coord
		 */

		$this->_permissions['view_team'] = true;
		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->set_template_file("League/standings.tmpl");
	
		$row = $DB->getRow(
			"SELECT 
				l.name,
				l.tier,
				l.ratio,
				l.season,
				l.current_round,
				l.stats_display
			FROM 
				league l
			WHERE 
				l.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign("league_id", $id);
		$this->tmpl->assign("league_name",   $row['name']);
		$this->tmpl->assign("league_tier",   $row['tier']);
		$this->tmpl->assign("league_ratio",  $row['ratio']);
		$this->tmpl->assign("league_season", $row['season']);
		$this->tmpl->assign("league_current_round", $row['current_round']);

		$round = var_from_getorpost('round');
		if(! isset($round) ) {
			$round = $row['current_round'];
		}
		
		/* ... and set permissions flags */
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		$this->tmpl->assign("current_round", $round);
		
		/* Now, crunch the stats */
		return $this->fill_standings($id, $round);
	}
	
	function fill_standings ($id, $current_round)
	{
		global $DB;
		$teams = $DB->getAll(
				"SELECT
					lt.team_id AS id,
					t.name
				FROM
					leagueteams lt, 
					team t
				WHERE
					lt.team_id = t.team_id
					AND league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($teams)) {
			return false;
		}

		$season = array();
		$round  = array();
		
		$this->init_season_array($season, $teams);
		$this->init_season_array($round, $teams);

		/* Now, fetch the schedule.  Get all games played by anyone who is
		 * currently in this league, regardless of whether or not their
		 * opponents are still here
		 */
		$games = $DB->getAll(
			"SELECT DISTINCT
				s.game_id, 
				s.home_team, 
				s.away_team, 
				s.home_score, 
				s.away_score,
				s.home_spirit, 
				s.away_spirit,
				s.round,
				s.defaulted
			 FROM
			  	schedule s, leagueteams t
			 WHERE 
				t.league_id = ?
				AND (s.home_team = t.team_id OR s.away_team = t.team_id)
		 		ORDER BY s.game_id",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($games)) {
			return false;
		}

		while(list(,$game) = each($games)) {
			if(is_null($game['home_score']) || is_null($game['away_score'])) {
				/* Skip unscored games */
				continue;
			}
			$this->record_game($season, $game);
			if($current_round == $game['round']) {
				$this->record_game($round, $game);
			}
		}

		/* Now, sort it all */
		if($current_round > 0) {
			uasort($round, array($this, 'sort_standings'));	
			$sorted_order = &$round;
		} else {
			uasort($season, array($this, 'sort_standings'));	
			$sorted_order = &$season;
		}

		/* and display */
		$standings = array();
		while(list(, $data) = each($sorted_order)) {
			$id = $data['id'];
			$srow = $season[$id];
			if($season[$id]['games'] > 1) {
				$srow['sotg'] = sprintf("%.2f", $season[$id]['spirit'] / ($season[$id]['games'] - ($season[$id]['defaults_for'] + $season[$id]['defaults_against'])));
				
			} else {
				$srow['sotg'] = "---";
			}
			$srow['plusminus'] = $srow['points_for'] - $srow['points_against'];
			
			/* TODO: round standings */
			$srow['round_win'] = $round[$id]['win'];
			$srow['round_loss'] = $round[$id]['loss'];
			$srow['round_tie'] = $round[$id]['tie'];
			$srow['round_defaults_against'] = $round[$id]['defaults_against'];
			$srow['round_defaults_for'] = $round[$id]['defaults_for'];
			$srow['round_points_for'] = $round[$id]['points_for'];
			$srow['round_points_against'] = $round[$id]['points_against'];
			$srow['round_plusminus'] = $round[$id]['points_for'] - $round[$id]['points_against'];

			$standings[] = $srow;
		}
		$this->tmpl->assign("standings", $standings);
		$this->tmpl->assign("want_round", true);
		return true;
	}

	/*
	 * Initialise an empty array of season info
	 */
	function init_season_array(&$season, &$teams) 
	{
		while(list(,$team) = each($teams)) {
			$season[$team['id']] = array(
				'name' => $team['name'],
				'id' => $team['id'],
				'points_for' => 0,
				'points_against' => 0,
				'spirit' => 0,
				'win' => 0,
				'loss' => 0,
				'tie' => 0,
				'defaults_for' => 0,
				'defaults_against' => 0,
				'games' => 0,
				'vs' => array()
			);
		}
		reset($teams);

	}

	function record_game(&$season, &$game)
	{
	
		if(isset($season[$game['home_team']])) {
			$data = &$season[$game['home_team']];
			
			$data['games']++;
			$data['points_for'] += $game['home_score'];
			$data['points_against'] += $game['away_score'];

			/* Need to initialize if not set */
			if(!isset($data['vs'][$game['away_team']])) {
				$data['vs'][$game['away_team']] = 0;
			}
			
			if($game['defaulted'] == 'home') {
				$data['defaults_against']++;
			} else if($game['defaulted'] == 'away') {
				$data['defaults_for']++;
			} else {
				$data['spirit'] += $game['home_spirit'];
			}

			if($game['home_score'] == $game['away_score']) {
				$data['tie']++;
				$data['vs'][$game['away_team']]++;
			} else if($game['home_score'] > $game['away_score']) {
				$data['win']++;
				$data['vs'][$game['away_team']] += 2;
			} else {
				$data['loss']++;
				$data['vs'][$game['away_team']] += 0;
			}
		}
		if(isset($season[$game['away_team']])) {
			$data = &$season[$game['away_team']];
			
			$data['games']++;
			$data['points_for'] += $game['away_score'];
			$data['points_against'] += $game['home_score'];

			/* Need to initialize if not set */
			if(!isset($data['vs'][$game['home_team']])) {
				$data['vs'][$game['home_team']] = 0;
			}
			
			if($game['defaulted'] == 'away') {
				$data['defaults_against']++;
			} else if($game['defaulted'] == 'home') {
				$data['defaults_for']++;
			} else {
				$data['spirit'] += $game['away_spirit'];
			}

			if($game['away_score'] == $game['home_score']) {
				$data['tie']++;
				$data['vs'][$game['home_team']]++;
			} else if($game['away_score'] > $game['home_score']) {
				$data['win']++;
				$data['vs'][$game['home_team']] += 2;
			} else {
				$data['loss']++;
				$data['vs'][$game['home_team']] += 0;
			}
		}
	}


	/**
	 * TODO Finish me!
	 */
	function sort_standings ($a, $b) 
	{
		$b_points = (( 2 * $b['win'] ) + $b['tie']);
		$a_points = (( 2 * $a['win'] ) + $a['tie']);
		$rc = cmp($b_points, $a_points);  /* B first, as we want descending */
#		if($rc != 0) {
			return $rc;
#		}
	}
}

/*
 * Fucking php doesn't have the Perlish comparisons of cmp and <=>
 * Grr.
 */
function cmp ($a, $b) 
{
	if($a > $b) {
		return 1;
	}
	if($a < $b) {
		return -1;
	}
	return 0;
}



?>
