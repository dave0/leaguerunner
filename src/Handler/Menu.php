<?php
register_page_handler('myaccount','MainMenu');
register_page_handler('menu','MainMenu');
register_page_handler('admin','AdminMenu');

class MainMenu extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			"league_admin"  => false,
			"league_create" => false,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'allow'
		);
		$this->op = 'myaccount';
		$this->section = 'myaccount';
		return true;
	}
	
	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} 
	}

	function process ()
	{
		global $session, $DB;
		$id = $session->attr_get("user_id");
		$this->set_title(
			$session->attr_get('firstname') 
			. " " . $session->attr_get('lastname'));

		
		$accountMenu = "<table>";

		/* General Account Info */
		$accountMenu .= tr(th("Account Settings"));
		$accountMenu .= tr(td(
			l("View/Edit My Account", "op=person_view&id=$id")));
		$accountMenu .= tr(td(
			l("Change My Password", "op=person_changepassword&id=$id")));
		$accountMenu .= tr(td(
			l("View/Sign Player Waiver", "op=person_signwaiver")));

		if( $session->attr_get('has_dog') == 'Y' ) {
			$accountMenu .= tr(td(
				l("View/Sign Dog Waiver", "op=person_signdogwaiver")));
		}
				
		$accountMenu .= "</table>";

		$accountMenu = div($accountMenu, array('class' => 'oldmenu'));
		

		$teamsAndLeagues = "<div class='myteams'><table border='0'>";
		$teamsAndLeagues .= tr(th("My Teams", array('colspan' => 4)));
		
		$teams = get_teams_for_user($id);
		if($this->is_database_error($teams)) {
			return false;
		}
		if(count($teams) > 0) {
			$queryString = "SELECT 
					s.game_id, s.home_score, s.away_score,
					DATE_FORMAT(s.date_played, '%a %b %d %Y %H:%i') as date, 
					s.home_team, h.name AS home_name, 
					s.away_team, a.name AS away_name, 
					site.site_id AS site_id, site.code AS site_code, 
					f.num AS site_num
				  FROM schedule s 
				  LEFT JOIN team h ON (h.team_id = s.home_team) 
				  LEFT JOIN team a ON (a.team_id = s.away_team) 
				  LEFT JOIN field f ON (f.field_id = s.field_id) 
				  LEFT JOIN site ON (f.site_id = site.site_id) ";
			foreach($teams as $team) { 
				$nextGame = $DB->getRow( $queryString . " WHERE s.date_played > NOW()
				    AND ( s.home_team = ? OR s.away_team = ? ) 
				  ORDER BY date_played desc LIMIT 1",array($team['id'],$team['id']), DB_FETCHMODE_ASSOC);
				if($this->is_database_error($nextGame)) {
					return false;
				}

				$prevGame = $DB->getRow($queryString . " WHERE s.date_played < NOW() 
				    AND (s.home_team = ? OR s.away_team = ?) 
				  ORDER BY date_played desc LIMIT 1",array($team['id'],$team['id']), DB_FETCHMODE_ASSOC);
				if($this->is_database_error($nextGame)) {
					return false;
				}

				$teamsAndLeagues .= tr(
					td( bold($team['name']) . " (" . $team['position'] . ")", array('colspan' => 3))
				);
				$teamsAndLeagues .= tr(
					td('&nbsp;&nbsp;&nbsp;', array('width' => 25))
					. td(theme_links(array(
						l("info", "op=team_view&id=" . $team['id']),
						l("scores and schedules", "op=team_schedule_view&id=" . $team['id']),
						l("standings", "op=team_standings&id=" . $team['id']))), array('colspan' => 2))
				);
				$teamsAndLeagues .= tr(
					td('&nbsp;&nbsp;&nbsp;', array('width' => 25))
					. td( 
						bold("Most recent game:") . " " . getPrintableGameData($prevGame, $team['id']),
						array('colspan' => '2'))
				);
				$teamsAndLeagues .= tr(
					td('&nbsp;&nbsp;&nbsp;', array('width' => 25))
					. td( 
						bold("Next game:") . " " . getPrintableGameData($nextGame, $team['id']),
						array('colspan' => '2'))
				);
			}
		}

		if( $session->may_coordinate_league() ) {
			/* Fetch leagues coordinated */
			$leagues = $DB->getAll("
				SELECT 
					league_id AS id, name, allow_schedule, tier
				FROM 
					league 
				WHERE 
					coordinator_id = ? OR (alternate_id <> 1 AND alternate_id = ?)
				ORDER BY name, tier",
				array($id,$id), 
				DB_FETCHMODE_ASSOC);
				
			if($this->is_database_error($leagues)) {
				return false;
			}

			if(count($leagues) > 0) {
				$teamsAndLeagues .= tr(th("Leagues Coordinated", array('colspan' => 3)));
				$data = "<table border='0' cellpadding='3' cellspacing='0'>";
				// TODO: For each league, need to display # of missing scores,
				// pending scores, etc.
				foreach($leagues as $league) {
					$name = $league['name'];
					if($league['tier']) {
						$name .= " Tier " . $league['tier'];
					}
					$data .= "<tr><td>$name</td>";
					$links = array(
						l("view", "op=league_view&id=" . $league['id']),
						l("edit", "op=league_edit&id=" . $league['id'])
					);
					if($league['allow_schedule'] == 'Y') {
						$links[] = l("schedule", "op=league_schedule_view&id=" . $league['id']);
						$links[] = l("standings", "op=league_standings&id=" . $league['id']);
						$links[] = l("approve scores", "op=league_verifyscores&id=" . $league['id']);
					}
					$data .= "<td>" . theme_links($links) . "</td></tr>";
				}
				$data .= "</table>";
				$teamsAndLeagues .= tr(td( $data, array('colspan' => '3' )));
			}
		}
				
		$teamsAndLeagues .= "</table></div>";

		$output = "<table border='0' cellpadding='0' cellspacing='2' width='100%'>";
		$output .= tr(
			td($accountMenu, array('align' => 'left', 'valign' => 'top'))
			. td($teamsAndLeagues, array('align' => 'left', 'valign' => 'top')));
		$output .= "</table>";
		
		
		return $output;
	}
}

class AdminMenu extends Handler
{
	function initialize ()
	{
		$this->set_title("Admin Tools");

		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		$this->op = 'admin';
		$this->section = 'admin';
		return true;
	}

	function process ()
	{
		global $DB;
		$newUsers = $DB->getOne("SELECT COUNT(*) FROM person WHERE class = 'new'");
		if($this->is_database_error($newUsers)) {
			return false;
		}
				
		$links = array(
			l("List City Wards", "op=ward_list"),
			l("Approve New Accounts", "op=person_listnew") . " ($newUsers awaiting approval)",
		);
		$left = "<table>";
		$left .= tr(th("Admin Tools"));
		while(list(,$link) = each($links)) {
			$left .= tr(td( $link ));
		}
		$left .= "</table>";

		$left = div($left, array('class' => 'oldmenu'));
		
		$right = para("Administrative tools for managing the league are available here.");
		
		$output = "<table border='0' cellpadding='0' cellspacing='2'>";
		$output .= tr(
			td($left, array('align' => 'left', 'valign' => 'top'))
			. td($right, array('align' => 'left', 'valign' => 'top')));
		$output .= "</table>";
		
		return $output;
	}
}

function getPrintableGameData( &$game, $teamId )
{
	if(count($game) < 1) {
		return "n/a";
	}

	$data = $game['date'];
	$data .= " vs. ";
	if( $game['home_team'] == $teamId ) {
		$data .= l($game['away_name'], 'op=team_view&id=' . $game['away_team']);
		if($game['home_score'] || $game['away_score']) {
			$score = " (" . $game['home_score'] . " - " . $game['away_score'] . ")";
		}
	} else if( $game['away_team'] == $teamId ) {
		$data .= l($game['home_name'], 'op=team_view&id=' . $game['home_team']);
		if($game['home_score'] || $game['away_score']) {
			$score = " (" . $game['away_score'] . " - " . $game['home_score'] . ")";
		}
	}

	$data .= " at " . l($game['site_code'] . " " . $game['site_num'], 'op=site_view&id=' . $game['site_id']);

	$data .= $score;

	return $data;
}
?>
