<?php
require_once('Handler/LeagueHandler.php');
class game_create extends LeagueHandler
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

	private $types;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','add game', $this->league->league_id);
	}

	function process ()
	{
		$this->title = "{$this->league->fullname} &raquo; Add Game";

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
				list( $rc, $message) = $this->league->create_empty_game( $edit['startdate'], $should_publish ) ;
				break;
			case 'blankset':
				# Create game for all teams in tier
				list( $rc, $message) = $this->league->create_empty_set( $edit['startdate'], $edit['excludeTeamID'], $should_publish ) ;
				break;
			case 'oneset':
				# Create game for all teams in tier
				list( $rc, $message) = $this->league->create_scheduled_set( $edit['startdate'], $edit['excludeTeamID'], $should_publish ) ;
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

?>
