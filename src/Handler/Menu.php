<?php

function home_dispatch() 
{
	return new MainMenu;
}

function home_menu() 
{
	menu_add_child('_root','home','Home', array('link' => 'home', 'weight' => '-20'));
}

class MainMenu extends Handler
{
	/* TODO: idea for cleaning this up:
	 *  - implement $MODULENAME_splash hook for each module that returns
	 *  appropriate info for displaying on front page.  Then implement
	 *  team_splash to list teams, league_splash for leagues, etc.
	 *  Also an admin_splash for approving new users
	 */
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
		$this->section = 'home';
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
		global $session;

		$id = $session->attr_get("user_id");
		$this->setLocation(array( $session->attr_get('fullname') => 0 ));
		$header = array(
			array('data' => 'My Teams', 'width' => 90 ),
			array('data' => '&nbsp', 'colspan' => 3 )
		);
		$rows = array();
		
		$rows[] = array('','', array( 'data' => '','width' => 90), '');
		
		$rosterPositions = getRosterPositions();
		$rows = array();
		while(list(,$team) = each($session->user->teams)) {
			$position = $rosterPositions[$team->position];
			
			$rows[] = 
				array(
					array('data' => "$team->name ($team->position)", 
					      'colspan' => 3, 'class' => 'highlight'),
					array('data' => theme_links(array(
							l("info", "team/view/$team->id"),
							l("scores and schedules", "team/schedule/$team->id"),
							l("standings", "league/standings/$team->league_id"))),
						  'align' => 'right', 'class' => 'highlight')
			);
			
			$rows[] = array(
				'&nbsp;', 
				"Last game:",
				array(
					'data' => getPrintableGameData('prev', $team->id),
					'colspan' => 2
				)
			);
			$rows[] = array(
				'&nbsp;', 
				"Next game:",
				array(
					'data' => getPrintableGameData('next', $team->id),
					'colspan' => 2
				)
			);
		}
		reset($session->user->teams);
		
		$teams = "<div class='myteams'>" . table( $header, $rows ) . "</div>";
		if( $session->may_coordinate_league() ) {

			$result = db_query(
				"SELECT 
					league_id AS id, name, allow_schedule, tier
				FROM 
					league 
				WHERE 
					coordinator_id = %d OR (alternate_id <> 1 AND alternate_id = %d)
				ORDER BY name, tier", $id, $id);
				

			if(db_num_rows($result) > 0) {
				$header = array(
					array( 'data' => "Leagues Coordinated", 'colspan' => 4)
				);
				$rows = array();
				
				// TODO: For each league, need to display # of missing scores,
				// pending scores, etc.
				while($league = db_fetch_object($result)) {
					$links = array(
						l("view", "league/view/$league->id"),
						l("edit", "league/edit/$league->id")
					);
					if($league->allow_schedule == 'Y') {
						$links[] = l("schedule", "schedule/view/$league->id");
						$links[] = l("standings", "league/standings/$league->id");
						$links[] = l("approve scores", "league/verifyscores/$league->id");
					}

					$rows[] = array(
						array( 
							'data' => $league->tier ? "$league->name Tier $league->tier" : $league->name, 
							'colspan' => 3
						),
						array(
							'data' => theme_links($links), 
							'align' => 'right'
						)
					);
				}
			}
			$leagues = "<div class='myteams'>" . table( $header, $rows ) . "</div>";
		}
				
		$rows = array(
			array(
				array('data'=> $teams, 'valign' => 'top'),
			),
			array(
				array('data'=> $leagues, 'valign' => 'top'),
			)
		);

		return table(null, $rows);
	}
}

function getPrintableGameData( $which, $teamId )
{
	if($which == 'next') {
		$dateCompare = "s.date_played > NOW()";
		$dateSort = 'asc';
	} else if ($which == 'prev') {
		$dateCompare = "s.date_played < NOW()";
		$dateSort = 'desc';
	} else if ($which == 'today') {
		$dateCompare = "(CURDATE() == DATE(s.date_played))"; 
	}

	$result = db_query(
		"SELECT
			s.game_id, s.home_score, s.away_score,
			DATE_FORMAT(s.date_played, '%%a %%b %%d %%Y %%H:%%i') as date, 
			s.home_team, h.name AS home_name, 
			s.away_team, a.name AS away_name, 
			site.site_id AS site_id, site.code AS site_code, 
			f.num AS site_num
		  FROM schedule s 
		  LEFT JOIN team h ON (h.team_id = s.home_team) 
		  LEFT JOIN team a ON (a.team_id = s.away_team) 
		  LEFT JOIN field f ON (f.field_id = s.field_id) 
		  LEFT JOIN site ON (f.site_id = site.site_id) 
		  WHERE $dateCompare
		    AND ( s.home_team = %d OR s.away_team = %d ) 
		  ORDER BY date_played $dateSort LIMIT 1",$teamId,$teamId);

	if( ! db_num_rows($result) ) {
		return "n/a";
	}

	$game = db_fetch_object($result);

	$data = "$game->date vs. ";
	
	if( $game->home_team == $teamId ) {
		$data .= l($game->away_name, "team/view/$game->away_team");
		if($game->home_score || $game->away_score) {
			$score = " ($game->home_score  - $game->away_score )";
		}
	} else if( $game->away_team == $teamId ) {
		$data .= l($game->home_name, "team/view/$game->home_team");
		if($game->home_score || $game->away_score) {
			$score = " ($game->away_score - $game->home_score )";
		}
	}

	$data .= " at " . l("$game->site_code $game->site_num", "site/view/$game->site_id");

	$data .= $score;

	return $data;
}
?>
