<?php
/* 
 * Handle operations specific to leagues
 */

function league_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new LeagueCreate;
		case 'edit':
			return new LeagueEdit;
		case 'view':
			return new LeagueView;
		case 'list':
			return new LeagueList;
		case 'standings':
			return new LeagueStandings;
		case 'captemail':
			return new LeagueCaptainEmails;
		case 'moveteam':
			return new LeagueMoveTeam;
		case 'approvescores':
			return new LeagueApproveScores;
		case 'member':
			return new LeagueMemberStatus;
	}
	return null;
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
			## TODO: permissions hack must die!
			$this->_permissions['administer_league'] = true;
			league_add_to_menu($this, $league);
		}
		reset($session->user->leagues);
	}
}

/**
 * Add view/edit/delete links to the menu for the given league
 * TODO: when permissions are fixed, remove the evil passing of $this
 * TODO: fix ugly evil things like LeagueEdit so that this can be called to add
 * league being edited to the menu.
 */
function league_add_to_menu( $this, &$league, $parent = 'league' ) 
{
	global $session;

	menu_add_child($parent, $league->fullname, $league->fullname, array('weight' => -10, 'link' => "league/view/$league->league_id"));
	
	if($league->allow_schedule == 'Y') {
		menu_add_child($league->fullname, "$league->fullname/standings",'standings', array('weight' => -1, 'link' => "league/standings/$league->league_id"));
		menu_add_child($league->fullname, "$league->fullname/schedule",'schedule', array('weight' => -1, 'link' => "schedule/view/$league->league_id"));
		if($this->_permissions['administer_league']) {
			menu_add_child("$league->fullname/schedule", 'edit', 'add games', array('link' => "game/create/$league->league_id"));
			menu_add_child($league->fullname, "$league->fullname/approvescores",'approve scores', array('weight' => 1, 'link' => "league/approvescores/$league->league_id"));
		}
	}
	
	if($this->_permissions['administer_league']) {
		menu_add_child($league->fullname, "$league->fullname/edit",'edit league', array('weight' => 1, 'link' => "league/edit/$league->league_id"));
		menu_add_child($league->fullname, "$league->fullname/member",'add coordinator', array('weight' => 2, 'link' => "league/member/$league->league_id"));
		menu_add_child($league->fullname, "$league->fullname/captemail",'captain emails', array('weight' => 3, 'link' => "league/captemail/$league->league_id"));
	}
	if($session->is_admin()) {
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
			l("view", "league/view/$league->league_id"),
			l("edit", "league/edit/$league->league_id")
		);
		if($league->allow_schedule == 'Y') {
			$links[] = l("schedule", "schedule/view/$league->league_id");
			$links[] = l("standings", "league/standings/$league->league_id");
			$links[] = l("approve scores", "league/verifyscores/$league->league_id");
		}

		$rows[] = array(
			array( 
				'data' => $league->fullname,
				'colspan' => 3
			),
			array(
				'data' => theme_links($links), 
				'align' => 'right'
			)
		);
	}
	reset($session->user->leagues);
			
	return "<div class='myteams'>" . table( $header, $rows ) . "</div>";
}

/**
 * Create handler
 */
class LeagueCreate extends LeagueEdit
{
	function initialize ()
	{
		if(parent::initialize() == false) {
			return false;
		}
		$this->title = "Create League";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		return true;
	}
	
	function process ()
	{
		$id = -1;
		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$league = new League;
				$this->perform( $league, $edit );
				local_redirect(url("league/view/" . $league->league_id));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm( $edit );
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function perform ( &$league, $edit )
	{
		global $session;
		
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$league->set('name',$session->attr_get('user_id'));
		$league->add_coordinator($session->user);
		
		return parent::perform( $league, $edit);
	}
}

/**
 * League edit handler
 */
class LeagueEdit extends Handler
{
	function initialize ()
	{
		$this->title = "Edit League";

		$this->_permissions = array(
			'edit_info'			=> false,
			'edit_coordinator'		=> false,
		);
		
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);
		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->_permissions['edit_info'] = true;
			$this->_permissions['edit_coordinator'] = true;
		} else if($type == 'coordinator') {
			$this->_permissions['edit_info'] = true;
		} 
	}

	function process ()
	{
		$id = arg(2);
		$edit = &$_POST['edit'];

		$league = league_load( array('league_id' => $id) );
		if( !$league ) {
			$this->error_exit("That league does not exist");
		}
		# league_add_to_menu(TODO)

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->perform( $league, $edit );
				local_redirect(url("league/view/$id"));
				break;
			default:
				$edit = $this->getFormData( $league );
				$rc = $this->generateForm( $edit );
		}
		$this->setLocation(array( $edit['name'] => "league/view/$id", $this->title => 0));

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

		$rows[] = array("Allow Scheduling:",
			form_select("", "edit[allow_schedule]", $formData['allow_schedule'], getOptionsFromEnum('league','allow_schedule'), "Whether or not this league can have games scheduled and standings displayed."));
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		
		return form($output);
	}

	function generateConfirm ( $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
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

		$rows[] = array("Allow Scheduling:",
			form_hidden('edit[allow_schedule]', $edit['allow_schedule']) . $edit['allow_schedule']);

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));

		return form($output);
	}

	function perform ( &$league, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		if($this->_permissions['edit_info']) {
			$league->set('name', $edit['name']);
			$league->set('day', $edit['day']);
			$league->set('season', $edit['season']);
			$league->set('tier', $edit['tier']);
			$league->set('ratio', $edit['ratio']);
			$league->set('current_round', $edit['current_round']);
			$league->set('allow_schedule', $edit['allow_schedule']);
		}

		if( !$league->save() ) {
			$this->error_exit("Internal error: couldn't save changes");
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
		
		if( $edit['allow_schedule'] != 'Y' && $edit['allow_schedule'] != 'N' ) {
			$errors .= "<li>Values for allow schedule are Y and N";
		}

		if($edit['allow_schedule'] == 'Y') {
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
	function initialize ()
	{
		$this->title = "List Leagues";
		$this->_permissions = array(
			'delete' => false,
			'create' => false,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'allow'
		);
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
			$this->error_exit("That is not a valid season"); 
		}
		
		$this->setLocation(array(
			$this->title => "league/list/$season",
			$season => 0
		));

		$output = para(theme_links($seasonLinks));

		$header = array( "Name", "Ratio", "&nbsp;") ;
		$rows = array();
		
		$result = db_query("SELECT * FROM league WHERE season = '%s' ORDER BY FIELD(MAKE_SET((day & 62), 'BUG','Monday','Tuesday','Wednesday','Thursday','Friday'),'Monday','Tuesday','Wednesday','Thursday','Friday'), ratio, tier", $season);

		while($league = db_fetch_object($result)) {
			if($league->tier) {
				$league->fullname = sprintf("$league->name Tier %02d", $league->tier);
			} else {
				$league->fullname = $league->name;
			}
		
			$links = array(
				l('view',"league/view/$league->league_id")
			);
			if($league->allow_schedule == 'Y') {
				$links[] = l('schedule',"schedule/view/$league->league_id");
				$links[] = l('standings',"league/standings/$league->league_id");
			}
			if($this->_permissions['delete']) {
				$links[] = l('delete',"league/delete/$league->league_id");
			}
			$rows[] = array($league->fullname,$league->ratio,theme_links($links));
		}

		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		
		return $output;
	}
}

class LeagueStandings extends Handler
{
	function initialize ()
	{
		$this->title = "Standings";
		$this->_permissions = array(
			"administer_league" => false,
		);

		$this->_required_perms = array(
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);
		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->_permissions['administer_league'] = true;
		} else if($type == 'coordinator') {
			$this->_permissions['administer_league'] = true;
		} 
	}

	function process ()
	{
		$id = arg(2);

		$league = league_load( array('league_id' => $id) );
		if( !$league ) {
			$this->error_exit("That league does not exist.");
		}
		
		if($league->allow_schedule == 'N') {
			$this->error_exit("This league does not have a schedule or standings.");
		}

		$round = $_GET['round'];
		if(! $round ) {
			$round = $league->current_round;
		}
		
		$this->setLocation(array(
			$league->fullname => "league/view/$id",
			$this->title => 0,
		));

		league_add_to_menu($this, $league);
		
		return $this->generate_standings($id, $round);
	}

	function calculate_sotg( &$stats, $drop_best_worst = false ) 
	{	
		$raw = $stats['spirit'];
		$games = $stats['games'] - ($stats['defaults_for'] + $stats['defaults_against']);
		if($games > 0) {
			if($games >= 3 && $drop_best_worst) {
				$raw = $raw - ($stats['best_spirit'] + $stats['worst_spirit']);
				$games = $games - 2;
			}
			return $raw / $games;
		} else {
			return 0;
		}
	}

	/**
	 * TODO: this should be split into:
	 * 	1) loading data into $season/$round data structures
	 * 	2) sorting
	 * 	3) displaying
	 * as this will allow us to create multiple sort modules
	 */
	function generate_standings ($id, $current_round = 0)
	{
		$result = db_query(
				"SELECT t.team_id AS id, 
			 	 t.name, t.rating
				 FROM leagueteams l
				 LEFT JOIN team t ON (l.team_id = t.team_id)
				 WHERE
					league_id = %d", $id);
					
		$season = array();
		$round  = array();
		while($team = db_fetch_object($result)) {
			$this->seasonAddTeam($season, $team);
			$this->seasonAddTeam($round, $team);
		}
	
		/* Now, fetch the schedule.  Get all games played by anyone who is
		 * currently in this league, regardless of whether or not their
		 * opponents are still here
		 */
		$result = db_query(
			"SELECT DISTINCT s.*
			 FROM
			  	schedule s, leagueteams t
			 WHERE 
				t.league_id = %d 
				AND (s.home_team = t.team_id OR s.away_team = t.team_id)
		 		ORDER BY s.game_id", $id);

		while($game = db_fetch_array($result) ) {
			if(is_null($game['home_score']) || is_null($game['away_score'])) {
				/* Skip unscored games */
				continue;
			}
			$this->record_game($season, $game);
			if($current_round == $game['round']) {
				$this->record_game($round, $game);
			}
		}

		/* HACK: Before we sort everything, we've gotta copy the 
		 * $season's spirit and games values into the $round array 
		 * because otherwise, in any round after the first we're 
		 * only sorting on the spirit scores received in the current 
		 * round.
		 */
		while(list($id,$info) = each($season))
		{
			$round[$id]['spirit'] = $info['spirit'];
			$round[$id]['games'] = $info['games'];
		}
		reset($season);
		
		/* Now, sort it all */
		if($current_round) {
			uasort($round, array($this, 'sort_standings'));	
			$sorted_order = &$round;
		} else {
			uasort($season, array($this, 'sort_standings'));	
			$sorted_order = &$season;
		}
		
		/* Build up header */
		$header = array( array('data' => 'Teams', 'rowspan' => 2) );
		$subheader = array();
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
		
		$header[] = array('data' => "Rating", 'rowspan' => 2);
		$header[] = array('data' => "Avg.<br>SOTG", 'rowspan' => 2);
		
		$rows[] = $subheader;

		while(list(, $data) = each($sorted_order)) {

			$id = $data['id'];
			$row = array( l($data['name'], "team/view/$id"));

			if($current_round) {
				$row[] = $round[$id]['win'];
				$row[] = $round[$id]['loss'];
				$row[] = $round[$id]['tie'];
				$row[] = $round[$id]['defaults_against'];
				$row[] = $round[$id]['points_for'];
				$row[] = $round[$id]['points_against'];
				$row[] = $round[$id]['points_for'] - $round[$id]['points_against'];
			}
			$row[] = $season[$id]['win'];
			$row[] = $season[$id]['loss'];
			$row[] = $season[$id]['tie'];
			$row[] = $season[$id]['defaults_against'];
			$row[] = $season[$id]['points_for'];
			$row[] = $season[$id]['points_against'];
			$row[] = $season[$id]['points_for'] - $season[$id]['points_against'];
			$row[] = $season[$id]['rating'];
		
			if($season[$id]['games'] < 3 && !($this->_permissions['administer_league'])) {
				 $sotg = "---";
			} else {
				$sotg = sprintf("%.2f", $sotg = $this->calculate_sotg($season[$id], true));
			}
			
			$row[] = $sotg;
			$rows[] = $row;
		}

		return "<div class='listtable'>" . table($header, $rows) . "</div>";
	}
	
	/*
	 * Add initial team info to a season.
	 */
	function seasonAddTeam(&$season, &$team) 
	{
		$season[$team->id] = array(
			'name' => $team->name,
			'id' => $team->id,
			'points_for' => 0,
			'points_against' => 0,
			'spirit' => 0,
			'worst_spirit' => 99999,
			'best_spirit' => 0,
			'win' => 0,
			'loss' => 0,
			'tie' => 0,
			'defaults_for' => 0,
			'defaults_against' => 0,
			'games' => 0,
			'rating' => $team->rating,
			'vs' => array()
		);
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
				if($data['worst_spirit'] > $game['home_spirit']) {
					$data['worst_spirit'] = $game['home_spirit'];
				}
				if($data['best_spirit'] < $game['home_spirit']) {
					$data['best_spirit'] = $game['home_spirit'];
				}
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
				if($data['worst_spirit'] > $game['away_spirit']) {
					$data['worst_spirit'] = $game['away_spirit'];
				}
				if($data['best_spirit'] < $game['away_spirit']) {
					$data['best_spirit'] = $game['away_spirit'];
				}
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

	function sort_standings (&$a, &$b) 
	{

		/* First, order by wins */
		$b_points = (( 2 * $b['win'] ) + $b['tie']);
		$a_points = (( 2 * $a['win'] ) + $a['tie']);
		$rc = cmp($b_points, $a_points);  /* B first, as we want descending */
		if($rc != 0) {
			return $rc;
		}
		
		/* Then, check head-to-head wins */
		if(isset($b['vs'][$a['id']]) && isset($a['vs'][$b['id']])) {
			$rc = cmp($b['vs'][$a['id']], $a['vs'][$b['id']]);
			if($rc != 0) {
				return $rc;
			}
		}

		/* Check SOTG */
		if($a['games'] > 0 && $b['games'] > 0) {
			$rc = cmp( $this->calculate_sotg($b,true), $this->calculate_sotg($b,true));
			if($rc != 0) {
				return $rc;
			}
		}
		
		/* Next, check +/- */
		$rc = cmp($b['points_for'] - $b['points_against'], $a['points_for'] - $a['points_against']);
		if($rc != 0) {
			return $rc;
		}
		
		/* 
		 * Finally, check losses.  This ensures that teams with no record
		 * appear above teams who have losses.
		 */
		$rc = cmp($a['loss'], $b['loss']);
		if($rc != 0) {
			return $rc;
		}
	}
}

/**
 * League viewing handler
 */
class LeagueView extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			"administer_league" => false,
		);
		$this->title = "View League";

		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);
		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator' || $type == 'coordinator') {
			$this->_permissions['administer_league'] = true;
		} 
	}

	function process ()
	{
		global $session;

		$id = arg(2);

		$league = league_load( array('league_id' => $id ));
		if( !$league ) {
			$this->error_exit("That league does not exist.");
		}
		
		foreach( $league->coordinators as $c ) {
			$coordinator = l($c->fullname, "person/view/$c->user_id");
			if($this->_permissions['administer_league']) {
				$coordinator .= "&nbsp;[&nbsp;" . l('remove coordinator', url("league/member/$id/$c->user_id", 'edit[status]=remove')) . "&nbsp;]";
			}
			$coordinators[] = $coordinator;
		}
		reset($league->coordinators);

		$rows = array();
		if( count($coordinators) ) {
			$rows[] = array("Coordinators:", 
				join("<br />", $coordinators));
		}

		$rows[] = array("Season:", $league->season);
		if($league->day) {
			$rows[] = array("Day(s):", $league->day);
		}
		if($league->tier) {
			$rows[] = array("Tier:", $league->tier);
		}

		# Now, if this league should contain schedule info, grab it
		if($league->allow_schedule == 'Y') {
			$rows[] = array("Current Round:", $league->current_round);
			$rows[] = array("League SBF:", $league->calculate_sbf());
		}
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$header = array( "Team Name", "Shirt Colour", "Avg. Skill", "&nbsp;",);
		$rows = array();
		$league->load_teams();
		foreach($league->teams as $team) {
			$team_links = array(
				l('view', "team/view/$team->team_id"),
			);
			if($team->status == 'open') {
				$team_links[] = l('join team', "team/roster/$team->team_id/" . $session->attr_get('user_id'));
			}
			if($this->_permissions['administer_league']) {
				$team_links[] = l('move team', "league/moveteam/$id/$team->team_id");
				$team_links[] = l('delete team', "team/delete/$team->team_id");
			}
			
			$rows[] = array(
				check_form($team->name),
				check_form($team->shirt_colour),
				$team->calculate_avg_skill(),
				theme_links($team_links)
			);
		}
		
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		
		$this->setLocation(array(
			$league->fullname => "league/view/$id",
			$this->title => 0));
		league_add_to_menu($this, $league);
		return $output;
	}
}

// TODO: Common email-list displaying, should take query as argument, return
// formatted list.
class LeagueCaptainEmails extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);
		$this->title = 'Captain Emails';
		return true;
	}

	function process ()
	{
		$id = arg(2);
		
		$league = league_load( array('league_id' => $id ) );
		if( !$league ) {
			$this->error_exit("That league does not exist.");
		}
		
		$result = db_query(
		   "SELECT 
				p.firstname, p.lastname, p.email
			FROM 
				leagueteams l, teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				l.league_id = %d
				AND l.team_id = r.team_id
				AND (r.status = 'captain' OR r.status = 'assistant')",$id);
		if( db_num_rows($result) <= 0 ) {
			return false;
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
			$league->fullname => "league/view/$id",
			$this->title => 0
		));

		$output = para("You can cut and paste the emails below into your addressbook, or click " . l('here to send an email', 'mailto:' . join(',',$emails)) . " right away.");
	
		$output .= pre(join(",\n", $nameAndEmails));
		return $output;
	}
}

class LeagueMoveTeam extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny'
		);
		$this->title = "Move Team";
		return true;
	}

	function process ()
	{
		$leagueId = arg(2);
		$teamId = arg(3);
		
		$league = league_load( array('league_id' => $leagueId ) );
		if( !$league ) {
			$this->error_exit("You must supply a valid league ID");
		}
		
		if( !validate_number($teamId) ) {
			$this->error_exit("You must supply a valid team ID");
		}

		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $leagueId, $teamId, $edit );
				break;
			case 'perform':
				$this->perform( $leagueId, $teamId, $edit);
				local_redirect(url("league/view/$leagueId"));
				break;
			default:
				$rc = $this->generateForm( $leagueId, $teamId );
		}
		
		$this->setLocation(array( $league->fullname => "league/view/$leagueId", $this->title => 0));

		return $rc;
	}
	
	function perform ( $leagueId, $teamId, $edit )
	{
		global $session;

		if($edit['target'] < 1) {
			$this->error_exit("That is not a valid league to move to");
		}
		if( ! $session->is_coordinator_of($edit['target']) ) {
			$this->error_exit("Sorry, you cannot move teams to leagues you do not coordinate");
		}

		db_query("UPDATE leagueteams SET league_id = %d WHERE team_id = %d AND league_id = %d", $edit['target'], $teamId, $leagueId);
		
		if( 1 != db_affected_rows() ) {
			$this->error_exit("Couldn't move team between leagues");
		}
		return true;
	}

	function generateConfirm ( $leagueId, $teamId, $edit )
	{
		global $session;

		if( ! $session->is_coordinator_of($edit['target']) ) {
			$this->error_exit("Sorry, you cannot move teams to leagues you do not coordinate");
		}

		$to = league_load( array('league_id' => $edit['target'] ) );
		if( !$to ) {
			$this->error_exit("That is not a valid league to move to");
		}
		
		$team = team_load( array('team_id' => $teamId) );
		if(! $team ) {
			$this->error_exit("That is not a valid team");
		}

		$output .= form_hidden('edit[step]', 'perform');
		$output .= form_hidden('edit[target]', $edit['target']);
		
		$output .= para( 
			"You are attempting to move the team <b>$team->name</b> to <b>$to->fullname</b>. <br />If this is correct, please click 'Submit' below."
		);

		$output .= form_submit("Submit");
		
		return form($output);
	}
	
	function generateForm ( $leagueId, $teamId )
	{
		global $session;

		$team = team_load( array('team_id' => $teamId) );
		if(!$team ) {
			$this->error_exit("That is not a valid team");
		}

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
			para("You are attempting to move the team <b>$team->name</b>. Select the league you wish to move it to")
			. form_select('', 'edit[target]', '', $leagues);
		$output .= form_submit("Submit");

		return form($output);
	}
}

class LeagueApproveScores extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny'
		);
		$this->_permissions = array(
			'administer_league' => true,
		);
		$this->title = "Approve Scores";
		return true;
	}

	function process ()
	{
		$id = arg(2);

		$league = league_load( array('league_id' => $id) );
		if(!$league) {
			$this->error_exit("That league does not exist!");
		}
		
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
			WHERE s.league_id = %d AND s.game_id = se.game_id ORDER BY timestamp", $id);

		$header = array(
			'Game Date',
			array('data' => 'Home Team Submission', 'colspan' => 2),
			array('data' => 'Away Team Submission', 'colspan' => 2),
			'&nbsp;'
		);
		$rows = array();
		
		$se_query = "SELECT score_for, score_against, spirit FROM score_entry WHERE team_id = %d AND game_id = %d";
		
		while($game = db_fetch_object($result)) {
			$rows[] = array(
				array('data' => strftime("%A %B %d %Y, %H%Mh",$game->timestamp),'rowspan' => 4),
				array('data' => $game->home_name, 'colspan' => 2),
				array('data' => $game->away_name, 'colspan' => 2),
				array('data' => l("approve score", "game/approve/$game->game_id"), 'rowspan' => 4)
			);
		
			$home = db_fetch_array(db_query($se_query, $game->home_team, $game->game_id));
			
			if(!$home) {
				$home = array(
					'score_for' => 'not entered',
					'score_against' => 'not entered',
					'spirit' => 'not entered',
				);
			}
			
			$away = db_fetch_array(db_query($se_query, $game->away_team, $game->game_id));
			if(!$away) {
				$away = array(
					'score_for' => 'not entered',
					'score_against' => 'not entered',
					'spirit' => 'not entered',
				);
			}

			$rows[] = array(
				"Home Score:", $home['score_for'], "Home Score:", $away['score_against']
			);
			
			$rows[] = array(
				"Away Score:", $home['score_against'], "Away Score:", $away['score_for']
			);
			
			$rows[] = array(
				"Away SOTG:", $home['spirit'], "Home SOTG:", $away['spirit']
			);
		}
		
		$output = para("The following games have not been finalized.");
		$output .= "<div class='listtable'>" . table( $header, $rows ) . "</div>";

		league_add_to_menu($this, $league);
		return $output;
	}
}

class LeagueMemberStatus extends Handler
{
	function initialize ()
	{
		$this->title = "League Member Status";

		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny'
		);
		return true;
	}

	function process ()
	{
		global $session;

		$id = arg(2);

		if( !$id ) {
			$this->error_exit("You must provide a league ID");
		}
		$league = league_load( array('league_id' => $id) );

		$player_id = arg(3);

		if( !$player_id ) {
			if( !($session->is_admin() || $session->is_coordinator_of($id)) ) {
				$this->error_exit("You cannot add a person to that league");
			}

			$this->setLocation(array( $league->fullname => "league/view/$league->id", $this->title => 0));
			$ops = array(
				array( 'name' => 'view', 'target' => 'person/view/'),
				array( 'name' => 'add coordinator', 'target' => "league/member/$league->league_id/")
			);
			$query = "SELECT CONCAT(lastname, ', ', firstname) AS value, user_id AS id FROM person WHERE (class = 'administrator' OR class = 'volunteer') AND lastname LIKE '%s%%' ORDER BY lastname, firstname";
			return 
				para("Select the player you wish to add as league coordinator")
				. $this->generateAlphaList($query, $ops, 'lastname', 'person', "league/member/$league->league_id", $_GET['letter']);
		}

		if( !$session->is_admin() && $player_id == $session->attr_get('user_id') ) {
			$this->error_exit("You cannot add or remove yourself as league coordinator");
		}

		$player = person_load( array('user_id' => $player_id) );
		
		switch($_GET['edit']['status']) {
			case 'remove':
				if( ! $league->remove_coordinator($player) ) {
					$this->error_exit("Failed attempting to remove coordinator from league");
				}
				break;
			default:
				if($player->class != 'administrator' && $player->class != 'volunteer') {
					$this->error_exit("Only volunteer-class players can be made coordinator");
				}
				if( ! $league->add_coordinator($player) ) {
					$this->error_exit("Failed attempting to add coordinator to league");
				}
				break;
		}

		if( ! $league->save() ) {
			$this->error_exit("Failed attempting to modify coordinators for league");
		}
		
		local_redirect(url("league/view/$league->league_id"));
	}
}

/*
 * TODO: Make this go away by cleaning up the standings calculation.
 * PHP doesn't have the Perlish comparisons of cmp and <=>
 * so we fake a numeric cmp() here.
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
