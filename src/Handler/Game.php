<?php
/*
 * Handle operations specific to games
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
			return new GameEdit;
		case 'approve':
			return new GameEdit;
		case 'edit':
			return new GameEdit;
		case 'reschedule':
			# TODO: move a game from one gameslot to another.
			#       Requires addition of a 'rescheduled' flag in db
			return new GameReschedule;
/* TODO:
		case 'delete':
			// Allow deletion of a game (not gameslot!)
			return new GameDelete;
 */
	}
	return null;
}

/**
 * Add game information to menu
 * TODO: when permissions are fixed, remove the evil passing of $this
 */
function game_add_to_menu( $this, &$league, &$game )
{
	global $session;
	league_add_to_menu($this, $league);
	menu_add_child("$league->fullname", "$league->fullname/games", "Games");
	menu_add_child("$league->fullname/games", "$league->fullname/games/$game->game_id", "Game $game->game_id", array('link' => "game/view/$game->game_id"));

	if( $session->is_coordinator_of( $game->league_id ) ) {
		menu_add_child("$league->fullname/games/$game->game_id", "$league->fullname/games/$game->game_id/edit", "edit game", array('link' => "game/edit/$game->game_id"));
		menu_add_child("$league->fullname/games/$game->game_id", "$league->fullname/games/$game->game_id/reschedule", "reschedule game", array('link' => "game/reschedule/$game->game_id"));
	}
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
	var $league;

	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'coordinator_sufficient',
			'deny',
		);

		$this->title = "Add Game";

		return true;
	}
	
	function process ()
	{
		$league_id = arg(2);
		$year  = arg(3);
		$month = arg(4);
		$day   = arg(5);
		$end_year  = arg(6);
		$end_month = arg(7);
		$end_day   = arg(8);

		$this->league = league_load( array('league_id' => $league_id) );
		if(! $this->league ) {
			$this->error_exit("That league does not exist");
		}

		if ( $day ) {
			if ( ! validate_date_input($year, $month, $day) ) {
				$this->error_exit("That date is not valid");
			}
			$datestamp = mktime(0,0,0,$month,$day,$year);
			if ($end_day) {
				if ( ! validate_date_input($end_year, $end_month, $end_day) ) {
					$this->error_exit("That date is not valid");
				}
				$end_datestamp = mktime(0,0,0,$end_month,$end_day,$end_year);
			} else {
				$this->setLocation(array(
					$this->league->name => "league/view/$league_id",
					$this->title => 0
				));
				return $this->datePick($end_year, $end_month, $end_day, "/$year/$month/$day");
			}
		} else {
			$this->setLocation(array( 
				$this->league->name => "league/view/$league_id",
				$this->title => 0
			));
			return $this->datePick($year, $month, $day);
		}
		
		// Make sure we have fields allocated to this league for this date
		// before we proceed.
		$result = db_query("SELECT COUNT(*) FROM league_gameslot_availability a, gameslot s WHERE (a.slot_id = s.slot_id) AND a.league_id = %d AND UNIX_TIMESTAMP(s.game_date) = %d", $this->league->league_id, $datestamp);
		if( db_result($result) == 0) {
			$this->error_exit("Sorry, there are no fields available for your league on " . date("Y",$datestamp) . "-" . date("m",$datestamp) . "-" . date("d",$datestamp) . ".  Check that fields have been allocated before attempting to proceed.");
		}

		// Set up our menu
		switch($this->league->schedule_type) {
			case 'roundrobin':
				$this->types = array(
					'single' => 'single regular-season game',
					'oneset' => 'set of games for all teams in a tier',
					'fullround' => 'full-tier round-robin (does not work yet)',
					'halfround' => 'half-tier round-robin (does not work yet)',
					'qplayoff' => 'playoff ladder with quarter, semi and final games, and a consolation round (does not work yet)',
					'splayoff' => 'playoff ladder with semi and final games, and a consolation round (does not work yet)',
				);
				break;
			case 'ladder':
				$this->types = array(
					'ladder' => 'One set of games, ladder-style, 1vs2, 3vs4, etc...',
					'fullladder' => 'A <b>full season</b> of games using ladder-style two-week shuffle "hold and move" system',
					'qplayoff' => 'playoff ladder with quarter, semi and final games, and a consolation round (does not work yet)',
					'splayoff' => 'playoff ladder with semi and final games, and a consolation round (does not work yet)',
				);
				break;
			default:
				break;
		}

		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				return $this->perform($edit, $datestamp, $end_datestamp);
				break;
			case 'confirm':
				$this->setLocation(array( 
					$this->league->name => "league/view/$league_id",
					$this->title => 0
				));
				return $this->generateConfirm($edit, $datestamp, $end_datestamp);
				break;
			default:
				$this->setLocation(array( 
					$this->league->name => "league/view/$league_id",
					$this->title => 0
				));
				return $this->generateForm($datestamp, $end_datestamp);
				break;
		}
		$this->error_exit("Error: This code should never be reached.");
	}

	
	function datePick ( $year, $month, $day, $end = 0)
	{
		if ($end) {
			$output = para("Select a date below to <b>end</b> games for this league.  Days on which this league usually plays are highlighted in green.");
		} else {
			$output = para("Select a date below to <b>start</b> adding games to this league.  Days on which this league usually plays are highlighted in green.");
		}

		$today = getdate();
	
		if(! validate_number($month)) {
			$month = $today['mon'];
		}

		if(! validate_number($year)) {
			$year = $today['year'];
		}

		if ($end) {
			$output .= generateCalendar( $year, $month, $day, "game/create/" . $this->league->league_id . $end, "game/create/" . $this->league->league_id . $end, split(',',$this->league->day));
		} else {
			$output .= generateCalendar( $year, $month, $day, "game/create/" . $this->league->league_id, "game/create/" . $this->league->league_id, split(',',$this->league->day));
		}

		return $output;
	}
	
	function generateForm ( $datestamp, $end_datestamp = "" )
	{
		$output = para("Please enter some information about the game(s) to create.");
		$output .= para("<b>Note</b>: Creating full or half-tier round-robin games does not yet work.");
		$output .= form_hidden('edit[step]', 'confirm');
		
		$group = form_item("Starting on", strftime("%A %B %d %Y", $datestamp));
		if ($end_datestamp) {
			$group .= form_item("Ending on", strftime("%A %B %d %Y", $end_datestamp));
		}

		$group .= form_radiogroup("Create a", 'edit[type]', 'single', $this->types, "Select the type of game or games to add.  Note that for auto-generated round-robins, fields will be automatically allocated.");
		$output .= form_group("Game Information", $group);
		
		$output .= form_submit('submit') . form_reset('reset');

		return form($output);
	}
	
	function generateConfirm ( &$edit, $datestamp, $end_datestamp )
	{

		if (  ! array_key_exists( $edit['type'], $this->types) ) {
			$this->error_exit("That is not a valid selection for adding games");
		}
		

		// TODO HACK EVIL DMO
		switch( $edit['type'] ) {
			case 'ladder':
			case 'fullladder':
			case 'oneset':
				break;
			default:
				$this->error_exit("That selection doesn't work yet!  Don't bug Dave, he's got a lot to do right now.");
	
		}
	
		$output = para("Confirm that this information is correct for the game(s) you wish to create.");
		$output .= form_hidden('edit[step]', 'perform');
		
		$group = form_item("Starting on", strftime("%A %B %d %Y", $datestamp));
		if ($end_datestamp) {
			$group .= form_item("Ending on", strftime("%A %B %d %Y", $end_datestamp));
		}
		$group .= form_item("Create a", $this->types[$edit['type']] . form_hidden('edit[type]', $edit['type']));

		$group .= form_radiogroup("What to add", 'edit[type]', 'single', $types, "Select the type of game or games to add.  Note that for auto-generated round-robins, fields will be automatically allocated.");
		$output .= form_group("Game Information", $group);
		
		$output .= form_submit('submit') . form_reset('reset');

		return form($output);
	}

	function perform ( &$edit, $timestamp, $end_timestamp = "") {
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
			case 'oneset':
				return $this->createDayOfGames( $edit, $timestamp);
				break;
			case 'ladder':
				return $this->createLadderGameSet( $edit, $timestamp );
				break;
			case 'fullladder':
				return $this->createLadderSeason( $edit, $timestamp, $end_timestamp );
			default:
				$this->error_exit("That is not a valid option right now.");
	
		}
		$this->error_exit("This line of code should never be reached");
	}

	/**
	 * Create a single "round" for the ladder system
	 */
	function createLadderGameSet( $edit, $timestamp )
	{
		$league = $this->league;  // shorthand

		if ( ! $league->load_teams() ) {
			$this->error_exit("Error loading teams for league $league->fullname");
		}

		$num_teams = count($league->teams);

		if ($num_teams % 4 != 0) {
			$this->error_exit("The league MUST have a multiple of 4 teams.");
		}

		usort($league->teams, array($this, 'sort_teams_by_ranking'));
		$sorted_order = &$league->teams;

		$num_games = $num_teams / 2;

		// the array of games:
		$array_of_games = array();

		for($i = 0; $i < $num_games*2; $i=$i+2) {
			$ii = $i+1;
			$g = new Game;
			$g->set('league_id', $league->league_id);
			$g->set('home_team', $sorted_order[$i]->team_id);
			$g->set('away_team', $sorted_order[$ii]->team_id);
			if ( ! $g->save() ) {
				$this->rollback_games($array_of_games, "Could not successfully create a new game");
			}
			array_push($array_of_games,$g);
			if ( $g->select_random_gameslot($timestamp) ) {
				$this->rollback_games($array_of_games, "Could not assign a randome gameslot!");
			}
		}
	}
	
	/**
	 * Create an entire season of hold/move pairs for this league.
	 */
	function createLadderSeason( $edit, $timestamp, $end_timestamp )
	{
		$league = $this->league;  // shorthand

		if ( ! $league->load_teams() ) {
			$this->error_exit("Error loading teams for league $league->fullname");
		}

		// get an ordered array of team id's (ordered by rank by default)
		$team_ids = array();
		foreach ($league->teams as $key => $value) {
			array_push ($team_ids, $key);
		}

		$num_teams = count($league->teams);

		if ($num_teams % 4 != 0) {
			$this->error_exit("The league MUST have a multiple of 4 teams.");
		}
		
		$num_games = $num_teams / 2;

		// start with round 1, so HOLD game
		$round = 1;

		// all games array!
		$all_games = array();

		// DO THE FIRST GAME, SETTINGS TEAMS AND SUCH
		for($i = 0; $i < $num_games*2; $i=$i+2) {
			$ii = $i+1;
			$g = new Game;
			$g->set('league_id', $league->league_id);
			$g->set('round', $round);
			$g->set('home_team', $team_ids[$i]);
			$g->set('away_team', $team_ids[$ii]);
			if ( ! $g->save() ) {
				$this->error_exit("Could not successfully create a new game");
			}
			if( ! $g->select_random_gameslot($timestamp) ) {
				$this->rollback_games($all_games, "Sorry, could not assign a gameslot for " . date("Y",$timestamp) . "-" . date("m",$timestamp) . "-" . date("d",$timestamp));
			}

			array_push ( $all_games, $g );
		}


		// figure out how many games there are between the start and end dates (inclusively)
		$game_dates = $this->find_game_dates($league, $timestamp, $end_timestamp);

		// ensure that there are enough game slots on each of these days!
		foreach ($game_dates as $date) {
			$date_string = date("Y",$date) . "-" . date("m",$date) . "-" . date("d",$date);
			$foundGameSlot = db_query("SELECT COUNT(*) FROM league_gameslot_availability a LEFT JOIN gameslot g ON (a.slot_id = g.slot_id) WHERE game_date = '$date_string' AND league_id = $league->league_id");
			if (db_result($foundGameSlot) < $num_games) {
				$this->rollback_games($all_games, "Could not schedule games!  Not enough gameslots found for: " . date("Y",$date) . "-" . date("m",$date) . "-" . date("d",$date));
			}
		}
		reset($game_dates);

		// now, prepare the subsequent games
		foreach ($game_dates as $date) {
			// skip first set of games, which we've already created!
			if ($date == $timestamp) {
				continue;
			}
			$round++;
			for($i = 0; $i < $num_games; $i++) {
				$g = new Game;
				$g->set('league_id', $league->league_id);
				$g->set('round', $round);
				if ( ! $g->save() ) {
					$this->rollback_games($all_games, "Could not successfully create a new game");
				}
				if( ! $g->select_random_gameslot($date) ) {
					$this->rollback_games($all_games, "Could not assign a gameslot for " . date("Y",$date) . "-" . date("m",$date) . "-" . date("d",$date));
				}
				
				array_push ( $all_games, $g );
			}
		}

		$all_games = $this->set_dependents($all_games, $num_teams);
		
		local_redirect(url("schedule/view/$league->league_id"));
	}

	/********************************************************************************
	 *  This function takes in the array of games to delete, and an error message.
	 *  It will try to delete all the games, and will then exit with the error message
	 *   passed in.  If there's a problem deleting games, it'll tell you too.
	 */
	function rollback_games ($games, $error) {
		foreach ($games as $g) {
			if (! $g->delete() ) {
				$this->error_exit("First error: $error ... Then, on top of that, there was a problem deleting the games!!!");
			}
		}
		$this->error_exit($error);
	}

	/********************************************************************************
	 *  This function takes in the league object, a start date and an end date.
	 *  The return value is an array of dates for which this league will have games,
	 *   between the start and end dates (inclusively)
	 */
	function find_game_dates ($league, $start, $end) {
		$game_dates = array();
		array_push($game_dates, $start);

		$days = split(',', $league->day);

		$date = $start;
		while ($date < $end) {
			$date = mktime(0,0,0,date("n", $date), date("j", $date)+1, date("Y", $date));
			// loop through the days that this league plays
			foreach ($days as $d) {
				if ( date("l", $date) == $d ) {
					array_push($game_dates, $date);
				}
			}
		}
		return $game_dates;
	}

	/********************************************************************************
	 *  This function expects one ORDERED array with any number of sets of games, and will return a copy
	 *  of the input array with the dependent games filled in.
	 *  - The array should start with the first set of games with home/away teams already assigned, where
	 *    the first game is 1 vs 2, second game is 3 vs 4, etc...
	 *  - The function will skip the first game set, and then use it to assign the dependent games for
	 *    the second game set.
	 *  - It will then use the second game set to assign the dependent games for the third game set, and so on
	 *  - ASSUMPTION: the round number is used to determine HOLD and MOVE transitions, and it is assumed
	 *    that each game set has a round number incremented by one compared to the previous game set.  Furthermore,
	 *    it is assumed that the first game set starts with round 1.
	 */
	function set_dependents ($games, $number_of_teams) {
		
		$games_per_set = $number_of_teams / 2;
		$sets = count($games) / $games_per_set;

		$return_games = array();
		$count = 1;
		$wlflag = 0;
		$game_set = 1;
		$rankings = 1;
		foreach ($games as $g) {
			// don't do anything for the first game set
			if ($count <= $games_per_set) {
				array_push ( $return_games, $g );
				$count++;
				continue;
			}
			// first game will always be winners of first 2 "prev" games
			if ( $count - ($games_per_set*$game_set) == 1 ) {
				// you ALWAYS want to start the rankings at 1 here!
				$rankings = 1;
				$get = $count - $games_per_set - 1;
				$game = $games[ $get ];
				$g->set('home_dependant_game', $game->game_id);
				$g->set('home_dependant_type', "winner");
				$g->set('home_dependant_rank', $rankings);
				$rankings++;
				$game = $games[ $get+1 ];
				$g->set('away_dependant_game', $game->game_id);
				$g->set('away_dependant_type', "winner");
				$g->set('away_dependant_rank', $rankings);
				$rankings++;
				if ( !$g->save() ) {
					$this->rollback_games($return_games, "Could not save a game!");
				}
				array_push ( $return_games, $g );
				$count++;
				continue;
			}
			// the last game will always be the losers of the last 2 "prev" games
			if ( $count - ($games_per_set*$game_set) == $games_per_set ) {
				$get = $count - $games_per_set - 2;
				$game = $games[ $get ];
				$g->set('home_dependant_game', $game->game_id);
				$g->set('home_dependant_type', "loser");
				$g->set('home_dependant_rank', $rankings);
				$rankings++;
				$game = $games[ $get+1 ];
				$g->set('away_dependant_game', $game->game_id);
				$g->set('away_dependant_type', "loser");
				$g->set('away_dependant_rank', $rankings);
				$rankings++;
				if ( !$g->save() ) {
					$this->rollback_games($return_games, "Could not save a game!");
				}
				array_push ( $return_games, $g );
				$count++;
				$game_set++;
				continue;
			}

			$holdmove = $g->round % 2;
			// Invert the holdmove since very first set of games will be round 1, and
			// so the subsequent games which you're now scheduling should start with
			// a hold week, but because we're using the next game's round number, 
			// that number mod 2 will be 0, and we want 1 to start!
			$holdmove = !$holdmove;

			// if you've got here, you're looking at middle games, and the behaviour
			// here is dependent on the hold or move weeks!
			if ($holdmove) {
				// HOLD TRANSITION:
				if ($wlflag) {
					// do winners:
					$get = $count - $games_per_set - 1;
					$game = $games[ $get ];
					$g->set('home_dependant_game', $game->game_id);
					$g->set('home_dependant_type', "winner");
					$g->set('home_dependant_rank', $rankings);
					$rankings++;
					$game = $games[ $get+1 ];
					$g->set('away_dependant_game', $game->game_id);
					$g->set('away_dependant_type', "winner");
					$g->set('away_dependant_rank', $rankings);
					$rankings++;
				} else {
					// do losers:
					$get = $count - $games_per_set - 2;
					$game = $games[ $get ];
					$g->set('home_dependant_game', $game->game_id);
					$g->set('home_dependant_type', "loser");
					$g->set('home_dependant_rank', $rankings);
					$rankings++;
					$game = $games[ $get+1 ];
					$g->set('away_dependant_game', $game->game_id);
					$g->set('away_dependant_type', "loser");
					$g->set('away_dependant_rank', $rankings);
					$rankings++;
				}
				if ( !$g->save() ) {
					$this->rollback_games($return_games, "Could not save a game!");
				}
				array_push ( $return_games, $g );
				$count++;
				$wlflag = !$wlflag;
			} else {
				// MOVE TRANSITION:
				// get the loser:
				$get = $count - $games_per_set - 2;
				$game = $games[ $get ];
				$g->set('home_dependant_game', $game->game_id);
				$g->set('home_dependant_type', "loser");
				$g->set('home_dependant_rank', $rankings);
				$rankings++;
				// get the winner:
				$game = $games[ $get+2 ];
				$g->set('away_dependant_game', $game->game_id);
				$g->set('away_dependant_type', "winner");
				$g->set('away_dependant_rank', $rankings);
				$rankings++;
				if ( !$g->save() ) {
					$this->rollback_games($return_games, "Could not save a game!");
				}
				array_push ( $return_games, $g );
				$count++;
			}
		}
		return $return_games;
	}

	/** sorts an array of teams by their rank, from lowest rank (best) to highest rank (worst) **/
        function sort_teams_by_ranking (&$a, &$b)
        {
		// A first as we want ascending
                if ($a->rank == $b->rank) {
			return 0;
		}
		return ($a->rank < $b->rank) ? -1 : 1;
	}

	function createDayOfGames( &$edit, $timestamp ) 
	{
		$league = $this->league;  // shorthand
		
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
		 * TODO This would be soooo much nicer with transactions...
		 */
		$rollback_list = array();
		for($i = 0; $i < $num_games; $i++) {
			$g = new Game;
			$g->set('league_id', $league->league_id);
			if ( ! $g->save() ) {
				$this->error_exit("Could not successfully create a new game");
			}
			$rollback_list[] = $g;

			if( ! $g->select_random_gameslot($timestamp) ) {
				/* Argh, something failed, so roll back the whole pile of
				 * games */
				foreach( $rollback_list as $to_rollback ) {
					if( ! $to_rollback->delete() ) {
						$extra_errors = "<br />Also, failed to delete failed games correctly.  Please contact the system administrator";
					}
				}
				$this->error_exit("Could not create the games you requested, most likely due to an insufficient number of available fields.$extra_errors");
			}
		}
	
		// TODO: schedule/edit should edit a week of the schedule, similar to
		// how it's done now  (but without all the context?)
		// local_redirect(url("schedule/edit/$league->league_id/$timestamp"));
		local_redirect(url("schedule/view/$league->league_id"));
	}
}

class GameSubmit extends Handler
{
	var $game;
	var $team;

	function initialize ()
	{
		$this->title = "Submit Game Results";
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

		$this->game = game_load( array('game_id' => $gameID) );

		if( !$this->game ) {
			$this->error_exit("That game does not exist");
		}
	
		$this->team = team_load( array('team_id' => $teamID) );
		if( !$this->team ) {
			$this->error_exit("That team does not exist");
			return false;
		}
		
		if( $teamID != $this->game->home_id && $teamID != $this->game->away_id ) {
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
		if( $this->game->is_finalized() ) {
			$this->error_exit("The score for that game has already been submitted.");
		}

		$this->game->load_score_entries();

		if ( $this->game->get_score_entry( $this->team->team_id ) ) {
			$this->error_exit("The score for your team has already been entered.");
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
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = formbuilder_load('team_spirit');
			$questions->bulk_set_answers( $_POST['team_spirit'] );
			$dataInvalid = $questions->answers_invalid();
			if( $dataInvalid ) {
				$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
			}
			
			// Save the spirit entry if non-default
			if( !$this->game->save_spirit_entry( $opponent->team_id, $questions->bulk_get_answers()) ) {
				$this->error_exit("Error saving spirit entry for " . $this->team->team_id);
			}
		}

		// Now, we know we haven't finalized the game, so we first
		// save this team's score entry, as there isn't one already.
		if( !$this->game->save_score_entry( $this->team->team_id, $session->attr_get('user_id'), $edit['score_for'],$edit['score_against'],$edit['defaulted'] ) ) {
			$this->error_exit("Error saving score entry for " . $this->team->team_id);
		}
		
		// now, check if the opponent has an entry
		if( ! $this->game->get_score_entry( $opponent->team_id ) ) {
			// No, so we just mention that it's been saved and move on
			$resultMessage ="This score has been saved.  Once your opponent has entered their score, it will be officially posted";
			return para($resultMessage);
		}

		// Otherwise, both teams have an entry.  So, compare them:
		$home_entry = $this->game->get_score_entry( $this->game->home_id );
		$away_entry = $this->game->get_score_entry( $this->game->away_id );
		if( $this->game->score_entries_agree( object2array($home_entry), object2array($away_entry) ) ) {
			switch( $home_entry->defaulted ) {
				case 'us':
					$this->game->set('status', 'home_default');
					$this->game->save_spirit_entry( $this->game->away_id, $this->default_spirit('winner'));
					$this->game->save_spirit_entry( $this->game->home_id, $this->default_spirit('loser'));
					break;
				case 'them':
					$this->game->set('status', 'away_default');
					$this->game->save_spirit_entry( $this->game->away_id, $this->default_spirit('loser'));
					$this->game->save_spirit_entry( $this->game->home_id, $this->default_spirit('winner'));
					break;
				case 'no':
				default:
					// No default.  Just finalize score.
					$this->game->set('home_score', $home_entry->score_for);
					$this->game->set('away_score', $home_entry->score_against);
					$this->game->set('approved_by', -1);
			}

			if ( ! $this->game->save() ) {
				$this->error_exit("Could not successfully save game results");
			}
			
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
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if( $edit['defaulted'] == 'us' || $edit['defaulted'] == 'them' ) {
			// If it's a default, short-circuit the spirit-entry form and skip
			// straight to the confirmation
			return $this->generateConfirm($edit, $opponent);
		} else {
			// Force a non-default to display correctly
			$edit['defaulted'] = 'no';
		}

		$output = $this->interim_game_result(&$edit, &$opponent);
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
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if( $edit['defaulted'] != 'us' && $edit['defaulted'] != 'them' ) {
			$questions = formbuilder_load('team_spirit');
			$questions->bulk_set_answers( $_POST['team_spirit'] );
			$dataInvalid = $questions->answers_invalid();
			if( $dataInvalid ) {
				$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
			}
			
			// Force a non-default to display correctly
			$edit['defaulted'] = 'no';
		}
		
		$output = $this->interim_game_result(&$edit, &$opponent);
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

	/** 
	 * Return spirit to be given to a tean involved in a default
	 */
	function default_spirit ( $type )
	{
		switch( $type ) {
			case 'winner':
				return array(
					'Timeliness' => 'OnTime',
					'RulesKnowlege' => 'AcceptableRules',
					'Sportsmanship' => 'AcceptableSportsmanship',
					'Enjoyment' => 'MostEnjoyed',
					'GameOverall' => 'OverallAverage'
				);
				break;
			case 'loser':
				return array(
					'Timeliness' => 'MoreThanFive',
					'RulesKnowlege' => 'AcceptableRules',
					'Sportsmanship' => 'AcceptableSportsmanship',
					'Enjoyment' => 'FewEnjoyed',
					'GameOverall' => 'OverallAverage'
					
				);
				break;
			default:
				die("Invalid type $type given to default_spirit()");
		}
	}
}

class GameEdit extends Handler
{
	var $game;
	
	function initialize ()
	{
		$this->title = "Game";
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'allow'  # TODO: evil hack.  We do perms checks in process() below.
		);
		
		return true;
	}
	
	function process ()
	{
		global $session;
		$gameID = arg(2);
		
		$this->game = game_load( array('game_id' => $gameID) );
		if(!$this->game) {
			$this->error_exit("That game does not exist");
		}

		$this->_permissions = array(
			'edit_game'   => false,
			'view_spirit' => false,
		);

		if( arg(1) == 'edit' || arg(1) == 'approve' ) {
			$want_edit = true;
		} else {
			$want_edit = false;
		}
		if( $session->is_admin() ) {
			$this->_permissions['edit_game'] = $want_edit;
			$this->_permissions['view_spirit'] = true;
		}
		
		if( $session->is_coordinator_of($game->league_id)) {
			$this->_permissions['edit_game'] = $want_edit;
			$this->_permissions['view_spirit'] = true;
		}
		
		$this->setLocation(array(
			"$this->title &raquo; Game " . $this->game->game_id => 0));

		$edit = $_POST['edit'];
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $this->game, &$edit );
				break;
			case 'perform':
				$this->perform( &$edit );
				local_redirect(url("game/view/" . $this->game->game_id));
				break;
			default:
				$rc = $this->generateForm( );
		}

		return $rc;
	}
	
	function generateForm ( ) 
	{
		# Alias, to avoid typing.  Bleh.
		$game = &$this->game;

		$game->load_score_entries();
		
		$output = form_hidden('edit[step]', 'confirm');
		
		$output .= form_item("Game ID", $game->game_id);

		$league = league_load( array('league_id' => $game->league_id) );
		$teams = $league->teams_as_array();
		/* Now, since teams may not be in league any longer, we need to force
		 * them to appear in the pulldown
		 */
		$teams[$game->home_id] = $game->home_name;
		$teams[$game->away_id] = $game->away_name;

		$output .= form_item("League/Division", l($league->fullname, "league/view/$league->league_id"));

		$output .= form_item( "Home Team", l($game->home_name,"team/view/$game->home_id"));
		$output .= form_item( "Away Team", l($game->away_name,"team/view/$game->away_id"));

		if( $this->_permissions['edit_game'] ) {
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

			if( ! $this->_permissions['edit_game'] ) {
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
			
			if($game->approved_by != -1) {
				$approver = person_load( array('user_id' => $game->approved_by));
				$approver = l($approver->fullname, "person/view/$approver->user_id");
			} else {
				$approver = 'automatic approval';
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
			if( $this->_permissions['edit_game'] ) {
				$score_group .= form_item("Score as entered", game_score_entry_display( $game ));
				
			}
		}

		// Now, we always want to display this edit code if we have
		// permission to edit.
		if( $this->_permissions['edit_game'] ) {
			$score_group .= form_select('Game Status','edit[status]', $game->status, getOptionsFromEnum('schedule','status'), "To mark a game as defaulted, select the appropriate option here.  Appropriate scores will automatically be entered.");
			$score_group .= form_textfield( "Home ($game->home_name) score", 'edit[home_score]',$game->home_score,2,2);
			$score_group .= form_textfield( "Away ($game->away_name) score",'edit[away_score]',$game->away_score,2,2);
		
		}
		
		$output .= form_group("Scoring", $score_group);
		if ($this->_permissions['view_spirit']) {
		
			$formbuilder = formbuilder_load('team_spirit');
			$ary = $game->get_spirit_entry( $game->home_id );
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );
			}
			if($this->_permissions['edit_game']) {
				$home_spirit_group = $formbuilder->render_editable( $ary, 'home' );
			} else {
				$home_spirit_group = $formbuilder->render_viewable( $ary );
			}
		
			$formbuilder->clear_answers();
			$ary = $game->get_spirit_entry( $game->away_id );
			if( $ary ) {
				$formbuilder->bulk_set_answers( $ary );
			}
			if($this->_permissions['edit_game']) {
				$away_spirit_group = $formbuilder->render_editable( $ary , 'away');
			} else {
				$away_spirit_group = $formbuilder->render_viewable( $ary );
			}
			
			$output .= form_group("Spirit assigned TO home ($game->home_name)", $home_spirit_group);
			$output .= form_group("Spirit assigned TO away ($game->away_name)", $away_spirit_group);
		}

		game_add_to_menu($this, $league, $game);
	
		if( $this->_permissions['edit_game'] ) {
			$output .= para(form_submit("submit") . form_reset("reset"));
		}
		return $script . form($output);
	}
	
	function generateConfirm ( $game, $edit )
	{

		if( ! $this->_permissions['edit_game'] ) {
			$this->error_exit("You do not have permission to edit this game");
		}
	
		$dataInvalid = $this->isDataInvalid( $edit );
		
		$home_spirit = formbuilder_load('team_spirit');
		$home_spirit->bulk_set_answers( $_POST['team_spirit_home'] );
		$away_spirit = formbuilder_load('team_spirit');
		$away_spirit->bulk_set_answers( $_POST['team_spirit_away'] );
	
		if($edit['status'] == 'normal') {
			$dataInvalid .= $home_spirit->answers_invalid();
			$dataInvalid .= $away_spirit->answers_invalid();
		}
		
		if( $dataInvalid ) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$output = para( "You have made the changes below for the $game->game_date $game->game_start game between $game->home_name and $game->away_name.  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the score.");

		$output .= form_hidden('edit[step]', 'perform');
		if($edit['defaulted'] == 'home' || $edit['defaulted'] == 'away') {
			$output .= form_hidden('edit[defaulted]', $edit['defaulted']);		
		} else {
			$output .= form_hidden('edit[home_score]', $edit['home_score']);		
			$output .= form_hidden('edit[away_score]', $edit['away_score']);		
			$output .= form_hidden('edit[home_spirit]', $edit['home_spirit']);		
			$output .= form_hidden('edit[away_spirit]', $edit['away_spirit']);		
			$output .= $home_spirit->render_hidden('home');
			$output .= $away_spirit->render_hidden('away');
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
		
		$score_group .= form_item("Home ($game->home_name) Score",$edit['home_score']);
		$score_group .= form_item("Away ($game->away_name) Score", $edit['away_score']);
		$output .= form_group("Scoring", $score_group);
		
		$output .= form_group("Spirit assigned to home ($game->home_name)", $home_spirit->render_viewable());
		$output .= form_group("Spirit assigned to away ($game->away_name)", $away_spirit->render_viewable());
		
		$output .= para(form_submit('submit'));

		game_add_to_menu($this, $league, $game);


		return form($output);
	}
	
	
	function perform ( $edit )
	{
		global $session;
		
		if( ! $this->_permissions['edit_game'] ) {
			$this->error_exit("You do not have permission to edit this game");
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
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		// Now, finalize score.
		$this->game->set('home_score', $edit['home_score']);
		$this->game->set('away_score', $edit['away_score']);
		$this->game->set('approved_by', $session->attr_get('user_id'));

		// TODO: the default_spirit() stuff should be pushed into game.inc
		switch( $edit['status'] ) {
			case 'home_default':
				$home_spirit_values = $this->default_spirit('loser');
				$away_spirit_values = $this->default_spirit('winner');
				break;
			case 'away_default':
				$away_spirit_values = $this->default_spirit('loser');
				$home_spirit_values = $this->default_spirit('winner');
				break;
			case 'forfeit':
				$away_spirit_values = $this->default_spirit('loser');
				$home_spirit_values = $this->default_spirit('loser');
				break;
			case 'normal':
			default:
				$home_spirit_values = $home_spirit->bulk_get_answers();
				$away_spirit_values = $away_spirit->bulk_get_answers();
				break;
		}

		if( !$this->game->save_spirit_entry( $this->game->home_id, $home_spirit_values) ) {
			$this->error_exit("Error saving spirit entry for " . $this->game->home_name);
		}
		if( !$this->game->save_spirit_entry( $this->game->away_id, $away_spirit_values) ) {
			$this->error_exit("Error saving spirit entry for " . $this->game->away_name);
		}

		if ( ! $this->game->save() ) {
			$this->error_exit("Could not successfully save game results");
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
	}
	
	$away = db_fetch_array(db_query($se_query,$game->away_team,$game->game_id));
	if(!$away) {
		$away = array(
			'score_for' => 'not entered',
			'score_against' => 'not entered',
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
	return'<div class="listtable">' . table($header, $rows) . "</div>";
}
?>
