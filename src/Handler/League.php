<?php
/* 
 * Handle operations specific to leagues
 */

function league_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new LeagueCreate; // TODO
		case 'edit':
			return new LeagueEdit; // TODO
		case 'view':
			return new LeagueView; // TODO
		case 'list':
		case '':
			return new LeagueList; // TODO
		case 'standings':
			return new LeagueStandings; // TODO
		case 'captemail':
			return new LeagueCaptainEmails; // TODO
		case 'moveteam':
			return new LeagueMoveTeam; // TODO
		case 'verifyscores':
			return new LeagueVerifyScores; // TODO
		// TODO: The following should all be renamed, or moved back into
		// Schedule.php	
		case 'schedule_addweek':
			return new LeagueScheduleAddWeek; // TODO
		case 'schedule_edit':
			return new LeagueScheduleEdit; // TODO
		case 'schedule_view':
			return new LeagueScheduleView; // TODO
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
		$this->op = 'league_create';
		$this->section = 'league';
		return true;
	}

	/**
	 *  No data to fill in.
	 */
	function getFormData ( $id ) 
	{
		return array();
	}

	function perform ( $id )
	{
		global $session;
		$league_name = trim(var_from_getorpost("league_name"));
		
		db_query("INSERT into league (name,coordinator_id) VALUES ('%s',%d)", $league_name, $session->data['user_id']);
		
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		$id = db_result(db_query("SELECT LAST_INSERT_ID() from league"));
		return parent::perform( $id );
	}

	function isDataInvalid ()
	{
		$errors = "";
		
		$league_name = trim(var_from_getorpost("league_name"));
		if(0 == strlen($league_name)) {
			$errors .= "League name cannot be left blank<br>";
		}
	
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
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
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);

		$this->op = 'league_edit';
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
		$step = var_from_getorpost('step');

		$id = var_from_getorpost('id');
		
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				$this->perform( &$id );
				local_redirect("op=league_view&id=$id");
				break;
			default:
				$formData = $this->getFormData( $id );
				$rc = $this->generateForm( $id, $formData );
		}

		return $rc;
	}

	function getFormData ( $id )
	{
		$result = db_query(
			"SELECT 
				l.name as league_name,
				l.day  as league_day,
				l.season as league_season,
				l.tier as league_tier,
				l.ratio as league_ratio,
				l.max_teams as max_teams,
				l.coordinator_id,
				l.alternate_id,
				l.stats_display as stats_display,
				l.current_round as league_round,
				l.year,
				l.allow_schedule as league_allow_schedule,
				l.start_time as league_start_time
			FROM league l WHERE l.league_id = %d", $id);

		$formData = db_fetch_array($result);

		/* Deal with multiple days */
		if(strpos($formData['league_day'], ",")) {
			$formData['league_day'] = split(",",$formData['league_day']);
		}
		return $formData;
	}

	function generateForm ( $id, $formData )
	{
		$output = form_hidden("op", $this->op);
		$output .= form_hidden("step", 'confirm');
		$output .= form_hidden("id", $id);

		$rows = array();
		$rows[] = array("League Name:", form_textfield('', 'league_name', $formData['league_name'], 35,200, "The full name of the league.  Tier numbering will be automatically appended."));
		
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
				form_select("", "coordinator_id", $formData['coordinator_id'], $volunteers, "League Coordinator.  Must be set."));
			$rows[] = array("Assistant Coordinator",
				form_select("", "alternate_id", $formData['alternate_id'], $volunteers, "Assistant Coordinator (optional)"));
		}
		
		$rows[] = array("Season:", 
			form_select("", "league_season", $formData['league_season'], getOptionsFromEnum('league','season'), "Season of play for this league. Choose 'none' for administrative groupings and comp teams."));
			
		$rows[] = array("Day(s) of play:", 
			form_select("", "league_day", $formData['league_day'], getOptionsFromEnum('league','day'), "Day, or days, on which this league will play.", 0, true));
			
		/* TODO: 10 is a magic number.  Make it a config variable */
		$rows[] = array("Tier:", 
			form_select("", "league_tier", $formData['league_tier'], getOptionsFromRange(0, 10), "Tier number.  Choose 0 to not have numbered tiers."));
			
		$rows[] = array("Gender Ratio:", 
			form_select("", "league_ratio", $formData['league_ratio'], getOptionsFromEnum('league','ratio'), "Gender format for the league."));
			
		/* TODO: 5 is a magic number.  Make it a config variable */
		$rows[] = array("Current Round:", 
			form_select("", "league_round", $formData['league_round'], getOptionsFromRange(1, 5), "New games will be scheduled in this round by default."));

		$rows[] = array("Regular Start Time(s):",
			form_select("", "league_start_time", split(",",$formData['league_start_time']), getOptionsFromTimeRange(900,2400,15), "One or more times at which games will start in this league", "size=5", true));

		$rows[] = array("Allow Scheduling:",
			form_select("", "league_allow_schedule", $formData['league_allow_schedule'], getOptionsFromEnum('league','allow_schedule'), "Whether or not this league can have games scheduled and standings displayed."));
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		if($formData['league_name']) {
			$leagueName = $formData['league_name'];
			if($formData['league_tier']) {
				$leagueName .= " Tier " . $formData['league_tier'];
			}
			$this->setLocation(array(
				$leagueName => "op=league_view&id=$id",
				$this->title => 0));
		} else {
			$this->setLocation(array( $this->title => "op=" . $this->op));
		}
		

		return form($output);
	}

	function generateConfirm ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$league_name = var_from_getorpost('league_name');
		$league_season = var_from_getorpost('league_season');
		$league_day = join(",",var_from_getorpost('league_day'));
		$league_tier = var_from_getorpost('league_tier');
		$league_round = var_from_getorpost('league_round');
		$league_ratio = var_from_getorpost('league_ratio');
		$league_allow_schedule = var_from_getorpost('league_allow_schedule');
		$league_start_time = join(",",var_from_getorpost('league_start_time'));
		
		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");
		$output .= form_hidden("op", $this->op);
		$output .= form_hidden("step", 'perform');
		$output .= form_hidden("id", $id);

		$rows = array();
		$rows[] = array("League Name:", 
			form_hidden('league_name', $league_name) . $league_name);
		
		if($this->_permissions['edit_coordinator']) {
				$c_id = var_from_getorpost('coordinator_id');
				$c_name = db_result(db_query("SELECT CONCAT(p.firstname,' ',p.lastname) FROM person p WHERE p.user_id = %d",$c_id));
				
				$rows[] = array("Coordinator:",
					form_hidden("coordinator_id", $c_id) . $c_name);
			
				$a_id = var_from_getorpost('alternate_id');
				if($a_id > 0) {
					$a_name = db_result(db_query("SELECT CONCAT(p.firstname,' ',p.lastname) FROM person p WHERE p.user_id = %d",$a_id));
				} else {
					$a_name = "N/A";
				}
				$rows[] = array("Assistant Coordinator:", 
					form_hidden("alternate_id", $a_id) . $a_name);
		}
		
		$rows[] = array("Season:", 
			form_hidden('league_season', $league_season) . $league_season);
			
		$rows[] = array("Day(s) of play:", 
			form_hidden('league_day',$league_day) . $league_day);
			
		$rows[] = array("Tier:", 
			form_hidden('league_tier', $league_tier) . $league_tier);
			
		$rows[] = array("Gender Ratio:", 
			form_hidden('league_ratio', $league_ratio) . $league_ratio);
			
		$rows[] = array("Current Round:", 
			form_hidden('league_round', $league_round) . $league_round);

		$rows[] = array("Regular Start Time(s):",
			form_hidden('league_start_time', $league_start_time) . $league_start_time);

		$rows[] = array("Allow Scheduling:",
			form_hidden('league_allow_schedule', $league_allow_schedule) . $league_allow_schedule);

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));

		if($league_tier) {
			$league_name .= " Tier $league_tier";
		}
		
		$this->setLocation(array(
			$league_name => "op=league_view&id=$id",
			$this->title => 0));

		return form($output);
	}

	function perform ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$fields      = array();
		$fields_data = array();

		if($this->_permissions['edit_info']) {
			$fields[] = "name = '%s'";
			$fields_data[] = var_from_getorpost("league_name");
			$fields[] = "day = '%s'";
			$fields_data[] = var_from_getorpost("league_day");
			$fields[] = "season = '%s'";
			$fields_data[] = var_from_getorpost("league_season");
			$fields[] = "tier = %d";
			$fields_data[] = var_from_getorpost("league_tier");
			$fields[] = "ratio = '%s'";
			$fields_data[] = var_from_getorpost("league_ratio");
			$fields[] = "current_round = %d";
			$fields_data[] = var_from_getorpost("league_round");
			$fields[] = "allow_schedule = '%s'";
			$fields_data[] = var_from_getorpost("league_allow_schedule");
			$fields[] = "start_time = '%s'";
			$fields_data[] = var_from_getorpost("league_start_time");
		}
		
		if($this->_permissions['edit_coordinator']) {
			$fields[] = "coordinator_id = '%d'";
			$fields_data[] = var_from_getorpost("coordinator_id");
			$fields[] = "alternate_id = '%d'";
			$fields_data[] = var_from_getorpost("alternate_id");
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
	function isDataInvalid ()
	{
		$errors = "";

		$league_name = var_from_getorpost("league_name");
		if ( ! validate_nonhtml($league_name)) {
			$errors .= "<li>A valid league name must be entered";
		}

		if($this->_permissions['edit_coordinator']) {
				$coord_id = var_from_getorpost("coordinator_id");
				if($coord_id <= 0) {
					$errors .= "<li>A coordinator must be selected";
				}
		}
		
		$league_allow_schedule = var_from_getorpost("league_allow_schedule");
		if( $league_allow_schedule != 'Y' && $league_allow_schedule != 'N' ) {
			$errors .= "<li>Values for allow schedule are Y and N";
		}

		if($league_allow_schedule == 'Y') {
			$league_day = var_from_getorpost("league_day");
			if( !isset($league_day) ) {
				$errors .= "<li>One or more days of play must be selected";
			}
			$league_start_time = var_from_getorpost("league_start_time");
			if( !isset($league_start_time) ) {
				$errors .= "<li>One or more start times must be selected";
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
	/** 
	 * Initializer
	 *
	 * @access public
	 */
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

		$this->op = 'league_list';
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
		$wantedSeason = var_from_getorpost('season');
		if( ! isset($wantedSeason) ) {
			$wantedSeason = 'none';
		}
		
		/* Fetch league names */
		$seasonNames = array_values( getOptionsFromEnum('league', 'season') );
		/* TODO: getOptionsFromEnum prepends the '---' item for 
		 * denoting a <select> that hasn't had a selection made.
		 * We need to shift it off the front, as it's not needed here.
		 */
		array_shift($seasonNames);

		
		if( !in_array($wantedSeason, $seasonNames) ) {
			$this->error_exit("That is not a valid season"); 
		} else {
			$this->setLocation(array(
				$this->title => 'op=' . $this->op,
				$wantedSeason => 0
			));
		}

		$output = "";
		if($this->_permissions['create']) {
			$output .= para(l("create league", "op=league_create"));
		}
		$seasonLinks = array();
		foreach($seasonNames as $curSeason) {
			if($curSeason == $wantedSeason) {
				$seasonLinks[] = $curSeason;
			} else {
				$seasonLinks[] = l($curSeason, "op=$this->op&season=$curSeason");
			}
		}
		$output .= para(theme_links($seasonLinks));

		$header = array( "Name", "Ratio", "&nbsp;") ;
		$rows = array();
		
		$result = db_query("SELECT * FROM league WHERE season = '%s' ORDER BY day, ratio, tier, name", $wantedSeason);

		while($league = db_fetch_object($result)) {
			$name = $league->name;
			if($league->tier) { 
				$name .= " Tier $league->tier";
			}
			$links = array();
			$links[] = l('view',"op=league_view&id=$league->league_id");
			if($league->allow_schedule == 'Y') {
				$links[] = l('schedule',"op=league_schedule_view&id=$league->league_id");
				$links[] = l('standings',"op=league_standings&id=$league->league_id");
			}
			if($this->_permissions['delete']) {
				$links[] = l('delete',"op=league_delete&id=$league->league_id");
			}
			$rows[] = array($name,$league->ratio,theme_links($links));
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
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);

		$this->op = 'league_standings';
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
		$id = var_from_getorpost('id');
	
		$result = db_query("SELECT * FROM league l WHERE l.league_id = %d", $id);

		if(1 != db_num_rows($result)) {
			return false;
		}
		
		$league = db_fetch_object($result);
		
		if($league->allow_schedule == 'N') {
			$this->error_exit("This league does not have a schedule or standings.");
		}

		$round = var_from_getorpost('round');
		if(! isset($round) ) {
			$round = $league->current_round;
		}
		
		$leagueName = $league->name;
		if($league->tier) {
			$leagueName .= " Tier $league->tier";
		}
		$this->setLocation(array(
			$leagueName => "op=league_view&id=$id",
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

	function generate_standings ($id, $current_round = 0)
	{
		$result = db_query(
				"SELECT
					t.team_id AS id, t.name
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
			$row = array( l($data['name'], "op=team_view&id=$id"));

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
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'allow',
		);

		$this->op = 'league_view';
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

		$id = var_from_getorpost('id');
	
		$result = db_query(
			"SELECT l.*,
				CONCAT(c.firstname,' ',c.lastname) AS coordinator_name, 
				CONCAT(co.firstname,' ',co.lastname) AS alternate_name
			FROM 
				league l
				LEFT JOIN person c ON (l.coordinator_id = c.user_id) 
				LEFT JOIN person co ON (l.alternate_id = co.user_id)
			WHERE 
				l.league_id = %d", $id);
				
		if( 1 != db_num_rows($result) ) {
			return false;
		}
				
		$league = db_fetch_object($result);

		$links = array();
		if($league->allow_schedule == 'Y') {
			$links[] = l("schedule", "op=league_schedule_view&id=$id");
			$links[] = l("standings", "op=league_standings&id=$id");
			if($this->_permissions['administer_league']) {
				$links[] = l("approve scores", "op=league_verifyscores&id=$id");
			}
		}
		if($this->_permissions['administer_league']) {
			$links[] = l("edit info", "op=league_edit&id=$id");
			$links[] = l("fetch captain emails", "op=league_captemail&id=$id");
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
				l('view', "op=team_view&id=$team->team_id"),
			);
			if($team->status == 'open') {
				$team_links[] = l('join team', "op=team_playerstatus&id=$team->team_id&status=player_request&step=confirm");
			}
			if($this->_permissions['administer_league']) {
				$team_links[] = l('move team', "op=league_moveteam&id=$id&team_id=$team->team_id");
			}
			
			$rows[] = array(
				check_form($team->name),
				check_form($team->shirt_colour),
				$team->skill,
				theme_links($team_links)
			);
		}
		
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";

		$leagueName = $league->name;
		if($league->tier) {
			$leagueName .= " Tier " . $league->tier;
		}
		
		$this->setLocation(array(
			$leagueName => "op=league_view&id=$id",
			$this->title => 0));
		return $output;
	}
}

class LeagueCaptainEmails extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);
		$this->section = 'league';
		$this->title = 'Captain Emails';
		return true;
	}

	function process ()
	{
		$id = var_from_getorpost('id');

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

		/* Get league info */
		/* TODO: create a league_load() instead */
		$league = db_fetch_object(db_query("SELECT name,tier,season,ratio,year FROM league WHERE league_id = %d", $id));
		
		$leagueName = $league->name;
		if($league->tier) {
			$leagueName .= " Tier ". $league->tier;
		}
		$this->setLocation(array(
			$leagueName => "op=league_view&id=$id",
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
			'require_var:id',
			'require_var:team_id',
			'admin_sufficient',
			'coordinate_league_containing:team_id',
			'deny'
		);

		$this->op = 'league_moveteam';
		$this->section = 'league';
		$this->title = "Move Team";
		return true;
	}

	function process ()
	{
		$step = var_from_getorpost('step');

		$id = var_from_getorpost('id');
		$team_id = var_from_getorpost('team_id');
		
		if( !validate_number($id) ) {
			$this->error_exit("You must supply a valid league ID");
		}
		if( !validate_number($team_id) ) {
			$this->error_exit("You must supply a valid team ID");
		}
		
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $team_id );
				break;
			case 'perform':
				$this->perform( $id, $team_id );
				local_redirect("op=league_view&id=$id");
				break;
			default:
				$rc = $this->generateForm( $id, $team_id );
		}

		return $rc;
	}
	
	function perform ( $id, $team_id )
	{
		global $session;

		$target_id = var_from_getorpost('target_id');
		if($target_id < 1) {
			$this->error_exit("That is not a valid league to move to");
		}
		if( ! $session->is_coordinator_of($target_id) ) {
			$this->error_exit("Sorry, you cannot move teams to leagues you do not coordinate");
		}

		db_query("UPDATE leagueteams SET league_id = %d WHERE team_id = %d AND league_id = %d", $target_id, $team_id, $id);
		
		if( 1 != db_affected_rows() ) {
			$this->error_exit("Couldn't move team between leagues");
			return false;
		}

		return true;
	}

	function generateConfirm ( $id, $team_id )
	{
		global $session;

		$target_id = var_from_getorpost('target_id');
		if( ! $session->is_coordinator_of($target_id) ) {
			$this->error_exit("Sorry, you cannot move teams to leagues you do not coordinate");
		}

		/* TODO: create a league_load() instead */
		$result = db_query("SELECT * FROM league WHERE league_id = %d", $id);
		$from_league = db_fetch_array($result);
		
		if( ! $from_league ) {
			$this->error_exit("That is not a valid league to move from");
		}
		
		$from_name = $from_league['name'];
		if($from_league['tier']) {
			$from_name .= " Tier " . $from_league['tier'];
		}

		/* TODO: create a league_load() instead */
		$result = db_query("SELECT * FROM league WHERE league_id = %d", $target_id);
		$to_league = db_fetch_array($result);
		
		if( ! $to_league ) {
			$this->error_exit("That is not a valid league to move to");
		}
		$to_name = $to_league['name'];
		if($to_league['tier']) {
			$to_name .= " Tier " . $to_league['tier'];
		}

		$team_name = db_result(db_query("SELECT name FROM team WHERE team_id = %d",$team_id));
		
		if(! $team_name ) {
			$this->error_exit("That is not a valid team");
		}

		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('team_id', $team_id);
		$output .= form_hidden('target_id', $target_id);
		
		$output .= para( 
			"You are attempting to move the team <b>$team_name</b> "
			. "from <b>$from_name</b> to <b>$to_name</b>. "
			. "If this is correct, please click 'Submit' below."
		);

		$output .= form_submit("Submit");
		
		$this->setLocation(array(
			$from_name => "op=league_view&id=$id",
			$this->title => 0));
		

		return form($output);
	}
	
	function generateForm ( $id, $team_id)
	{
		global $session;

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

		$team_name = db_result(db_query("SELECT name FROM team WHERE team_id = %d",$team_id));
		
		if(! $team_name ) {
			$this->error_exit("That is not a valid team");
		}

		/* TODO: create a league_load() instead */
		$result = db_query("SELECT * FROM league WHERE league_id = %d", $id);
		$from_league = db_fetch_array($result);
		
		$from_name = $from_league['name'];
		if($from_league['tier']) {
			$from_name .= " Tier " . $from_league['tier'];
		}
		
		$this->setLocation(array(
			$from_name => "op=league_view&id=$id",
			$this->title => 0));
		
		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'confirm');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('team_id', $team_id);
		
		$output .= 
			para("You are attempting to move the team <b>"
				. $team_name . "</b>. "
				. "Select the league you wish to move it to")
			. form_select('', 'target_id', '', $leagues);

		$output .= form_submit("Submit");

		return form($output);
	}
}

class LeagueVerifyScores extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny'
		);

		$this->op = 'league_verifyscores';
		$this->title = "Verify Scores";
		$this->section = 'league';
		return true;
	}

	function process ()
	{

		$id = var_from_getorpost('id');
		
		if( !validate_number($id) ) {
			$this->error_exit("You must supply a valid league ID");
		}

		/* TODO: create a league_load() instead */
		$result = db_query("SELECT * FROM league WHERE league_id = %d", $id);
		$league = db_fetch_array($result);

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
				array('data' => l("finalize score", "op=game_finalize&id=" . $game->game_id), 'rowspan' => 4)
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

		$leagueName = $league['name'];
		if($league['tier'] > 0) {
			$leagueName .= " Tier ". $league['tier'];
		}
		$this->setLocation(array(
			$leagueName => "op=league_view&id=$id",
			$this->title => 0
		));
		
		return $output;
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

?>
