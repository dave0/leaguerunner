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
		case '':
			return new LeagueList;
		case 'standings':
			return new LeagueStandings;
		case 'captemail':
			return new LeagueCaptainEmails;
		case 'moveteam':
			return new LeagueMoveTeam;
		case 'approvescores':
			return new LeagueApproveScores;
	}
	return null;
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
		$this->section = 'league';
		return true;
	}
	
	function process ()
	{
		$id = -1;
		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $edit );
				break;
			case 'perform':
				$this->perform( &$id, $edit );
				local_redirect(url("league/view/$id"));
				break;
			default:
				$rc = $this->generateForm( $id, array() );
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function perform ( $id, $edit )
	{
		global $session;
		
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		db_query("INSERT into league (name,coordinator_id) VALUES ('%s',%d)", trim($edit['name']), $session->attr_get('user_id'));
		
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		$id = db_result(db_query("SELECT LAST_INSERT_ID() from league"));
		return parent::perform( $id, $edit);
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
		$this->section = 'league';
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
		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $edit );
				break;
			case 'perform':
				$this->perform( $id, $edit );
				local_redirect(url("league/view/$id"));
				break;
			default:
				$edit = $this->getFormData( $id );
				$rc = $this->generateForm( $id, $edit );
		}
		$this->setLocation(array( $edit['name'] => "league/view/$id", $this->title => 0));

		return $rc;
	}

	function getFormData ( $id )
	{
		$league = league_load( array('league_id' => $id) );
		if( !$league ) {
			$this->error_exit("That league does not exist.");
		}
		
		/* Deal with multiple days and start times */
		if(strpos($league->day, ",")) {
			$league->day = split(",",$league->day);
		}
		return object2array($league);
	}

	function generateForm ( $id, $formData )
	{
		$output .= form_hidden("edit[step]", 'confirm');

		$rows = array();
		$rows[] = array("League Name:", form_textfield('', 'edit[name]', $formData['name'], 35,200, "The full name of the league.  Tier numbering will be automatically appended."));
		
		if($this->_permissions['edit_coordinator']) {

			$volunteers = array();
			$volunteers[0] = "---";

			$result = db_query(
				"SELECT
					p.user_id,
					CONCAT(p.firstname,' ',p.lastname) as name
				 FROM
					person p
				 WHERE
					p.class = 'volunteer'
					OR p.class = 'administrator'
				 ORDER BY p.lastname");
			while($vol = db_fetch_object($result)) {
				$volunteers[$vol->user_id] = $vol->name;
			}

			$rows[] = array("Coordinator",
				form_select("", "edit[coordinator_id]", $formData['coordinator_id'], $volunteers, "League Coordinator.  Must be set."));
			$rows[] = array("Assistant Coordinator",
				form_select("", "edit[alternate_id]", $formData['alternate_id'], $volunteers, "Assistant Coordinator (optional)"));
		}
		
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

		$rows[] = array("Regular Start Time:",
			form_select("", "edit[start_time]", $formData['start_time'], getOptionsFromTimeRange(900,2400,15), "Time at which games usually start in this league"));

		$rows[] = array("Allow Scheduling:",
			form_select("", "edit[allow_schedule]", $formData['allow_schedule'], getOptionsFromEnum('league','allow_schedule'), "Whether or not this league can have games scheduled and standings displayed."));
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		
		return form($output);
	}

	function generateConfirm ( $id, $edit )
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
		
		if($this->_permissions['edit_coordinator']) {
				$coord = person_load( array('user_id' => $edit['coordinator_id']) );
				$rows[] = array("Coordinator:",
					form_hidden("edit[coordinator_id]", $edit['coordinator_id']) . $coord->fullname);
			
				if($edit['alternate_id'] > 0) {
					$alt = person_load( array('user_id' => $edit['alternate_id']) );
					$rows[] = array("Assistant Coordinator:", 
						form_hidden("edit[alternate_id]", $edit['alternate_id']) . $alt->fullname);
				}
		}
		
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

		$rows[] = array("Regular Start Time(s):",
			form_hidden('edit[start_time]', $edit['start_time']) . $edit['start_time']);

		$rows[] = array("Allow Scheduling:",
			form_hidden('edit[allow_schedule]', $edit['allow_schedule']) . $edit['allow_schedule']);

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));

		return form($output);
	}

	function perform ( $id, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$fields      = array();
		$fields_data = array();

		if($this->_permissions['edit_info']) {
			$fields[] = "name = '%s'";
			$fields_data[] = $edit['name'];
			$fields[] = "day = '%s'";
			$fields_data[] = $edit['day'];
			$fields[] = "season = '%s'";
			$fields_data[] = $edit['season'];
			$fields[] = "tier = %d";
			$fields_data[] = $edit['tier'];
			$fields[] = "ratio = '%s'";
			$fields_data[] = $edit['ratio'];
			$fields[] = "current_round = %d";
			$fields_data[] = $edit['current_round'];
			$fields[] = "allow_schedule = '%s'";
			$fields_data[] = $edit['allow_schedule'];
			$fields[] = "start_time = '%s'";
			$fields_data[] = $edit['start_time'];
		}
		
		if($this->_permissions['edit_coordinator']) {
			$fields[] = "coordinator_id = '%d'";
			$fields_data[] = $edit['coordinator_id'];
			$fields[] = "alternate_id = '%d'";
			$fields_data[] = $edit['alternate_id'];
		}
			
		$sql = "UPDATE league SET ";
		$sql .= join(",", $fields);	
		$sql .= "WHERE league_id = %d";

		$fields_data[] = $id;

		db_query($sql, $fields_data);

		if(1 != db_affected_rows() ) {
			return false;
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

		if($this->_permissions['edit_coordinator']) {
				if($edit['coordinator_id'] <= 0) {
					$errors .= "<li>A coordinator must be selected";
				}
		}
		
		if( $edit['allow_schedule'] != 'Y' && $edit['allow_schedule'] != 'N' ) {
			$errors .= "<li>Values for allow schedule are Y and N";
		}

		if($edit['allow_schedule'] == 'Y') {
			if( !$edit['day'] ) {
				$errors .= "<li>One or more days of play must be selected";
			}
			if( !$edit['start_time'] ) {
				$errors .= "<li>A start time must be selected";
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
			'admin_sufficient',
			'allow'
		);
		$this->section = 'league';
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
			$season = 'none';
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

		$output = "";
		if($this->_permissions['create']) {
			$output .= para(l("create league", "league/create"));
		}
		
		$output .= para(theme_links($seasonLinks));

		$header = array( "Name", "Ratio", "&nbsp;") ;
		$rows = array();
		
		$result = db_query("SELECT l.*, IF(l.tier,CONCAT(l.name,' Tier ',l.tier),l.name) AS name FROM league l WHERE season = '%s' ORDER BY l.day, l.ratio, l.tier, name", $season);

		while($league = db_fetch_object($result)) {
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
			$rows[] = array($league->name,$league->ratio,theme_links($links));
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
			"view_spirit" => false,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);
		$this->section = 'league';
		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->_permissions['view_spirit'] = true;
		} else if($type == 'coordinator') {
			$this->_permissions['view_spirit'] = true;
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
	/**
	 * TODO: remove hacks for Elo rating, replace with proper support
	 */
	function generate_standings ($id, $current_round = 0)
	{
		$result = db_query(
				"SELECT t.team_id AS id, t.name
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
		
			if($season[$id]['games'] < 3 && !($this->_permissions['view_spirit'])) {
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
			'rating' => 1500,
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

			$homeRating = $data['rating'];
			$data['rating'] = $this->calculateRating($game['home_score'], $game['away_score'], $data['rating'], $season[$game['away_team']]['rating']);
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
			$data['rating'] = $this->calculateRating($game['away_score'], $game['home_score'], $data['rating'], $homeRating);
		}
	}

	/**
	 * Calculate new rating for team.  Modified Elo system, similar to the one
	 * used for international soccer (http://www.eloratings.net), with several
	 * Ultimate-specific modifications:
	 * 	- all games currently weighted equally (though playoff games will be
	 * 	  weighted differently in the future)
	 * 	- score differential bonus modified for Ultimate
	 * 	- no bonus given for 'home field advantage' since there's no
	 * 	  real advantage in OCUA.
	 */
	function calculateRating($scoreFor, $scoreAgainst, $oldRating, $opponentRating)
	{

		if(!isset($opponentRating)) {
			$opponentRating = 1500;
		}

		$weightConstant = 40;
	
		if($scoreFor > $scoreAgainst) {
			$gameValue = 1;
		} else if($scoreFor == $scoreAgainst) {
			$gameValue = 0.5;
		} else {
			$gameValue = 0;
		}

		$scoreWeight = 1;

		/* If the score differential is greater than 1/3 the 
		 * winning score, add a bonus.
		 * This means that the bonus is given in summer games of 15-10 or
		 * worse, and in indoor games with similar score ratios.
		 */
		$scoreDiff = abs($scoreFor - $scoreAgainst);
		$scoreMax  = max($scoreFor, $scoreAgainst);
		if(($scoreDiff / $scoreMax) > (1/3)) {
			$scoreWeight += $scoreDiff / $scoreMax;
		}

		$power = pow(10, ((0 - ($oldRating - $opponentRating)) / 400));
		$expectedWin = (1 / ($power + 1));

		$newRating = $oldRating + ($weightConstant * $scoreWeight * ($gameValue - $expectedWin));

		return round($newRating);
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
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);
		$this->section = 'league';
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

		$league = league_load( array('league_id' => $id ) );
		if( !$league ) {
			$this->error_exit("That league does not exist.");
		}

		$links = array();
		if($league->allow_schedule == 'Y') {
			$links[] = l("schedule", "schedule/view/$id");
			$links[] = l("standings", "league/standings/$id");
			if($this->_permissions['administer_league']) {
				$links[] = l("approve scores", "league/approvescores/$id");
			}
		}
		if($this->_permissions['administer_league']) {
			$links[] = l("edit info", "league/edit/$id");
			$links[] = l("fetch captain emails", "league/captemail/$id");
		}
		
		$output =  theme_links($links);
		$rows = array();
		$rows[] = array("Coordinator:", 
			l($league->coordinator_name, "person/view/$league->coordinator_id"));
		if($league->alternate_id) {
			$rows[] = array("Co-Coordinator:", 
				l($league->alternate_name, "person/view&/league->alternate_id"));
		}
		$rows[] = array("Season:", $league->season);
		$rows[] = array("Day(s):", $league->day);
		if($league->tier) {
			$rows[] = array("Tier:", $league->tier);
		}

		# Now, if this league should contain schedule info, grab it
		if($league->allow_schedule == 'Y') {
			$rows[] = array("Current Round:", $league->current_round);
			$rows[] = array("Usual Start Time:", $league->start_time);
			$rows[] = array("Maximum teams:", $league->max_teams);
			$rows[] = array("League SBF:", league_calculate_sbf($league->league_id));
		}
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";

		/* Now, fetch teams */
		$result = db_query(
			"SELECT 
			    t.*, ROUND(AVG(p.skill_level),2) AS skill
			 FROM 
			    leagueteams l 
				INNER JOIN teamroster r ON (r.team_id = l.team_id) 
				INNER JOIN team t ON (l.team_id = t.team_id) 
				INNER JOIN person p ON (r.player_id = p.user_id) 
			 WHERE l.league_id = %d GROUP BY l.team_id", $id);
		
		$header = array( "Team Name", "Shirt Colour", "Avg. Skill", "&nbsp;",);
		$rows = array();
		while($team = db_fetch_object($result)) {
			$team_links = array(
				l('view', "team/view/$team->team_id"),
			);
			if($team->status == 'open') {
				$team_links[] = l('join team', "team/roster/$team->team_id/" . $session->attr_get('user_id'));
			}
			if($this->_permissions['administer_league']) {
				$team_links[] = l('move team', "league/moveteam/$id/$team->team_id");
			}
			
			$rows[] = array(
				check_form($team->name),
				check_form($team->shirt_colour),
				$team->skill,
				theme_links($team_links)
			);
		}
		
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		
		$this->setLocation(array(
			$league->fullname => "league/view/$id",
			$this->title => 0));
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
		$this->section = 'league';
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
		$this->section = 'league';
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
		
		/* TODO: team_load() */
		$team = db_fetch_object(db_query("SELECT * FROM team WHERE team_id = %d",$teamId));
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

		/* TODO: team_load() */
		$teamName = db_result(db_query("SELECT name FROM team WHERE team_id = %d",$teamId));
		if(! $teamName ) {
			$this->error_exit("That is not a valid team");
		}

		$leagues = getOptionsFromQuery("SELECT league_id AS theKey, IF(tier,CONCAT(name, ' Tier ', tier), name) AS theValue FROM
		  		league l,
				person p
			WHERE
				l.league_id = 1 
				OR l.coordinator_id = %d
				OR l.alternate_id = %d 
				OR (p.class = 'administrator' AND p.user_id = %d)
			ORDER BY l.season,l.day,l.name,l.tier",
			array( $session->attr_get('user_id'), $session->attr_get('user_id'), $session->attr_get('user_id')));
		
		$output = form_hidden('edit[step]', 'confirm');
		$output .= 
			para("You are attempting to move the team <b>$teamName</b>. Select the league you wish to move it to")
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
		$this->title = "Approve Scores";
		$this->section = 'league';
		return true;
	}

	function process ()
	{
		$id = arg(2);
		
		$league = league_load( array('league_id' => $id) );
		if( !$league ) {
			$this->error_exit("That league does not exist.");
		}

		$this->setLocation(array(
			$league->fullname => "league/view/$id",
			$this->title => 0
		));

		$gameId = arg(3);
		if( !$gameId ) {
			return $this->listUnverifiedGames( $id );
		}

		$edit = $_POST['edit'];
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $gameId, &$edit );
				break;
			case 'perform':
				$this->perform( $id, $gameId, &$edit );
				break;
			default:
				$rc = $this->generateForm( $id, $gameId );
		}

		return $rc;
	}

	function listUnverifiedGames ( $id )
	{
		/* Now fetch games in need of verification */
		$result = db_query("SELECT DISTINCT
			se.game_id,
			UNIX_TIMESTAMP(s.date_played) as timestamp,
			s.home_team,
			h.name AS home_name,
			s.away_team,
			a.name AS away_name
			FROM schedule s, score_entry se
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
				array('data' => l("approve score", "league/approvescores/$id/$game->game_id"), 'rowspan' => 4)
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

		return $output;
	}
	
	function perform ( $leagueId, $gameId, $edit )
	{
		global $session;
	
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		switch($edit['defaulted']) {
		case 'home':
			$edit['home_score'] = 0;
			$edit['away_score'] = 6;
			$edit['home_sotg'] = 0;
			$edit['away_sotg'] = 0;
			break;
		case 'away':
			$edit['home_score'] = 6;
			$edit['away_score'] = 0;
			$edit['home_sotg'] = 0;
			$edit['away_sotg'] = 0;
			break;
		default:
			$edit['defaulted'] = 'no';
		}
		
		db_query("UPDATE schedule SET home_score = %d, away_score = %d, defaulted = '%s', home_spirit = %d, away_spirit = %d, approved_by = %d WHERE game_id = %d", 
			$edit['home_score'],
			$edit['away_score'],
			$edit['defaulted'],
			$edit['home_sotg'],
			$edit['away_sotg'],
			$session->attr_get('user_id'), 
			$gameId);

		if( 1 != db_affected_rows() ) {
			return false;
		}

		/* And remove any score_entry fields */
		db_query("DELETE FROM score_entry WHERE game_id = %d", $gameId);

		local_redirect(url("league/approvescores/$leagueId"));
	}

	function generateConfirm ( $leagueId, $gameId, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$result = db_query(
			"SELECT 
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team AS home_id,
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = %d", $gameId);
			 
		if( 1 != db_num_rows($result) ) {
			return false;
		}
			 
		$game = db_fetch_object($result);

		$datePlayed = strftime("%A %B %d %Y, %H%Mh",$game->timestamp);
		$output = para( "You have entered the following score for the $datePlayed game between $game->home_name and $game->away_name.  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the score.");

		$output .= form_hidden('edit[step]', 'perform');
		if($edit['defaulted'] == 'home' || $edit['defaulted'] == 'away') {
			$output .= form_hidden('edit[defaulted]', $edit['defaulted']);		
		} else {
			$output .= form_hidden('edit[home_score]', $edit['home_score']);		
			$output .= form_hidden('edit[away_score]', $edit['away_score']);		
			$output .= form_hidden('edit[home_sotg]', $edit['home_sotg']);		
			$output .= form_hidden('edit[away_sotg]', $edit['away_sotg']);		
		}
		
		if($edit['defaulted'] == 'home') {
			$edit['home_score'] = '0 (defaulted)';
			$edit['away_score'] = '6';
			$edit['home_sotg'] = 'n/a';
			$edit['away_sotg'] = 'n/a';
		} else if ($edit['defaulted'] == 'away') {
			$edit['home_score'] = '6';
			$edit['away_score'] = '0 (defaulted)';
			$edit['home_sotg'] = 'n/a';
			$edit['away_sotg'] = 'n/a';
		}
	
		$header = array( "Team", "Score", "SOTG");
		$rows = array(
			array($game->home_name, $edit['home_score'], $edit['home_sotg']),
			array($game->away_name, $edit['away_score'], $edit['away_sotg'])
		);
	
		$output .= '<div class="listtable">' . table($header, $rows) . "</div>";

		$output .= para(form_submit('submit'));

		return form($output);
	}

	function generateForm ( $leagueId, $gameId ) 
	{
		$result = db_query(
			"SELECT 
				s.game_id,
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team,
				h.name AS home_name, 
				s.away_team,
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = %d", $gameId);
			 
		if( 1 != db_num_rows($result) ) {
			return false;
		}

		$game = db_fetch_object($result);
		
		$output = para( "Finalize the score for <b>Game $gameId</b> of $datePlayed between <b>$game->home_name</b> and <b>$game->away_name</b>.");
		
		$output .= form_hidden('edit[step]', 'confirm');
		$output .= "<h2>Score as entered:</h2>";
		
		$output .= game_score_entry_display( $game );
		
		$output .= "<h2>Score as approved:</h2>";
		
		$rows = array();
		
		$rows[] = array(
			"$game->home_name (home) score:",
			form_textfield('','edit[home_score]','',2,2)
				. "or default: <input type='checkbox' name='edit[defaulted]' value='home' onclick='defaultCheckboxChanged()'>"
		);
		$rows[] = array(
			"$game->away_name (away) score:",
			form_textfield('','edit[away_score]','',2,2)
				. "or default: <input type='checkbox' name='edit[defaulted]' value='away' onclick='defaultCheckboxChanged()'>"
		);
		
		$rows[] = array(
			"$game->home_name (home) assigned spirit:",
			form_select("", "edit[home_sotg]", '', getOptionsFromRange(1,10))
		);
		$rows[] = array(
			"$game->away_name (away) assigned spirit:",
			form_select("", "edit[away_sotg]", '', getOptionsFromRange(1,10))
		);

		$output .= '<div class="pairtable">' . table(null, $rows) . '</div>';
		$output .= para(form_submit("submit") . form_reset("reset"));
	
		$script = <<<ENDSCRIPT
<script type="text/javascript"> <!--
  function defaultCheckboxChanged() {
    if (document.forms[0].elements['edit[defaulted]'][0].checked == true) {
        document.forms[0].elements['edit[home_score]'].value = '0';
        document.forms[0].elements['edit[home_score]'].disabled = true;
        document.forms[0].elements['edit[away_score]'].value = '6';
        document.forms[0].elements['edit[away_score]'].disabled = true;
        document.forms[0].elements['edit[home_sotg]'].disabled = true;
        document.forms[0].elements['edit[away_sotg]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][1].disabled = true;
    } else if (document.forms[0].elements['edit[defaulted]'][1].checked == true) {
        document.forms[0].elements['edit[home_score]'].value = '6';
        document.forms[0].elements['edit[home_score]'].disabled = true;
        document.forms[0].elements['edit[away_score]'].value = '0';
        document.forms[0].elements['edit[away_score]'].disabled = true;
        document.forms[0].elements['edit[home_sotg]'].disabled = true;
        document.forms[0].elements['edit[away_sotg]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][0].disabled = true;
    } else {
        document.forms[0].elements['edit[home_score]'].disabled = false;
        document.forms[0].elements['edit[away_score]'].disabled = false;
        document.forms[0].elements['edit[home_sotg]'].disabled = false;
        document.forms[0].elements['edit[away_sotg]'].disabled = false;
        document.forms[0].elements['edit[defaulted]'][0].disabled = false;
        document.forms[0].elements['edit[defaulted]'][1].disabled = false;
    }
  }
// -->
</script>
ENDSCRIPT;

		return $script . form($output);
	}

	function isDataInvalid( $edit )
	{
		$errors = "";

		if($edit['defaulted'] != 'home' && $edit['defaulted'] != 'away') {
			if( !validate_number($edit['home_score']) ) {
				$errors .= "<br>You must enter a valid number for the home score";
			}
			if( !validate_number($edit['away_score']) ) {
				$errors .= "<br>You must enter a valid number for the away score";
			}
			if( !validate_number($edit['home_sotg']) ) {
				$errors .= "<br>You must enter a valid number for the home SOTG";
			}
			if( !validate_number($edit['away_sotg']) ) {
				$errors .= "<br>You must enter a valid number for the away SOTG";
			}
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
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

/* League helper functions */

/**
 * Calculates the "Spence Balancing Factor" or SBF.
 * This is the average of all score differentials for games played 
 * to-date.  A lower value indicates a more evenly matched league.
 */
function league_calculate_sbf( $leagueId )
{
	return db_result(db_query("SELECT ROUND(AVG(ABS(s.home_score - s.away_score)),2) FROM schedule s WHERE s.league_id = %d", $leagueId));
}

/**
 * Load a single league object from the database using the supplied query
 * data.  If more than one league matches, we will return only the first one.
 * If fewer than one matches, we return null.
 *
 * @param	mixed 	$array key-value pairs that identify the league to be loaded.
 */
function league_load ( $array = array() )
{
	$query = array();

	foreach ($array as $key => $value) {
		if($key == '_extra') {
			/* Just slap on any extra query fields desired */
			$query[] = $value;
		} else {
			$query[] = "l.$key = '" . check_query($value) . "'";
		}
	}
	
	$result = db_query_range("SELECT 
		l.*,
		TIME_FORMAT(start_time,'%H:%i') AS start_time,
		CONCAT(c.firstname,' ',c.lastname) AS coordinator_name, 
		CONCAT(co.firstname,' ',co.lastname) AS alternate_name
		FROM league l
		LEFT JOIN person c ON (l.coordinator_id = c.user_id) 
		LEFT JOIN person co ON (l.alternate_id = co.user_id)
		WHERE " . implode(' AND ',$query),0,1);

	/* TODO: we may want to abort here instead */
	if(1 != db_num_rows($result)) {
		return null;
	}

	$league = db_fetch_object($result);

	/* set derived attributes */
	if($league->tier) {
		$league->fullname = "$league->name Tier $league->tier";
	} else {
		$league->fullname = $league->name;
	}

	return $league;
}

?>
