<?php
/*
 * Handle operations specific to a single game
 */

function game_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new GameCreate;
		case 'submitscore':
			return new GameSubmit;
		case 'view':
			return new GameView;
		case 'approve':
			return new GameApprove;
/* TODO:
		case 'edit':
			# TODO: Allow editing of all game data
			#       Not of gameslot data, aside from rescheduling, though
			return new GameEdit;
		case 'reschedule:
			# TODO: move a game from one gameslot to another.
			#       Requires addition of a 'rescheduled' flag in db
			return new GameReschedule;
*/
	}
	return null;
}

class GameCreate extends Handler
{
/*
 * This is intended to replace ScheduleAddDay.  It will (in a similar manner
 * to the gameslot creation)
 *   - present user with a calendar to select a day
 *   - ask user what they want to schedule on that day:
 *      - one game, manually scheduled
 *      - (n - 1)   game round-robin (full-tier)
 *      - (n/2 - 1) game round-robin (half-tier)
 *   - if one game, present with form for editing game info
 *   - if round-robin, auto-generate games with random fields and insert them
 *   (no confirmation -- coordinator can fix later if necessary)
 *
 * Need to make some changes to the schedule table, too:
 *   - add support for postponed/rescheduled games.  This should be done by
 *     adding a new field to the game table, called 'status'. Status would be
 *     'normal' under most circumstances, but could be changed to
 *     'rescheduled', 'cancelled', or 'forfeit'.   Another field named
 *     'rescheduled_slot' would store rescheduling info.
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
 *
 * Also need to look at adding fields for dependant games:
 *   - game would need a way of determining which games to populate 
 *     schedule from.  One way of doing it:
 *        home_from: game id of another game
 *        away_from: game id of another game
 *        home_wanted: (winner|loser)
 *        away_wanted: (winner|loser)
 *     fields would be added to schedule, and a cron script would need to
 *     update home_team/away_team based on this info.  Either that, or we
 *     update it each time we finalize a score.
 *
 *   - schedule's 'round' field needs to be varchr instead of integer, to
 *   allow for rounds named 'quarter-final', 'semi-final', and 'final', and
 *   pulldown menus updated appropriately. 
 *   (DONE in db, needs code);
 */

	var $types;

	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);

		$this->title = "Add Game";
		$this->types = array(
			'single' => 'single regular-season game',
			'oneday' => 'day of games for all teams in a tier',
			'fullround' => 'full-tier round-robin (does not work yet)',
			'halfround' => 'half-tier round-robin (does not work yet)',
			'splayoff' => 'playoff ladder with semi and final games, and a consolation round (does not work yet)',
		);

		
		return true;
	}
	
	function process ()
	{
		$league_id = arg(2);
		$year  = arg(3);
		$month = arg(4);
		$day   = arg(5);

		$league = league_load( array('league_id' => $league_id) );
		if(! $league ) {
			$this->error_exit("That league does not exist");
		}

		if ( $day ) {
			if ( ! validate_date_input($year, $month, $day) ) {
				$this->error_exit("That date is not valid");
			}
			$datestamp = mktime(0,0,0,$month,$day,$year);
		} else {
			$this->setLocation(array( 
				$league->name => "league/view/$league->league_id",
				$this->title => 0
			));
			return $this->datePick($league, $year, $month, $day);
		}
		
		// Make sure we have fields allocated to this league for this date
		// before we proceed.
		$result = db_query("SELECT COUNT(*) FROM league_gameslot_availability a, gameslot s WHERE (a.slot_id = s.slot_id) AND a.league_id = %d AND UNIX_TIMESTAMP(s.game_date) = %d", $league->league_id, $datestamp);
		if( db_result($result) == 0) {
			$this->error_exit("Sorry, there are no fields available for your league on that day");
		}

		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				return $this->perform( $league, $edit, $datestamp);
				break;
			case 'confirm':
				$this->setLocation(array( 
					$league->name => "league/view/$league->league_id",
					$this->title => 0
				));
				return $this->generateConfirm($league, $edit, $datestamp);
				break;
			default:
				$this->setLocation(array( 
					$league->name => "league/view/$league->league_id",
					$this->title => 0
				));
				return $this->generateForm($league, $datestamp);
				break;
		}
		$this->error_exit("Error: This code should never be reached.");
	}

	
	function datePick ( &$league, $year, $month, $day)
	{
		$output = para("Select a date below to start adding games to this league.  Days on which this league usually plays are highlighted in green.");

		$today = getdate();
	
		if(! ctype_digit($month)) {
			$month = $today['mon'];
		}

		if(! ctype_digit($year)) {
			$year = $today['year'];
		}

		$output .= generateCalendar( $year, $month, $day, "game/create/$league->league_id", "game/create/$league->league_id", split(',',$league->day));

		return $output;
	}
	
	function generateForm ( &$league, $datestamp )
	{
		$output = para("Please enter some information about the game(s) to create.");
		$output .= para("<b>Note</b>: Creating full or half-tier round-robin games does not yet work.");
		$output .= form_hidden('edit[step]', 'confirm');
		
		$group = form_item("Starting on", strftime("%A %B %d %Y", $datestamp));

		$group .= form_radiogroup("Create a", 'edit[type]', 'single', $this->types, "Select the type of game or games to add.  Note that for auto-generated round-robins, fields will be automatically allocated.");
		$output .= form_group("Game Information", $group);
		
		$output .= form_submit('submit') . form_reset('reset');

		return form($output);
	}
	
	function generateConfirm ( &$league, &$edit, $datestamp )
	{

		if (  ! array_key_exists( $edit['type'], $this->types) ) {
			$this->error_exit("That is not a valid selection for adding games");
		}

		// TODO HACK EVIL DMO
		if( ($edit['type'] != 'single') && ($edit['type'] != 'oneday')) {
			$this->error_exit("That selection doesn't work yet!  Don't bug Dave, he's got a lot to do right now.");
		}
	
		$output = para("Confirm that this information is correct for the game(s) you wish to create.");
		$output .= form_hidden('edit[step]', 'perform');
		
		$group = form_item("Starting on", strftime("%A %B %d %Y", $datestamp));
		$group .= form_item("Create a", $this->types[$edit['type']] . form_hidden('edit[type]', $edit['type']));

		$group .= form_radiogroup("What to add", 'edit[type]', 'single', $types, "Select the type of game or games to add.  Note that for auto-generated round-robins, fields will be automatically allocated.");
		$output .= form_group("Game Information", $group);
		
		$output .= form_submit('submit') . form_reset('reset');

		return form($output);
	}

	function perform ( &$league, &$edit, $timestamp) {
/*
 * Possible problems with autogeneration ( needs checking in code, possible
 * bailouts):
 *   - insufficient fields for game(s) wanted
 *   - how to handle gappy scheduling (ie: 3-week round-robin, played on two
 *   consecutive weeks, a week off, and then a third game)
 *   - how to handle selection of days/fields when auto-round-robin is
 *     selected for tiers that play multiple nights (think fall league).
 *     Current guess is that we:
 *       - start at the given day, schedule all games there.  Then find the
 *         next weekday we play on (from league table), and look ahead in the
 *         available fields to see if there are enough fields on the next
 *         matching day.  If so, schedule it then, otherwise look to the next
 *         weekday with matching fields and so on.
 *
 */
		switch( $edit['type'] ) {
			case 'single':
				// TODO: 
				// - create a single game entry, with a single gameslot
				// allocated to it.  
				$this->error_exit("That is not a valid option right now.");
				break;
			case 'oneday':
				return $this->createDayOfGames( $league, $edit, $timestamp);
				break;
			default:
				$this->error_exit("That is not a valid option right now.");
	
		}
		$this->error_exit("This line of code should never be reached");
	}

	function createDayOfGames( &$league, &$edit, $timestamp ) 
	{
		if ( ! $league->load_teams() ) {
			$this->error_exit("Error loading teams for league $league->fullname");
		}
		
		$num_teams = count($league->teams);

		if($num_teams < 2) {
			$this->error_exit("Cannot schedule games in a league with less than two teams");
		}
		
		/*
		 * TODO: We only schedule floor($num_teams / 2) games.  This means
		 * that the odd team out won't show up on the schedule.  Perhaps we
		 * should schedule ceil($num_teams / 2) and have the coordinator
		 * explicitly set a bye?
		 */
		$num_games = floor($num_teams / 2);
		
		/* Now, randomly create our games.  Don't add any teams, or set a
		 * round, or anything.  Then, use that game ID to randomly allocate us
		 * a gameslot.
		 */
		db_query('START TRANSACTION');
		for($i = 0; $i < $num_games; $i++) {
			$g = new Game;
			$g->set('league_id', $league->league_id);
			if ( ! $g->save() ) {
				$this->error_exit("Could not successfully create a new game");
			}

			// This is how we randomly select a field. 
			// TODO: Need to switch to transactional tables!  Major breakage!
			// TODO WARNING DMO: the following is untested.  Check return
			// codes!
			$result = db_query("SELECT s.slot_id FROM gameslot s, league_gameslot_availability a WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) = %d AND a.league_id = %d ORDER BY RAND() LIMIT 1", $timestamp, $league->league_id);
			$slot_id = db_result($result);
			print "Slot ID is $slot_id";
			if( ! $slot_id ) {
				db_query('ROLLBACK');			
				$this->error_exit("Not enough field slots available for that league");
			}

			db_query("UPDATE gameslot SET game_id = %d WHERE ISNULL(game_id) AND slot_id = %d", $g->game_id, $slot_id);
			if(1 != db_affected_rows() ) {
				db_query('ROLLBACK');			
				$this->error_exit("Not enough field slots available for that league");
			}
		}
		db_query('COMMIT');
	
		$this->error_exit("Not completed yet");
		// TODO: schedule/edit should edit a week of the schedule, similar to
		// how it's done now  (but without all the context?)
		local_redirect(url("schedule/edit/$league->league_id/$timestamp"));
	}
}

class GameSubmit extends Handler
{
	function initialize ()
	{
		$this->title = "Submit Game Score";
		return true;
	}

	function has_permission ()
	{
		global $session;
		if(!$session->is_valid()) {
			$this->error_exit("You do not have a valid session");
			return false;
		}

		if(!$session->is_player()) {
			$this->error_exit("You do not have permission to perform that operation");
			return false;
		}

		$gameID = arg(2);
		$teamID = arg(3);
		if( !$gameID ) {
			return false;
		}

		if( !$teamID ) {
			/* TODO: write code to allow coordinators/admins to submit
			 * final scores for entire games, not just one team.  Probably
			 * should do it as a game/edit/ handler.
			 */
			return false;
		}

		$game = game_load( array('game_id' => $gameID) );
		
		if( $teamID != $game->home_id && $teamID != $game->away_id ) {
			$this->error_exit("That team did not play in that game!");
		}
		
		if($session->is_admin()) {
			$this->set_permission_flags('administrator');
			return true;
		}

		if($session->is_coordinator_of($game->league_id)) {
			$this->set_permission_flags('coordinator');
			return true;
		}

		if($session->is_captain_of($teamID)) {
			$this->set_permission_flags('captain');
			return true;
		}

		return false;
	}

	function process ()
	{
		$gameID = arg(2);
		$teamID = arg(3);

		$game = game_load( array('game_id' => $gameID) );
		if( !$game ) {
			$this->error_exit("That game does not exist");
		}
		
		if(isset($game->home_score) && isset($game->away_score) ) {
			$this->error_exit("The score for that game has already been submitted.");
		}
		
		$result = db_query(
			"SELECT entered_by FROM score_entry WHERE game_id = %d AND team_id = %d", $gameID, $teamID);

		if(db_num_rows($result) > 0) {
			$this->error_exit("The score for your team has already been entered.");
		}

		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($game, $teamID, $edit);
				break;
			case 'perform':
				$rc = $this->perform($game, $teamID, $edit);
				break;
			default:
				$rc = $this->generateForm($game, $teamID);
		}
	
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function isDataInvalid( $edit )
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

		if( !validate_number($edit['spirit']) ) {
			$errors .= "<br>You must enter a valid number for your opponent's SOTG";
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
	
	function perform ($game, $teamID, $edit)
	{
		global $session;

		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$result = db_query("SELECT score_for, score_against, spirit, defaulted FROM score_entry WHERE game_id = %d",$game->game_id);
		$opponent_entry = db_fetch_array($result);

		if( ! db_num_rows($result) ) {
			// No opponent entry, so just add to the score_entry table
			if(game_save_score_entry($game->game_id, $teamID, $edit) == false) {
				return false;
			}
			$resultMessage ="This score has been saved.  Once your opponent has entered their score, it will be officially posted";
		} else {
			/* See if we agree with opponent score */
			if( defaults_agree($edit, $opponent_entry) ) {
				// Both teams agree that a default has occurred. 
				if(
					($teamID == $game->home_id)
					&& ($edit['defaulted'] == 'us')
				) {
					$rc = game_save_score_final( $game, array('defaulted' => 'home') );
				} else {
					$rc = game_save_score_final( $game, array('defaulted' => 'away') );
				}

				if( !$rc ) {
					return false;
				}
				
				$resultMessage = "This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.";
				
			} else if( scores_agree($edit, $opponent_entry) ) {
				/* Agree. Make it official */
				if($teamID == $game->home_id) {
					$rc = game_save_score_final( $game, array( 
						'defaulted' => 'no', 
						'home_score' => $edit['score_for'], 
						'away_score' => $edit['score_against'],
						'home_spirit' => $opponent_entry['spirit'],
						'away_spirit' => $edit['spirit']));
				} else {
					$rc = game_save_score_final( $game, array( 
						'defaulted' => 'no', 
						'home_score' => $edit['score_against'], 
						'away_score' => $edit['score_for'],
						'home_spirit' => $edit['spirit'],
						'away_spirit' => $opponent_entry['spirit']));
				}
				if( !$rc ) {
					return false;
				}

				$resultMessage = "This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.";
			} else {
				if(game_save_score_entry($game->game_id, $teamID, $edit) == false) {
					return false;
				}
				$resultMessage = "This score doesn't agree with the one your opponent submitted.  Because of this, the score will not be posted until your coordinator approves it.";
			}
		}
		
		return para($resultMessage);
	}

	function generateConfirm ($game, $teamID, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
	
		if($game->home_id == $teamID) {
			$myName = $game->home_name;
			$opponentName = $game->away_name;
			$opponentId = $game->away_id;
		} else {
			$myName = $game->away_name;
			$opponentName = $game->home_name;
			$opponentId = $game->home_id;
		}

		$output = para( "You have entered the following score for the $game->game_date $game->game_start game between $myName and $opponentName.");
		$output .= para("If this is correct, please click 'Submit' to continue.  "
			. "If not, use your back button to return to the previous page and correct the score."
		);

		$output .= form_hidden('edit[step]', 'perform');
		$output .= form_hidden('edit[defaulted]', $edit['defaulted']);

		$rows = array();
		switch($edit['defaulted']) {
		case 'us':
			$rows[] = array("$myName:", "0 (defaulted)");
			$rows[] = array("$opponentName:", 6);
			break;
		case 'them':
			$rows[] = array("$myName:", 6);
			$rows[] = array("$opponentName:", "0 (defaulted)");
			break;
		default:
			$rows[] = array("$myName:", $edit['score_for'] . form_hidden('edit[score_for]', $edit['score_for']));
			$rows[] = array("$opponentName:", $edit['score_against'] . form_hidden('edit[score_against]', $edit['score_against']));
			$rows[] = array("SOTG for $opponentName:", $edit['spirit'] . form_hidden('edit[spirit]', $edit['spirit']));
			break;
		}

		$output .= '<div class="pairtable">' . table(null, $rows) . "</div>";
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function generateForm ( $game, $teamID ) 
	{

		if($game->home_id == $teamID) {
			$myName = $game->home_name;
			$opponentName = $game->away_name;
			$opponentId = $game->away_id;
		} else {
			$myName = $game->away_name;
			$opponentName = $game->home_name;
			$opponentId = $game->home_id;
		}

		$output = para( "Submit the score for the $game->game_date, $game->game_start game between $myName and $opponentName.");
		$output .= para("If your opponent has already entered a score, it will be displayed below.  "
  			. "If the score you enter does not agree with this score, posting of the score will "
			. "be delayed until your coordinator can confirm the correct score.");

		$output .= form_hidden('edit[step]', 'confirm');

		$result = db_query("SELECT score_for, score_against, defaulted FROM score_entry WHERE game_id = %d AND team_id = %d", $game->game_id, $opponentId);
				
		$opponent = db_fetch_array($result);

		if($opponent) {
			if($opponent['defaulted'] == 'us') {
				$opponent['score_for'] .= " (defaulted)"; 
			} else if ($opponent['defaulted'] == 'them') {
				$opponent['score_against'] .= " (defaulted)"; 
			}
			
		} else {
			$opponent = array();
			$opponent['score_for'] = "not yet entered";
			$opponent['score_against'] = "not yet entered";
		}
		
		$rows = array();
		$header = array( "Team Name", "Defaulted?", "Your Score Entry", "Opponent's Score Entry", "SOTG");
	
	
		$rows[] = array(
			$myName,
			"<input type='checkbox' name='edit[defaulted]' value='us' onclick='defaultCheckboxChanged()'>",
			form_textfield("","edit[score_for]","",2,2),
			$opponent['score_against'],
			"&nbsp;"
		);
		
		$rows[] = array(
			$opponentName,
			"<input type='checkbox' name='edit[defaulted]' value='them' onclick='defaultCheckboxChanged()'>",
			form_textfield("","edit[score_against]","",2,2),
			$opponent['score_for'],
			form_select("", "edit[spirit]", "--", getOptionsFromRange(1,10))
				. "<font size='-2'>(<a href='/leagues/spirit_guidelines.html' target='_new'>spirit guideline</a>)</font>"
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
        document.forms[0].elements['edit[spirit]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][1].disabled = true;
    } else if (document.forms[0].elements['edit[defaulted]'][1].checked == true) {
        document.forms[0].elements['edit[score_for]'].value = "6";
        document.forms[0].elements['edit[score_for]'].disabled = true;
        document.forms[0].elements['edit[score_against]'].value = "0";
        document.forms[0].elements['edit[score_against]'].disabled = true;
        document.forms[0].elements['edit[spirit]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][0].disabled = true;
    } else {
        document.forms[0].elements['edit[score_for]'].disabled = false;
        document.forms[0].elements['edit[score_against]'].disabled = false;
        document.forms[0].elements['edit[spirit]'].disabled = false;
        document.forms[0].elements['edit[defaulted]'][0].disabled = false;
        document.forms[0].elements['edit[defaulted]'][1].disabled = false;
    }
  }
// -->
</script>
ENDSCRIPT;

		return $script . form($output);
	}
}

class GameView extends Handler
{
	function initialize ()
	{
		$this->title = "View Game";
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'allow'
		);
		
		$this->_permissions = array(
			'view_entered_scores' => false,
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

		$id = arg(2);

		$game = game_load( array('game_id' => $id) );
			 
		if( !$game ) {
			$this->error_exit('That game does not exist');
		}
		
		$rows[] = array("Game ID:", $game->game_id);
		$rows[] = array("Date:", $game->game_date);
		$rows[] = array("Time:", $game->game_start);

		$league = league_load( array('league_id' => $game->league_id) );
		$rows[] = array("League/Division:",
			l($league->fullname, "league/view/$league->league_id")
		);
		
		$rows[] = array("Home Team:", 
			l($game->home_name, "team/view/$game->home_team"));
		$rows[] = array("Away Team:", 
			l($game->away_name, "team/view/$game->away_team"));

	
		$site = site_load( array('site_id' => $game->site_id) );
		$rows[] = array("Field:",
			l("$site->name $game->field_num ($game->field_code)", "site/view/$game->site_id"));
			
		$rows[] = array("Round:", $game->round);

		if($game->home_score || $game->away_score) {
			// TODO: show default status here.
			$scoreRows[] = array($game->home_name, $game->home_score);
			$scoreRows[] = array($game->away_name, $game->away_score);
				
			$rows[] = array("Score:", "<div class='pairtable'>" . table(null, $scoreRows) . "</div>");
			$rows[] = array("Rating Points:", $game->rating_points);

		} else {
			/* Use our ratings to try and predict the game outcome */
			$homePct = elo_expected_win($game->home_rating, $game->away_rating);
			$awayPct = 1 - $homePct;

			$rows[] = array("Chance to win:", table(null, array(
				array($game->home_name, sprintf("%0.1f%%", (100 * $homePct))),
				array($game->away_name, sprintf("%0.1f%%", (100 * $awayPct))))));

			/* And of course, show the scores to those who are allowed */	
			if( $this->_permissions['view_entered_scores'] ) {
				$rows[] = array("Score:", game_score_entry_display( $game ));
				if($game->approved_by) {
					if($game->approved_by != -1) {
						$approver = person_load( array('user_id' => $game->approved_by));
						$approver = l($approver->fullname, "person/view/$approver->user_id");
					} else {
						$approver = 'automatic';
					}
				} else {
					$approver = 'awaiting approval';
				}
				$rows[] = array("Score Approved By:", $approver);		
			} else {
				$rows[] = array("Score:","not yet entered");
				
			}
		}


		$this->setLocation(array(
			"$this->title &raquo; $game->home_name vs. $game->away_name" => 0));

		# TODO: this is a little unpleasant
		$league = league_load( array('league_id' =>  $game->league_id) );
		league_add_to_menu($this, $league);
		menu_add_child("$league->fullname", "$league->fullname/games", "Games");
		menu_add_child("$league->fullname/games", "$league->fullname/games/$game->game_id", "$game->home_name vs $game->away_name", array('link' => "game/view/$game->game_id"));
			
		return "<div class='pairtable'>" . table(null, $rows) . "</div>";
	}
}

class GameApprove extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'allow'  # TODO: evil hack.  We do perms checks in process() below.
		);
		$this->title = "Approve Game Score";
		return true;
	}
	
	function process ()
	{
		global $session;
		$id = arg(2);

		$game = game_load( array('game_id' => $id) );
		if(!$game) {
			$this->error_exit("That game does not exist");
		}
		
		if(!($session->is_admin() || $session->is_coordinator_of($game->league_id) ) ) {
			$this->error_exit("You do not have permission to approve that game.");
		}
		
		$this->setLocation(array(
			"Game $id" => "game/view/$id",
			$this->title => 0
		));

		$edit = $_POST['edit'];
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $game, &$edit );
				break;
			case 'perform':
				$this->perform( $game, &$edit );
				local_redirect(url("league/approvescores/$game->league_id"));
				break;
			default:
				$rc = $this->generateForm( $game );
		}

		return $rc;
	}

	function perform ( $game, $edit )
	{
		global $session;
	
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$edit['approved_by'] = $session->attr_get('user_id');

		return game_save_score_final($game, $edit);
	}

	function generateConfirm ( $game, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para( "You have entered the following score for the $game->game_date $game->game_start game between $game->home_name and $game->away_name.  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the score.");

		$output .= form_hidden('edit[step]', 'perform');
		if($edit['defaulted'] == 'home' || $edit['defaulted'] == 'away') {
			$output .= form_hidden('edit[defaulted]', $edit['defaulted']);		
		} else {
			$output .= form_hidden('edit[home_score]', $edit['home_score']);		
			$output .= form_hidden('edit[away_score]', $edit['away_score']);		
			$output .= form_hidden('edit[home_spirit]', $edit['home_spirit']);		
			$output .= form_hidden('edit[away_spirit]', $edit['away_spirit']);		
		}
		
		if($edit['defaulted'] == 'home') {
			$edit['home_score'] = '0 (defaulted)';
			$edit['away_score'] = '6';
			$edit['home_spirit'] = 'n/a';
			$edit['away_spirit'] = 'n/a';
		} else if ($edit['defaulted'] == 'away') {
			$edit['home_score'] = '6';
			$edit['away_score'] = '0 (defaulted)';
			$edit['home_spirit'] = 'n/a';
			$edit['away_spirit'] = 'n/a';
		}
	
		$header = array( "Team", "Score", "SOTG");
		$rows = array(
			array($game->home_name, $edit['home_score'], $edit['home_spirit']),
			array($game->away_name, $edit['away_score'], $edit['away_spirit'])
		);
	
		$output .= '<div class="listtable">' . table($header, $rows) . "</div>";

		$output .= para(form_submit('submit'));

		return form($output);
	}

	function generateForm ( $game ) 
	{
		$output = para( "Finalize the score for <b>Game $game->game_id</b> of $game->game_date $game->game_start between <b>$game->home_name</b> and <b>$game->away_name</b>.");
		
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
			form_select("", "edit[home_spirit]", '', getOptionsFromRange(1,10))
		);
		$rows[] = array(
			"$game->away_name (away) assigned spirit:",
			form_select("", "edit[away_spirit]", '', getOptionsFromRange(1,10))
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
        document.forms[0].elements['edit[home_spirit]'].disabled = true;
        document.forms[0].elements['edit[away_spirit]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][1].disabled = true;
    } else if (document.forms[0].elements['edit[defaulted]'][1].checked == true) {
        document.forms[0].elements['edit[home_score]'].value = '6';
        document.forms[0].elements['edit[home_score]'].disabled = true;
        document.forms[0].elements['edit[away_score]'].value = '0';
        document.forms[0].elements['edit[away_score]'].disabled = true;
        document.forms[0].elements['edit[home_spirit]'].disabled = true;
        document.forms[0].elements['edit[away_spirit]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][0].disabled = true;
    } else {
        document.forms[0].elements['edit[home_score]'].disabled = false;
        document.forms[0].elements['edit[away_score]'].disabled = false;
        document.forms[0].elements['edit[home_spirit]'].disabled = false;
        document.forms[0].elements['edit[away_spirit]'].disabled = false;
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
			if( !validate_number($edit['home_spirit']) ) {
				$errors .= "<br>You must enter a valid number for the home SOTG";
			}
			if( !validate_number($edit['away_spirit']) ) {
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

function game_score_entry_display( $game )
{
	$se_query = "SELECT * FROM score_entry WHERE team_id = %d AND game_id = %d";
	$home = db_fetch_array(db_query($se_query,$game->home_team,$game->game_id));
	if(!$home) {
		$home = array(
			'score_for' => 'not entered',
			'score_against' => 'not entered',
			'spirit' => 'not entered',
			'defaulted' => 'no' 
		);
	}
	
	$away = db_fetch_array(db_query($se_query,$game->away_team,$game->game_id));
	if(!$away) {
		$away = array(
			'score_for' => 'not entered',
			'score_against' => 'not entered',
			'spirit' => 'not entered',
			'defaulted' => 'no' 
		);
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
	$rows[] = array( "Opponent SOTG:", $home['spirit'], $away['spirit'],);
	
	return'<div class="listtable">' . table($header, $rows) . "</div>";
}


function scores_agree( $one, $two )
{
	if(($one['score_for'] == $two['score_against']) && ($one['score_against'] == $two['score_for']) ) {
		return true;
	} 
	return false;
}

function defaults_agree( $one, $two )
{
	if(
		($one['defaulted'] == 'us' && $two['defaulted'] == 'them')
		||
		($one['defaulted'] == 'them' && $two['defaulted'] == 'us')
	) {
		return true;
	} 
	return false;
}

/**
 * Save a score entry for one of a game's participants
 */
function game_save_score_entry ( $gameID, $teamID, $our_entry ) 
{
	global $session;

	if($our_entry['defaulted'] == 'us') {
		$our_entry['score_for'] = 0;
		$our_entry['score_against'] = 6;
		$our_entry['spirit'] = 0;
	} else if($our_entry['defaulted'] == 'them') {
		$our_entry['score_for'] = 6;
		$our_entry['score_against'] = 0;
		$our_entry['spirit'] = 0;
	} else {
		$our_entry['defaulted'] = 'no';
	} 
		
	db_query("INSERT INTO score_entry 
		(game_id,team_id,entered_by,score_for,score_against,spirit,defaulted)
			VALUES(%d,%d,%d,%d,%d,%d,'%s')",
			$gameID, $teamID, $session->attr_get('user_id'), $our_entry['score_for'], $our_entry['score_against'], $our_entry['spirit'], $our_entry['defaulted']);

	if( 1 != db_affected_rows() ) {
		return false;
	}
	
	return true;
}

/**
 * Save final score for a single game
 * $entry should contain:
 * 	defaulted - final value for defaulted
 * 	home_score, away_score - final values for score (if not defaulted)
 * 	home_spirit, away_spirit - final values for spirit (if not defaulted)
 * and optionally:
 *  approved_by = ID of user approving it
 */
function game_save_score_final( $game, $entry )
{
	switch($entry['defaulted']) {
	case 'home':
		$entry['home_score'] = 0;
		$entry['away_score'] = 6;
		$entry['home_spirit'] = 0;
		$entry['away_spirit'] = 0;
		$rating_points = 0;
		break;
	case 'away':
		$entry['away_score'] = 0;
		$entry['home_score'] = 6;
		$entry['home_spirit'] = 0;
		$entry['away_spirit'] = 0;
		$rating_points = 0;
		break;
	default:
		$entry['defaulted'] = 'no';
		$home = team_load( array('team_id' => $game->home_id) );
		$away = team_load( array('team_id' => $game->away_id) );
		/* And, calculate the Elo value for this game.  It's only
		 * applicable iff we're not defaulted.
		 */
		if($entry['home_score'] > $entry['away_score']) {
			$rating_points = calculate_elo_change( $entry['home_score'], $entry['away_score'], $home->rating, $away->rating);
		} else {
			$rating_points = calculate_elo_change( $entry['away_score'], $entry['home_score'], $away->rating, $home->rating);
		}
	}
	if( !array_key_exists("approved_by", $entry) ) {
		$entry['approved_by'] = -1;
	}

	db_query("UPDATE schedule SET
		home_score = %d,
		away_score = %d,
		home_spirit = %d,
		away_spirit = %d,
		approved_by = %d,
		rating_points = %d,
		defaulted = '%s' WHERE game_id = %d", array(
			$entry['home_score'],
			$entry['away_score'],
			$entry['home_spirit'],
			$entry['away_spirit'],
			$entry['approved_by'],
			$rating_points,
			$entry['defaulted'],
			$game->game_id) );

	if(1 != db_affected_rows()) {
		return false;
	}

	/* Update ratings if we've got a change */
	if($rating_points && ($entry['home_score'] > $entry['away_score']) ) {
		db_query("UPDATE team SET rating = rating + %d WHERE team_id = %d", $rating_points, $game->home_id);
		db_query("UPDATE team SET rating = rating + %d WHERE team_id = %d", (0 - $rating_points), $game->away_id);
	} else {
		db_query("UPDATE team SET rating = rating + %d WHERE team_id = %d", (0 - $rating_points), $game->home_id);
		db_query("UPDATE team SET rating = rating + %d WHERE team_id = %d", $rating_points, $game->away_id);
	}

	db_query("DELETE FROM score_entry WHERE game_id = %d",$game->game_id);
	if(1 != db_affected_rows()) {
		return false;
	}

	return true;
}



/**
 * Calculate the value to be added/subtracted from the competing teams' 
 * ratings.
 * Modified Elo system, similar to the one
 * used for international soccer (http://www.eloratings.net), with several
 * Ultimate-specific modifications:
 * 	- all games currently weighted equally (though playoff games will be
 * 	  weighted differently in the future)
 * 	- score differential bonus modified for Ultimate
 * 	- no bonus given for 'home field advantage' since there's no
 * 	  real advantage in OCUA.
 *
 * Now, this code should work regardless of which score is passed as A and B,
 * but since we'd like to have positive change numbers as much as possible (in
 * the case of ties, we sometimes won't) we'll make sure when calling this
 * code to pass the winning score as scoreA and the losing score as scoreB.
 *
 * TODO: The 'right' way to fix this would probably be to modify this (and the
 * calling code) to use 'home' and 'away' everywhere instead of winner/loser
 * or A/B, as there's no chance of screwing up the order.
 */
function calculate_elo_change($scoreA, $scoreB, $ratingA, $ratingB)
{
	/* TODO: Should be a config variable in the league table? */
	$weightConstant = 40;
	$scoreWeight = 1;

	if($scoreA > $scoreB) {
		$gameValue = 1;
	} else if($scoreA == $scoreB) {
		$gameValue = 0.5;
	} else {
		$gameValue = 0;
	}
	
	/* If the score differential is greater than 1/3 the 
	 * winning score, add a bonus.
	 * This means that the bonus is given in summer games of 15-10 or
	 * worse, and in indoor games with similar score ratios.
	 */
	$scoreDiff = $scoreA - $scoreB;
	$scoreMax  = max($scoreA, $scoreB);
	if($scoreMax && (($scoreDiff / $scoreMax) > (1/3)) ) {
		$scoreWeight += $scoreDiff / $scoreMax;
	}

	$expectedWin = elo_expected_win($ratingA, $ratingB);

	return $weightConstant * $scoreWeight * ($gameValue - $expectedWin);
}

/* 
 * Calculate the expected win percentage for team A over team B
 * Used both in the elo calculations and as a fun thing to display on the
 * schedule.
 */
function elo_expected_win( $ratingA, $ratingB ) 
{
	$power = pow(10, ((0 - ($ratingA - $ratingB)) / 400));
	$expectedWin = (1 / ($power + 1));
	return $expectedWin;
}

?>
