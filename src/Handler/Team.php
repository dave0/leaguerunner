<?php
/*
 * Code for dealing with teams
 */
function team_dispatch()
{
	global $lr_session;
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'create':
			return new TeamCreate;
			break;
		case 'list':
			return new TeamList;
			break;
		case 'edit':
			$obj = new TeamEdit;
			break;
		case 'view':
			$obj = new TeamView;
			break;
		case 'delete':
			$obj = new TeamDelete;
			break;
		case 'move':
			$obj = new TeamMove;
			break;
		case 'roster':
			$obj = new TeamRosterStatus;
			$player_id = arg(3);
			if( $player_id ) {
				$obj->player = person_load( array('user_id' => arg(3)) );
			}
			break;
		case 'request':
			$obj = new TeamRosterRequest;
			$player_id = arg(3);
			if( $player_id ) {
				$obj->player = person_load( array('user_id' => arg(3)) );
			}
			break;
		case 'schedule':
			$obj = new TeamSchedule;
			break;
		case 'spirit':
			$obj = new TeamSpirit;
			break;
		case 'emails':
			$obj = new TeamEmails;
			break;
		case 'ical':
			$obj = new TeamICALSchedule;
			break;
		default:
			$obj = null;
	}
	$obj->team = team_load( array('team_id' => $id) );
	if( ! $obj->team ) {
		error_exit('That team does not exist');
	}
	team_add_to_menu( $obj->team );
	return $obj;
}

function team_permissions ( &$user, $action, $id, $data_field )
{
	switch( $action )
	{
		case 'create':
			// Players can create teams at-will
			return ($user && $user->is_active());
		case 'list':
		case 'view':
			// Everyone can list and view if they're a player
			return ($user && $user->is_active());
		case 'view schedule':
			return true;
		case 'edit':
		    if( $data_field == 'home_field' ) {
				return ($user && $user->is_admin());
			}
			return ($user && $user->is_captain_of( $id ) );
		case 'player shirts':
			if( $user && $user->coordinates_league_containing( $id ) ) {
				return true;
			}
			break;
		case 'email':
			if( $user && $user->is_captain_of( $id ) ) {
				return true;
			}
			if( $user && $user->coordinates_league_containing( $id ) ) {
				return true;
			}
			break;
		case 'player status':
			if( $user && $user->is_captain_of( $id ) ) {
				// Captain can adjust status of other players
				return true;
			}
			if( $user && $user->user_id == $data_field ) {
				// Player can adjust status of self
				return true;
			}
			break;
		case 'delete':
			if( $user && $user->is_captain_of( $id ) ) {
				return true;
			}
			break;
		case 'move':
			if( $user && $user->coordinates_league_containing( $id ) ) {
				return true;
			}
			break;
		case 'statistics':
			// admin-only
			break;
		case 'spirit':
			return ($user && $user->is_player_on( $id ));
	}
	return false;
}

function team_menu()
{
	global $lr_session;

	if( ! $lr_session->is_player() ) {
		return;
	}

	menu_add_child('_root','team','Teams', array('weight' => -8));
	menu_add_child('team','team/list','list teams', array('link' => 'team/list') );
	menu_add_child('team','team/create','create team', array('link' => 'team/create', 'weight' => 1) );

	if( $lr_session->is_valid() ) {
		while(list(,$team) = each($lr_session->user->teams) ) {
			team_add_to_menu($team);
		}
		reset($lr_session->user->teams);
	}
	if( count($lr_session->user->historical_teams) ) {
		menu_add_child('team','person/historical','my historical teams', array('link' => "person/historical/{$lr_session->user->user_id}", 'weight' => 5) );
	}

	if($lr_session->has_permission('team','statistics')) {
		menu_add_child('statistics', 'statistics/team', 'team statistics', array('link' => 'statistics/team'));
	}
}

/**
 * Add view/edit/delete links to the menu for the given team
 */
function team_add_to_menu( &$team ) 
{
	global $lr_session;

	// Now that team names aren't unique, we need a unique id for the menu
	$menu_name = $team->name . $team->team_id;

	menu_add_child('team', $menu_name, $team->name, array('weight' => -10, 'link' => "team/view/$team->team_id"));
	menu_add_child($menu_name, "$menu_name/standings",'standings', array('weight' => -1, 'link' => "league/standings/$team->league_id/$team->team_id"));
	menu_add_child($menu_name, "$menu_name/schedule",'schedule', array('weight' => -1, 'link' => "team/schedule/$team->team_id"));

	if( $lr_session->user && !array_key_exists( $team->team_id, $lr_session->user->teams ) ) {
		if($team->status != 'closed') {
			menu_add_child($menu_name, "$menu_name/join",'join team', array('weight' => 0, 'link' => "team/roster/$team->team_id/" . $lr_session->attr_get('user_id')));
		}
	} 

	menu_add_child($menu_name, "$menu_name/spirit", "spirit", array('weight' => 1, 'link' => "team/spirit/$team->team_id"));

	if( $lr_session->has_permission('team','edit',$team->team_id)) {
		menu_add_child($menu_name, "$menu_name/edit",'edit team', array('weight' => 1, 'link' => "team/edit/$team->team_id"));
		menu_add_child($menu_name, "$menu_name/add",'add player', array('weight' => 0, 'link' => "team/roster/$team->team_id"));
	}

	if( $lr_session->has_permission('team','email',$team->team_id)) {
		menu_add_child($menu_name, "$menu_name/emails",'player emails', array('weight' => 2, 'link' => "team/emails/$team->team_id"));
	}

	if( $lr_session->has_permission('team','delete',$team->team_id)) {
		menu_add_child($menu_name, "$menu_name/delete",'delete team', array('weight' => 1, 'link' => "team/delete/$team->team_id"));
	}

	if( $lr_session->has_permission('team','move',$team->team_id)) {
		menu_add_child($menu_name, "$menu_name/move",'move team', array('weight' => 1, 'link' => "team/move/$team->team_id"));
	}
}

/**
 * Generate view of teams for initial login splash page.
 */
function team_splash ()
{
	global $lr_session;
	$rows = array();
	$rows[] = array('','', array( 'data' => '','width' => 90), '');

	$rosterPositions = getRosterPositions();
	$rows = array();
	foreach($lr_session->user->teams as $team) {
		$position = $rosterPositions[$team->position];

		$rows[] =
			array(
				l($team->name, "team/view/$team->id") . " ($team->position)",
				array('data' => theme_links(array(
						l("schedule", "team/schedule/$team->id"),
                  l("standings", "league/standings/$team->league_id/$team->team_id"))),
					  'align' => 'right')
		);

	}
	reset($lr_session->user->teams);
	if( count($lr_session->user->teams) < 1) {
		$rows[] = array( array('colspan' => 2, 'data' => 'You are not yet on any teams'));
	}
	if( count($lr_session->user->historical_teams) ) {
		$rows[] = array( array('colspan' => 2, 'data' => 'You have ' . l('historical team data', "person/historical/{$lr_session->user->user_id}") . ' saved'));
	}
	return table( array( array('data' => 'My Teams', colspan => 2),), $rows);
}


/**
 * Team create handler
 */
class TeamCreate extends TeamEdit
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','create');
	}

	function process ()
	{
		global $lr_session;

		$this->title = "Create Team";
		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$this->team = new Team;
				$this->team->league_id = 1;	// inactive teams
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->team = new Team;
				$this->team->league_id = 1;	// inactive teams
				$this->perform($edit);
				local_redirect(url("team/view/" . $this->team->team_id));
				break;
			default:
				if( variable_get('registration', 0) && ! $lr_session->is_admin() ) {
					$mail = l(variable_get('app_admin_name', 'Leaguerunner Administrator'),
								'mailto:' . variable_get('app_admin_email','webmaster@localhost'));
					$rc = para(theme_error("Team creation is currently suspended, as this is integrated with team registration. If you need a team created for some other reason (e.g. a touring team), please email $mail with the details, or call the office."));
				}
				else {
					$edit = array();
					$rc = $this->generateForm( $edit );
				}
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function perform ($edit = array() )
	{
		global $lr_session, $dbh;

		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if( ! parent::perform($edit) ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT INTO leagueteams (league_id, team_id) VALUES(?, ?)');
		$sth->execute( array(1, $this->team->team_id) );
		if( 1 != $sth->rowCount() ) {
			return false;
		}

		# TODO: Replace with $team->add_player($lr_session->user,'captain')
		#       and call before parent::perform()
		$sth = $dbh->prepare('INSERT INTO teamroster (team_id, player_id, status, date_joined) VALUES(?, ?, ?, NOW())');
		$sth->execute( array($this->team->team_id, $lr_session->attr_get('user_id'), 'captain'));
		if( 1 != $sth->rowCount() ) {
			return false;
		}

		return true;
	}
}

/**
 * Team edit handler
 */
class TeamEdit extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','edit',$this->team->team_id);
	}

	function process ()
	{
		$this->title = "Edit Team";
		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->perform($edit);
				local_redirect(url("team/view/" . $this->team->team_id));
				break;
			default:
				$edit = object2array($this->team);
				$rc = $this->generateForm($edit);
		}
		$this->setLocation(array($edit['name']  => "team/view/" . $this->team->team_id, $this->title => 0));
		return $rc;
	}

	function generateForm (&$formData)
	{
		global $lr_session;

		$output = form_hidden("edit[step]", 'confirm');

		$rows = array();
		$rows[] = array("Team Name:", form_textfield('', 'edit[name]', $formData['name'], 35,200, "The full name of your team.  Text only, no HTML"));
		$rows[] = array("Website:", form_textfield('', 'edit[website]', $formData['website'], 35,200, "Your team's website (optional)"));
		$rows[] = array("Shirt Colour:", form_textfield('', 'edit[shirt_colour]', $formData['shirt_colour'], 35,200, "Shirt colour of your team.  If you don't have team shirts, pick 'light' or 'dark'"));

		if( $lr_session->has_permission('team','edit', $this->team->team_id, 'home_field')) {
			$rows[] = array("Home Field", form_textfield('','edit[home_field]', $formData['home_field'], 3,3,"Home field, if you happen to have one"));

		}

		$rows[] = array("Region Preference", form_select('','edit[region_preference]', $formData['region_preference'], getOptionsFromEnum('field', 'region'), "Area of city where you would prefer to play"));

		$rows[] = array("Team Status:",
			form_select("", "edit[status]", $formData['status'], getOptionsFromEnum('team','status'), "Is your team open (others can join) or closed (only captain can add players)"));

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		return form($output);
	}

	function generateConfirm ($edit = array() )
	{
		global $lr_session;
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes");
		$output .= form_hidden("edit[step]", 'perform');

		$rows[] = array("Team Name:", form_hidden('edit[name]',$edit['name']) .  $edit['name']);
		$rows[] = array("Website:", form_hidden('edit[website]',$edit['website']) .  $edit['website']);
		$rows[] = array("Shirt Colour:", form_hidden('edit[shirt_colour]',$edit['shirt_colour']) .  $edit['shirt_colour']);

		if( $lr_session->has_permission('team','edit', $this->team->team_id, 'home_field')) {
			$rows[] = array("Home Field:", form_hidden('edit[home_field]',$edit['home_field']) .  $edit['home_field']);
		}

		$rows[] = array("Region Preference:", form_hidden('edit[region_preference]',$edit['region_preference']) .  $edit['region_preference']);

		$rows[] = array("Team Status:", form_hidden('edit[status]',$edit['status']) .  $edit['status']);
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));

		return form($output);
	}

	function perform ($edit = array())
	{
		global $lr_session;
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$this->team->set('name', $edit['name']);
		$this->team->set('website', $edit['website']);
		$this->team->set('shirt_colour', $edit['shirt_colour']);
		$this->team->set('status', $edit['status']);
		if( $lr_session->has_permission('team','edit', $this->team->team_id, 'home_field')) {
			$this->team->set('home_field', $edit['home_field']);
		}
		$this->team->set('region_preference', $edit['region_preference']);

		if( !$this->team->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = '';

		if( !validate_nonhtml($edit['name']) ) {
			$errors .= '<li>You must enter a valid team name';
		}
		else if( !$this->team->validate_unique($edit['name']) ) {
			$errors .= '<li>You must enter a unique team name';
		}

		if( !validate_nonhtml($edit['shirt_colour']) ) {
			$errors .= '<li>Shirt colour cannot be left blank';
		}

		if(validate_nonblank($edit['website'])) {
			if( ! validate_nonhtml($edit['website']) ) {
				$errors .= '<li>If you provide a website URL, it must be valid. Otherwise, leave the website field blank.';
			}
		}

		if(strlen($errors) > 0) {
			return "<ul>$errors</ul>";
		} else {
			return false;
		}
	}
}

class TeamDelete extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','delete',$this->team->team_id);
	}

	function process ()
	{
		$this->title = "Delete Team";

		$this->setLocation(array(
			$this->team->name => "team/view/" . $this->team->team_id,
			$this->title => 0
		));

		switch($_POST['edit']['step']) {
			case 'perform':
				if ( $this->team->delete() ) {
					local_redirect(url("league/view/1"));
				} else {
					error_exit("Failure deleting team");
				}
				break;
			case 'confirm':
			default:
				return $this->generateConfirm();
				break;
		}
		error_exit("Error: This code should never be reached.");
	}

	function generateConfirm ()
	{
		global $dbh;
		$rows = array();
		$rows[] = array("Team Name:", check_form($this->team->name, ENT_NOQUOTES));
		if($this->team->website) {
			$rows[] = array("Website:", l($this->team->website, $this->team->website));
		}
		$rows[] = array("Shirt Colour:", check_form($this->team->shirt_colour, ENT_NOQUOTES));
		$rows[] = array("League/Tier:", l($this->team->league_name, "league/view/" . $this->team->league_id));

		$rows[] = array("Team Status:", $this->team->status);

		/* and, grab roster */
		$sth = $dbh->prepare('SELECT COUNT(r.player_id) as num_players FROM teamroster r WHERE r.team_id = ?');
		$sth->execute( array( $this->team->team_id) );

		$rows[] = array("Num. players on roster:", $sth->fetchColumn());

		$output = form_hidden('edit[step]', 'perform');
		$output .= "<p>Do you really wish to delete this team?</p>";
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= form_submit('submit');

		return form($output);
	}
}

class TeamMove extends Handler
{
	var $team;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','move',$this->team->team_id);
	}

	function process ()
	{
		global $lr_session;

		# Nuke HTML just in case
		$team_name = check_form($this->team->name, ENT_NOQUOTES);
		$this->setLocation(array(
			$team_name => "team/view/" . $this->team->team_id,
			"Move Team" => 0));

		$edit = $_POST['edit'];
		if( $edit['step'] ) {
			if($edit['target'] < 1) {
				error_exit("That is not a valid league to move to");
			}

			if( ! $lr_session->has_permission('league','manage teams', $edit['target']) ) {
				error_exit("Sorry, you cannot move teams to leagues you do not coordinate");
			}

			$targetleague = league_load( array('league_id' => $edit['target']));
			if( !$targetleague ) {
				error_exit("You must supply a valid league to move to");
			}

			if( $targetleague->league_id == $this->team->league_id ) {
				error_exit("You can't move a team to the league it's currently in!");
			}
		}

		if( $edit['swaptarget'] ) {
			$target_team = team_load( array('team_id' => $edit['swaptarget'] ) );
			if( !$target_team ) {
				error_exit("You must supply a valid target team ID");
			}

			if( $target_team->league_id == $this->team->league_id ) {
				error_exit("You can't swap with a team that's already in the same league!");
			}

			if( $target_team->league_id != $targetleague->league_id ) {
				error_exit("You can't swap with a team that's not in the league you want to move to!");
			}

			if( ! $lr_session->has_permission('league','manage teams', $target_team->league_id ) ) {
				error_exit("Sorry, you cannot move teams to leagues you do not coordinate");
			}
		}

		switch($edit['step']) {
			case 'perform':
				$sourceleague = league_load( array('league_id' => $this->team->league_id));
				$this->perform($targetleague, $target_team);
				local_redirect(url("league/view/" . $sourceleague->league_id));
			case 'confirm':
				return $this->confirm( $targetleague, $target_team);
			case 'swaptarget':
				return $this->choose_swaptarget($targetleague);
			default:
				return $this->choose_league();
		}

		error_exit("Error: This code should never be reached.");

	}

	function perform ($targetleague, $target_team)
	{
		global $lr_session;

		$rc = null;
		if( $target_team ) {
			$rc = $this->team->swap_team_with( $target_team );
		} else {
         $newrank = 0;
         if ($targetleague->schedule_type == 'pyramid') {
            // Default rank for pyramid league!
            $newrank = 1000;
         }
			$rc = $this->team->move_team_to( $targetleague->league_id, $newrank );
		}

		if( !$rc  ) {
			error_exit("Couldn't move team between leagues");
		}
		return true;
	}

	function confirm ( $targetleague, $target_team )
	{
		$output .= form_hidden('edit[step]', 'perform');
		$output .= form_hidden('edit[target]', $targetleague->league_id);

		if( $target_team ) {
			$output .= form_hidden('edit[swaptarget]', $target_team->team_id);
		}

		$sourceleague = league_load( array('league_id' => $this->team->league_id));
		$output .= para(
			"You are attempting to move the team <b>" . $this->team->name . "</b> to <b>$targetleague->fullname</b>");
		if( $target_team ) {
			$output .= para("This team will be swapped with <b>$target_team->name</b>, which will be moved to <b>$sourceleague->fullname</b>.");
			$output .= para("Both teams' schedules will be adjusted so that each team faces any opponents the other had been scheduled for");
		}
		$output .= para("If this is correct, please click 'Submit' below.");
		$output .= form_submit("Submit");
		return form($output);
	}

	function choose_swaptarget ( $targetleague )
	{
		$output = form_hidden('edit[step]', 'confirm');
		$output .= form_hidden('edit[target]', $targetleague->league_id);
		$output .= para("You are attempting to move the team <b>" . $this->team->name . "</b> to <b>$targetleague->fullname</b>.");
		$output .= para("Using the list below, you may select a team to replace this one with. If chosen, the two teams will be swapped between leagues.  Any future games already scheduled will also be swapped so that each team takes over the existing schedule of the other");

		$teams = $targetleague->teams_as_array();
		$teams[0] = "No swap, just move";
		ksort($teams);
		reset($teams);

		$output .= form_select('', 'edit[swaptarget]', '', $teams);
		$output .= form_submit("Submit");
		$output .= form_reset("Reset");

		return form($output);
	}

	function choose_league ( )
	{
		global $lr_session, $dbh;

		$leagues = array();
		$leagues[0] = '-- select from list --';
		if( $lr_session->is_admin() ) { 
			# TODO: league_load?
			$sth = $dbh->prepare("
				SELECT
					league_id as theKey,
					IF(tier,CONCAT(name,' Tier ',IF(tier>9,tier,CONCAT('0',tier))), name) as theValue
				FROM league
				WHERE league.status = 'open'
				ORDER BY season,TheValue,tier");
			$sth->execute();
			while($row = $sth->fetch()) {
				$leagues[$row['theKey']] = $row['theValue'];
			}
		} else {
			$leagues[1] = 'Inactive Teams';
			foreach( $lr_session->user->leagues as $league ) {
				$leagues[$league->league_id] = $league->fullname;
			}
		}

		$output = form_hidden('edit[step]', 'swaptarget');
		$output .=
			para("You are attempting to move the team <b>" . $this->team->name . "</b>. Select the league you wish to move it to");

		$output .= form_select('', 'edit[target]', '', $leagues);
		$output .= form_submit("Submit");
		$output .= form_reset("Reset");

		return form($output);
	}
}

/**
 * Team list handler
 */
class TeamList extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','list');
	}

	function process ()
	{
		global $lr_session, $dbh;
		$ops = array(
			array(
				'name' => 'view',
				'target' => 'team/view/'
			),
		);
		if($lr_session->has_permission('team','delete')) {
			$ops[] = array(
				'name' => 'delete',
				'target' => 'team/delete/'
			);
		}

		$this->setLocation(array("List Teams" => 'team/list'));

		$letter = arg(2);
		$sth = $dbh->prepare("SELECT DISTINCT UPPER(SUBSTRING(t.name,1,1)) as letter
			FROM team t 
			LEFT JOIN leagueteams lt ON t.team_id = lt.team_id 
			LEFT JOIN league l       ON lt.league_id = l.league_id
			WHERE l.status = 'open'
			ORDER BY letter asc");
		$sth->execute();
		$letters = $sth->fetchAll(PDO::FETCH_COLUMN);
		if(!isset($letter)) {
			$letter = 'A';
		}

		$letterLinks = array();
		foreach($letters as $curLetter) {
			if($curLetter == $letter) {
				$letterLinks[] = "<b>$curLetter</b>";
			} else {
				$letterLinks[] = l($curLetter, url("team/list/$curLetter"));
			}
		}
		$output = para(theme_links($letterLinks, "&nbsp;&nbsp;"));
		$dbParams[] = $letter;
		$query = "SELECT
				t.name AS value,
				t.team_id AS id
			FROM team t 
			LEFT JOIN leagueteams lt ON t.team_id = lt.team_id 
			LEFT JOIN league l       ON lt.league_id = l.league_id
			WHERE l.status = 'open'
			AND
				t.name LIKE ?
			ORDER BY t.name";
		$output .= $this->generateSingleList($query, $ops, array("$letter%"));
		return $output;
	}
}

/**
 * Player status handler
 */
class TeamRosterStatus extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','player status',$this->team->team_id, $this->player->user_id);
	}

	/**
	 * Loads the permittedStates variable, and checks that the session user is
	 * allowed to change the state of the specified player on this team.
	 */
	function loadPermittedStates ($teamId, $playerId)
	{
		global $lr_session, $dbh;

		$is_captain = false;
		$is_administrator = false;

		if($lr_session->attr_get('class') == 'administrator') {
			$is_administrator = true;
		}

		if($lr_session->is_captain_of($teamId)) {
			$is_captain = true;
		}

		/* Ordinary player can only set things for themselves */
		if(!($is_captain  || $is_administrator)) {
			$allowed_id = $lr_session->attr_get('user_id');
			if($allowed_id != $playerId) {
				error_exit("You cannot change status for that player ID");
			}
		}

		/* Now, check for the player's status, or set 'none' if
		 * not currently on team.
		 */
		$sth = $dbh->prepare('SELECT status FROM teamroster WHERE team_id = ? AND player_id = ?');
		$sth->execute( array( $teamId, $playerId) );
		$this->currentStatus = $sth->fetchColumn();

		if(!$this->currentStatus) {
			$this->currentStatus = 'none';
		}

		/*
		 * Sets the permissions for a state change.
		 * 	- check who user is.  Captain and administrator can:
		 * 		- 'none' -> 'captain_request'
		 *	 	- 'player_request' -> 'none', 'player' or 'substitute'
		 *	 		- 'player' -> 'captain', 'substitute', 'none'
		 * 		- 'substitute' -> 'captain', 'player', 'none'
		 * 		- 'captain' -> 'player', 'substitute', 'none'
		 * 	  in addition, administrator can go from anything to anything.
		 * 	  Players are allowed to (for their own player_id):
		 * 	  	- 'none' -> 'player_request'
		 * 	  	- 'captain_request' -> 'none', 'player' or 'substitute'
		 * 	  	- 'player_request' -> 'none'
		 * 	  	- 'player' -> 'substitute', 'none'
		 * 	  	- 'substitute' -> 'none'
		 */
		$this->permittedStates = array();
		if($is_administrator) {
			/* can't change to current value, but all others OK */
			$this->permittedStates = array_keys($this->positions);
			array_splice($this->permittedStates, array_search($this->currentStatus, $this->permittedStates), 1);

		} else if ($is_captain) {
			$this->permittedStates = $this->getStatesForCaptain($teamId);
		} else {
			$this->permittedStates = $this->getStatesForPlayer($teamId);
		}

		return true;
	}

	function getStatesForCaptain($id)
	{
		global $dbh;
		switch($this->currentStatus) {
		case 'captain':
			$sth = $dbh->prepare('SELECT COUNT(*) FROM teamroster where status = ? AND team_id = ?');
			$sth->execute( array('captain', $id));

			if($sth->fetchColumn() <= 1) {
				error_exit("All teams must have at least one player with captain status.");
			}

			return array( 'none', 'coach', 'assistant', 'player', 'substitute');
		case 'coach':
			return array( 'none', 'captain', 'assistant', 'player', 'substitute');
		case 'assistant':
			return array( 'none', 'coach', 'captain', 'player', 'substitute');
		case 'player':
			return array( 'none', 'coach', 'captain', 'assistant', 'substitute');
		case 'substitute':
			return array( 'none', 'coach', 'captain', 'assistant', 'player');
		case 'captain_request':
			/* Captains cannot move players from this state,
			 * except to remove them.
			 */
			return array( 'none' );
		case 'player_request':
			return array( 'none', 'coach', 'captain', 'assistant', 'player', 'substitute');
		case 'none':
			return array( 'captain_request' );
		default:
			error_exit("Internal error in player status");
		}
	}

	function getStatesForPlayer($id)
	{
		global $dbh;
		switch($this->currentStatus) {
		case 'captain':
			$sth = $dbh->prepare('SELECT COUNT(*) FROM teamroster WHERE status = ? AND team_id = ?');
			$sth->execute( array('captain', $id));

			if($sth->fetchColumn() <= 1) {
				error_exit("All teams must have at least one player with captain status.");
			}

			return array( 'none', 'coach', 'assistant', 'player', 'substitute');
		case 'coach':
			return array( 'none', 'captain', 'assistant', 'player', 'substitute');
		case 'assistant':
			return array( 'none', 'player', 'substitute');
		case 'player':
			return array( 'none', 'substitute');
		case 'substitute':
			return array( 'none' );
		case 'captain_request':
			return array( 'none', 'player', 'substitute');
		case 'player_request':
			return array( 'none' );
		case 'none':
			$sth = $dbh->prepare('SELECT status FROM team WHERE team_id = ?');
			$sth->execute( array( $id ));
			if($sth->fetchColumn() != 'open') {
				error_exit("Sorry, this team is not open for new players to join");
			}
			return array( 'player_request' );
		default:
			error_exit("Internal error in player status");
		}
	}

	function process ()
	{
		global $lr_session;

		$this->title = "Roster Status";

		if( $this->team->roster_deadline > 0 &&
			!$lr_session->is_admin() &&
			time() > $this->team->roster_deadline )
		{
			return para( 'The roster deadline has passed.' );
		}
		$this->positions = getRosterPositions();
		$this->currentStatus = null;

		if( !$this->player ) {
			if( !($lr_session->is_admin() || $lr_session->is_captain_of($this->team->team_id))) {
				error_exit("You cannot add a person to that team!");
			}

			$this->setLocation(array( $this->team->name => "team/view/" . $this->team->team_id, "Add Player" => 0));

			$new_handler = new PersonSearch;
			$new_handler->initialize();
			$new_handler->ops['Add to ' . $this->team->name] = 'team/roster/' .$this->team->team_id . '/%d';
			return $new_handler->process();
		}

		$this->loadPermittedStates($this->team->team_id, $this->player->user_id);
		$edit = &$_POST['edit'];

		if($this->player->status != 'active' && $edit['status'] && $edit['status'] != 'none') {
			error_exit("Inactive players may only be removed from a team.  Please contact this player directly to have them activate their account.");
		}
		if(!$this->player->is_member() && !$lr_session->is_admin()) {
			error_exit('Only registered players can be added to a team.');
		}

		if( $edit['step'] == 'perform' ) {
				if($this->perform($edit)) {
					local_redirect(url("team/view/" . $this->team->team_id));
				} else {
					return false;
				}
		} else {
				$rc = $this->generateForm();
		}

		return $rc;
	}

	function formPrompt()
	{
		$output = para("You are attempting to change player status for <b>" . $this->player->fullname . "</b> on team <b>" . $this->team->name . "</b>.");
		$output .= para("Current status: <b>" . $this->positions[$this->currentStatus] . "</b>");

		return $output;
	}

	function generateForm ()
	{
		$this->setLocation(array( $this->team->name => "team/view/" . $this->team->team_id, $this->title => 0));

		$output .= form_hidden('edit[step]', 'perform');

		$output .= $this->formPrompt();

		$options = "";
		foreach($this->permittedStates as $state) {
			$options .= form_radio($this->positions[$state], 'edit[status]', $state);
		}
		reset($this->permittedStates);

		$output .= para("Choices are:<br />$options");

		$output .= para( form_submit('submit') . form_reset('reset') );

		return form($output);
	}

	function perform ( $edit )
	{
		global $lr_session, $dbh;

		/* To be valid:
		 *  - ID and player ID required (already checked by the
		 *    has_permission code)
		 *  - status variable set to a valid value
		 */
		if( ! in_array($edit['status'], $this->permittedStates) ) {
			error_exit("You do not have permission to set that status.");
		}

		/* Perms already checked, so just do it */
		// TODO: this belongs in classes/team.inc
		if($this->currentStatus != 'none') {
			switch($edit['status']) {
			case 'coach':
			case 'captain':
			case 'assistant':
			case 'player':
			case 'substitute':
			case 'captain_request':
			case 'player_request':
				$sth = $dbh->prepare('UPDATE teamroster SET status = ? WHERE team_id = ? AND player_id = ?');
				$sth->execute( array($edit['status'], $this->team->team_id, $this->player->user_id) );
				break;
			case 'none':
				$sth = $dbh->prepare('DELETE FROM teamroster WHERE team_id = ? AND player_id = ?');
				$sth->execute( array($this->team->team_id, $this->player->user_id));
				break;
			default:
				error_exit("Cannot set player to that state.");
			}
			if( 1 != $sth->rowCount() ) {
				return false;
			}
		} else {
			switch($edit['status']) {
			case 'coach':
			case 'captain':
			case 'assistant':
			case 'player':
			case 'substitute':
			case 'captain_request':
			case 'player_request':
				$sth = $dbh->prepare('INSERT INTO teamroster VALUES(?,?,?,NOW())');
				$sth->execute( array($this->team->team_id, $this->player->user_id, $edit['status']));
				if( 1 != $sth->rowCount() ) {
					return false;
				}
				break;
			default:
				error_exit("Cannot set player to that state.");
			}
		}

		if( variable_get( 'generate_roster_email', 0 ) ) {
			if( $edit['status'] == 'captain_request') {
				$variables = array( 
					'%fullname' => $this->player->fullname,
					'%userid' => $this->player->user_id,
					'%captain' => $lr_session->user->fullname,
					'%teamurl' => url("team/view/{$this->team->team_id}"),
					'%team' => $this->team->name,
					'%league' => $this->team->league_name,
					'%day' => $this->team->league_day,
					'%adminname' => variable_get('app_admin_name', 'Leaguerunner Admin'),
					'%site' => variable_get('app_org_name','league'));
				$message = _person_mail_text('captain_request_body', $variables);

				$rc = send_mail($this->player->email, $this->player->fullname,
					false, false, // from the administrator
					false, false, // no Cc
					_person_mail_text('captain_request_subject', $variables), 
					$message);
				if($rc == false) {
					error_exit("Error sending email to " . $this->person->email);
				}
			}
			else if( $edit['status'] == 'player_request') {

				// Find the list of captains and assistants for the team
				if( variable_get('postnuke', 0) ) {
					$sth = $dbh->prepare("SELECT
								firstname,
								lastname,
								n.pn_email as email,
								r.status
							FROM
								person p
							LEFT JOIN
								nuke_users n
							ON
								p.user_id = n.pn_uid
							LEFT JOIN
								teamroster r
							ON
								p.user_id = r.player_id
							WHERE
								team_id = ?
							AND
								(
									r.status = 'captain'
								OR
									r.status = 'assistant'
								)");
				} else {
					$sth = $dbh->prepare("SELECT
								firstname,
								lastname,
								email,
								r.status
							FROM
								person p
							LEFT JOIN
								teamroster r
							ON
								p.user_id = r.player_id
							WHERE
								team_id = %d
							AND
								(
									r.status = 'captain'
								OR
									r.status = 'assistant'
								)");
				}
				$sth->execute( array( $this->team->team_id) );

				$captains = array();
				$captain_names = array();
				$assistants = array();
				$assistant_names = array();
				while( $row = $sth->fetch(PDO::FETCH_OBJ) ) {
					if( $row->status == 'captain' ) {
						$captains[] = $row->email;
						$captain_names[] = "$row->firstname $row->lastname";
					} else {
						$assistants[] = $row->email;
						$assistant_names[] = "$row->firstname $row->lastname";
					}
				}

				$variables = array( 
					'%fullname' => $this->player->fullname,
					'%userid' => $this->player->user_id,
					'%captains' => join(',', $captain_names),
					'%teamurl' => url("team/view/{$this->team->team_id}"),
					'%team' => $this->team->name,
					'%league' => $this->team->league_name,
					'%day' => $this->team->league_day,
					'%adminname' => variable_get('app_admin_name', 'Leaguerunner Admin'),
					'%site' => variable_get('app_org_name','league'));
				$message = _person_mail_text('player_request_body', $variables);

				$rc = send_mail($captains, $captain_names,
					false, false, // from the administrator
					$assistants, $assistant_names,
					_person_mail_text('player_request_subject', $variables), 
					$message);
				if($rc == false) {
					error_exit("Error sending email to team captains");
				}
			}
		}

		return true;
	}
}

/**
 * Handler for forced roster updates, can't check prereqs, because that's
 * how we got here in the first place!
 */
class TeamRosterRequest extends TeamRosterStatus
{
	function checkPrereqs( $next )
	{
		return false;
	}

	function formPrompt()
	{
		return para("You have been invited to join the team <b>{$this->team->name}</b>. To ensure up-to-date rosters, you must either accept or decline this invitation. Please select your desired level of participation on this team from the list below:");
	}
}

class TeamView extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view', $this->team->team_id);
	}

	function process ()
	{
		global $lr_session, $dbh;

		// Team names might have HTML in them, so we need to nuke it.
		$team_name = check_form($this->team->name, ENT_NOQUOTES);
		$this->setLocation(array(
			$team_name => "team/view/" . $this->team->team_id,
			"View Team" => 0));

		// Now build up team data
		$rows = array();
		if($this->team->website) {
			$rows[] = array("Website:", l($this->team->website, $this->team->website));
		}
		$rows[] = array("Shirt Colour:", check_form($this->team->shirt_colour, ENT_NOQUOTES));
		$rows[] = array("League/Tier:", l($this->team->league_name, "league/view/" . $this->team->league_id));

// TONY: we don't care anymore about the rank, but instead of deleting this right away,
// keep it around in case we go back to using the old pyramid ladder system...
// only show the rank selectively because in this view, we can only show the backend database rank,
// which is near 1000, and not useful for people to see...
//      if($this->team->rank && $lr_session->is_admin()) {
//			$rows[] = array("Ranked:", $this->team->rank);
//		}

		if($this->team->home_field) {
			$field = field_load(array('fid' => $this->team->home_field));
			$rows[] = array("Home Field:", l($field->fullname,"field/view/$field->fid"));
		}

		if($this->team->region_preference) {
			$rows[] = array("Region preference:", $this->team->region_preference);
		}

		$rows[] = array("Team Status:", $this->team->status);

		/* Spence Balancing Factor:
		 * Average of all score differentials.  Lower SBF means more
		 * evenly-matched games.
		 */
		$teamSBF = $this->team->calculate_sbf( );
		if( $teamSBF ) {
			$league = league_load( array('league_id' => $this->team->league_id) );
			$leagueSBF = $league->calculate_sbf();
			if( $leagueSBF ) {
				$teamSBF .= " (league $leagueSBF)";
			}
			$rows[] = array("Team SBF:", $teamSBF);
		}
		$rows[] = array("Rating:", $this->team->rating);

		$teamdata = "<div class='pairtable'>" . table(null, $rows) . "</div>";

		/* and, grab roster */
		// TODO: turn this into $team->get_roster()
		$sth = $dbh->prepare(
			"SELECT
				p.user_id as id,
				CONCAT(p.firstname, ' ', p.lastname) as fullname,
				p.gender,
				p.shirtsize,
				p.skill_level,
				p.status AS player_status,
				r.status
			FROM
				teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				r.team_id = ?
			ORDER BY r.status, p.gender, p.lastname");
		$sth->execute(array($this->team->team_id));

		$header = array( 'Name', 'Position', 'Gender','Rating' );
		if( $lr_session->has_permission('team','player shirts', $this->team->team_id) ) {
			array_push($header, 'Shirt Size');
		}
		$rows = array();
		$totalSkill = 0;
		$skillCount = 0;
		$rosterCount = 0;
		$rosterPositions = getRosterPositions();
		while($player = $sth->fetch(PDO::FETCH_OBJ) ) {

			/* 
			 * Now check for conflicts.  Players who are subs get
			 * conflicts ignored, but not others.
			 *
			 * TODO: This is time-consuming and resource-inefficient.
			 * TODO: Turn this into $team->check_roster_conflicts()
			 */
			$c_sth = $dbh->prepare("SELECT COUNT(*) from
					league l, leagueteams t, teamroster r
				WHERE
					l.year = ? AND l.season = ? AND l.day = ?
					AND r.status != 'substitute'
					AND l.schedule_type != 'none'
					AND l.league_id = t.league_id 
					AND l.status = 'open'
					AND t.team_id = r.team_id
					AND r.player_id = ?");
			$c_sth->execute(array(
				$this->team->league_year,
				$this->team->league_season,
				$this->team->league_day,
				$player->id
			));

			if($c_sth->fetchColumn() > 1) {
				$conflictText = "(roster conflict)";
			} else {
				$conflictText = null;
			}

			if($player->player_status == "inactive" ) {
				if($conflictText) {
					$conflictText .= "<br />(account inactive)";
				} else {
					$conflictText .= "(account inactive)";
				}
			}

			$player_name = l($player->fullname, "person/view/$player->id");
			if( $conflictText ) {
				$player_name .= "<div class='roster_conflict'>$conflictText</div>";
			}

			if($lr_session->has_permission('team','player status', $this->team->team_id, $player->id) ) {
				$roster_info = l($rosterPositions[$player->status], "team/roster/" . $this->team->team_id . "/$player->id");
			} else {
				$roster_info = $rosterPositions[$player->status];
			}
			if( $player->status == 'captain' ||
				$player->status == 'assistant' ||
				$player->status == 'player'
			) {
				++$rosterCount;
			}

			$row = array(
				$player_name,
				$roster_info,
				$player->gender,
				$player->skill_level
			);
			if( $lr_session->has_permission('team','player shirts', $this->team->team_id) ) {
				array_push($row, $player->shirtsize);
			}
			$rows[] = $row;

			$totalSkill += $player->skill_level;
			if ($player->skill_level) {
				$skillCount ++;
			}
		}

		if($skillCount > 0) {
			$avgSkill = sprintf("%.2f", ($totalSkill / $skillCount));
		} else {
			$avgSkill = 'N/A';
		}
		$rows[] = array(
			array('data' => 'Average Skill Rating', 'colspan' => 3),
			$avgSkill
		);

		$rosterdata = "<div class='listtable'>" . table($header, $rows) . "</div>";

		if( variable_get('narrow_display', '0') ) {
			$rc = $teamdata . '<p />' . $rosterdata;
		} else {
			$rc = table(null, array(
				array( $teamdata, $rosterdata ),
			));
		}

		if( $rosterCount < 12 && $lr_session->is_captain_of($this->team->team_id) && $this->team->roster_deadline > 0 ) {
			$rc .= "<p><p class='error'>Your team currently has only $rosterCount full-time players listed. Your team roster must be completed (minimum of 12 rostered players) by the team roster deadline (" . strftime ('%Y-%m-%d', $this->team->roster_deadline) . "), and all team members must be listed as a 'regular player'.  If an individual has not replied promptly to your request to join, we suggest that you contact them to remind them to respond.</p>";
		}

		return $rc;
	}
}

/**
 * Team schedule viewing handler
 */
class TeamSchedule extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view schedule', $this->team->team_id);
	}

	function process ()
	{
		global $lr_session;
		$this->title = "Schedule";
		$this->setLocation(array(
			$this->team->name => "team/view/" . $this->team->team_id,
			$this->title => 0));

		/*
		 * Grab schedule info
		 */
		$games = game_load_many( array( 'either_team' => $this->team->team_id, '_order' => 'g.game_date,g.game_start,g.game_id') );

		if( !is_array($games) ) {
			error_exit("There are no games scheduled for this team");
		}

		$header = array(
			"Game",
			"Date",
			"Start",
			"End",
			"Opponent",
			array('data' => "Location",'colspan' => 2),
			array('data' => "Score",'colspan' => 2)
		);
		$rows = array();

		$empty_row_added = 0;
		$prev_game_id = 0;
		$countgames = 0;
		$numgames = count($games);
		$update_prev_game_id = 1;
		while(list(,$game) = each($games)) {
			$countgames++;
			$space = '&nbsp;';
			$dash = '-';
			if($game->home_id == $this->team->team_id) {
				$opponent_id = $game->away_id;
				$opponent_name = $game->away_name;
				$home_away = '(home)';
			} else {
				$opponent_id = $game->home_id;
				$opponent_name = $game->home_name;
				$home_away = '(away)';
			}

			if ($opponent_name == "") {
				$opponent_name = "(to be determined)";
			} else {
				$opponent_name = l($opponent_name, "team/view/$opponent_id");
			}

			$game_score = $space;
			$score_type = $space;

			if($game->is_finalized()) {
				/* Already entered */
				$score_type = '(accepted final)';
				if($game->home_id == $this->team->team_id) {
					$game_score = "$game->home_score - $game->away_score";
				} else {
					$game_score = "$game->away_score - $game->home_score";
				}
			} else {
				/* Not finalized yet, so we will either:
				 *   - display entered score if present
				 *   - display score entry link if game date has passed
				 *   - display a blank otherwise
				 */
				$entered = $game->get_score_entry( $this->team->team_id );
				if($entered) {
					$score_type = '(unofficial, waiting for opponent)';
					$game_score = "$entered->score_for - $entered->score_against";
				} else if($lr_session->has_permission('game','submit score', $game, $this->team)
					&& ($game->timestamp < time()) ) {
						$score_type = l("submit score", "game/submitscore/$game->game_id/" . $this->team->team_id);
				} else {
					$score_type = "&nbsp;";
				}
			}
			if($game->status == 'home_default' || $game->status == 'away_default') {
				$score_type .= " (default)";
			}

			// see if you're at the next dependant games (only for ladder!)
			if ( ($numgames - $countgames < 2) && ($game->home_id == "" || $game->away_id == "") ) {
				$update_prev_game_id = 0;
				if (!$empty_row_added) {
					$rows[] = array($dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash);
					$empty_row_added = 1;
				}
				if ($game->home_dependant_game == $prev_game_id) {
					if ($game->home_dependant_type == "winner") {
						$score_type = "<b>(if win $prev_game_id)</b>";
					} else {
						$score_type = "<b>(if lose $prev_game_id)</b>";
					}
				}
				if ($game->away_dependant_game == $prev_game_id) {
					if ($game->away_dependant_type == "winner") {
						$score_type = "<b>(if win $prev_game_id)</b>";
					} else {
						$score_type = "<b>(if lose $prev_game_id)</b>";
					}
				}
			}
			$field = field_load(array('fid' => $game->fid));
			$rows[] = array(
				l($game->game_id, "game/view/$game->game_id"),
				strftime('%a %b %d %Y', $game->timestamp),
				$game->game_start,
				$game->game_end,
				$opponent_name,
				l($game->field_code, "field/view/$game->fid", array('title' => $field->fullname)),
				$home_away,
				$game_score,
				$score_type
			);

			if ($update_prev_game_id) {
				$prev_game_id = $game->game_id;
			}
		}
		// add another row of dashes when you're done.
		$rows[] = array($dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash);

		// add iCal link
		$ical_url = url("team/ical/".$this->team->team_id);

		return "<div class='schedule'>" . table($header,$rows, array('alternate-colours' => true) ) . "</div>"
		  . para("Get your team schedule in "
		  . "<a href=\"$ical_url/team.ics\"><img style=\"display: inline\" src=\"/images/misc/ical.gif\" alt=\"iCal\" /></a>"
		  . " format or <a href=\"http://www.google.com/calendar/render?cid=$ical_url\" target=\"_blank\"><img style=\"display: inline; vertical-align: middle\" src=\"http://www.google.com/calendar/images/ext/gc_button6.gif\" alt=\"Add to Google Calendar\"></a>");
	}
}

class TeamSpirit extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view', $this->team->team_id);
	}

	function process ()
	{
		global $lr_session, $dbh, $FILE_URL;
		$this->title = "Team Spirit";

		$this->setLocation(array(
			$this->team->name => "team/spirit/". $this->team->team_id,
			$this->title => 0));

		/*
		 * Grab schedule info
		 */
		$games = game_load_many( array( 'either_team' => $this->team->team_id, '_order' => 'g.game_date') );

		if( !is_array($games) ) {
			error_exit("There are no games scheduled for this team");
		}

		$header = array(
			"ID",
			"Date",
			"Opponent"
		);

		$rows = array();

		// TODO load all point values for answers into array
		$answer_values = array();
		$sth = $dbh->prepare('SELECT akey, value FROM multiplechoice_answers');
		$sth->execute();
		while( $ary = $sth->fetch() ) {
			$answer_values[ $ary['akey'] ] = $ary['value'];
		}

		// load the league
		$league = league_load( array('league_id' => $this->team->league_id) );

		$question_sums = array();
		$num_games = 0;
		$no_spirit_questions = 0;
		$sotg_scores = array();

		foreach($games as $game) {

			if( ! $game->is_finalized() ) {
				continue;
			}

			$spirit = 10;

			if($game->home_id == $this->team->team_id) {
				$opponent_id = $game->away_id;
				$opponent_name = $game->away_name;
				$home_away = '(home)';
				$spirit = $game->home_spirit;
			} else {
				$opponent_id = $game->home_id;
				$opponent_name = $game->home_name;
				$home_away = '(away)';
				$spirit = $game->away_spirit;
			}

			$thisrow = array(
				l($game->game_id, "game/view/$game->game_id"),
				strftime('%a %b %d %Y', $game->timestamp),
				l($opponent_name, "team/view/$opponent_id")
			);


			# Fetch spirit answers for games
			$entry = $game->get_spirit_entry( $this->team->team_id );
			if( !$entry ) {
				continue;
			}

			if( !$num_games ) {
				$header[] = "Score";
			}

			// get_spirit_numeric looks at the SOTG answers to determine the score
			$numeric = $game->get_spirit_numeric( $this->team->team_id );
			// but, now we want to use the home/away assigned spirit...
			// so, see if there is a value in $spirit, otherwise, use $numeric:
			if ($spirit == null || $spirit == "") {
				$spirit = $numeric;
			}
			$thisrow[] = sprintf("%.2f",$spirit);
			$score_total += $spirit;
			$sotg_scores[] = $spirit;

			while( list($qkey,$answer) = each($entry) ) {

				if( !$num_games ) {
					if( variable_get('narrow_display', '0') ) {
						$h = preg_replace( '/([a-z])([A-Z])/', '$1 $2', $qkey );
					} else {
						$h = $qkey;
					}
					if( $qkey == 'CommentsToCoordinator') {
						if ($lr_session->has_permission('league', 'view', $this->team->league_id, 'spirit') ) {
							$header[] = $h;
						} else {
							$header[] = '&nbsp;';
						}
					} else {
						$header[] = $h;
					}
				}
				if( $qkey == 'CommentsToCoordinator' ) {
					// can only see comments if you're a coordinator
					if( $lr_session->has_permission('league', 'view', $this->team->league_id, 'spirit') ) {
						$thisrow[] = $answer;
					} else {
						$thisrow[] = '&nbsp;';
					}
					continue;
				}
				if ($answer == null || $answer == "") {
					$thisrow[] = "?";
					$no_spirit_questions++;
				} else {
					switch( $answer_values[$answer] ) {
						case -3:
						case -2:
							$thisrow[] = "<img src='$FILE_URL/misc/x.png' />";
							break;
						case -1:
							$thisrow[] = "-";
							break;
						case 0:
							$thisrow[] = "<img src='$FILE_URL/misc/check.png' />";
							break;
						default:
							$thisrow[] = "?";
					}
					$question_sums[ $qkey ] += $answer_values[ $answer ];
				}
			}

			$num_games++;

			// if the person doesn't have permission to see this team's spirit, don't print this row.
			if( !$lr_session->has_permission('team', 'view', $this->team->team_id, 'spirit') ) {
				continue;
			}

			// if the league is not allowing spirit to be viewed, skip this row (unless this is a coordinator)
			if ( !$lr_session->is_coordinator_of( $this->team->league_id ) && $league->see_sotg == "false" ) {
				continue;
			}

			$rows[] = $thisrow;
		}

		if( !$num_games ) {
			error_exit("No games played, cannot display spirit");
		}

		$thisrow = array(
			"Average","-","-"
		);

		//$thisrow[] = sprintf("%.2f",$score_total / $num_games );
		$thisrow[] = sprintf("%.2f", calculateAverageSOTG($sotg_scores, true) );

		reset($question_sums);
		foreach( $question_sums as $qkey => $answer) {
			$avg = ($answer / ($num_games - $no_spirit_questions));
			if( $avg < -1.5 ) {
				$thisrow[] = "<img src='$FILE_URL/misc/x.png' />";
			} else if ( $avg < -0.5 ) {
				$thisrow[] = "-";
			} else {
				$thisrow[] = "<img src='$FILE_URL/misc/check.png' />";
			}
		}
		$thisrow[] = '';
		$rows[] = $thisrow;

		$style = '#main table td { font-size: 80% }';
		if( variable_get('narrow_display', '0') ) {
			$style .= ' th { font-size: 70%; }';
		}
		return "<style>$style</style>" . table($header,$rows, array('alternate-colours' => true) );
	}
}

class TeamEmails extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','email',$this->team->team_id);
	}

	function process ()
	{
		global $lr_session, $dbh;
		$this->title = 'Player Emails';
		$sth = $dbh->prepare('SELECT
				p.firstname, p.lastname, p.email
			FROM
				teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				r.team_id = ?
			AND
				p.user_id != ?
			ORDER BY
				p.lastname, p.firstname');
		$sth->execute( array( $this->team->team_id, $lr_session->user->user_id) );

		$emails = array();
		$names = array();
		while($user = $sth->fetch(PDO::FETCH_OBJ)) {
			$names[] = "$user->firstname $user->lastname";
			$emails[] = $user->email;
		}
		if( count($names) <= 0 ) {
			return false;
		}

		$team = team_load( array('team_id' => $this->team->team_id) );

		$this->setLocation(array(
			$team->name => "team/view/" . $this->team->team_id,
			$this->title => 0));

		$list = create_rfc2822_address_list($emails, $names, true);
		$output = para("You can cut and paste the emails below into your addressbook, or click " . l('here to send an email', "mailto:$list") . " right away.");

		$output .= pre($list);
		return $output;
	}
}

function team_statistics ( )
{
	global $dbh;
	$rows = array();

	$current_season = variable_get('current_season', 'Summer');

	$sth = $dbh->prepare('SELECT COUNT(*) FROM team');
	$sth->execute();
	$rows[] = array("Number of teams (total):", $sth->fetchColumn() );

	$sth = $dbh->prepare('SELECT l.season, COUNT(*) FROM leagueteams t, league l WHERE t.league_id = l.league_id AND l.status = "open" GROUP BY l.season');
	$sth->execute();
	$sub_table = array();
	while($row = $sth->fetch(PDO::FETCH_ASSOC) ) {
		$sub_table[] = $row;
	}
	$rows[] = array("Teams by season:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT t.team_id,t.name, COUNT(r.player_id) as size 
        FROM teamroster r, league l, leagueteams lt
        LEFT JOIN team t ON (t.team_id = r.team_id) 
        WHERE
                lt.team_id = r.team_id
                AND l.league_id = lt.league_id
				AND l.status = 'open'
                AND l.schedule_type != 'none'
				AND l.season = ?
                AND (r.status = 'player' OR r.status = 'captain' OR r.status = 'assistant')
        GROUP BY t.team_id
        HAVING size < 12
        ORDER BY size desc, t.name");
	$sth->execute( array($current_season) );
	$sub_table = array();
	$sub_sth = $dbh->prepare("SELECT COUNT(*) FROM teamroster r WHERE r.team_id = ? AND r.status = 'substitute'");
	while($row = $sth->fetch() ) {
		if( $row['size'] < 12 ) {
			$sub_sth->execute( array($row['team_id']) );
			$substitutes = $sub_sth->fetchColumn();
			if( ($row['size'] + floor($substitutes / 3)) < 12 ) {
				$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), ($row['size'] + floor($substitutes / 3)));
			}
		}
	}
	$rows[] = array("$current_season teams with too few players:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT t.team_id, t.name, t.rating
		FROM team t, league l, leagueteams lt
		WHERE
			lt.team_id = t.team_id
			AND l.league_id = lt.league_id
			AND l.status = 'open'
			AND l.schedule_type != 'none'
			AND l.season = ?
		ORDER BY t.rating DESC LIMIT 10");
	$sth->execute( array( $current_season ) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), $row['rating']);
	}
	$rows[] = array("Top-rated $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT t.team_id, t.name, t.rating
		FROM team t, league l, leagueteams lt
		WHERE
			lt.team_id = t.team_id
			AND l.league_id = lt.league_id
			AND l.status = 'open'
			AND l.schedule_type != 'none'
			AND l.season = ?
		ORDER BY t.rating ASC LIMIT 10");
	$sth->execute( array($current_season) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), $row['rating']);
	}
	$rows[] = array("Lowest-rated $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT COUNT(*) AS num,
			IF(s.status = 'home_default',s.home_team,s.away_team) AS team_id
		FROM schedule s, league l
		WHERE
			s.league_id = l.league_id
			AND l.status = 'open'
			AND l.season = ?
			AND (s.status = 'home_default' OR s.status = 'away_default')
		GROUP BY team_id ORDER BY num DESC");
	$sth->execute( array($current_season) );
	$sub_table = array();
	while($row = $sth->fetch()) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['num']);
	}
	$rows[] = array("Top defaulting $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT COUNT(*) AS num,
			IF(s.approved_by = -3,s.home_team,s.away_team) AS team_id
		FROM schedule s, league l
		WHERE
			s.league_id = l.league_id
			AND l.status = 'open'
			AND l.season = ?
			AND (s.approved_by = -2 OR s.approved_by = -3)
		GROUP BY team_id ORDER BY num DESC");
	$sth->execute( array($current_season) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['num']);
	}
	$rows[] = array("Top non-score-submitting $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT
		ROUND( AVG( IF( lt.team_id = s.home_team, s.home_spirit, s.away_spirit ) ), 2) AS avgspirit,
			lt.team_id AS team_id
		FROM league l, leagueteams lt, schedule s
		WHERE
			lt.league_id = l.league_id
			AND l.league_id = s.league_id
			AND s.league_id = lt.league_id
			AND l.status = 'open'
			AND l.season = ?
			AND (lt.team_id = s.home_team OR lt.team_id = s.away_team)
			AND s.approved_by
		GROUP BY team_id
		ORDER BY avgspirit DESC
		LIMIT 10");
	$sth->execute ( array ($current_season) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['avgspirit']);
	}
	$rows[] = array("Best spirited $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare("SELECT
		ROUND( AVG( IF( lt.team_id = s.home_team, s.home_spirit, s.away_spirit ) ), 2) AS avgspirit,
			lt.team_id AS team_id
		FROM league l, leagueteams lt, schedule s
		WHERE
			lt.league_id = l.league_id
			AND l.league_id = s.league_id
			AND s.league_id = lt.league_id
			AND l.status = 'open'
			AND l.season = '%s'
			AND (lt.team_id = s.home_team OR lt.team_id = s.away_team)
			AND s.approved_by
		GROUP BY team_id
		HAVING avgspirit < 10
		ORDER BY avgspirit ASC
		LIMIT 10");
	$sth->execute ( array ($current_season) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['avgspirit']);
	}
	$rows[] = array("Lowest spirited $current_season teams:", table(null, $sub_table));

	$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
	return form_group("Team Statistics", $output);
}


/**
 * RMK April 2008
 * Team schedule as ical handler
 */
class TeamICALSchedule extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view schedule', $this->team->team_id);
	}

	// Does not return, as we don't want normal LR theme output
	// Will output in target format (ical)
	function process ()
	{
		$my_team = $this->team->name;

		/*
		 * Grab schedule info 
		 */
		$games = game_load_many( array( 'either_team' => $this->team->team_id, '_order' => 'g.game_date DESC,g.game_start,g.game_id') );

		// We'll be outputting an ical
		header('Content-type: text/calendar; charset=UTF-8');
		// Prevent caching
		header("Cache-Control: no-cache, must-revalidate");

		// get league name for iCalendar name
		$short_league_name = variable_get('app_org_short_name', 'League');

		// get domain URL for signing games
		$arr = split('@',variable_get('app_admin_email',"@$short_league_name"));
		$domain_url = $arr[1];

		// ical header
		print utf8_encode("BEGIN:VCALENDAR
PRODID:-//Leaguerunner//Team Schedule//EN
VERSION:2.0
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:$my_team schedule from $short_league_name
");

		// TODO: add VTIMEZONE group


		while(list(,$game) = each($games)) {
			if($game->home_id == $this->team->team_id) {
				$opponent_id = $game->away_id;
				$opponent_name = $game->away_name;
				$home_away = '(home)';
			} else {
				$opponent_id = $game->home_id;
				$opponent_name = $game->home_name;
				$home_away = '(away)';
			}

			if ($opponent_name == "") {
				$opponent_name = "(to be determined)";
				$opponent_colour = "";
			} else {
				// look up opponent's shirt colour
				$opponent_team = team_load( array('team_id' => $opponent_id) );
				$opponent_colour = $opponent_team->shirt_colour;
			}

			// encode game start and end times
			$game_date = strftime('%Y%m%d', $game->timestamp); // from date type
			$game_start = $game_date . 'T' 
			  . join(explode(':', $game->game_start)) // from 'hh:mm' string
			  . '00';
			// HACK to fix games until dark
			if ($game->game_end == 'dark') {
				$game_end = $game_date . 'T210000'; // default 'dark' to 9pm
			} else {
				$game_end = $game_date . 'T' 
					. join(explode(':', $game->game_end))  // from 'hh:mm' string
					. '00';
			}

			// date stamp this file
			$now = gmstrftime('%Y%m%dT%H%M%SZ'); // MUST be in UTC

			// generate field url
			$field_url = url("field/view/$game->fid");

			// look up field's full name
			$field = field_load(array('fid' => $game->fid));

			// output game
			// TODO: need to track when games are created/modified
			print utf8_encode("BEGIN:VEVENT
UID:$game->game_id@$domain_url
DTSTAMP:$now
CREATED:20080101T000000Z
LAST-MODIFIED:20080101T000000Z
DTSTART:$game_start
DTEND:$game_end
LOCATION:$field->fullname ($game->field_code)
X-LOCATION-URL:$field_url
SUMMARY:$my_team vs. $opponent_name
DESCRIPTION:Game $game->game_id: $my_team vs. $opponent_name at $field->fullname ($game->field_code) on ".strftime('%a %b %d %Y', $game->timestamp)." $game->game_start to $game->game_end"
. ($opponent_colour ? " (they wear $opponent_colour)" : "") . "
X-OPPONENT-COLOUR:$opponent_colour
STATUS:CONFIRMED
TRANSP:OPAQUE
END:VEVENT
");

		}

		print "END:VCALENDAR\n";
		exit; // don't return, as we don't want the HTML printed
	}
}


?>
