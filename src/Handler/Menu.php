<?php
register_page_handler('home','MainMenu');
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
		$this->op = 'home';
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
		$this->setLocation(array( $session->attr_get('firstname') . " " . $session->attr_get('lastname') => 0 ));

	
		/* TODO: this menu should go away in favour of an integrated menu
		 * system */
		$header = array( "Account Settings" );
		$rows = array();	

		$rows[] = array(l("View/Edit My Account", "op=person_view&id=$id"));
		$rows[] = array(
			l("Change My Password", "op=person_changepassword&id=$id"));

		$rows[] = array( 
			l("View/Sign Player Waiver", "op=person_signwaiver"));
			
		if( $session->attr_get('has_dog') == 'Y' ) {
			$rows[] = array( 
				l("View/Sign Dog Waiver", "op=person_signdogwaiver"));
		}

		/* TODO: This should be a config option */
		$signupTime = mktime(9,0,0,10,22,2003);
		if(	time() >= $signupTime) {
			$rows[] = array( 
				l("Winter Indoor Signup", "op=wlist_viewperson&id=$id"));
		} else {
			$rows[] = array("Indoor Signup opens<br/>" . date("F j Y h:i A", $signupTime));
		}

		$accountMenu = "<div class='oldmenu'>" . table( $header, $rows ) . "</div>";

		$header = array(
			array('data' => 'My Teams', 'width' => 90 ),
			array('data' => '&nbsp', 'colspan' => 3 )
		);
		$rows = array();
		
		$rows[] = array('','', array( 'data' => '','width' => 90), '');
		
		$result = db_query(
			"SELECT 
				r.status AS position,
				r.team_id AS id,
				t.name AS name
			FROM 
				teamroster r LEFT JOIN team t USING(team_id)
			WHERE 
				r.player_id = %d", $id);
			
		$rosterPositions = getRosterPositions();

		$rows = array();
		while($team = db_fetch_object($result)) {
			$position = $rosterPositions[$team->position];
			
			$rows[] = 
				array(
					array('data' => "$team->name ($team->position)", 
					      'colspan' => 3, 'class' => 'highlight'),
					array('data' => theme_links(array(
							l("info", "op=team_view&id=$team->id"),
							l("scores and schedules", "op=team_schedule_view&id=$team->id"),
							l("standings", "op=team_standings&id=$team->id"))),
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
						l("view", "op=league_view&id=$league->id"),
						l("edit", "op=league_edit&id=$league->id")
					);
					if($league->allow_schedule == 'Y') {
						$links[] = l("schedule", "op=league_schedule_view&id=$league->id");
						$links[] = l("standings", "op=league_standings&id=$league->id");
						$links[] = l("approve scores", "op=league_verifyscores&id=$league->id");
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
				array('data'=> $accountMenu, 'valign' => 'top', 'rowspan' => 2),
				array('data'=> $teams, 'valign' => 'top'),
			),
			array(
				array('data'=> $leagues, 'valign' => 'top'),
			)
		);

		return table(null, $rows);
	}
}

class AdminMenu extends Handler
{
	function initialize ()
	{
		$this->setLocation(array("Admin Tools" => 0));

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
		$result = db_query("SELECT COUNT(*) FROM person WHERE status = 'new'");
		$newUsers = db_result($result);
				
		$links = array(
			array(l("List City Wards", "op=ward_list")),
			array(l("Approve New Accounts", "op=person_listnew") . " ($newUsers awaiting approval)"),
			array(l("Create New Waiting List", "op=wlist_create")),
			array(l("List Waiting Lists", "op=wlist_list")),
		);

		$header = array ("Admin Tools");

		$left = "<div class='oldmenu'>" . table($header, $links) . "</div>";
		$right = para("Administrative tools for managing the league are available here.");
		
		$rows = array(
			array(
				array('data'=> $left, 'valign' => 'top'),
				array('data'=> $right, 'valign' => 'top'),
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
		$data .= l($game->away_name, "op=team_view&id=$game->away_team");
		if($game->home_score || $game->away_score) {
			$score = " ($game->home_score  - $game->away_score )";
		}
	} else if( $game->away_team == $teamId ) {
		$data .= l($game->home_name, "op=team_view&id=$game->home_team");
		if($game->home_score || $game->away_score) {
			$score = " ($game->away_score - $game->home_score )";
		}
	}

	$data .= " at " . l("$game->site_code $game->site_num", "op=site_view&id=$game->site_id");

	$data .= $score;

	return $data;
}
?>
