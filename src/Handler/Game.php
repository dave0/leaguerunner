<?php
/*
 * Handle operations specific to games
 */

function game_dispatch()
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'create':
			$obj = new GameCreate;
			$obj->league = league_load( array('league_id' => $id) );
			break;
		case 'submitscore':
			$obj = new GameSubmit;
			$obj->game = game_load( array('game_id' => $id) );
			$obj->team = team_load( array('team_id' => arg(3)) );
			$obj->league = league_load( array('league_id' => $obj->game->league_id) );
			break;
		case 'view':
		case 'approve':
		case 'edit':
			$obj = new GameEdit;
			$obj->game = game_load( array('game_id' => $id) );
			$obj->league = league_load( array('league_id' => $obj->game->league_id) );
			break;
		case 'delete':
			// Allow deletion of a game (not gameslot!)
			$obj = new GameDelete;
			$obj->game = game_load( array('game_id' => $id) );
			$obj->league = league_load( array('league_id' => $obj->game->league_id) );
			break;
		case 'removeresults':
			// Allow deletion of a game (not gameslot!)
			$obj = new GameRemoveResults;
			$obj->game = game_load( array('game_id' => $id) );
			$obj->league = league_load( array('league_id' => $obj->game->league_id) );
			break;
/* TODO:
		case 'reschedule':
			# TODO: move a game from one gameslot to another.
			#       Requires addition of a 'rescheduled' flag in db
			$obj = new GameReschedule;
			break;
 */
		case 'ratings':
			$obj = new GameRatings;
			$obj->game = game_load( array('game_id' => $id) );
			$obj->league = league_load( array('league_id' => $obj->game->league_id) );
			break;
		default:
			$obj = null;
	}
	if( $obj->team ) {
		team_add_to_menu( $obj->team );
	}
	if( $obj->league ) {
		league_add_to_menu( $obj->league );
	}
	if( $obj->game ) {
		game_add_to_menu( $obj->league, $obj->game );
	}

	return $obj;
}

function game_permissions ( &$user, $action, &$game, $extra )
{
	switch($action)
	{
		case 'submit score':
			if($extra) {
				if( $user && $user->is_captain_of($extra->team_id)) {
					// If we have a specific team in mind, this user must be a
					// captain to submit
					return true;
				} else {
					return false;
				}
			}
			if( $user && $user->is_captain_of( $game->home_team )
				|| $user->is_captain_of($game->away_team )) {
				// Otherwise, check that user is captain of one of the teams
				return true;
			}
			if($user && $user->is_coordinator_of($game->league_id)) {
				return true;
			}
			break;
		case 'edit':
			return ($user && $user->is_coordinator_of($game->league_id));
			break; // unreached
		case 'view':
			if( $extra == 'spirit' ) {
				return ($user && $user->is_coordinator_of($game->league_id));
			}
			if( $extra == 'submission' ) {
				return ($user && $user->is_coordinator_of($game->league_id));
			}
			return ($user && $user->is_active());
			break; // unreached
		case 'reschedule':
			//TODO

	}
	return false;
}

/**
 * Generate view of games for initial login splash page.
 */
function game_splash ()
{
	global $lr_session, $dbh;

	$sth = game_load(array());
	$sth = $dbh->prepare('SELECT s.game_id, t.team_id, t.status FROM schedule s, gameslot g, teamroster t WHERE s.published AND ((s.home_team = t.team_id OR s.away_team = t.team_id) AND t.player_id = ?) AND g.game_id = s.game_id AND g.game_date < CURDATE() ORDER BY g.game_date desc, g.game_start desc LIMIT 4');
	$sth->execute( array($lr_session->user->user_id) );

	$rows = array();
	while($row = $sth->fetch(PDO::FETCH_OBJ) ) {
		$game = game_load(array('game_id' => $row->game_id));
		if( $game->is_finalized() ) {
			$score = "$game->home_score - $game->away_score"	;
		} else {
			/* Not finalized yet, so we will either:
			 *   - display entered score if present
			 *   - display score entry link if game date has passed
			 *   - display a blank otherwise
			 */
			$entered = $game->get_score_entry( $row->team_id );
			if($entered) {
				// need to match entered score order to displayed team order!
				if ($entered->team_id == $game->home_id) {
					$score = "$entered->score_for - $entered->score_against";
				} else {
					$score = "$entered->score_against - $entered->score_for";
				}
				$score .= " (unofficial, waiting for opponent)";
			} else if($lr_session->has_permission('game','submit score', $game)
				&& ($game->timestamp < time()) ) {
					$score = l("submit score", "game/submitscore/$game->game_id/" . $row->team_id);
			}
		}
		$field = field_load(array('fid' => $game->fid));
		array_unshift($rows, array(
			l( strftime('%a %b %d', $game->timestamp) . ", $game->game_start-" . $game->display_game_end(),"game/view/$game->game_id"),
			array('data' =>
				l($game->home_name, "team/view/$game->home_id") .
				" (home) vs. " .
				l($game->away_name, "team/view/$game->away_id") .
				" (away) at " .
				l($game->field_code, "field/view/$game->fid",
				  array('title' => $field->fullname))),
			$score
		));
	}

	$sth = $dbh->prepare('SELECT s.game_id, t.team_id, t.status FROM schedule s, gameslot g, teamroster t WHERE s.published AND ((s.home_team = t.team_id OR s.away_team = t.team_id) AND t.player_id = ?) AND g.game_id = s.game_id AND g.game_date >= CURDATE() ORDER BY g.game_date asc, g.game_start asc LIMIT 4');
	$sth->execute( array($lr_session->user->user_id) );

	while($row = $sth->fetch(PDO::FETCH_OBJ) ) {
		$game = game_load(array('game_id' => $row->game_id));
		$score = '';
		if( $game->is_finalized() ) {
			$score = "$game->home_score - $game->away_score"	;
		} else {
			/* Not finalized yet, so we will either:
			 *   - display entered score if present
			 *   - display score entry link if game date has passed
			 *   - display a blank otherwise
			 */
			$entered = $game->get_score_entry( $row->team_id );
			if($entered) {
				// need to match entered score order to displayed team order!
				if ($entered->team_id == $game->home_id) {
					$score = "$entered->score_for - $entered->score_against";
				} else {
					$score = "$entered->score_against - $entered->score_for";
				}
				$score .= " (unofficial, waiting for opponent)";
			} else if($lr_session->has_permission('game','submit score', $game)
				&& ($game->timestamp < time()) ) {
					$score = l("submit score", "game/submitscore/$game->game_id/" . $row->team_id);
			}
		}
		$field = field_load(array('fid' => $game->fid));
		$rows[] = array(
			l( strftime('%a %b %d', $game->timestamp) . ", $game->game_start-" . $game->display_game_end(),"game/view/$game->game_id"),
			array('data' =>
				l($game->home_name, "team/view/$game->home_id") .
				" (home) vs. " .
				l($game->away_name, "team/view/$game->away_id") .
				" (away) at " .
				l($game->field_code, "field/view/$game->fid",
				  array('title' => $field->fullname))),
			$score
		);
	}

	# If no recent games, don't display the table
	if( count($rows) < 1)  {
		return;
	}

	return "<div class='schedule'>" . table(array( array( 'data' => "Recent and Upcoming Games", 'colspan' => 3)), $rows, array('alternate-colours' => true) ) . "</div>";
}

/**
 * Add game information to menu
 */
function game_add_to_menu( &$league, &$game )
{
	global $lr_session;
	menu_add_child("$league->fullname/schedule", "$league->fullname/schedule/$game->game_id", "Game $game->game_id", array('link' => "game/view/$game->game_id"));

	if( $lr_session->has_permission('league','edit game', $game->league_id) ) {
		menu_add_child("$league->fullname/schedule/$game->game_id", "$league->fullname/schedule/$game->game_id/edit", "edit game", array('link' => "game/edit/$game->game_id"));
#		menu_add_child("$league->fullname/schedule/$game->game_id", "$league->fullname/schedule/$game->game_id/reschedule", "reschedule game", array('link' => "game/reschedule/$game->game_id"));
		menu_add_child("$league->fullname/schedule/$game->game_id", "$league->fullname/schedule/$game->game_id/delete", "delete game", array('link' => "game/delete/$game->game_id"));
		menu_add_child("$league->fullname/schedule/$game->game_id", "$league->fullname/schedule/$game->game_id/removeresults", "remove results", array('link' => "game/removeresults/$game->game_id"));
	}
}

class GameCreate extends Handler
{
 /*TODO:
 *     When setting a game to 'cancelled':
 *     	  - game is terminated without predjudice.  Any scores and spirit are
 *     	  removed from the schedule table and from the submitted scores table.
 *     	  - game is marked as 'cancelled' in schedule, but is still displayed.
 *     	  - game should not be counted at all in standings
 *        This would typically occur when a game needs to be cancelled and
 *        it's convenient for the coordinator to simply ignore it and move on
 *        with a new round-robin, rather than push everything back by a week.
 *
 *     When setting a game to 'rescheduled':
 *        - game marked as rescheduled in schedule.
 *        - a new gameslot must be selected.  Once this is done, a new game
 *        entry in the table is added for that slot, and the slot ID is also
 *        added to the existing game, in 'rescheduled_slot' so it can easily
 *        be linked to.
 *        - old game doesn't count in standings, but new one does.
 *        This should be used for make-up games, moving games to other fields,
 *        etc.  An automated tool should post these changes on the front page.
 *
 *     When setting a game to 'forfeit':
 *        - score and spirit entries set to zero (or other BoD determined
 *        number).
 *        - game counts as having been played, with no winner and poor spirit.
 *   - schedule's 'round' field needs to be varchr instead of integer, to
 *   allow for rounds named 'quarter-final', 'semi-final', and 'final', and
 *   pulldown menus updated appropriately.
 *   (DONE in db, needs code);
 */

	var $types;
	var $league;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','add game', $this->league->league_id);
	}

	function process ()
	{
		$this->title = "Add Game";

		if(! $this->league ) {
			error_exit("That league does not exist");
		}

		if ( ! $this->league->load_teams() ) {
			error_exit("Error loading teams for league $league->fullname");
		}

		$num_teams = count($this->league->teams);

		if($num_teams < 2) {
			error_exit("Cannot schedule games in a league with less than two teams");
		}

		# Must currently have even # of teams for scheduling unless the excludeTeams flag is set
		if ($num_teams % 2 && $this->league->excludeTeams == "false") {
			error_exit("Must currently have an even number of teams in your league. " . 
			"If you need a bye, please create a team named Bye and add it to your league. " .
			"Otherwise, edit your league and set the 'excludeTeams' flag.");
		}

		$edit = &$_POST['edit'];
		$this->setLocation(array(
			$this->league->fullname => "league/view/" . $this->league->league_id,
			$this->title => 0
		));

		switch($edit['step']) {
			case 'perform':
				return $this->perform($edit);
				break;
			case 'confirm':
				return $this->confirm($edit);
				break;
			case 'selectdate':
				return $this->selectDate( $edit );
				break;
			case 'selecttype':
				return $this->selectType( $edit );
				break;
			case 'excludeTeams':
				return $this->excludeTeams( $edit );
				break;
			default:
				if ($this->league->excludeTeams == "true") {
					return $this->excludeTeams($edit);
				} else {
					return $this->selectType( $edit );
				}
				break;
		}
		error_exit("Error: This code should never be reached.");
	}

	function excludeTeams ( $edit ) {
		$this->league->load_teams();

		$output = "<P><br>The 'excludeTeams' option is set for this league.  This gives you the chance to <b>EXCLUDE</b> some teams from scheduling. ";
		$output .= "You may want to do this because you have an un-even number of teams in your league, or if your league consists of some teams who don't play every game...</P>";
		$output .= "<P>Please select the teams you wish to <b>EXCLUDE</b> from scheduling.</P>";
		$output .= "<P>You must ensure that you leave an even number of teams.</P>";
		$output .= form_hidden('edit[step]', 'selecttype');

		foreach($this->league->teams as $team) {
			$output .= form_checkbox( $team->name, "edit[excludeTeamID][]", $team->team_id );
		}

		$output .= form_submit('Next step');

		return form($output);

	}

	function selectType ( $edit )
	{
		$num_teams = count($this->league->teams);

		if (isset($edit['excludeTeamID'])) {
			$output = "<p><br>You will be excluding the following teams from the schedule: <br><b>";
			$counter = 0;
			$excludes = "";
			foreach ($edit['excludeTeamID'] as $teamid) {
				$excludes .= $this->league->teams[$teamid]->name . "<br>";
				$output .= form_hidden("edit[excludeTeamID][$counter]",$teamid);
				$counter++;
				$num_teams--;
			}
			$output .= $excludes . "</b></p>";

			if ($num_teams % 2) {
				error_exit("You marked " . count($edit['excludeTeamID']) . " teams to exclude, that leaves $num_teams.  Cannot schedule games for an un-even number of teams!");
			}
		}

		$this->loadTypes ($num_teams);

		$output .= "<p>Please enter some information about the game(s) to create.</p>";
		$output .= form_hidden('edit[step]', 'selectdate');

		$group .= form_radiogroup('', 'edit[type]', 'single', $this->types, "Select the type of game or games to add.  Note that for auto-generated round-robins, fields will be automatically allocated.");
		$group .= form_checkbox("Publish created games for player viewing?", 'edit[publish]', 'yes', true, "If this is checked, players will be able to view games immediately after creation.  Uncheck it if you wish to make changes before players can view.");
		$output .= form_group("Create a ... ", $group);

		$output .= form_submit('Next step');

		return form($output);
	}

	function loadTypes ( $num_teams ) {
		// Set up our menu
		switch($this->league->schedule_type) {
			case 'roundrobin':
				$this->types = array(
					'single' => 'single blank, unscheduled game (2 teams, one field, one day)',
					'blankset' => "set of blank unscheduled games for all teams in a tier ($num_teams teams, " . ($num_teams / 2) . " games, one day)",
					'oneset' => "set of randomly scheduled games for all teams in a tier ($num_teams teams, " . ($num_teams / 2) . " games, one day)",
					'fullround' => "full-tier round-robin ($num_teams teams, " . (($num_teams - 1) * ($num_teams / 2)) . " games over " .($num_teams - 1) . " weeks)",
					'halfroundstandings' => "half-tier round-robin ($num_teams teams, " . ((($num_teams / 2 ) - 1) * ($num_teams / 2)) . " games over " .($num_teams/2 - 1) . " weeks).  2 pools (top, bottom) divided by team standings.  You should use this one if some games have already been played.",
					'halfroundrating' => "half-tier round-robin ($num_teams teams, " . ((($num_teams / 2 ) - 1) * ($num_teams / 2)) . " games over " .($num_teams/2 - 1) . " weeks).  2 pools (top/bottom) divided by rating and skill level.  Use this if no games have been played, or you don't wish to have the teams' record directly affect the scheduling.",
#TODO					'qplayoff' => 'playoff ladder with quarter, semi and final games, and a consolation round (does not work yet)',
#TODO					'splayoff' => 'playoff ladder with semi and final games, and a consolation round (does not work yet)',
				);
				if( (($num_teams / 2) % 2) ) {
					# Can't do a half-round without an even number of teams in
					# each half.
					unset($this->types['halfroundstandings']);
					unset($this->types['halfroundrating']);
				}
				break;
			case 'ratings_ladder':
			case 'ratings_wager_ladder':
				$this->types = array(
					'single' => 'single blank, unscheduled game (2 teams, one field, one day)',
					'oneset_ratings_ladder' => "set of ratings-scheduled games for all teams ($num_teams teams, " . ($num_teams / 2) . " games, one day)"
				);
				break;

			default:
				error_exit("Wassamattayou!");
				break;
		}
	}

	function selectDate ( $edit )
	{
		global $dbh;
		$num_teams = count($this->league->teams);

		if (isset($edit['excludeTeamID'])) {
			$output = "<p><br>You will be excluding the following teams from the schedule: <br><b>";
			$counter = 0;
			$excludes = "";
			foreach ($edit['excludeTeamID'] as $teamid) {
				$excludes .= $this->league->teams[$teamid]->name . "<br>";
				$output .= form_hidden("edit[excludeTeamID][$counter]",$teamid);
				$counter++;
				$num_teams--;
			}
			$output .= $excludes . "</b></p>";
		}

		$type = $edit['type'];

		switch($type) {
			case 'single':
				$num_fields = 1;
				$num_dates = 1;
				break;
			case 'oneset':
			case 'oneset_ratings_ladder':
			case 'blankset':
				$num_dates = 1;
				$num_fields = ($num_teams / 2);
				break;
			case 'fullround':
				$num_dates = ($num_teams - 1);
				$num_fields = ($num_teams / 2);
				break;
			case 'halfroundstandings':
			case 'halfroundrating':
				$num_dates = (($num_teams / 2) - 1);
				$num_fields = ($num_teams / 2);
				break;
			default:
				error_exit("Please don't try to do that; it won't work, you fool");
				break;
		}

		$tot_fields = $num_fields * $num_dates;
		$output .= "<p>Select desired start date.  Scheduling a $type will require $tot_fields fields: $num_fields per day on $num_dates dates.</p>";

		$sth = $dbh->prepare(
			"SELECT DISTINCT UNIX_TIMESTAMP(s.game_date) as datestamp from league_gameslot_availability a, gameslot s WHERE (a.slot_id = s.slot_id) AND isnull(s.game_id) AND a.league_id = ? ORDER BY s.game_date, s.game_start");
		$sth->execute( array( $this->league->league_id) );

		$possible_dates = array();
		while($date = $sth->fetch(PDO::FETCH_OBJ)) {
			# TODO: for each day, ensure that:
			#     a) the minimum $num_fields is available
			#     b) there are $num_dates - 1 days beyond this onewith $num fields
			#     available
			$possible_dates[$date->datestamp] = strftime("%A %B %d %Y", $date->datestamp);
		}
		if( count($possible_dates) == 0) {
			error_exit("Sorry, there are no fields available for your league.  Check that fields have been allocated before attempting to proceed.");
		}

		$output .= form_hidden('edit[step]','confirm');
		$output .= form_hidden('edit[type]',$type);
		$output .= form_hidden('edit[publish]', ($edit['publish'] == 'yes') ? 'yes' : 'no');
		$output .= form_select('Start date','edit[startdate]', null, $possible_dates);
		$output .= form_submit('Next step');
		return form($output);
	}

	function confirm ( &$edit )
	{
		switch($edit['type']) {
			case 'single':
			case 'oneset':
			case 'oneset_ratings_ladder':
			case 'blankset':
			case 'fullround':
			case 'halfroundstandings':
			case 'halfroundrating':
				break;
			default:
				error_exit("Please don't try to do that; it won't work, you fool");
				break;
		}

		$output = "<p>The following information will be used to create your games:</p>";
		$output .= form_hidden('edit[step]','perform');
		$output .= form_hidden('edit[type]',$edit['type']);
		$output .= form_hidden('edit[startdate]',$edit['startdate']);
		$output .= form_hidden('edit[publish]', ($edit['publish'] == 'yes') ? 'yes' : 'no');

		$num_teams = count($this->league->teams) - count($edit['excludeTeamID']);
		$this->loadTypes ($num_teams);

		$output .= form_item('What', $this->types[$edit['type']]);
		$output .= form_item('Start date', strftime("%A %B %d %Y", $edit['startdate']));

		if (isset($edit['excludeTeamID'])) {
			$counter = 0;
			$excludes = "";
			foreach ($edit['excludeTeamID'] as $teamid) {
				$excludes .= $this->league->teams[$teamid]->name . "<br>";
				$output .= form_hidden("edit[excludeTeamID][$counter]",$teamid);
				$counter++;
			}
			$output .= form_item('Teams to exclude:', "<b>$excludes</b>");
		}
		$output .= form_submit('Create Games', 'submit');
		return form($output);
	}

	function perform ( &$edit )
	{
		# generate appropriate games, roll back on error
		$should_publish = ($edit['publish'] == 'yes');
		switch($edit['type']) {
			case 'single':
				# Create single game
				$g = new Game;
				$g->set('league_id', $this->league->league_id);
				$g->set('published', $should_publish);
				if( ! $g->save() ) {
					$rc = false;
					$message = "Could not create single game";
				} else {
					list( $rc, $message) = $g->select_random_gameslot( $edit['startdate'] ) ;
				}
				break;
			case 'blankset':
				# Create game for all teams in tier
				list( $rc, $message) = $this->league->create_empty_set( $edit['startdate'], $should_publish ) ;
				break;
			case 'oneset':
				# Create game for all teams in tier
				list( $rc, $message) = $this->league->create_scheduled_set( $edit['startdate'], $should_publish ) ;
				break;
			case 'oneset_ratings_ladder':
				# Create game for all teams in league
				list( $rc, $message) = $this->league->create_scheduled_set_ratings_ladder( $edit['startdate'] , $edit['excludeTeamID'], $should_publish) ;
				break;
			case 'fullround':
				# Create full roundrobin
				list($rc, $message) = $this->league->create_full_roundrobin( $edit['startdate'], null, $should_publish );
				break;
			case 'halfroundstandings':
				list($rc, $message) = $this->league->create_half_roundrobin( $edit['startdate'], 'standings', $should_publish );
				break;
			case 'halfroundrating':
				list($rc, $message) = $this->league->create_half_roundrobin( $edit['startdate'], 'rating', $should_publish );
				break;
			default:
				error_exit("Please don't try to do that; it won't work, you fool... " + $edit['type']);
				break;
		}

		if( $rc ) {
			local_redirect(url("schedule/view/" . $this->league->league_id));
		} else {
			error_exit("Failure creating games: $message");
		}
	}
}

class GameSubmit extends Handler
{
	var $game;
	var $team;

	function has_permission ()
	{
		global $lr_session;

		return $lr_session->has_permission('game','submit score', $this->game, $this->team);
	}

	function process ()
	{
		$this->title = "Submit Game Results";

		if( $this->team->team_id != $this->game->home_id && $this->team->team_id != $this->game->away_id ) {
			error_exit("That team did not play in that game!");
		}

		if( $this->game->timestamp > time() ) {
			error_exit("That game has not yet occurred!");
		}

		if( $this->game->is_finalized() ) {
			error_exit("The score for that game has already been submitted.");
		}

		$this->game->load_score_entries();

		if ( $this->game->get_score_entry( $this->team->team_id ) ) {
			error_exit("The score for your team has already been entered.");
		}

		if($this->game->home_id == $this->team->team_id) {
			$opponent->name = $this->game->away_name;
			$opponent->team_id = $this->game->away_id;
		} else {
			$opponent->name = $this->game->home_name;
			$opponent->team_id = $this->game->home_id;
		}

		$edit = $_POST['edit'];
		$spirit = $incident = $allstar = null;
		if (array_key_exists ('team_spirit', $_POST))
			$spirit = $_POST['team_spirit'];
		if (array_key_exists ('incident', $_POST))
			$incident = $_POST['incident'];
		if (array_key_exists ('allstars', $_POST))
			$allstars = $_POST['allstars'];

		switch($edit['step']) {
			case 'spirit':
				$rc = $this->generateSpiritForm($edit, $opponent);
				break;
			case 'incident':
				$rc = $this->generateIncidentForm($edit, $opponent, $spirit);
				break;
			case 'allstar':
				$rc = $this->generateAllStarForm($edit, $opponent, $spirit, $incident);
				break;
			case 'confirm':
				$rc = $this->generateConfirm($edit, $opponent, $spirit, $incident, $allstars);
				break;
			case 'save':
				$rc = $this->perform($edit, $opponent, $spirit, $incident, $allstars);
				break;
			default:
				$rc = $this->generateForm( $opponent );
		}

		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	// We don't know what path we may take through the submission, so we don't
	// know what data may be present at any particular step. This checks for
	// any possible data errors.
	function isDataInvalid ($edit, $questions = null, $incident = null, $allstars = null) {
		$ret = $this->isScoreDataInvalid ($edit);
		if( $edit['sotg'] ) {
			$msg = $this->isSOTGDataInvalid ($edit);
			if( $msg ) {
				$ret .= $msg;
			}
		}
		if ($ret === false && $questions != null) {
			$msg = $questions->answers_invalid();
			if ($msg) {
				$ret .= $msg;
			}
		}
		if ($ret === false && $incident != null)
			$ret = $this->isIncidentDataInvalid ($incident);
		if ($ret === false && $allstars != null)
			$ret = $this->isAllStarDataInvalid ($allstars);
		return $ret;
	}

	function isSOTGDataInvalid ( $edit ) {

		if( ! validate_numeric_sotg( $edit['sotg'] ) ) {
			return '<br>An invalid value was specified for SOTG.  Please use a whole number between 1 and 10.';
		}

		return false;
	}

	function isScoreDataInvalid( $edit )
	{
		$errors = '';

		if( $edit['defaulted'] ) {
			switch($defaulted) {
				case 'us':
				case 'them':
				case '':
					return false;  // Ignore other data in cases of default.
				default:
					return 'An invalid value was specified for default.';
			}
		}

		if( !validate_number($edit['score_for']) ) {
			$errors .= '<br>You must enter a valid number for your score.';
		}

		if( !validate_number($edit['score_against']) ) {
			$errors .= '<br>You must enter a valid number for your opponent\'s score.';
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}

	function isIncidentDataInvalid( $invalid )
	{
		$errors = '';

		$types = $this->incident_types();
		if (!array_key_exists('type', $invalid) ||
			empty ($invalid['type']) ||
			!array_key_exists ($invalid['type'], $types))
		{
			$errors .= '<br>You must select a valid incident type.';
		}

		if (!array_key_exists('details', $invalid) ||
			empty ($invalid['details']))
		{
			$errors .= '<br>You must enter the details of the incident.';
		}
		if (!validate_nonhtml($invalid['details']))
		{
			$errors .= '<br>HTML is not allowed in the incident details.';
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}

	function isAllStarDataInvalid( $allstars )
	{
		$errors = '';

		if (!array_key_exists('male', $allstars)) {
			$errors .= '<br>You must select an option from the list of males ("none" if you don\'t want to nominate a male all-star).';
		}

		if (!array_key_exists('female', $allstars)) {
			$errors .= '<br>You must select an option from the list of females ("none" if you don\'t want to nominate a female all-star).';
		}

		// If all-star nominations are optional, then the captain said they want to
		// nominate someone, so don't let them forget to do so. If the nomination form
		// is always shown, they don't have to pick someone.
		if ($this->league->allstars == 'optional' &&
			array_key_exists('male', $allstars) && $allstars['male'] == 0 &&
			array_key_exists('female', $allstars) && $allstars['female'] == 0) {
			$errors .= '<br>You must select at least one all-star.';
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}

	function perform ($edit, $opponent, $spirit, $incident, $allstars)
	{
		global $lr_session, $dbh;

		if( $this->league->enter_survey_sotg() && $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = formbuilder_load('team_spirit');
			$questions->bulk_set_answers( $spirit );
		} else {
			$questions = null;
		}

		$dataInvalid = $this->isDataInvalid( $edit, $questions, $incident, $allstars );
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		if( $this->league->enter_survey_sotg() && $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			// Save the spirit entry if non-default
			if( !$this->game->save_spirit_entry( $opponent->team_id, $questions->bulk_get_answers()) ) {
				error_exit("Error saving spirit entry for " . $this->team->team_id);
			}
			// If no numeric value set, create one from survey.  We may not need it, but do it for consistency's sake in the table.
			if( ! $edit['sotg'] ) {
				$edit['sotg'] = $this->game->get_spirit_numeric( $this->game->get_opponent_id( $this->team->team_id ) );
			}
		}

		// Now, we know we haven't finalized the game, so we first
		// save this team's score entry, as there isn't one already.
		if( !$this->game->save_score_entry( $this->team->team_id, $lr_session->attr_get('user_id'), $edit['score_for'],$edit['score_against'],$edit['defaulted'], $edit['sotg'] ) ) {
			error_exit("Error saving score entry for " . $this->team->team_id);
		}

		// now, check if the opponent has an entry
		if( ! $this->game->get_score_entry( $opponent->team_id ) ) {
			// No, so we just mention that it's been saved and move on
			$resultMessage = para('This score has been saved.  Once your opponent has entered their score, it will be officially posted.');
		} else {
			// Otherwise, both teams have an entry.  So, attempt to finalize using
			// this information.
			if( $this->game->finalize() ) {
				$resultMessage = para('This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.');
			} else {
				// Or, we have a disagreement.  Since we've already saved the
				// score, just say so, and continue.
				$resultMessage = para('This score doesn\'t agree with the one your opponent submitted.  Because of this, the score will not be posted until your coordinator approves it.');
			}
		}

		// Save and send the incident report, if required
		if( $incident ) {
			$sth = $dbh->prepare('INSERT into incidents (game_id, team_id, type, details) VALUES(?,?,?,?)');
			$sth->execute( array($this->game->game_id, $this->team->team_id, $incident['type'], $incident['details']) );

			$addr = variable_get('incident_report_email', $_SERVER['SERVER_ADMIN']);
			$link = l($addr, "mailto:$addr");
			$rc = send_mail($addr, 'Incident Manager',
				false, false, // from the administrator
				false, false, // no Cc
				"Incident report: {$incident['type']}",
				$incident['details']);
			if($rc) {
				$resultMessage .= para('Your incident report details have been sent for handling.');
			} else {
				$resultMessage .= para(theme_error('There was an error sending your incident report details. Please send them to $link to ensure proper handling.'));
			}
		}

		// Save the all-star nominations, if present
		if( $allstars ) {
			$sth = $dbh->prepare('INSERT into allstars (game_id, player_id) VALUES(?,?)');
			foreach ($allstars as $player_id) {
				if ($player_id != 0) {
					$sth->execute( array($this->game->game_id, $player_id) );
				}
			}
			$resultMessage .= para('Your all-star nominations have been saved.');
		}

		return $resultMessage;
	}

	function generateSpiritForm ($edit, $opponent )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		if( $edit['defaulted'] == 'us' || $edit['defaulted'] == 'them' ) {
			// If it's a default, short-circuit the spirit-entry form and skip
			// straight to the confirmation.  This skips incident reports and
			// all-star nominations as well, but that's probably okay...
			// TODO: should skip all-stars for a default, but perhaps allow incident reports.
			return $this->generateConfirm($edit, $opponent);
		} else {
			// Force a non-default to display correctly
			$edit['defaulted'] = 'no';
		}

		$output = $this->interim_game_result($edit, $opponent);
		if (array_key_exists ('incident', $edit) && $edit['incident']) {
			$edit['step'] = 'incident';
		} else if (array_key_exists ('allstar', $edit) && $edit['allstar']) {
			$edit['step'] = 'allstar';
		} else {
			$edit['step'] = 'confirm';
		}
		$output .= $this->hidden_fields ('edit', $edit);

		$output .= para("Now you must rate your opponent's Spirit of the Game.");
		switch( $this->league->enter_sotg ) {
			case 'numeric_only':
				$output .= para("To do so, please enter a value from 1 to 10.");
				$output .= para(
					form_textfield('Opponent SOTG','edit[sotg]','',2,2)
				);
				$output .= para(form_submit("Next Step", "submit") . form_reset("reset"));
				break;
			case 'both':
			default:
				$output .= para("Leaguerunner will ask you a few questions.  These questions are used to indicate to the league what areas might be problematic, and to generate a suggested spirit score, which you may modify.");
				// Fall through
			case 'survey_only':
				$output .= para("Please fill out the questions below.");
				$questions = formbuilder_load('team_spirit');
				$output .= $questions->render_editable(false);
				//TODO: This javascript has HARD CODED names of the elements and should probably be worked into the formbuilder...
				// javascript to ask for comments if any of the "worst" answers are chosen...
				$javascript = "var form = document.getElementById('score_form'); ";
				$javascript .= "if (form.elements['team_spirit[RulesKnowledge]'][2].checked || form.elements['team_spirit[RulesKnowledge]'][3].checked || ";
				$javascript .= "form.elements['team_spirit[Sportsmanship]'][2].checked || form.elements['team_spirit[Sportsmanship]'][3].checked || ";
				$javascript .= "form.elements['team_spirit[Enjoyment]'][3].checked) { ";
				$javascript .= "  if (form.elements['team_spirit[CommentsToCoordinator]'].value == '') { ";
				$javascript .= "    alert('Please enter a comment for the coordinators to help explain why you answered the Spirit questions the way you did.'); return false; } }";
				// javascript to ask for comments if the SOTG score will be 5 or less:
				$javascript .= "if (form.elements['edit[sotg]'].value == '') { form.elements['edit[sotg]'].value = sotg(); } ";
				$javascript .= "if (sotg() <= 5 || form.elements['edit[sotg]'].value <= 5) { if (form.elements['team_spirit[CommentsToCoordinator]'].value == '') { ";
				$javascript .= "    alert('Please enter a comment for the coordinators to help explain why you assigned an SOTG score the way you did.'); return false; } }";
				if( $this->league->enter_numeric_sotg() ) {
					$output .= generateSOTGButtonAndJavascript("", "Click the Suggest button to calculate a SOTG score based on your responses above, or manually enter a score (out of 10).");
				}
				$output .= para(form_submit("Next Step", "submit", "onclick=\"$javascript\"") . form_reset("reset"));
				break;
		}

		return form($output, 'post', null, 'id="score_form"');
	}

	function generateIncidentForm ($edit, $opponent, $spirit = null )
	{
		if( $this->league->enter_survey_sotg() && $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = formbuilder_load('team_spirit');
			$questions->bulk_set_answers( $spirit );
		} else {
			$questions = null;
		}

		$dataInvalid = $this->isDataInvalid( $edit, $questions );
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$output = para('You have indicated that you want to report an incident that occurred during this game. Please enter the details of the incident below.');

		if (array_key_exists ('allstar', $edit) && $edit['allstar']) {
			$edit['step'] = 'allstar';
		} else {
			$edit['step'] = 'confirm';
		}
		$output .= $this->hidden_fields ('edit', $edit);
		$output .= $this->hidden_fields ('team_spirit', $spirit);

		$output .= form_select('Incident type', 'incident[type]', '', $this->incident_types(), '');
		$output .= form_textarea('Incident Details', 'incident[details]', '', 60, 5, '');

		$output .= para(form_submit('Next Step'));

		return form($output);
	}

	function generateAllStarForm ($edit, $opponent, $spirit = null, $incident = null )
	{
		if( $this->league->enter_survey_sotg() && $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = formbuilder_load('team_spirit');
			$questions->bulk_set_answers( $spirit );
		} else {
			$questions = null;
		}

		$dataInvalid = $this->isDataInvalid( $edit, $questions, $incident );
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		if ( $this->league->allstars == 'optional' ) {
			$output = para('You have indicated that you want to nominate all-stars from this game. You may select one male and/or one female all-star from the list below.');
		} else {
			$output = para('You may select one male and/or one female all-star from the list below, if you think they deserve to be nominated as an all-star.');
		}

		$edit['step'] = 'confirm';
		$output .= $this->hidden_fields ('edit', $edit);
		$output .= $this->hidden_fields ('team_spirit', $spirit);
		$output .= $this->hidden_fields ('incident', $incident);

		$team = team_load( array('team_id' => $opponent->team_id) );
		$team->get_roster();
		$males = $females = array();
		$valid_status = array('captain', 'assistant', 'player', 'substitute');
		foreach ($team->roster as $player) {
			if (in_array($player->status, $valid_status)) {
				if ($player->gender == 'Male')
					$males[$player->id] = $player->fullname;
				else
					$females[$player->id] = $player->fullname;
			}
		}
		$males[0] = 'none';
		$females[0] = 'none';
		$output .= h2('Males');
		$output .= form_radiogroup('', 'allstars[male]', 'none', $males, '');
		$output .= h2('Females');
		$output .= form_radiogroup('', 'allstars[female]', 'none', $females, '');
		$convener = 'league convener';
		if (! empty ($this->league->coord_list)) {
			$convener = l($convener, "mailto:{$this->league->coord_list}");
		}
		$output .= para("If you feel strongly about nominating a second male or female please contact your $convener.");

		$output .= para(form_submit('Next Step'));

		return form($output);
	}

	function generateConfirm ($edit, $opponent, $spirit = null, $incident = null, $allstars = null )
	{
		if( $this->league->enter_survey_sotg() && $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = formbuilder_load('team_spirit');
			$questions->bulk_set_answers( $spirit );
		} else {
			$questions = null;
		}

		$dataInvalid = $this->isDataInvalid( $edit, $questions, $incident, $allstars );
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$output = $this->interim_game_result($edit, $opponent);

		$edit['step'] = 'save';
		$output .= $this->hidden_fields ('edit', $edit);
		$output .= $this->hidden_fields ('team_spirit', $spirit);
		$output .= $this->hidden_fields ('incident', $incident);
		$output .= $this->hidden_fields ('allstars', $allstars);

		if( $this->league->enter_numeric_sotg() ) {
			if( $edit['sotg'] ) {
				$output .= para("A <b>Spirit Of The Game</b> score of <b>{$edit['sotg']}</b> will be assigned.");
			} else {
				$output .= para('A <b>Spirit Of The Game</b> score will be automatically generated for your opponents.');
			}
		}
		if( $this->league->enter_survey_sotg() ) {
			$output .= para('The following answers will be tracked by your coordinator:');
			$output .= $questions->render_viewable();
		}

		if( $incident != null ) {
			$output .= para("You are reporting an incident of type <b>{$incident['type']}</b> with the following details:");
			$output .= para($incident['details']);
		}

		if( $allstars != null ) {
			$players = array();

			if ($allstars['male'] != 0) {
				$player = person_load( array('user_id' => $allstars['male']));
				$players[] = "<b>{$player->fullname}</b>";
			}

			if ($allstars['female'] != 0) {
				$player = person_load( array('user_id' => $allstars['female']));
				$players[] = "<b>{$player->fullname}</b>";
			}

			if (! empty ($players)) {
				$output .= para('You are nominating ' . implode(' and ', $players) . ' as ' .
					((count($players) == 1) ? 'an all-star.' : 'all-stars.'));
			} else if ($this->league->allstars == 'always') {
				$output .= para('You are not nominating any all-stars.');
			}
		}

		$output .= para("If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the problems.");

		$output .= para(form_submit('Submit'));

		return form($output);
	}

	function generateForm ( $opponent )
	{
		$output = para( "Submit the score for the "
			. $this->game->sprintf('short')
			. " between " . $this->team->name . " and $opponent->name.");
		$output .= para("If your opponent has already entered a score, it will be displayed below.  If the score you enter does not agree with this score, posting of the score will be delayed until your coordinator can confirm the correct score.");

		$output .= form_hidden('edit[step]', 'spirit');

		$opponent_entry = $this->game->get_score_entry( $opponent->team_id );

		if($opponent_entry) {
			if($opponent_entry->defaulted == 'us') {
				$opponent_entry->score_for .= " (defaulted)";
			} else if ($opponent_entry->defaulted == 'them') {
				$opponent_entry->score_against .= " (defaulted)";
			}

		} else {
			$opponent_entry->score_for = "not yet entered";
			$opponent_entry->score_against = "not yet entered";
		}

		$rows = array();
		$header = array( "Team Name", "Defaulted?", "Your Score Entry", "Opponent's Score Entry");

		$rows[] = array(
			$this->team->name,
			"<input type='checkbox' name='edit[defaulted]' value='us' onclick='defaultCheckboxChanged()'>",
			form_textfield("","edit[score_for]","",2,2),
			$opponent_entry->score_against
		);

		$rows[] = array(
			$opponent->name,
			"<input type='checkbox' name='edit[defaulted]' value='them' onclick='defaultCheckboxChanged()'>",
			form_textfield("","edit[score_against]","",2,2),
			$opponent_entry->score_for
		);

		$output .= '<div class="listtable">' . table($header, $rows) . "</div>";
		if (variable_get('incident_reports', false))
			$output .= form_checkbox( 'I have an incident to report', "edit[incident]" );
		if ($this->league->allstars == 'optional') {
			$output .= form_checkbox( 'I want to nominate an all-star', "edit[allstar]" );
		} else if ($this->league->allstars == 'always') {
			$output .= form_hidden( "edit[allstar]", true );
		}
		$output .= para(form_submit("Next Step") . form_reset("reset"));

		$win = variable_get('default_winning_score', 6);
		$lose = variable_get('default_losing_score', 0);

		$script = <<<ENDSCRIPT
<script type="text/javascript"> <!--
  function defaultCheckboxChanged() {
	form = document.getElementById('score_form');
    if (form.elements['edit[defaulted]'][0].checked == true) {
        form.elements['edit[score_for]'].value = "$lose";
        form.elements['edit[score_for]'].disabled = true;
        form.elements['edit[score_against]'].value = "$win";
        form.elements['edit[score_against]'].disabled = true;
        form.elements['edit[defaulted]'][1].disabled = true;
        form.elements['edit[incident]'].checked = false;
        form.elements['edit[incident]'].disabled = true;
        form.elements['edit[allstar]'].checked = false;
        form.elements['edit[allstar]'].disabled = true;
    } else if (form.elements['edit[defaulted]'][1].checked == true) {
        form.elements['edit[score_for]'].value = "$win";
        form.elements['edit[score_for]'].disabled = true;
        form.elements['edit[score_against]'].value = "$lose";
        form.elements['edit[score_against]'].disabled = true;
        form.elements['edit[defaulted]'][0].disabled = true;
        form.elements['edit[incident]'].checked = false;
        form.elements['edit[incident]'].disabled = true;
        form.elements['edit[allstar]'].checked = false;
        form.elements['edit[allstar]'].disabled = true;
    } else {
        form.elements['edit[score_for]'].disabled = false;
        form.elements['edit[score_against]'].disabled = false;
        form.elements['edit[defaulted]'][0].disabled = false;
        form.elements['edit[defaulted]'][1].disabled = false;
        form.elements['edit[incident]'].disabled = false;
        form.elements['edit[allstar]'].disabled = false;
    }
  }
// -->
</script>
ENDSCRIPT;

		return $script . form($output, 'post', null, 'id="score_form"');
	}

	function interim_game_result( $edit, $opponent )
	{
		$win = variable_get('default_winning_score', 6);
		$lose = variable_get('default_losing_score', 0);

		$output = para( "For the game of " . $this->game->sprintf('short') . " you have entered:");
		$rows = array();
		switch($edit['defaulted']) {
		case 'us':
			$rows[] = array($this->team->name, "$lose (defaulted)");
			$rows[] = array($opponent->name, $win);
			break;
		case 'them':
			$rows[] = array($this->team->name, $win);
			$rows[] = array($opponent->name, "$lose (defaulted)");
			break;
		default:
			$rows[] = array($this->team->name, $edit['score_for']);
			$rows[] = array($opponent->name, $edit['score_against']);
			break;
		}

		$output .= '<div class="pairtable">'
			. table(null, $rows)
			. "</div>";

		// now, check if the opponent has an entry
		$opponent_entry = $this->game->get_score_entry( $opponent->team_id );

		if( $opponent_entry ) {
			if( ! $this->game->score_entries_agree( $edit, object2array($opponent_entry) ) ) {
				$output .= para("<b>Note:</b> this score does NOT agree with the one provided by your opponent, so coordinator approval will be required if you submit it");
			}
		}


		if( $edit['defaulted']== 'them' || ($edit['score_for'] > $edit['score_against']) ) {
			$what = 'win for your team';
		} else if( $edit['defaulted']=='us' || ($edit['score_for'] < $edit['score_against']) ) {
			$what = 'loss for your team';
		} else {
			$what = 'tie game';
		}
		$output .= para("If confirmed, this would be recorded as a <b>$what</b>.");

		return $output;
	}

	function hidden_fields ($group, $fields)
	{
		$output = '';
		if (is_array($fields) && !empty ($fields)) {
			foreach ($fields as $name => $value)
				$output .= form_hidden("{$group}[$name]", $value);
		}
		return $output;
	}

	function incident_types()
	{
		$types = array('Field condition', 'Injury', 'Rules disagreement', 'Illegal Substitution', 'Escalated incident', 'Other');
		return array_merge(array('' => 'Select one:'), array_combine ($types, $types));
	}
}

class GameEdit extends Handler
{
	var $game;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('game','view', $this->game);
	}

	function isSOTGDataInvalid ( $edit ) {

		if( ! validate_numeric_sotg( $edit['sotg_home'] ) ) {
			return '<br>An invalid value was specified for home SOTG.  Please use a whole number between 1 and 10.';
		}

		if( ! validate_numeric_sotg( $edit['sotg_away'] ) ) {
			return '<br>An invalid value was specified for away SOTG.  Please use a whole number between 1 and 10.';
		}

		return false;
	}

	function process ()
	{
		global $lr_session;
		if(!$this->game) {
			error_exit("That game does not exist");
		}

		if( arg(1) == 'edit' || arg(1) == 'approve' ) {
			if( $lr_session->is_admin() ) {
				$this->can_edit = true;
			}

			if( $lr_session->is_coordinator_of($this->game->league_id)) {
				$this->can_edit = true;
			}
		} else {
			$this->can_edit = false;
		}

		$this->title = "Game";

		$this->setLocation(array(
			"$this->title &raquo; Game " . $this->game->game_id => 0));

		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $this->game, $edit );
				break;
			case 'perform':
				$this->perform( $edit );
				local_redirect(url("game/view/" . $this->game->game_id));
				break;
			default:
				$rc = $this->generateForm( );
		}

		return $rc;
	}

	function generateForm ( )
	{
		global $lr_session, $dbh;
		# Alias, to avoid typing.  Bleh.
		$game = &$this->game;
		$league = &$this->league;

		$game->load_score_entries();

		$output = form_hidden('edit[step]', 'confirm');

		$output .= form_item("Game ID", $game->game_id);

		$teams = $league->teams_as_array();
		/* Now, since teams may not be in league any longer, we need to force
		 * them to appear in the pulldown
		 */
		$teams[$game->home_id] = $game->home_name;
		$teams[$game->away_id] = $game->away_name;

		$output .= form_item("League/Division", l($league->fullname, "league/view/$league->league_id"));

		$output .= form_item( "Home Team", l($game->home_name,"team/view/$game->home_id"));
		$output .= form_item( "Away Team", l($game->away_name,"team/view/$game->away_id"));

		if( $this->can_edit ) {
			$note = "To edit time, date, or location, use the 'reschedule' link";
		}
		$output .= form_item("Date and Time", "$game->game_date, $game->game_start until " . $game->display_game_end() . $note);

		$field = field_load( array('fid' => $game->fid) );
		$output .= form_item("Location",
			l("$field->fullname ($game->field_code)", "field/view/$game->fid"), $note);

		$output .= form_item("Game Status", $game->status);

		$output .= form_item("Round", $game->round);

		$spirit_group = '';
		$score_group = '';
		/*
		 * Now, for scores and spirit info.  Possibilities:
		 *  - game has been finalized:
		 *  	- everyone can see scores
		 *  	- coordinator can edit scores/spirit
		 *  - game has not been finalized
		 *  	- players only see "not yet submitted"
		 *  	- captains can see submitted scores
		 *  	- coordinator can see everything, edit final scores/spirit
		 */

		if($game->approved_by) {
			// Game has been finalized

			if( ! $this->can_edit ) {
				// If we're not editing, display score.  If we are,
				// it will show up below.
				switch($game->status) {
					case 'home_default':
						$home_status = " (defaulted)";
						break;
					case 'away_default':
						$away_status = " (defaulted)";
						break;
					case 'forfeit':
						$home_status = " (forfeit)";
						$away_status = " (forfeit)";
						break;
				}
				$score_group .= form_item("Home ($game->home_name [rated: $game->rating_home]) Score", "$game->home_score $home_status");
				$score_group .= form_item("Away ($game->away_name [rated: $game->rating_away]) Score", "$game->away_score $away_status");
				if( $this->league->display_numeric_sotg() ) {
					$score_group .= form_item("SOTG score for $game->home_name" , $game->home_spirit);
					$score_group .= form_item("SOTG score for $game->away_name" , $game->away_spirit);
				}
			}

			if ($game->home_score == $game->away_score && $game->rating_points == 0){
				$score_group .= form_item("Rating Points", "No points were transferred between teams");
			}
			else {
				if ($game->home_score >= $game->away_score) {
					$winner = l($game->home_name,"team/view/$game->home_id");
					$loser = l($game->away_name,"team/view/$game->away_id");
				}
				elseif ($game->home_score < $game->away_score) {
					$winner = l($game->away_name,"team/view/$game->away_id");
					$loser = l($game->home_name,"team/view/$game->home_id");

				}
				$score_group .= form_item("Rating Points", $game->rating_points , $winner." gain " .$game->rating_points. " points and " .$loser. " lose " .$game->rating_points. " points");
			}

			switch($game->approved_by) {
				case APPROVAL_AUTOMATIC:
					$approver = 'automatic approval';
					break;
				case APPROVAL_AUTOMATIC_HOME:
					$approver = 'automatic approval using home submission';
					break;
				case APPROVAL_AUTOMATIC_AWAY:
					$approver = 'automatic approval using away submission';
					break;
				case APPROVAL_AUTOMATIC_FORFEIT:
					$approver = 'game automatically forfeited due to lack of score submission';
					break;
				default:
					$approver = person_load( array('user_id' => $game->approved_by));
					$approver = l($approver->fullname, "person/view/$approver->user_id");
			}
			$score_group .= form_item("Score Approved By", $approver);

		} else {
			/*
			 * Otherwise, scores are still pending.
			 */
			if( $lr_session->is_coordinator_of($game->league_id)) {
				$sth = $dbh->prepare("SELECT
								user_id
							FROM
								person p
							LEFT JOIN
								teamroster r
							ON
								p.user_id = r.player_id
							WHERE
								r.team_id IN (?,?)
							AND
								r.status = 'captain'
							AND
								p.user_id != ?");
				$sth->execute( array( $game->home_id, $game->away_id, $lr_session->user->user_id) );
				$emails = array();
				$names = array();
				while($user = $sth->fetch(PDO::FETCH_OBJ)) {
					$captain = person_load(array('user_id' => $user->user_id));
					$emails[] = $captain->email;
					$names[] = $captain->fullname;
				}

				$list = create_rfc2822_address_list($emails, $names, true);
				$output .= para( l('Click here to send an email', "mailto:$list") . ' to all captains.' );
			}

			$stats_group = '';
			/* Use our ratings to try and predict the game outcome */
			$homePct = $game->home_expected_win();
			$awayPct = $game->away_expected_win();

			$stats_group .= form_item("Chance to win", table(null, array(
				array($game->home_name, sprintf("%0.1f%%", (100 * $homePct))),
				array($game->away_name, sprintf("%0.1f%%", (100 * $awayPct))),
				array("View the " . l('Ratings Table', "game/ratings/$game->game_id") . " for this game." ))
				));
			$output .= form_group("Statistics", $stats_group);


			$score_group .= form_item('',"Score not yet finalized");
			if( $lr_session->has_permission('game','view', $game, 'submission') ) {
				$score_group .= form_item("Score as entered", game_score_entry_display( $game ));

			}
		}

		// Now, we always want to display this edit code if we have
		// permission to edit.
		if( $this->can_edit ) {
			$score_group .= form_select('Game Status','edit[status]', $game->status, getOptionsFromEnum('schedule','status'), "To mark a game as defaulted, select the appropriate option here.  Appropriate scores will automatically be entered.");
			$score_group .= form_textfield( "Home ($game->home_name [rated: $game->rating_home]) score", 'edit[home_score]',$game->home_score,2,2);
			$score_group .= form_textfield( "Away ($game->away_name [rated: $game->rating_away]) score",'edit[away_score]',$game->away_score,2,2);

			// TODO: horribly inefficient to run this query again
			// from here, when it was just run from the
			// "game_score_entry_display" call a few lines above
			// here...

			$sth = $dbh->prepare('SELECT * FROM score_entry WHERE team_id = ? AND game_id = ?');
			$sth->execute(array($game->home_team, $game->game_id));
			$home = $sth->fetch();
			$sth->execute(array($game->away_team, $game->game_id));
			$away = $sth->fetch();

			// if the game has not yet been finalized, spirit for home team was reported by away team (and vice-versa)
			$hs = $away['spirit'];
			$as = $home['spirit'];
			if ($game->is_finalized()) {
				// if the game was finalized, the home/away assigned spirit is already saved in place
				$hs = $game->home_spirit;
				$as = $game->away_spirit;
			}
			$score_group .= generateSOTGButtonAndJavascript("home", "SOTG score for $game->home_name", $hs);
			$score_group .= generateSOTGButtonAndJavascript("away", "SOTG score for $game->away_name", $as);
		}

		$output .= form_group("Scoring", $score_group);

		if( $lr_session->has_permission('game','view',$game,'spirit') ) {

			$formbuilder = formbuilder_load('team_spirit');
			$ary = $game->get_spirit_entry( $game->home_id );
			// hack: if Timeliness is empty then it's because of an auto-finalize,
			// so make the $ary null otherwise a later check for invalid answers will
			// cause nothing to be displayed...
			// TONY: when can I remove this hack?? if ever?? otherwise make it less of a hack
			if ($ary["Timeliness"] == null || $ary["Timeliness"] == "") {
				$ary = null;
			}
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );
			}

			if($this->can_edit) {
				$home_spirit_group = $formbuilder->render_editable( $ary, 'home' );
			} else {
				$home_spirit_group = $formbuilder->render_viewable( $ary );
			}

			$formbuilder->clear_answers();
			$ary = $game->get_spirit_entry( $game->away_id );
			// hack: if Timeliness is empty then it's because of an auto-finalize,
			// so make the $ary null otherwise a later check for invalid answers will
			// cause nothing to be displayed...
			// TONY: when can I remove this hack?? if ever?? otherwise make it less of a hack
			if ($ary["Timeliness"] == null || $ary["Timeliness"] == "") {
				$ary = null;
			}
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );
			}
			if($this->can_edit) {
				$away_spirit_group = $formbuilder->render_editable( $ary , 'away');
			} else {
				$away_spirit_group = $formbuilder->render_viewable( $ary );
			}

			$output .= form_group("Spirit assigned TO home ($game->home_name)", $home_spirit_group);
			$output .= form_group("Spirit assigned TO away ($game->away_name)", $away_spirit_group);
		}

		if( $this->can_edit ) {
			$output .= para(form_submit("submit") . form_reset("reset"));
		}
		return $script . form($output, 'post', null, 'id="score_form"');
	}

	function generateConfirm ( $game, $edit )
	{
		if( ! $this->can_edit ) {
			error_exit("You do not have permission to edit this game");
		}

		$dataInvalid = $this->isDataInvalid( $edit );

		$home_spirit = formbuilder_load('team_spirit');
		$away_spirit = formbuilder_load('team_spirit');

		$win = variable_get('default_winning_score', 6);
		$lose = variable_get('default_losing_score', 0);

		switch($edit['status']) {
			case 'home_default':
				$home_spirit->bulk_set_answers($this->game->default_spirit('loser'));
				$away_spirit->bulk_set_answers($this->game->default_spirit('winner'));
				$edit['home_score'] = "$lose (defaulted)";
				$edit['away_score'] = $win;
				break;
			case 'away_default':
				$away_spirit->bulk_set_answers($this->game->default_spirit('loser'));
				$home_spirit->bulk_set_answers($this->game->default_spirit('winner'));
				$edit['home_score'] = $win;
				$edit['away_score'] = "$lose (defaulted)";
				break;
			case 'forfeit':
				$away_spirit->bulk_set_answers($this->game->default_spirit('loser'));
				$home_spirit->bulk_set_answers($this->game->default_spirit('loser'));
				$edit['home_score'] = '0 (forfeit)';
				$edit['away_score'] = '0 (forfeit)';
				break;
			case 'normal':
			default:
				$home_spirit->bulk_set_answers( $_POST['team_spirit_home'] );
				$away_spirit->bulk_set_answers( $_POST['team_spirit_away'] );
				$dataInvalid .= $home_spirit->answers_invalid();
				$dataInvalid .= $away_spirit->answers_invalid();
				break;
		}

		if( $dataInvalid ) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para( "You have made the changes below for the $game->game_date $game->game_start game between $game->home_name and $game->away_name.  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the score.");

		$output .= form_hidden('edit[step]', 'perform');
		$output .= form_hidden('edit[status]', $edit['status']);
		$output .= form_hidden('edit[home_score]', $edit['home_score']);
		$output .= form_hidden('edit[away_score]', $edit['away_score']);
		$output .= form_hidden('edit[sotg_home]', $edit['sotg_home']);
		$output .= form_hidden('edit[sotg_away]', $edit['sotg_away']);
		$output .= $home_spirit->render_hidden('home');
		$output .= $away_spirit->render_hidden('away');

		$score_group .= form_item("Home ($game->home_name [rated: $game->rating_home]) Score",$edit['home_score']);
		$score_group .= form_item("Away ($game->away_name [rated: $game->rating_away]) Score", $edit['away_score']);

		if ($edit['status'] != 'home_default' && $edit['status'] != 'away_default') {
			$score_group .= "<div class=\"form-item\"><label>SOTG score assigned to $game->home_name:</label><br>" . $edit['sotg_home'];
			$score_group .= "<div class=\"form-item\"><label>SOTG score assigned to $game->away_name:</label><br>" . $edit['sotg_away'];
		} else {
			$score_group .= "Due to the game default, the SOTG scores for this game will be automatically assigned.";
		}
		$output .= form_group("Scoring", $score_group);

		// only show SOTG of the home team if they didn't default!
		if ($edit['status'] != 'home_default') {
			$output .= form_group("Spirit assigned to home ($game->home_name)", $home_spirit->render_viewable());
		}
		// only show SOTG of the away team if they didn't default!
		if ($edit['status'] != 'away_default') {
			$output .= form_group("Spirit assigned to away ($game->away_name)", $away_spirit->render_viewable());
		}

		$output .= para(form_submit('submit'));

		return form($output);
	}


	function perform ( $edit )
	{
		global $lr_session;

		if( ! $this->can_edit ) {
			error_exit("You do not have permission to edit this game");
		}
		$home_spirit = formbuilder_load('team_spirit');
		$home_spirit->bulk_set_answers( $_POST['team_spirit_home'] );
		$away_spirit = formbuilder_load('team_spirit');
		$away_spirit->bulk_set_answers( $_POST['team_spirit_away'] );

		$dataInvalid = $this->isDataInvalid( $edit );

		if($edit['status'] == 'normal') {
			$dataInvalid .= $home_spirit->answers_invalid();
			$dataInvalid .= $away_spirit->answers_invalid();
		}
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		// store the old info:
		$oldgameresults['home_score'] = $this->game->home_score;
		$oldgameresults['away_score'] = $this->game->away_score;

		// Now, finalize score.
		$this->game->set('home_score', $edit['home_score']);
		$this->game->set('away_score', $edit['away_score']);
		if( $edit['sotg_home'] != '' ) {
			$this->game->set('home_spirit', $edit['sotg_home']);
		}
		if( $edit['sotg_away'] != '' ) {
			$this->game->set('away_spirit', $edit['sotg_away']);
		}
		$this->game->set('status', $edit['status']);
		$this->game->set('approved_by', $lr_session->attr_get('user_id'));

		switch( $edit['status'] ) {
			// for defaults, have to prepare both home and away spirit scores!
			case 'home_default':
				$home_spirit_values = $this->game->default_spirit('loser'); // penalize home for default
				$away_spirit_values = $this->game->default_spirit('winner');
				$this->game->set('home_spirit', $this->game->default_spirit('loser', true));
				$this->game->set('away_spirit', $this->game->default_spirit('winner', true));
				break;
			case 'away_default':
				$home_spirit_values = $this->game->default_spirit('winner');
				$away_spirit_values = $this->game->default_spirit('loser'); // penalize away for default
				$this->game->set('home_spirit', $this->game->default_spirit('winner', true));
				$this->game->set('away_spirit', $this->game->default_spirit('loser', true));
				break;
			case 'forfeit':
				$away_spirit_values = $this->game->default_spirit('loser');
				$home_spirit_values = $this->game->default_spirit('loser');
				break;
			case 'normal':
			default:
				$home_spirit_values = $home_spirit->bulk_get_answers();
				$away_spirit_values = $away_spirit->bulk_get_answers();
				break;
		}

		if( $edit['status'] != 'home_default' && !$this->game->save_spirit_entry( $this->game->home_id, $home_spirit_values) ) {
			error_exit("Error saving spirit entry for " . $this->game->home_name);
		}
		if( $edit['status'] != 'away_default' && !$this->game->save_spirit_entry( $this->game->away_id, $away_spirit_values) ) {
			error_exit("Error saving spirit entry for " . $this->game->away_name);
		}

		// load the teams in order to be able to save their current rating
		$home_team = team_load( array('team_id' => $this->game->home_id) );
		$away_team = team_load( array('team_id' => $this->game->away_id) );

		// only save the current team ratings if we didn't already save them
		if ($this->game->rating_home == null || $this->game->rating_home == "" &&
		    $this->game->rating_away == null || $this->game->rating_away == "") {
			// save the current snapshot of each team's rating:
			$this->game->set('rating_home', $home_team->rating);
			$this->game->set('rating_away', $away_team->rating);
		}

		if ( ! $this->game->save() ) {
			error_exit("Could not successfully save game results");
		}

		return true;
	}

	function isDataInvalid( $edit )
	{
		$errors = "";

		if($edit['status'] == 'normal') {
			if( !validate_number($edit['home_score']) ) {
				$errors .= '<br>You must enter a valid number for the home score.';
			}
			if( !validate_number($edit['away_score']) ) {
				$errors .= '<br>You must enter a valid number for the away score.';
			}

			$errors .= $this->isSOTGDataInvalid( $edit );

		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

# TODO: use classes/game.inc for loading, and pull this back into GameEdit
# since it's only ever used there
function game_score_entry_display( $game )
{
	global $dbh;
	$sth = $dbh->prepare('SELECT * FROM score_entry WHERE team_id = ? AND game_id = ?');
	$sth->execute(array($game->home_team, $game->game_id));
	$home = $sth->fetch();

	if(!$home) {
		$home = array(
			'score_for' => 'not entered',
			'score_against' => 'not entered',
			'defaulted' => 'no'
		);
	} else {
		$entry_person = person_load( array('user_id' => $home['entered_by']));
		$home['entered_by'] = l($entry_person->fullname, "person/view/$entry_person->user_id");
	}

	$sth->execute(array($game->away_team, $game->game_id));
	$away = $sth->fetch();
	if(!$away) {
		$away = array(
			'score_for' => 'not entered',
			'score_against' => 'not entered',
			'defaulted' => 'no'
		);
	} else {
		$entry_person = person_load( array('user_id' => $away['entered_by']));
		$away['entered_by'] = l($entry_person->fullname, "person/view/$entry_person->user_id");
	}

	$header = array(
		"&nbsp;",
		"$game->home_name (home)",
		"$game->away_name (away)"
	);

	$rows = array();

	$rows[] = array( "Home Score:", $home['score_for'], $away['score_against'],);
	$rows[] = array( "Away Score:", $home['score_against'], $away['score_for'],);
	$rows[] = array( "Defaulted?", $home['defaulted'], $away['defaulted'],);
	$rows[] = array( "Entered By:", $home['entered_by'], $away['entered_by'],);
	$rows[] = array( "Entry time:", $home['entry_time'], $away['entry_time'],);
	$rows[] = array( "SOTG Assigned:", $away['spirit'], $home['spirit'],);
	return'<div class="listtable">' . table($header, $rows) . "</div>";
}


######################################################################
# TONY added this GameDelete, which is pretty much a copy of GameEdit.
# It can use some work to be nicer, but I don't have time.
# For now, you can't delete a game that is already finalized.
# TODO: needs cleanup (as above)
#
class GameDelete extends Handler
{
	var $game;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('game','view', $this->game);
	}

	function process ()
	{
		global $lr_session;
		if(!$this->game) {
			error_exit("That game does not exist");
		}

		if( arg(1) == 'delete' ) {
			if( $lr_session->is_admin() ) {
				$this->can_edit = true;
			}

			if( $lr_session->is_coordinator_of($this->game->league_id)) {
				$this->can_edit = true;
			}
		} else {
			$this->can_edit = false;
		}

		$this->title = "Game Delete";

		$this->setLocation(array(
			"$this->title &raquo; Delete Game " . $this->game->game_id => 0));

		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $this->game, $edit );
				break;
			case 'perform':
				$this->perform( $edit );
				local_redirect(url("schedule/view/" . $this->league->league_id));
				break;
			default:
				$rc = $this->generateForm( );
		}

		return $rc;
	}

	function generateForm ( )
	{
		global $lr_session;
		# Alias, to avoid typing.  Bleh.
		$game = &$this->game;
		$league = &$this->league;

		$game->load_score_entries();

		$output = form_hidden('edit[step]', 'confirm');

		$output .= form_item("Game ID", $game->game_id);

		$teams = $league->teams_as_array();
		/* Now, since teams may not be in league any longer, we need to force
		 * them to appear in the pulldown
		 */
		$teams[$game->home_id] = $game->home_name;
		$teams[$game->away_id] = $game->away_name;

		$output .= form_item("League/Division", l($league->fullname, "league/view/$league->league_id"));

		$output .= form_item( "Home Team", l($game->home_name,"team/view/$game->home_id"));
		$output .= form_item( "Away Team", l($game->away_name,"team/view/$game->away_id"));

		if( $this->can_edit ) {
			$note = "To edit time, date, or location, use the 'reschedule' link";
		}
		$output .= form_item("Date and Time", "$game->game_date, $game->game_start until " . $game->display_game_end(), $note);

		$field = field_load( array('fid' => $game->fid) );
		$output .= form_item("Location",
			l("$field->fullname ($game->field_code)", "field/view/$game->fid"), $note);

		$output .= form_item("Game Status", $game->status);

		$output .= form_item("Round", $game->round);

		$spirit_group = '';
		$score_group = '';
		/*
		 * Now, for scores and spirit info.  Possibilities:
		 *  - game has been finalized:
		 *  	- everyone can see scores
		 *  	- coordinator can edit scores/spirit
		 *  - game has not been finalized
		 *  	- players only see "not yet submitted"
		 *  	- captains can see submitted scores
		 *  	- coordinator can see everything, edit final scores/spirit
		 */

		if($game->approved_by) {
			// Game has been finalized

			// TODO: Tony inserted this so that finalized games cannot be deleted!
			error_exit("Finalized games cannot be deleted at this time.");

			if( ! $this->can_edit ) {
				// If we're not editing, display score.  If we are,
				// it will show up below.
				switch($game->status) {
					case 'home_default':
						$home_status = " (defaulted)";
						break;
					case 'away_default':
						$away_status = " (defaulted)";
						break;
					case 'forfeit':
						$home_status = " (forfeit)";
						$away_status = " (forfeit)";
						break;
				}
				$score_group .= form_item("Home ($game->home_name [rated: $game->rating_home]) Score", "$game->home_score $home_status");
				$score_group .= form_item("Away ($game->away_name [rated: $game->rating_away]) Score", "$game->away_score $away_status");
			}

			$score_group .= form_item("Rating Points", $game->rating_points,"Rating points transferred to winning team from losing team");

			switch($game->approved_by) {
				case APPROVAL_AUTOMATIC:
					$approver = 'automatic approval';
					break;
				case APPROVAL_AUTOMATIC_HOME:
					$approver = 'automatic approval using home submission';
					break;
				case APPROVAL_AUTOMATIC_AWAY:
					$approver = 'automatic approval using away submission';
					break;
				case APPROVAL_AUTOMATIC_FORFEIT:
					$approver = 'game automatically forfeited due to lack of score submission';
					break;
				default:
					$approver = person_load( array('user_id' => $game->approved_by));
					$approver = l($approver->fullname, "person/view/$approver->user_id");
			}
			$score_group .= form_item("Score Approved By", $approver);

		} else {
			/*
			 * Otherwise, scores are still pending.
			 */
			$stats_group = '';
			/* Use our ratings to try and predict the game outcome */
			$homePct = $game->home_expected_win();
			$awayPct = $game->away_expected_win();

			$stats_group .= form_item("Chance to win", table(null, array(
				array($game->home_name, sprintf("%0.1f%%", (100 * $homePct))),
				array($game->away_name, sprintf("%0.1f%%", (100 * $awayPct))))));
			$output .= form_group("Statistics", $stats_group);


			$score_group .= form_item('',"Score not yet finalized");
			if( $lr_session->has_permission('game','view', $game, 'submission') ) {
				$score_group .= form_item("Score as entered", game_score_entry_display( $game ));

			}
		}

		$output .= form_group("Scoring", $score_group);

		if( $lr_session->has_permission('game','view',$game,'spirit') ) {

			$formbuilder = formbuilder_load('team_spirit');
			$ary = $game->get_spirit_entry( $game->home_id );
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );
			}
			$home_spirit_group = $formbuilder->render_viewable( $ary );

			$formbuilder->clear_answers();
			$ary = $game->get_spirit_entry( $game->away_id );
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );
			}
			$away_spirit_group = $formbuilder->render_viewable( $ary );

			$output .= form_group("Spirit assigned TO home ($game->home_name)", $home_spirit_group);
			$output .= form_group("Spirit assigned TO away ($game->away_name)", $away_spirit_group);
		}

		if( $this->can_edit ) {
			$output .= "<p><font color='red'>If you click <b>submit</b>, you will <b>delete</b> this game!</font></p>";
			$output .= para(form_submit("submit") . form_reset("reset"));
		}
		return $script . form($output);
	}

	function generateConfirm ( $game, $edit )
	{

		if( ! $this->can_edit ) {
			error_exit("You do not have permission to delete this game");
		}

		$output = para( "You have requested to <b>delete</b> the game: <b>$game->game_date $game->game_start between $game->home_name and $game->away_name</b>.  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page.");

		$output .= form_hidden('edit[step]', 'perform');

		$output .= para(form_submit('submit'));

		return form($output);
	}


	function perform ( $edit )
	{
		global $lr_session;

		if( ! $this->can_edit ) {
			error_exit("You do not have permission to delete this game");
		}

		if ( ! $this->game->delete() ) {
			error_exit("Could not successfully delete the game");
		}

		return true;
	}

}

######################################################################
# To remove just the results of a game... ie: teams enter wrong scores
# This will UNDO any change to rank, ratings, wins/losses/ties, goals for
#  goals against, SOTG, etc.....
# After this, the game can be re-entered since the game itself is not deleted.
#
class GameRemoveResults extends Handler
{
	var $game;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('game','view', $this->game);
	}

	function process ()
	{
		global $lr_session;
		if(!$this->game) {
			error_exit("That game does not exist");
		}

		if( arg(1) == 'removeresults' ) {
			if( $lr_session->is_admin() ) {
				$this->can_edit = true;
			}

			if( $lr_session->is_coordinator_of($this->game->league_id)) {
				$this->can_edit = true;
			}
		} else {
			$this->can_edit = false;
		}

		$this->title = "Game Remove Results";

		$this->setLocation(array(
			"$this->title &raquo; Remove Results for Game " . $this->game->game_id => 0));

		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $this->game, $edit );
				break;
			case 'perform':
				$this->perform( $edit );
				local_redirect(url("schedule/view/" . $this->league->league_id));
				break;
			default:
				$rc = $this->generateForm( );
		}

		return $rc;
	}

	function generateForm ( )
	{
		global $lr_session;
		# Alias, to avoid typing.  Bleh.
		$game = &$this->game;
		$league = &$this->league;

		$game->load_score_entries();

		$output = form_hidden('edit[step]', 'confirm');

		$output .= form_item("Game ID", $game->game_id);

		$teams = $league->teams_as_array();
		/* Now, since teams may not be in league any longer, we need to force
		 * them to appear in the pulldown
		 */
		$teams[$game->home_id] = $game->home_name;
		$teams[$game->away_id] = $game->away_name;

		$output .= form_item("League/Division", l($league->fullname, "league/view/$league->league_id"));

		$output .= form_item( "Home Team", l($game->home_name,"team/view/$game->home_id"));
		$output .= form_item( "Away Team", l($game->away_name,"team/view/$game->away_id"));

		if( $this->can_edit ) {
			$note = "To edit time, date, or location, use the 'reschedule' link";
		}
		$output .= form_item("Date and Time", "$game->game_date, $game->game_start until " . $game->display_game_end(), $note);

		$field = field_load( array('fid' => $game->fid) );
		$output .= form_item("Location",
			l("$field->fullname ($game->field_code)", "field/view/$game->fid"), $note);

		$output .= form_item("Game Status", $game->status);

		$output .= form_item("Round", $game->round);

		$spirit_group = '';
		$score_group = '';

		/*
		 * Now, for scores and spirit info.  Possibilities:
		 *  - game has been finalized:
		 *  	- everyone can see scores
		 *  	- coordinator can edit scores/spirit
		 *  - game has not been finalized
		 *  	- players only see "not yet submitted"
		 *  	- captains can see submitted scores
		 *  	- coordinator can see everything, edit final scores/spirit
		 */

		if( ! $game->approved_by) {
			// if the game is not finalized, results cannot be removed yet...
			error_exit("The game is not finalized, and so results cannot be removed at this time.");
		}

		// If we're not editing, display score.  If we are,
		// it will show up below.
		switch($game->status) {
			case 'home_default':
				$home_status = " (defaulted)";
				break;
			case 'away_default':
				$away_status = " (defaulted)";
				break;
			case 'forfeit':
				$home_status = " (forfeit)";
				$away_status = " (forfeit)";
				break;
		}
		$score_group .= form_item("Home ($game->home_name [rated: $game->rating_home]) Score", "$game->home_score $home_status");
		$score_group .= form_item("Away ($game->away_name [rated: $game->rating_away]) Score", "$game->away_score $away_status");

		$score_group .= form_item("Rating Points", $game->rating_points,"Rating points transferred to winning team from losing team");

		switch($game->approved_by) {
			case APPROVAL_AUTOMATIC:
				$approver = 'automatic approval';
				break;
			case APPROVAL_AUTOMATIC_HOME:
				$approver = 'automatic approval using home submission';
				break;
			case APPROVAL_AUTOMATIC_AWAY:
				$approver = 'automatic approval using away submission';
				break;
			case APPROVAL_AUTOMATIC_FORFEIT:
				$approver = 'game automatically forfeited due to lack of score submission';
				break;
			default:
				$approver = person_load( array('user_id' => $game->approved_by));
				$approver = l($approver->fullname, "person/view/$approver->user_id");
		}
		$score_group .= form_item("Score Approved By", $approver);

		$output .= form_group("Scoring", $score_group);

		if( $lr_session->has_permission('game','view',$game,'spirit') ) {

			$formbuilder = formbuilder_load('team_spirit');
			$ary = $game->get_spirit_entry( $game->home_id );
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );
			}
			$home_spirit_group = $formbuilder->render_viewable( $ary );

			$formbuilder->clear_answers();
			$ary = $game->get_spirit_entry( $game->away_id );
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );
			}
			$away_spirit_group = $formbuilder->render_viewable( $ary );

			$output .= form_group("Spirit assigned TO home ($game->home_name)", $home_spirit_group);
			$output .= form_group("Spirit assigned TO away ($game->away_name)", $away_spirit_group);
		}

		if( $this->can_edit ) {
			$output .= "<p><font color='red'>If you click <b>submit</b>, you will <b>remove all results</b> for this game!</font></p>";
			$output .= para(form_submit("submit") . form_reset("reset"));
		}
		return $script . form($output);
	}

	function generateConfirm ( $game, $edit )
	{

		if( ! $this->can_edit ) {
			error_exit("You do not have permission to remove results for this game");
		}

		$output = para( "You have requested to <b>remove results</b> for the game: <b>$game->game_date $game->game_start between $game->home_name and $game->away_name</b>.  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page.");

		$output .= form_hidden('edit[step]', 'perform');

		$output .= para(form_submit('submit'));

		return form($output);
	}


	function perform ( $edit )
	{
		global $lr_session;

		if( ! $this->can_edit ) {
			error_exit("You do not have permission to remove results for this game");
		}

		if ( ! $this->game->removeresults() ) {
			error_exit("Could not successfully remove results for the game");
		}

		return true;
	}

}

class GameRatings extends Handler
{
	var $game;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('game','view', $this->game);
	}

	function process ()
	{
		global $lr_session;
		if(!$this->game) {
			error_exit("That game does not exist");
		}

		$this->can_edit = false;

		$this->title = "Game Ratings Table";

		$this->setLocation(array(
			"$this->title &raquo; Game " . $this->game->game_id => 0));

		$rc = $this->generateForm( );

		return $rc;
	}

	function generateForm ( )
	{
		global $lr_session;

		$rating_home = arg(3);
		$rating_away = arg(4);
		$whatifratings = true;

		# Alias, to avoid typing.  Bleh.
		$game = &$this->game;
		$league = &$this->league;

		$game->load_score_entries();

		$teams = $league->load_teams();
		$teams = $league->teams;

		$home_team = null;
		$away_team = null;
		foreach ($teams as $team) {
			if ($team->team_id == $game->home_id) {
				$home_team = $team;
			} else if ($team->team_id == $game->away_id) {
				$away_team = $team;
			}
		}

		if ($rating_home == null || $rating_away == null) {
			$rating_home = $home_team->rating;
			$rating_away = $away_team->rating;
			$whatifratings = false;
		}

      $output = para("The number of rating points transferred depends on several factors:" .
      		"<br>- the total score" .
      		"<br>- the difference in score" .
      		"<br>- and the current rating of both teams");

      $output .= para("How to read the table below:" .
      		"<br>- Find the 'home' team's score along the left." .
      		"<br>- Find the 'away' team's score along the top." .
      		"<br>- The points shown in the table where these two scores intersect are the number of rating points that will be transfered from the losing team to the winning team.");

		$output .= para("A tie does not necessarily mean 0 rating points will be transfered... " .
				"Unless the two team's rating scores are very close, one team is expected to win. " .
				"If that team doesn't win, they will lose rating points. " .
				"The opposite is also true: if a team is expected to lose, but they tie, they will gain some rating points.");

		$output .= para("Ties are shown from the home team's perspective.  So, a negative value indicates " .
				"that in the event of a tie, the home team will lose rating points (and the away team will gain them).");

		$home = $game->home_name;
		$away = $game->away_name;

		if ($whatifratings) {
      	$output .= para("HOME: <b>$home</b>, 'what if' rating of <b>$rating_home</b> ".
      			"<br>AWAY: <b>$away</b>, 'what if' rating of <b>$rating_away</b>");
		} else {
      	$output .= para("HOME: <b>$home</b>, current rating of <b>$rating_home</b> ".
      			"<br>AWAY: <b>$away</b>, current rating of <b>$rating_away</b>");
		}

		$ratings_table = $game->get_ratings_table( $rating_home, $rating_away, true );

		return $output . $ratings_table ;
	}

}

/**
 * Generates the javascript, input textarea, and button for the SOTG suggestion
 * box.
 */
function generateSOTGButtonAndJavascript ($name, $label, $default = "") {
	$use = "";
	if ($name != null && $name != "") {
		$use = "_" . $name;
	}
	$sotgjs = "<script language='javascript'> \nfunction sotg$use() {\n";
	$sotgjs .= "var sotg=10;\n";
	$sotgjs .= "var form = document.getElementById('score_form');\n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[Timeliness]'][1].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[Timeliness]'][2].checked){sotg=sotg-2;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[Timeliness]'][3].checked){sotg=sotg-3;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[RulesKnowledge]'][1].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[RulesKnowledge]'][2].checked){sotg=sotg-2;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[RulesKnowledge]'][3].checked){sotg=sotg-3;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[Sportsmanship]'][1].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[Sportsmanship]'][2].checked){sotg=sotg-2;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[Sportsmanship]'][3].checked){sotg=sotg-3;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[Enjoyment]'][1].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[Enjoyment]'][2].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (form.elements['team_spirit" . $use . "[Enjoyment]'][3].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "return sotg;\n";
	$sotgjs .= "}\n";
	$sotgjs .= "</script>\n";

    return para( $sotgjs . "<div class=\"form-item\"><label>$label</label>" .
    	"<br> <input type=\"text\" maxlength=\"2\" class=\"form-text\" name=\"edit[sotg$use]\" size=\"2\" value=\"$default\" /> ".
		" <input type='button' name='suggest' value='Suggest' " .
		" onclick=\"document.getElementById('score_form').elements['edit[sotg$use]'].value=sotg$use();\"></div>");
}

?>
