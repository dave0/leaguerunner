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
			break;
		case 'view':
		case 'approve':
		case 'edit':
			$obj = new GameEdit;
			$obj->game = game_load( array('game_id' => $id) );
			$obj->league = league_load( array('league_id' => $obj->game->league_id) );
			break;
/* TODO:
		case 'reschedule':
			# TODO: move a game from one gameslot to another.
			#       Requires addition of a 'rescheduled' flag in db
			$obj = new GameReschedule;
			break;
		case 'delete':
			// Allow deletion of a game (not gameslot!)
			$obj = new GameDelete;
			break;
 */
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
			if($extra && $user->is_captain_of($extra->team_id)) {
				// If we have a specific team in mind, this user must be a
				// captain.
				return true;
			}
			if( $user->is_captain_of( $game->home_team )
			    || $user->is_captain_of($game->away_team )) {
				// Otherwise, check that user is captain of one of the teams
				return true;
			}
			if($user->is_coordinator_of($game->league_id)) {
				return true;
			}
			break;
		case 'edit':
			return ($user->is_coordinator_of($game->league_id));
			break; // unreached
		case 'view':
			if( $extra == 'spirit' ) { 
				return ($user->is_coordinator_of($game->league_id));
			}
			if( $extra == 'submission' ) { 
				return ($user->is_coordinator_of($game->league_id));
			}
			return ($user->status == 'active');
			break; // unreached
		case 'reschedule':
			//TODO
		
	}
	return false;
}

/**
 * Generate view of teams for initial login splash page.
 */
function game_splash ()
{
	global $session;

	$games = db_query("SELECT s.game_id, t.team_id, t.status FROM schedule s, gameslot g, teamroster t WHERE ((s.home_team = t.team_id OR s.away_team = t.team_id) AND t.player_id = %d) AND g.game_id = s.game_id
        AND g.game_date < CURDATE() ORDER BY g.game_date desc LIMIT 4", $session->user->user_id);
	$rows = array();
	while($row = db_fetch_object($games) ) {
		$game = game_load( array('game_id' => $row->game_id) );
		if( $game->home_score || $game->away_score ) { 
			$score = "$game->home_score - $game->away_score"	;
		} else if ( $row->status == 'captain' || $row->status == 'assistant') {
			$score = l('enter score', "game/submitscore/$game->game_id/$row->team_id");
		}
		array_unshift($rows, array( 
			l("$game->game_date $game->game_start-$game->game_end","game/view/$game->game_id"),
			array('data' => "$game->home_name vs. $game->away_name at $game->field_code", 'colspan' => 2),
			$score
		));
	}
	
	 $games = db_query("SELECT s.game_id FROM schedule s, gameslot g, teamroster t WHERE ((s.home_team = t.team_id OR s.away_team = t.team_id) AND t.player_id = %d) AND g.game_id = s.game_id
        AND g.game_date >= CURDATE() ORDER BY g.game_date asc LIMIT 4", $session->user->user_id);
	while($row = db_fetch_object($games) ) {
		$game = game_load( array('game_id' => $row->game_id) );
		$rows[] = array( 
			l("$game->game_date $game->game_start-$game->game_end","game/view/$game->game_id"),
			array('data' => "$game->home_name vs. $game->away_name at $game->field_code", 'colspan' => 3)
		);
	}

	# If no recent games, don't display the table
	if( count($rows) < 1)  {
		return;
	}
	
	return table(array(  array( 'data' => "Recent and Upcoming Games", 'colspan' => 4)), $rows);
}

/**
 * Add game information to menu
 */
function game_add_to_menu( &$league, &$game )
{
	global $session;
	menu_add_child("$league->fullname/schedule", "$league->fullname/schedule/$game->game_id", "Game $game->game_id", array('link' => "game/view/$game->game_id"));

	if( $session->has_permission('league','edit game', $game->league_id) ) {
		menu_add_child("$league->fullname/schedule/$game->game_id", "$league->fullname/schedule/$game->game_id/edit", "edit game", array('link' => "game/edit/$game->game_id"));
		menu_add_child("$league->fullname/schedule/$game->game_id", "$league->fullname/schedule/$game->game_id/reschedule", "reschedule game", array('link' => "game/reschedule/$game->game_id"));
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
		global $session;
		return $session->has_permission('league','add game', $this->league->league_id);
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

		# Must currently have even # of teams for scheduling
		if ($num_teams % 2) {
			error_exit("Must currently have an even number of teams in your league.  If you need a bye, please create a team named Bye and add it to your league");
		}

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
			default:
				error_exit("Wassamattayou!");
				break;
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
				return $this->selectDate( $edit['type'] );
				break;
			default:
				return $this->selectType();
				break;
		}
		error_exit("Error: This code should never be reached.");
	}
	
	function selectType ()
	{
		$output = "<p>Please enter some information about the game(s) to create.</p>";
		$output .= form_hidden('edit[step]', 'selectdate');
		
		$group .= form_radiogroup('', 'edit[type]', 'single', $this->types, "Select the type of game or games to add.  Note that for auto-generated round-robins, fields will be automatically allocated.");
		$output .= form_group("Create a ... ", $group);
		
		$output .= form_submit('Next step');

		return form($output);
	}

	function selectDate ( $type )
	{
		$num_teams = count($this->league->teams);

		switch($type) {
			case 'single':
				$num_fields = 1;
				$num_dates = 1;
				break;
			case 'oneset':
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
		$output = "<p>Select desired start date.  Scheduling a $type will require $tot_fields fields: $num_fields per day on $num_dates dates.</p>";

		$result = db_query(
			"SELECT DISTINCT UNIX_TIMESTAMP(s.game_date) as datestamp from league_gameslot_availability a, gameslot s WHERE (a.slot_id = s.slot_id) AND isnull(s.game_id) AND a.league_id = %d", $this->league->league_id);
			
		$possible_dates = array();
		while($date = db_fetch_object($result)) {
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
		$output .= form_select('Start date','edit[startdate]', null, $possible_dates);
		$output .= form_submit('Next step');
		return form($output);
	}

	function confirm ( &$edit )
	{
		switch($edit['type']) {
			case 'single':
			case 'oneset':
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
		
		$output .= form_item('What', $this->types[$edit['type']]);
		$output .= form_item('Start date', strftime("%A %B %d %Y", $edit['startdate']));
		$output .= form_submit('Create Games');
		return form($output);
	}
	
	function perform ( &$edit )
	{
		# TODO generate appropriate games, roll back on error
		switch($edit['type']) {
			case 'single':
				# Create single game
				break;
			case 'blankset':
				# Create game for all teams in tier
				list( $rc, $message) = $this->league->create_empty_set( $edit['startdate'] ) ;
				break;
			case 'oneset':
				# Create game for all teams in tier
				list( $rc, $message) = $this->league->create_scheduled_set( $edit['startdate'] ) ;
				break;
			case 'fullround':
				# Create full roundrobin
				list($rc, $message) = $this->league->create_full_roundrobin( $edit['startdate'] );
				break;
			case 'halfroundstandings':
				list($rc, $message) = $this->league->create_half_roundrobin( $edit['startdate'], 'standings' );
				break;
			case 'halfroundrating':
				list($rc, $message) = $this->league->create_half_roundrobin( $edit['startdate'], 'rating' );
				break;
			default:
				error_exit("Please don't try to do that; it won't work, you fool");
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
		global $session;
	
		return $session->has_permission('game','submit score', $this->game, $this->team);
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

		switch($edit['step']) {
			case 'spirit':
				$rc = $this->generateSpiritForm($edit, $opponent);
				break;
			case 'confirm':
				$rc = $this->generateConfirm($edit, $opponent);
				break;	
			case 'save':
				$rc = $this->perform($edit, $opponent);
				break;	
			default:
				$rc = $this->generateForm( $opponent );
		}
		
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function isScoreDataInvalid( $edit )
	{
		$errors = "";
		
		if( $edit['defaulted'] ) {
			switch($defaulted) {
				case 'us':
				case 'them':
				case '':
					return false;  // Ignore other data in cases of default.
				default:
					return "An invalid value was specified for default.";
			}
		}
		
		if( !validate_number($edit['score_for']) ) {
			$errors .= "<br>You must enter a valid number for your score";
		}

		if( !validate_number($edit['score_against']) ) {
			$errors .= "<br>You must enter a valid number for your opponent's score";
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
	
	function perform ($edit, $opponent)
	{
		global $session;

		$dataInvalid = $this->isScoreDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = formbuilder_load('team_spirit');
			$questions->bulk_set_answers( $_POST['team_spirit'] );
			$dataInvalid = $questions->answers_invalid();
			if( $dataInvalid ) {
				error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
			}
			
			// Save the spirit entry if non-default
			if( !$this->game->save_spirit_entry( $opponent->team_id, $questions->bulk_get_answers()) ) {
				error_exit("Error saving spirit entry for " . $this->team->team_id);
			}
		}

		// Now, we know we haven't finalized the game, so we first
		// save this team's score entry, as there isn't one already.
		if( !$this->game->save_score_entry( $this->team->team_id, $session->attr_get('user_id'), $edit['score_for'],$edit['score_against'],$edit['defaulted'] ) ) {
			error_exit("Error saving score entry for " . $this->team->team_id);
		}
		
		// now, check if the opponent has an entry
		if( ! $this->game->get_score_entry( $opponent->team_id ) ) {
			// No, so we just mention that it's been saved and move on
			$resultMessage ="This score has been saved.  Once your opponent has entered their score, it will be officially posted";
			return para($resultMessage);
		}

		// Otherwise, both teams have an entry.  So, attempt to finalize using
		// this information.
		if( $this->game->finalize() ) {
			$resultMessage = "This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.";
		} else {
			// Or, we have a disagreement.  Since we've already saved the
			// score, just say so, and continue.
			$resultMessage = "This score doesn't agree with the one your opponent submitted.  Because of this, the score will not be posted until your coordinator approves it.";
		}
		
		return para($resultMessage);
	}
	
	function generateSpiritForm ($edit, $opponent )
	{
		$dataInvalid = $this->isScoreDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if( $edit['defaulted'] == 'us' || $edit['defaulted'] == 'them' ) {
			// If it's a default, short-circuit the spirit-entry form and skip
			// straight to the confirmation
			return $this->generateConfirm($edit, $opponent);
		} else {
			// Force a non-default to display correctly
			$edit['defaulted'] = 'no';
		}

		$output = $this->interim_game_result($edit, $opponent);
		$output .= form_hidden('edit[step]', 'confirm');
			
		$output .= para("Now, you must rate your opponent's spirit using the following questions.  These are used both to generate an average spirit rating for each team, and to indicate to the league what areas might be problematic.");

		$questions = formbuilder_load('team_spirit');
		$output .= $questions->render_editable(false);

		$output .= para(form_submit("submit") . form_reset("reset"));

		return form($output);
	}


	function generateConfirm ($edit, $opponent )
	{
		$dataInvalid = $this->isScoreDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = formbuilder_load('team_spirit');
			$questions->bulk_set_answers( $_POST['team_spirit'] );
			$dataInvalid = $questions->answers_invalid();
			if( $dataInvalid ) {
				error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
			}
			
			// Force a non-default to display correctly
			$edit['defaulted'] = 'no';
		}
		
		$output = $this->interim_game_result($edit, $opponent);
		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$output .= para("The following answers will be used to assign your opponents' spirit:");
			$output .= $questions->render_viewable();
			$output .= $questions->render_hidden();
		} else {
			$output .= para("A spirit score will be automatically generated for your opponents.");
		}
		$output .= form_hidden('edit[step]', 'save');
	
		$output .= para("If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the score."
		);

		$output .= para(form_submit('submit'));

		return form($output);
	}

	function generateForm ( $opponent )
	{

		$output = para( "For the game of " . $this->game->sprintf('short') . " you have entered:");
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
		$output .= para(form_submit("submit") . form_reset("reset"));
		
		$script = <<<ENDSCRIPT
<script type="text/javascript"> <!--
  function defaultCheckboxChanged() {
    if (document.forms[0].elements['edit[defaulted]'][0].checked == true) {
        document.forms[0].elements['edit[score_for]'].value = "0";
        document.forms[0].elements['edit[score_for]'].disabled = true;
        document.forms[0].elements['edit[score_against]'].value = "6";
        document.forms[0].elements['edit[score_against]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][1].disabled = true;
    } else if (document.forms[0].elements['edit[defaulted]'][1].checked == true) {
        document.forms[0].elements['edit[score_for]'].value = "6";
        document.forms[0].elements['edit[score_for]'].disabled = true;
        document.forms[0].elements['edit[score_against]'].value = "0";
        document.forms[0].elements['edit[score_against]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][0].disabled = true;
    } else {
        document.forms[0].elements['edit[score_for]'].disabled = false;
        document.forms[0].elements['edit[score_against]'].disabled = false;
        document.forms[0].elements['edit[defaulted]'][0].disabled = false;
        document.forms[0].elements['edit[defaulted]'][1].disabled = false;
    }
  }
// -->
</script>
ENDSCRIPT;

		return $script . form($output);
	}

	function interim_game_result( $edit, $opponent )
	{
		$output = para( "For the game of " . $this->game->sprintf('short') . " you have entered:");
		$rows = array();
		switch($edit['defaulted']) {
		case 'us':
			$rows[] = array($this->team->name, "0 (defaulted)");
			$rows[] = array($opponent->name, 6);
			break;
		case 'them':
			$rows[] = array($this->team->name, 6);
			$rows[] = array($opponent->name, "0 (defaulted)");
			break;
		default:
			$rows[] = array($this->team->name, $edit['score_for'] . form_hidden('edit[score_for]', $edit['score_for']));
			$rows[] = array($opponent->name, $edit['score_against'] . form_hidden('edit[score_against]', $edit['score_against']));
			break;
		}

		$output .= form_hidden('edit[defaulted]', $edit['defaulted']);

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
}

class GameEdit extends Handler
{
	var $game;
	
	function has_permission ()
	{
		global $session;
		return $session->has_permission('game','view', $this->game);
	}
	
	function process ()
	{
		global $session;
		if(!$this->game) {
			error_exit("That game does not exist");
		}

		if( arg(1) == 'edit' || arg(1) == 'approve' ) {
			if( $session->is_admin() ) {
				$this->can_edit = true;
			}
	
			if( $session->is_coordinator_of($this->game->league_id)) {
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
		global $session;
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
		$output .= form_item("Date and Time", "$game->game_date, $game->game_start until $game->game_end", $note);

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
				$score_group .= form_item("Home ($game->home_name) Score", "$game->home_score $home_status");
				$score_group .= form_item("Away ($game->away_name) Score", "$game->away_score $away_status");
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
			if( $session->has_permission('game','view', $game, 'submission') ) {
				$score_group .= form_item("Score as entered", game_score_entry_display( $game ));
				
			}
		}

		// Now, we always want to display this edit code if we have
		// permission to edit.
		if( $this->can_edit ) {
			$score_group .= form_select('Game Status','edit[status]', $game->status, getOptionsFromEnum('schedule','status'), "To mark a game as defaulted, select the appropriate option here.  Appropriate scores will automatically be entered.");
			$score_group .= form_textfield( "Home ($game->home_name) score", 'edit[home_score]',$game->home_score,2,2);
			$score_group .= form_textfield( "Away ($game->away_name) score",'edit[away_score]',$game->away_score,2,2);
		
		}
		
		$output .= form_group("Scoring", $score_group);
	
		if( $session->has_permission('game','view',$game,'spirit') ) {
		
			$formbuilder = formbuilder_load('team_spirit');
			$ary = $game->get_spirit_entry( $game->home_id );
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
		return $script . form($output);
	}
	
	function generateConfirm ( $game, $edit )
	{

		if( ! $this->can_edit ) {
			error_exit("You do not have permission to edit this game");
		}

	
		$dataInvalid = $this->isDataInvalid( $edit );
		
		$home_spirit = formbuilder_load('team_spirit');
		$away_spirit = formbuilder_load('team_spirit');

		switch($edit['status']) {
			case 'home_default':
				$home_spirit->bulk_set_answers($this->game->default_spirit('loser'));
				$away_spirit->bulk_set_answers($this->game->default_spirit('winner'));
				$edit['home_score'] = '0 (defaulted)';
				$edit['away_score'] = '6';
				break;
			case 'away_default':
				$away_spirit->bulk_set_answers($this->game->default_spirit('loser'));
				$home_spirit->bulk_set_answers($this->game->default_spirit('winner'));
				$edit['home_score'] = '6';
				$edit['away_score'] = '0 (defaulted)';
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
		$output .= $home_spirit->render_hidden('home');
		$output .= $away_spirit->render_hidden('away');
		
		$score_group .= form_item("Home ($game->home_name) Score",$edit['home_score']);
		$score_group .= form_item("Away ($game->away_name) Score", $edit['away_score']);
		$output .= form_group("Scoring", $score_group);
		
		$output .= form_group("Spirit assigned to home ($game->home_name)", $home_spirit->render_viewable());
		$output .= form_group("Spirit assigned to away ($game->away_name)", $away_spirit->render_viewable());
		
		$output .= para(form_submit('submit'));

		return form($output);
	}
	
	
	function perform ( $edit )
	{
		global $session;
		
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

		// Now, finalize score.
		$this->game->set('home_score', $edit['home_score']);
		$this->game->set('away_score', $edit['away_score']);
		$this->game->set('status', $edit['status']);
		$this->game->set('approved_by', $session->attr_get('user_id'));

		switch( $edit['status'] ) {
			case 'home_default':
				$home_spirit_values = $this->game->default_spirit('loser');
				$away_spirit_values = $this->game->default_spirit('winner');
				break;
			case 'away_default':
				$away_spirit_values = $this->game->default_spirit('loser');
				$home_spirit_values = $this->game->default_spirit('winner');
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

		if( !$this->game->save_spirit_entry( $this->game->home_id, $home_spirit_values) ) {
			error_exit("Error saving spirit entry for " . $this->game->home_name);
		}
		if( !$this->game->save_spirit_entry( $this->game->away_id, $away_spirit_values) ) {
			error_exit("Error saving spirit entry for " . $this->game->away_name);
		}

		if ( ! $this->game->save() ) {
			error_exit("Could not successfully save game results");
		}

		// Game has been saved to database.  Now we can update the dependant games.
		if (! $this->game->updatedependentgames()) {
			error_exit("Could not update dependant games.");
		}

		return true;
	}
	
	function isDataInvalid( $edit )
	{
		$errors = "";

		if($edit['status'] == 'normal') {
			if( !validate_number($edit['home_score']) ) {
				$errors .= "<br>You must enter a valid number for the home score";
			}
			if( !validate_number($edit['away_score']) ) {
				$errors .= "<br>You must enter a valid number for the away score";
			}
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
	$se_query = "SELECT * FROM score_entry WHERE team_id = %d AND game_id = %d";
	$home = db_fetch_array(db_query($se_query,$game->home_team,$game->game_id));
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
	
	$away = db_fetch_array(db_query($se_query,$game->away_team,$game->game_id));
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
	return'<div class="listtable">' . table($header, $rows) . "</div>";
}
?>
