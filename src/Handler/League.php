<?php
/* 
 * Handle operations specific to leagues
 */

function league_dispatch() 
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'create':
			return new LeagueCreate;
		case 'list':
			return new LeagueList;
		case 'edit':
			$obj = new LeagueEdit;
			break;
		case 'view':
			$obj = new LeagueView;
			break;
		case 'standings':
			$obj = new LeagueStandings;
			break;
		case 'captemail':
			$obj = new LeagueCaptainEmails;
			break;
		case 'moveteam':
			$obj = new LeagueMoveTeam;
			break;
		case 'approvescores':
			$obj = new LeagueApproveScores;
			break;
		case 'member':
			$obj = new LeagueMemberStatus;
			break;
		case 'ladder':
			$obj = new LeagueLadder;
			break;
		case 'admin':
			$obj = new LeagueAdmin;
			break;
	}

	$obj->league = league_load( array('league_id' => $id) );
	if( ! $obj->league ){
		error_exit("That league does not exist");
	}
	league_add_to_menu($obj->league);
	return $obj;
}

function league_permissions( $user, $action, $id, $data_field = '' )
{
	// TODO: finish this!
	if( !$user ) {
		return false;
	}
	switch($action)
	{
		case 'view':
			switch($data_field) {
				case 'spirit':
					return $user->is_coordinator_of($id);
				case 'captain emails':
					return $user->is_coordinator_of($id);
				default:
					return true;
			}
			break;
		case 'list':
			return ($user->is_player());
		case 'edit':
		case 'edit game':
		case 'add game':
		case 'approve scores':
		case 'administer ladder':
		case 'edit schedule':
		case 'manage teams':
		case 'ladder seed':
			return ($user->is_coordinator_of($id));
		case 'create':
		case 'delete':
		case 'unsafe admin':
			// admin only
			break;
	}
	return false;
}

function league_menu()
{
	global $session;

	if( !$session->is_player() ) {
		return;
	}
	
	menu_add_child('_root','league','Leagues');
	menu_add_child('league','league/list','list leagues', array('link' => 'league/list') );
	if( $session->is_valid() ) {
		while(list(,$league) = each($session->user->leagues) ) {
			league_add_to_menu($league);
		}
		reset($session->user->leagues);
	}
}

/**
 * Add view/edit/delete links to the menu for the given league
 */
function league_add_to_menu( &$league, $parent = 'league' ) 
{
	global $session;

	menu_add_child($parent, $league->fullname, $league->fullname, array('weight' => -10, 'link' => "league/view/$league->league_id"));
	
	if($league->schedule_type == 'ladder' || $league->schedule_type == 'roundrobin') {
		menu_add_child($league->fullname, "$league->fullname/standings",'standings', array('weight' => -1, 'link' => "league/standings/$league->league_id"));
		menu_add_child($league->fullname, "$league->fullname/schedule",'schedule', array('weight' => -1, 'link' => "schedule/view/$league->league_id"));
		if($session->has_permission('league','add game', $league->league_id) ) {
			menu_add_child("$league->fullname/schedule", "$league->fullname/schedule/edit", 'add games', array('link' => "game/create/$league->league_id"));
		} 
		if($session->has_permission('league','approve scores', $league->league_id) ) {
			menu_add_child($league->fullname, "$league->fullname/approvescores",'approve scores', array('weight' => 1, 'link' => "league/approvescores/$league->league_id"));
		}
	}
	
	if($league->schedule_type == 'ladder') {
		if($session->has_permission('league','administer ladder', $league->league_id) ) {

			menu_add_child($league->fullname, "$league->fullname/admin", 
                               'league admin', array('link' => "league/admin/top/$league->league_id"));

			menu_add_child($league->fullname, "$league->fullname/ladder", 
                               'seed ladder', array('link' => "league/ladder/$league->league_id"));

			menu_add_child($league->fullname . "/admin/ladder", "$league->fullname/admin/ladder/byskill", 
                               'seed by average skill', array('link' => "league/ladder/$league->league_id" . "/byskill"));
		}
		menu_add_child($league->fullname, "$league->fullname/graph/rank",'graph ranks', array('weight' => 4, 'link' => "graph/leaguerank/$league->league_id"));
	}
	
	if($session->has_permission('league','edit', $league->league_id) ) {
		menu_add_child($league->fullname, "$league->fullname/edit",'edit league', array('weight' => 1, 'link' => "league/edit/$league->league_id"));
		menu_add_child($league->fullname, "$league->fullname/member",'add coordinator', array('weight' => 2, 'link' => "league/member/$league->league_id"));
	}
	if($session->has_permission('league','view', $league->league_id, 'captain emails') ) {
		menu_add_child($league->fullname, "$league->fullname/captemail",'captain emails', array('weight' => 3, 'link' => "league/captemail/$league->league_id"));
	}
	if($session->has_permission('league','create') ) {
		menu_add_child('league', 'league/create', "create league", array('link' => "league/create", 'weight' => 1));
	}
}

/**
 * Generate view of leagues for initial login splash page.
 */
function league_splash ()
{
	global $session;
	if( ! $session->user->is_a_coordinator ) {
		return;
	}

	$header = array(
			array( 'data' => "Leagues Coordinated", 'colspan' => 4)
	);
	$rows = array();
			
	// TODO: For each league, need to display # of missing scores,
	// pending scores, etc.
	while(list(,$league) = each($session->user->leagues)) {
		$links = array(
			l("edit", "league/edit/$league->league_id")
		);
		if($league->schedule_type != 'none') {
			$links[] = l("schedule", "schedule/view/$league->league_id");
			$links[] = l("standings", "league/standings/$league->league_id");
			$links[] = l("approve scores", "league/approvescores/$league->league_id");
		}

		$rows[] = array(
			array( 
				'data' => l($league->fullname, "league/view/$league->league_id"),
				'colspan' => 3
			),
			array(
				'data' => theme_links($links), 
				'align' => 'right'
			)
		);
	}
	reset($session->user->leagues);
			
	return table( $header, $rows );
}

/**
 * Periodic tasks to perform.  This should handle any internal checkpointing
 * necessary, as the cron task may be called more or less frequently than we
 * expect.
 */
function league_cron()
{
	$result = db_query("SELECT distinct league_id from league");
	while( $foo = db_fetch_array($result)) {
		$id = $foo['league_id'];
		$league = league_load( array('league_id' => $id) );
	
		// Task #1: 
		// For ladder leagues, find all games older than our expiry time, and
		// finalize them
		if($league->schedule_type == 'ladder') {
			$league->finalize_old_games();
		}

		// Task #2:
		// If schedule is round-robin, possibly update the current round
		if($league->schedule_type == 'roundrobin') {
			$league->update_current_round();
		}
	}

	return "<pre>Completed league_cron run</pre>";
}

/**
 * Create handler
 */
class LeagueCreate extends LeagueEdit
{	
	var $league;
	
	function has_permission()
	{
		global $session;
		return $session->has_permission('league','create');
	}
	
	function process ()
	{
		$id = -1;
		$edit = $_POST['edit'];
		$this->title = "Create League";
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->league = new League;
				$this->perform( $edit );
				local_redirect(url("league/view/" . $this->league->league_id));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm( $edit );
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function perform ( &$edit )
	{
		global $session;
		
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$this->league->set('name',$session->attr_get('user_id'));
		$this->league->add_coordinator($session->user);
		
		return parent::perform($edit);
	}
}

/**
 * League edit handler
 */
class LeagueEdit extends Handler
{
	var $league;

	function has_permission()
	{
		global $session;
		return $session->has_permission('league','edit',$this->league->league_id);
	}

	function process ()
	{
		$this->title = "Edit League";
		
		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->perform($edit);
				local_redirect(url("league/view/" . $this->league->league_id));
				break;
			default:
				$edit = $this->getFormData( $this->league );
				$rc = $this->generateForm( $edit );
		}
		$this->setLocation(array( $edit['name'] => "league/view/" . $this->league->league_id, $this->title => 0));

		return $rc;
	}

	function getFormData ( &$league )
	{
		/* Deal with multiple days and start times */
		if(strpos($league->day, ",")) {
			$league->day = split(",",$league->day);
		}
		return object2array($league);
	}

	function generateForm ( &$formData )
	{
		$output .= form_hidden("edit[step]", 'confirm');

		$rows = array();
		$rows[] = array("League Name:", form_textfield('', 'edit[name]', $formData['name'], 35,200, "The full name of the league.  Tier numbering will be automatically appended."));
		
		$rows[] = array("Season:", 
			form_select("", "edit[season]", $formData['season'], getOptionsFromEnum('league','season'), "Season of play for this league. Choose 'none' for administrative groupings and comp teams."));
			
		$rows[] = array("Day(s) of play:", 
			form_select("", "edit[day]", $formData['day'], getOptionsFromEnum('league','day'), "Day, or days, on which this league will play.", 0, true));
			
		/* TODO: 10 is a magic number.  Make it a config variable */
		$rows[] = array("Tier:", 
			form_select("", "edit[tier]", $formData['tier'], getOptionsFromRange(0, 10), "Tier number.  Choose 0 to not have numbered tiers."));
			
		$rows[] = array("Gender Ratio:", 
			form_select("", "edit[ratio]", $formData['ratio'], getOptionsFromEnum('league','ratio'), "Gender format for the league."));
			
		/* TODO: 5 is a magic number.  Make it a config variable */
		$rows[] = array("Current Round:", 
			form_select("", "edit[current_round]", $formData['current_round'], getOptionsFromRange(1, 5), "New games will be scheduled in this round by default."));

		$rows[] = array(" Scheduling Type:",
			form_select("", "edit[schedule_type]", $formData['schedule_type'], getOptionsFromEnum('league','schedule_type'), "What type of scheduling to use.  This affects how games are scheduled and standings displayed."));
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		
		return form($output);
	}

	function generateConfirm ( $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
	
		if(is_array($edit['day'])) {
			$edit['day'] = join(",",$edit['day']);
		}
		
		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");
		$output .= form_hidden("edit[step]", 'perform');

		$rows = array();
		$rows[] = array("League Name:", 
			form_hidden('edit[name]', $edit['name']) . $edit['name']);
		
		$rows[] = array("Season:", 
			form_hidden('edit[season]', $edit['season']) . $edit['season']);
			
		$rows[] = array("Day(s) of play:", 
			form_hidden('edit[day]',$edit['day']) . $edit['day']);
			
		$rows[] = array("Tier:", 
			form_hidden('edit[tier]', $edit['tier']) . $edit['tier']);
			
		$rows[] = array("Gender Ratio:", 
			form_hidden('edit[ratio]', $edit['ratio']) . $edit['ratio']);
			
		$rows[] = array("Current Round:", 
			form_hidden('edit[current_round]', $edit['current_round']) . $edit['current_round']);

		$rows[] = array("Scheduling Type:",
			form_hidden('edit[schedule_type]', $edit['schedule_type']) . $edit['schedule_type']);

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));

		return form($output);
	}

	function perform ( $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$this->league->set('name', $edit['name']);
		$this->league->set('day', $edit['day']);
		$this->league->set('season', $edit['season']);
		$this->league->set('tier', $edit['tier']);
		$this->league->set('ratio', $edit['ratio']);
		$this->league->set('current_round', $edit['current_round']);
		$this->league->set('schedule_type', $edit['schedule_type']);

		if( !$this->league->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	/* TODO: Properly validate other data */
	function isDataInvalid ( $edit )
	{
		$errors = "";

		if ( ! validate_nonhtml($edit['name'])) {
			$errors .= "<li>A valid league name must be entered";
		}
	
		switch($edit['schedule_type']) {
			case 'none':
			case 'roundrobin':
			case 'ladder':
				break;
			default:
				$errors .= "<li>Values for allow schedule are none, roundrobin, and ladder";
		}

		if($edit['schedule_type'] != 'none') {
			if( !$edit['day'] ) {
				$errors .= "<li>One or more days of play must be selected";
			}
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

/**
 * League list handler
 */
class LeagueList extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('league','list');
	}
	
	function process ()
	{
		global $session;

		$season = arg(2);
		if( ! $season ) {
			$season = strtolower(variable_get('current_season', "Summer"));
		}
		
		/* Fetch league names */
		$seasons = getOptionsFromEnum('league', 'season');
		
		$seasonLinks = array();
		$seasonNames = array();
		while(list(,$curSeason) = each($seasons)) {
			$curSeason = strtolower($curSeason);
			if($curSeason == '---') {
				continue;
			}
			$seasonNames[] = $curSeason;
			if($curSeason == $season) {
				$seasonLinks[] = $curSeason;
			} else {
				$seasonLinks[] = l($curSeason, "league/list/$curSeason");
			}
		}
		
		if( !in_array($season, $seasonNames) ) {
			error_exit("That is not a valid season"); 
		}
		
		$this->setLocation(array(
			$this->title => "league/list/$season",
			$season => 0
		));

		$output = para(theme_links($seasonLinks));

		$header = array( "Name", "&nbsp;") ;
		$rows = array();
		
		$result = db_query("SELECT name,tier,league_id FROM league WHERE season = '%s' ORDER BY FIELD(MAKE_SET((day & 62), 'BUG','Monday','Tuesday','Wednesday','Thursday','Friday'),'Monday','Tuesday','Wednesday','Thursday','Friday'), ratio, tier", $season);

		while($league = db_fetch_object($result)) {
			if($league->tier) {
				$league->fullname = sprintf("$league->name Tier %02d", $league->tier);
			} else {
				$league->fullname = $league->name;
			}
		
			$links = array();
			if($league->schedule_type != 'none') {
				$links[] = l('schedule',"schedule/view/$league->league_id");
				$links[] = l('standings',"league/standings/$league->league_id");
			}
			if( $session->has_permission('league','delete', $league->league_id) ) {
				$links[] = l('delete',"league/delete/$league->league_id");
			}
			$rows[] = array(
				l($league->fullname,"league/view/$league->league_id"),
				theme_links($links));
		}

		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		
		return $output;
	}
}

class LeagueStandings extends Handler
{
	var $league;

	function has_permission ()
	{	
		global $session;
		return $session->has_permission('league','view', $this->league->league_id);
	}

	function process ()
	{
		$id = arg(2);
		$this->title = "Standings";

		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}

		$round = $_GET['round'];
		if(! isset($round) ) {
			$round = $this->league->current_round;
		}
		
		$this->setLocation(array(
			$this->league->fullname => "league/view/$id",
			$this->title => 0,
		));
		
		return $this->generate_standings($round);
	}

	/**
	 * TODO: this should be split into:
	 * 	1) loading data into $season/$round data structures
	 * 	2) sorting
	 * 	3) displaying
	 * as this will allow us to create multiple sort modules
	 */
	function generate_standings ($current_round = 0)
	{
		global $session;
		$this->league->load_teams();

		if( count($this->league->teams) < 1 ) {
			error_exit("Cannot generate standings for a league with no teams");
		}

		while(list($id,) = each($this->league->teams)) {
			$this->league->teams[$id]->points_for = 0;
			$this->league->teams[$id]->points_against = 0;
			$this->league->teams[$id]->spirit = 0;
			$this->league->teams[$id]->win = 0;
			$this->league->teams[$id]->loss = 0;
			$this->league->teams[$id]->tie = 0;
			$this->league->teams[$id]->defaults_for = 0;
			$this->league->teams[$id]->defaults_against = 0;
			$this->league->teams[$id]->games = 0;
			$this->league->teams[$id]->vs = array();
			$this->league->teams[$id]->streak = array();
		}

               
		$season = $this->league->teams;
		$round  = $this->league->teams;

		/* Now, fetch the schedule.  Get all games played by anyone who is
		 * currently in this league, regardless of whether or not their
		 * opponents are still here
		 */
		// TODO: I'd like to use game_load_many here, but it's too slow.
		$result = db_query(
			"SELECT DISTINCT s.*, 
				s.home_team AS home_id, 
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			FROM schedule s, leagueteams t
			LEFT JOIN team h ON (h.team_id = s.home_team) 
			LEFT JOIN team a ON (a.team_id = s.away_team)
			WHERE t.league_id = %d 
				AND NOT ISNULL(s.home_score) AND NOT ISNULL(s.away_score) AND (s.home_team = t.team_id OR s.away_team = t.team_id) ORDER BY s.game_id", $this->league->league_id);
		while( $ary = db_fetch_array( $result) ) {
			$g = new Game;
			$g->load_from_query_result($ary);
			$this->record_game($season, $g);
			if($current_round == $g->round) {
				$this->record_game($round, $g);
			}
		}

		/* HACK: Before we sort everything, we've gotta copy the 
		 * $season's spirit and games values into the $round array 
		 * because otherwise, in any round after the first we're 
		 * only sorting on the spirit scores received in the current 
		 * round.
		 */
		while(list($team_id,$info) = each($season))
		{
			$round[$team_id]->spirit = $info->spirit;
			$round[$team_id]->games = $info->games;
		}
		reset($season);
		
		/* Now, sort it all */
                if ($this->league->schedule_type == "ladder") {
		  uasort($season, array($this, 'sort_standings_by_rank'));	

		  $sorted_order = &$season;
                }
                else {
  		  if($current_round) {
			  uasort($round, array($this, 'sort_standings'));	
			  $sorted_order = &$round;
		  } else {
			  uasort($season, array($this, 'sort_standings'));	
			  $sorted_order = &$season;
		  }
                }
		
		/* Build up header */
		$header = array( array('data' => 'Teams', 'rowspan' => 2) );
		$subheader = array();

                // Ladder leagues display standings differently.
                // Eventually this should just be a brand new object.
                if($this->league->schedule_type == "ladder") {
		  $header[] = array('data' => 'Season To Date', 'colspan' => 8); 
		  foreach(array("Rank", "Win", "Loss", "Tie", "Dfl", "PF", "PA", "+/-") as $text) {
		  	  $subheader[] = array('data' => $text, 'class'=>'subtitle', 'valign'=>'bottom');
		  }
                }
                else {
		  if($current_round) {
			$header[] = array('data' => "Current Round ($current_round)", 'colspan' => 7);
			foreach(array("Win", "Loss", "Tie", "Dfl", "PF", "PA", "+/-") as $text) {
				$subheader[] = array('data' => $text, 'class'=>'subtitle', 'valign'=>'bottom');
			}
		  }
		
		  $header[] = array('data' => 'Season To Date', 'colspan' => 7); 
		  foreach(array("Win", "Loss", "Tie", "Dfl", "PF", "PA", "+/-") as $text) {
		  	  $subheader[] = array('data' => $text, 'class'=>'subtitle', 'valign'=>'bottom');
		  }
                }
		
		$header[] = array('data' => "Rating", 'rowspan' => 2);
		$header[] = array('data' => "Streak", 'rowspan' => 2);
		$header[] = array('data' => "Avg.<br>SOTG", 'rowspan' => 2);
		
		$rows[] = $subheader;

                reset($sorted_order);
		while(list(, $data) = each($sorted_order)) {

			$id = $data->team_id;
			$row = array( l($data->name, "team/view/$id"));

                        // Don't need the current round for a ladder schedule.
                        if ($this->league->schedule_type != "ladder") {
       			  if($current_round) {
			  	  $row[] = $round[$id]->win;
  			  	  $row[] = $round[$id]->loss;
			  	  $row[] = $round[$id]->tie;
  			  	  $row[] = $round[$id]->defaults_against;
			  	  $row[] = $round[$id]->points_for;
			  	  $row[] = $round[$id]->points_against;
  				  $row[] = $round[$id]->points_for - $round[$id]->points_against;
			  }
                        }

                        if ($this->league->schedule_type == "ladder") {
			  $row[] = $season[$id]->rank; 
                        }
			$row[] = $season[$id]->win;
			$row[] = $season[$id]->loss;
			$row[] = $season[$id]->tie;
			$row[] = $season[$id]->defaults_against;
			$row[] = $season[$id]->points_for;
			$row[] = $season[$id]->points_against;
			$row[] = $season[$id]->points_for - $season[$id]->points_against;
			$row[] = $season[$id]->rating;
			
			if( count($season[$id]->streak) > 1 ) {
				$row[] = count($season[$id]->streak) . $season[$id]->streak[0];
			} else {
				$row[] = '-';
			}
	
			// initialize the sotg to dashes!
                        $sotg = "---";
			if($season[$id]->games < 3 && !($session->has_permission('league','view',$this->league->league_id, 'spirit'))) {
				 $sotg = "---";
			} else if ($season[$id]->games > 0) {
				$sotg = sprintf("%.2f", ($season[$id]->spirit / $season[$id]->games));
			}
			$row[] = $sotg;
			$rows[] = $row;
		}
		
		return "<div class='listtable'>" . table($header, $rows) . "</div>";
	}
	
	function record_game(&$season, &$game)
	{

		$game->home_spirit = $game->get_spirit_numeric( $game->home_team );
		$game->away_spirit = $game->get_spirit_numeric( $game->away_team );
		if(isset($season[$game->home_team])) {
			$team = &$season[$game->home_team];
			
			$team->games++;
			$team->points_for += $game->home_score;
			$team->points_against += $game->away_score;
			$team->spirit += $game->home_spirit;

			/* Need to initialize if not set */
			if(!isset($team->vs[$game->away_team])) {
				$team->vs[$game->away_team] = 0;
			}
			
			if($game->status == 'home_default') {
				$team->defaults_against++;
			} else if($game->status == 'away_default') {
				$team->defaults_for++;
			}

			$status = '';
			if($game->home_score == $game->away_score) {
				$team->tie++;
				$team->vs[$game->away_team]++;
				$status = 'T';
			} else if($game->home_score > $game->away_score) {
				$team->win++;
				$team->vs[$game->away_team] += 2;
				$status = 'W';
			} else {
				$team->loss++;
				$team->vs[$game->away_team] += 0;
				$status = 'L';
			}
			if(in_array($status, $team->streak)) {
				array_push($team->streak, $status);
			} else {
				$team->streak = array($status);
			}
		}
		if(isset($season[$game->away_team])) {
			$team = &$season[$game->away_team];
			
			$team->games++;
			$team->points_for += $game->away_score;
			$team->points_against += $game->home_score;
			$team->spirit += $game->away_spirit;

			/* Need to initialize if not set */
			if(!isset($team->vs[$game->home_team])) {
				$team->vs[$game->home_team] = 0;
			}
			
			if($game->status == 'away_default') {
				$team->defaults_against++;
			} else if($game->status == 'home_default') {
				$team->defaults_for++;
			}

			$status = '';
			if($game->away_score == $game->home_score) {
				$team->tie++;
				$team->vs[$game->home_team]++;
				$status = 'T';
			} else if($game->away_score > $game->home_score) {
				$team->win++;
				$team->vs[$game->home_team] += 2;
				$status = 'W';
			} else {
				$team->loss++;
				$team->vs[$game->home_team] += 0;
				$status = 'L';
			}
			if(in_array($status, $team->streak)) {
				array_push($team->streak, $status);
			} else {
				$team->streak = array($status);
			}
		}
	}

	function sort_standings_by_rank (&$a, &$b) 
	{
		if ($a->rank == $b->rank) {
			return 0;
		}
		return ($a->rank < $b->rank) ? -1 : 1;
	}

	function sort_standings (&$a, &$b) 
	{
		/* First, order by wins */
		$b_points = (( 2 * $b->win ) + $b->tie);
		$a_points = (( 2 * $a->win ) + $a->tie);
		if( $a_points > $b_points ) {
			return -1;
		} else if( $a_points < $b_points ) {
			return 1;
		}
		
		/* Then, check head-to-head wins */
		if(isset($b->vs[$a['id']]) && isset($a->vs[$b['id']])) {
			if( $b->vs[$a['id']] > $a->vs[$b['id']]) {
				return 1;
			} else if( $b->vs[$a['id']] < $a->vs[$b['id']]) {
				return -1;
			}
		}

		/* Check SOTG */
		if($a->games > 0 && $b->games > 0) {
			if( ($a->spirit / $a->games) > ($b->spirit / $b->games)) {
				return -1;
			} else if( ($a->spirit / $a->games) < ($b->spirit / $b->games)) {
				return 1;
			}
		}
		
		/* Next, check +/- */
		if( ($b->points_for - $b->points_against) > ($a->points_for - $a->points_against) ) {
			return 1;
		} else if( ($b->points_for - $b->points_against) > ($a->points_for - $a->points_against) ) {
			return -1;
		}
		
		/* 
		 * Finally, check losses.  This ensures that teams with no record
		 * appear above teams who have losses.
		 */
		if( $a->loss < $b->loss ) {
			return -1;
		} else if( $a->loss > $b->loss ) {
			return 1;
		}
		return 0;
	}
}

/**
 * League viewing handler
 */
class LeagueView extends Handler
{
	var $league;

	function has_permission()
	{
		global $session;
		return $session->has_permission('league','view',$this->league->league_id);
	}
	
	function process ()
	{
		global $session;
		
		$this->title = "View League";

		foreach( $this->league->coordinators as $c ) {
			$coordinator = l($c->fullname, "person/view/$c->user_id");
			if($session->has_permission('league','edit',$this->league->league_id)) {
				$coordinator .= "&nbsp;[&nbsp;" . l('remove coordinator', url("league/member/" . $this->league->league_id."/$c->user_id", 'edit[status]=remove')) . "&nbsp;]";
			}
			$coordinators[] = $coordinator;
		}
		reset($this->league->coordinators);

		$rows = array();
		if( count($coordinators) ) {
			$rows[] = array("Coordinators:", 
				join("<br />", $coordinators));
		}

		$rows[] = array("Season:", $this->league->season);
		if($this->league->day) {
			$rows[] = array("Day(s):", $this->league->day);
		}
		if($this->league->tier) {
			$rows[] = array("Tier:", $this->league->tier);
		}

		// Certain things should only be visible for certain types of league.
		if($this->league->schedule_type != 'none') {
			$rows[] = array("League SBF:", $this->league->calculate_sbf());
		}

		if($this->league->schedule_type == 'roundrobin') {
			$rows[] = array("Current Round:", $this->league->current_round);
		}
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";

		$header = array( "Team Name", "Players", "Rating", "Avg. Skill", "&nbsp;",);
		if( $this->league->schedule_type == 'ladder') {
			array_unshift($header, 'Rank');
		}
		$rows = array();
		$this->league->load_teams();
		foreach($this->league->teams as $team) {
			$team_links = array();
			if($team->status == 'open') {
				$team_links[] = l('join', "team/roster/$team->team_id/" . $session->attr_get('user_id'));
			}
			if($session->has_permission('league','edit',$this->league->league_id)) {
				$team_links[] = l('move', "league/moveteam/$id/$team->team_id");
			}
			if($this->league->league_id == 1 && $session->has_permission('team','delete',$team->team_id)) {
				$team_links[] = l('delete', "team/delete/$team->team_id");
			}

			$row = array();
			if( $this->league->schedule_type == 'ladder' ) {
				$row[] = $team->rank;
			}
			
			$row[] = l(check_form($team->name), "team/view/$team->team_id");
			$row[] = $team->count_players();
			$row[] = $team->rating;
			$row[] = $team->calculate_avg_skill();
			$row[] = theme_links($team_links);
			
			$rows[] = $row;
		}
		
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		
		$this->setLocation(array(
			$this->league->fullname => "league/view/$id",
			$this->title => 0));
		return $output;
	}
}

// TODO: Common email-list displaying, should take query as argument, return
// formatted list.
class LeagueCaptainEmails extends Handler
{
	var $league;

	function has_permission ()
	{
		global $session;
		return $session->has_permission('league','view',$this->league_id, 'captain emails');
	}

	function process ()
	{
		$this->title = 'Captain Emails';
		
		$result = db_query(
		   "SELECT 
				p.firstname, p.lastname, p.email
			FROM 
				leagueteams l, teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				l.league_id = %d
				AND l.team_id = r.team_id
				AND (r.status = 'captain' OR r.status = 'assistant')",$this->league->league_id);
		if( db_num_rows($result) <= 0 ) {
			error_exit("That league contains no teams.");
		}
		
		$emails = array();
		$nameAndEmails = array();
		while($user = db_fetch_object($result)) {
			$nameAndEmails[] = sprintf("\"%s %s\" &lt;%s&gt;",
				$user->firstname,
				$user->lastname,
				$user->email);
			$emails[] = $user->email;
		}
		
		$this->setLocation(array(
			$this->league->fullname => "league/view/" . $this->league->league_id,
			$this->title => 0
		));

		$output = para("You can cut and paste the emails below into your addressbook, or click " . l('here to send an email', 'mailto:' . join(',',$emails)) . " right away.");
	
		$output .= pre(join(",\n", $nameAndEmails));
		return $output;
	}
}

class LeagueMoveTeam extends Handler
{
	var $league;
	var $team;
	var $targetleague;

	function has_permission ()
	{
		global $session;
		return $session->has_permission('league','manage teams',$this->league->league_id);
	}

	function process ()
	{
		global $session;
		$this->title = "Move Team";

		$teamId = arg(3);
	
		$this->team = team_load( array('team_id' => $teamId ) );
		if( !$this->team ) {
			error_exit("You must supply a valid team ID");
		}

		$edit = $_POST['edit'];

		if( $edit['step'] == 'confirm' || $edit['step'] == 'perform' ) {
			if($edit['target'] < 1) {
				error_exit("That is not a valid league to move to");
			}
		
			if( ! $session->has_permission('league','manage teams', $edit['target']) ) {
				error_exit("Sorry, you cannot move teams to leagues you do not coordinate");
			}
			
			$this->targetleague = league_load( array('league_id' => $edit['target']));
			if( !$this->targetleague ) {
				error_exit("You must supply a valid league to move to");
			}
			
			switch($edit['step']) {
				case 'confirm':
					$rc = $this->generateConfirm();
					break;
				case 'perform':
					$this->perform();
					local_redirect(url("league/view/" . $this->league->league_id));
					break;
				default:
			}
		} else {
				$rc = $this->generateForm();
		}
		
		
		$this->setLocation(array( $this->league->fullname => "league/view/" . $this->league->league_id, $this->title => 0));

		return $rc;
	}
	
	function perform ()
	{

		$this->targetleague->load_teams();
		$rank = 0;
		if( $this->targetleague->schedule_type == 'ladder' ) {
			$rank = count($this->targetleague->teams) + 1;
		}
	
		db_query("UPDATE leagueteams SET league_id = %d, rank = %d WHERE team_id = %d AND league_id = %d", $this->targetleague->league_id, $rank, $this->team->team_id, $this->league->league_id);
		
		if( 1 != db_affected_rows() ) {
			error_exit("Couldn't move team between leagues");
		}
		return true;
	}

	function generateConfirm ( )
	{
		$output .= form_hidden('edit[step]', 'perform');
		$output .= form_hidden('edit[target]', $this->targetleague->league_id);
		
		$output .= para( 
			"You are attempting to move the team <b>" 
			. $this->team->name 
			. "</b> to <b>" 
			. $this->targetleague->fullname
			. "</b>");
		$output .= para("If this is correct, please click 'Submit' below.");
		$output .= form_submit("Submit");
		return form($output);
	}
	
	function generateForm ( )
	{
		global $session;

		$leagues = array();
		$leagues[0] = '-- select from list --';
		if( $session->is_admin() ) { 
			$result = db_query("SELECT league_id as theKey, IF(tier,CONCAT(name,' Tier ',tier), name) as theValue from league ORDER BY season,name,tier");
			while($row = db_fetch_array($result)) {
				$leagues[$row['theKey']] = $row['theValue'];	
			}
		} else {
			$leagues[1] = 'Inactive Teams';
			foreach( $session->user->leagues as $league ) {
				$leagues[$league->league_id] = $league->fullname;
			}
		}
		
		$output = form_hidden('edit[step]', 'confirm');
		$output .= 
			para("You are attempting to move the team <b>" 
				. $this->team->name 
				. "</b>. Select the league you wish to move it to");
				
		$output .= form_select('', 'edit[target]', '', $leagues);
		$output .= form_submit("Submit");
		$output .= form_reset("Reset");

		return form($output);
	}
}

class LeagueApproveScores extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('league','approve scores',$this->league->league_id);
	}

	function process ()
	{
		$this->title = "Approve Scores";

		/* Fetch games in need of verification */
		$result = db_query("SELECT DISTINCT
			se.game_id,
			UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) as timestamp,
			s.home_team,
			h.name AS home_name,
			s.away_team,
			a.name AS away_name
			FROM schedule s, score_entry se
			    LEFT JOIN gameslot g ON (s.game_id = g.game_id)
			    LEFT JOIN team h ON (s.home_team = h.team_id)
			    LEFT JOIN team a ON (s.away_team = a.team_id)
			WHERE s.league_id = %d AND s.game_id = se.game_id ORDER BY timestamp", $this->league->league_id);

		$header = array(
			'Game Date',
			array('data' => 'Home Team Submission', 'colspan' => 2),
			array('data' => 'Away Team Submission', 'colspan' => 2),
			'&nbsp;'
		);
		$rows = array();
		
		$se_query = "SELECT score_for, score_against FROM score_entry WHERE team_id = %d AND game_id = %d";
		
		while($game = db_fetch_object($result)) {
			$rows[] = array(
				array('data' => strftime("%A %B %d %Y, %H%Mh",$game->timestamp),'rowspan' => 3),
				array('data' => $game->home_name, 'colspan' => 2),
				array('data' => $game->away_name, 'colspan' => 2),
				array('data' => l("approve score", "game/approve/$game->game_id"), 'rowspan' => 3)
			);
		
			$home = db_fetch_array(db_query($se_query, $game->home_team, $game->game_id));
			
			if(!$home) {
				$home = array(
					'score_for' => 'not entered',
					'score_against' => 'not entered',
				);
			}
			
			$away = db_fetch_array(db_query($se_query, $game->away_team, $game->game_id));
			if(!$away) {
				$away = array(
					'score_for' => 'not entered',
					'score_against' => 'not entered',
				);
			}

			$rows[] = array(
				"Home Score:", $home['score_for'], "Home Score:", $away['score_against']
			);
			
			$rows[] = array(
				"Away Score:", $home['score_against'], "Away Score:", $away['score_for']
			);
			
		}
		
		$output = para("The following games have not been finalized.");
		$output .= "<div class='listtable'>" . table( $header, $rows ) . "</div>";
		return $output;
	}
}

class LeagueMemberStatus extends Handler
{
	function has_permission()
	{
		global $session;
		return $session->has_permission('league','edit', $this->league->league_id);
	}

	function process ()
	{
		global $session;
		$this->title = "League Member Status";

		$player_id = arg(3);

		if( !$player_id ) {
			$this->setLocation(array( $this->league->fullname => "league/view/" . $this->league->league_id, $this->title => 0));
			$new_handler = new PersonSearch;
			$new_handler->initialize();
			$new_handler->ops['Add to ' . $this->league->fullname] = 'league/member/' .$this->league->league_id . '/%d';
			$new_handler->extra_where = "(class = 'administrator' OR class = 'volunteer')";
			return $new_handler->process();
		}

		if( !$session->is_admin() && $player_id == $session->attr_get('user_id') ) {
			error_exit("You cannot add or remove yourself as league coordinator");
		}

		$player = person_load( array('user_id' => $player_id) );
		
		switch($_GET['edit']['status']) {
			case 'remove':
				if( ! $this->league->remove_coordinator($player) ) {
					error_exit("Failed attempting to remove coordinator from league");
				}
				break;
			default:
				if($player->class != 'administrator' && $player->class != 'volunteer') {
					error_exit("Only volunteer-class players can be made coordinator");
				}
				if( ! $this->league->add_coordinator($player) ) {
					error_exit("Failed attempting to add coordinator to league");
				}
				break;
		}

		if( ! $this->league->save() ) {
			error_exit("Failed attempting to modify coordinators for league");
		}
		
		local_redirect(url("league/view/" . $this->league->league_id));
	}
}

/**
 * Contains admin functions for the league.
 * TODO this code needs some cleanup
 */
class LeagueAdmin extends Handler
{
	var $league;

	function has_permission()
	{
		global $session;
		// Considered 'unsafe' as we haven't tested this much
		return $session->has_permission('league','unsafe admin', $this->league->league_id);
	}

	function process ()
	{
		$this->title = "League Administration";
             
		$operation = arg(2); 

		$this->setLocation(array(
			$this->league->fullname => "league/admin",
			$this->title => 0));
                
		switch($operation) {
			case 'cleanround':
				// TODO: remove this when this code is safe and tested
				error_exit("This function is still being developed");
				return($this->cleanround());
				break;
			case 'cancelround':
				return($this->cancelround());
				break;
			case 'finalizeround':
				// TODO: remove this when this code is safe and tested
				error_exit("This function is still being developed");
				return($this->finalizeround());
				break;
			case 'top':
				return($this->viewrounds());
				break;
			default:
				return($this->viewrounds());
		}
	}

	function viewrounds() 
	{

		$output = para("This is the league adminstration page.  " . 
			       "You can perform several highly destructive operations here. " .
			       "If you mess up here, your only recourse may be to restore the database from a previous backup! " .
			       "<b>You have been warned!</b>");

		$output .= para("Use <b>clean</b> to reset games (ie: deletes the home/away teams)");
		$output .= para("Use <b>cancel</b> to delete the entire round of games (ie: physically removes games from the system, adjusting all dependent game info)");
		$output .= para("Use <b>finalize</b> to force a result for all games in the round (ie: automatically approve partial score entries, assign 0-0 ties to games with no results)");

		$header = array(array('data'=>"Round",'align'=>"center"), 
				array('data'=>"Date",'align'=>"center"), 
				"&nbsp", 
				array('data'=>"Operations",'colspan'=>3,'align'=>"center") );

		$row = array();

		$dbQuery = "SELECT DISTINCT round, game_date FROM schedule, gameslot WHERE " .
			  "league_id = " . $this->league->league_id .
			  " AND schedule.game_id = gameslot.game_id ORDER BY game_date ASC"; 

		// TBD:  Need some error checking here.
		$allRounds = db_query($dbQuery);

		
		while($round = db_fetch_object($allRounds)) {

		  $row[] = array(
			     $round->round, 
			     $round->game_date, 
			     "&nbsp", 
			     l("clean",   "league/admin/cleanround/" . $this->league->league_id . "/confirm/" . $round->round ),
			     l("cancel",  "league/admin/cancelround/" . $this->league->league_id . "/confirm/" . $round->round),
			     l("finalize","league/admin/finalizeround/" . $this->league->league_id . "/confirm/" . $round->round) );
		}

		$output = $output . "<div class='listtable'>" . table( $header, $row ) . "</div>";

		return $output;
	}

	function cleanround()
	{
	    $suboperation = arg(4);

	    switch($suboperation) {
			case 'confirm':
				 return($this->cleanroundconfirm());
				break;
			case 'doit':
				 return($this->cleanrounddoit());
				break;
			default:
	    }
	}

	function cancelround()
	{
	    $suboperation = arg(4);

	    switch($suboperation) {
			case 'confirm':
				 return($this->cancelroundconfirm());
				break;
			case 'doit':
				 return($this->cancelrounddoit());
				break;
			default:
	    }
	}

	function finalizeround()
	{
	    $suboperation = arg(4);

	    switch($suboperation) {
			case 'confirm':
				 return($this->finalizeroundconfirm());
				break;
			case 'doit':
				 return($this->finalizerounddoit());
				break;
			default:
	    }
	}

	function cleanroundconfirm()
	{
		$output = para("This operation will remove all game data from the select round!!");
		$output .= para("Are you sure you want to proceed!?!");

		$output .= form_submit("Clean Round"); 
		return form($output,"post", url("league/admin/cleanround/" . $this->league->league_id . "/doit/" . arg(5)));
	}

	function cleanrounddoit()
	{
		$this->league->cleanround(arg(5));

		 return("Round <b>" . arg(5) . "</b> cleaned.");
	}
     
	function cancelroundconfirm()
	{
		$output = para("This operation will remove ALL GAMES from the selected round!!");
		$output .= para("Are you sure you want to proceed with cancelling round <b>" . arg(5) . "</b> ?!?!");

		$output .= form_submit("Cancel Round"); 
		return form($output,"post", url("league/admin/cancelround/" . $this->league->league_id . "/doit/" . arg(5)));
	}

	function cancelrounddoit()
	{
		if ( $this->league->cancelround(arg(5)) ) {
			return("Round <b>" . arg(5) . "</b> cancelled.");
		} else {
			return("Problem cancelling round <b>" . arg(5) . "</b> !!!");
		}
	}

	function finalizeroundconfirm()
	{
		$output = para("This operation will finalize the selected round.");
		$output .= para("Are you sure you want to proceed?");

		$output .= form_submit("Finalize Round"); 
		return form($output,"post", url("league/admin/finalizeround/" .$this->league->league_id . "/doit/" . arg(5)));
	}

	function finalizerounddoit()
	{
		//$this->league->finalizeround(arg(5));

		return("Round <b>" . arg(5) . "</b> finalized.");
	}
}

class LeagueLadder extends Handler
{
	var $league;

	function has_permission()
	{
		global $session;
		return $session->has_permission('league','ladder seed', $this->league->league_id);
	}

	function process ()
	{
		global $session;
		$this->title = "League Ladder Adjustment";
		
		// seed by rank flag:
		$byskill = 0;
		if (arg(3) == "byskill") {
			$teamID    = arg(4);
			$direction = arg(5);
			$byskill = 1;
		} else {
			$teamID    = arg(3);
			$direction = arg(4);
		}

		if( $this->league->schedule_type != "ladder" ) {
			error_exit("Ladder cannot be adjusted for a non-ladder league.");
		}

		// Re-seeding after scheduling is a bad idea, so disallow it
		if( $this->league->has_schedule() ) {
			error_exit("Ladder cannot be adjusted after games have been scheduled.");
		}
		
		$this->league->load_teams();
		
		// if the user chose to seed by rank, make the seeding changes
		if ($byskill) {
			$skill_array = array();
			foreach ($this->league->teams as $t) {
				$skill_array[$t->team_id] = $t->calculate_avg_skill();
			}
			arsort($skill_array, SORT_NUMERIC);
			$count = 1;
			foreach ($skill_array as $key => $value) {
				db_query("UPDATE leagueteams SET rank = %d WHERE league_id = %d AND team_id = %d", $count, $this->league->league_id, $key);
				$count++;
			}

			// now, reload the teams so that they display in the new order!
			$this->league->_teams_loaded = false;
			$this->league->load_teams();
		}

		if( $direction ) {
			$team = team_load( array('team_id' => $teamID) );
			if( !$team ) {
				error_exit("A team must be provided for that operation.");
			}
			if (! $league->contains_team ($team->team_id) ) {
				error_exit("That team is not in this league!");
			}

			switch($direction) {
				case 'lower':
					$new_rank = $team->rank + 1;
					break;
				case 'higher':
					$new_rank = $team->rank - 1;
					break;
				default:
					error_exit("That is not a valid ladder adjustment");
			}
			
			// Race condition here.  If someone else is doing this, we
			// may end up with mis-ranked teams.  It shouldn't be
			// too big of a worry, though.
			$other_team_id = db_result(db_query("SELECT team_id FROM leagueteams WHERE rank = %d AND league_id = %d", $new_rank, $this->league->league_id));
			db_query("UPDATE leagueteams SET rank = %d WHERE league_id = %d AND team_id = %d", $new_rank, $this->league->league_id, $team->team_id);
			if(db_affected_rows() != 1) {
				error_exit("Oh, no!  Looks like someone screwed up... couldn't change the rank due to an internal error.");
			}
			
			db_query("UPDATE leagueteams SET rank = %d WHERE league_id = %d AND team_id = %d", $team->rank, $this->league->league_id, $other_team_id);
			if(db_affected_rows() != 1) {
				error_exit("Oh, no!  Looks like someone screwed up... couldn't change the rank due to an internal error.");
			}
			
			// Redirect to prevent page-reload from breaking things.
			local_redirect(url("league/ladder/" . $this->league->league_id));
		}

		$output = para("This is the rank adjustment tool.  This should only be used to initially seed at the beginning of a season before games are scheduled.  Don't do it mid-season or bad things may happen");

		$header = array( "Rank", "Team Name", "Rating", "Avg. Skill", "&nbsp;","&nbsp");
		$last_rank = count($this->league->teams);
		$rows = array();
		foreach($this->league->teams as $team) {
			if($team->rank > 1) {
				$up_link = l('^^^', "league/ladder/" . $this->league->league_id ."/$team->team_id/higher");
			} else {
				$up_link = "";
			}
			if( $team->rank < $last_rank) {
				$down_link = l('vvv', "league/ladder/" . $this->league->league_id . "/$team->team_id/lower");
			} else {
				$down_link = "";
			}
			$rows[] = array(
				$team->rank,
				check_form($team->name),
				$team->rating,
				$team->calculate_avg_skill(),
				$up_link,
				$down_link
			);
		}
		
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		
		$this->setLocation(array(
			$this->league->fullname => "league/ladder/" . $this->league->league_id,
			$this->title => 0));
		return $output;
	}
}
?>
