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
		case 'approvescores':
			$obj = new LeagueApproveScores;
			break;
		case 'member':
			$obj = new LeagueMemberStatus;
			break;
		case 'spirit':
			$obj = new LeagueSpirit;
         break;
		case 'rank':
			$obj = new LeagueRank;
			break;
		case 'status':
			$obj = new LeagueStatusReport;
			break;
		default:
			return null;
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
		case 'edit schedule':
		case 'manage teams':
			return ($user->is_coordinator_of($id));
		case 'create':
		case 'delete':
			// admin only
			break;
	}
	return false;
}

function league_menu()
{
	global $lr_session;

	if( !$lr_session->is_player() ) {
		return;
	}
	
	menu_add_child('_root','league','Leagues');
	menu_add_child('league','league/list','list leagues', array('link' => 'league/list') );
	if( $lr_session->is_valid() ) {
		while(list(,$league) = each($lr_session->user->leagues) ) {
			league_add_to_menu($league);
		}
		reset($lr_session->user->leagues);
	}
	if($lr_session->has_permission('league','create') ) {
		menu_add_child('league', 'league/create', "create league", array('link' => "league/create", 'weight' => 1));
	}
}

/**
 * Add view/edit/delete links to the menu for the given league
 */
function league_add_to_menu( &$league, $parent = 'league' ) 
{
	global $lr_session;

	menu_add_child($parent, $league->fullname, $league->fullname, array('weight' => -10, 'link' => "league/view/$league->league_id"));
	
   if($league->schedule_type != 'none') {
		menu_add_child($league->fullname, "$league->fullname/standings",'standings', array('weight' => -1, 'link' => "league/standings/$league->league_id"));
		menu_add_child($league->fullname, "$league->fullname/schedule",'schedule', array('weight' => -1, 'link' => "schedule/view/$league->league_id"));
		if($lr_session->has_permission('league','add game', $league->league_id) ) {
			menu_add_child("$league->fullname/schedule", "$league->fullname/schedule/edit", 'add games', array('link' => "game/create/$league->league_id"));
		} 
		if($lr_session->has_permission('league','approve scores', $league->league_id) ) {
			menu_add_child($league->fullname, "$league->fullname/approvescores",'approve scores', array('weight' => 1, 'link' => "league/approvescores/$league->league_id"));
		}
	}
	
	if($lr_session->has_permission('league','edit', $league->league_id) ) {
		menu_add_child($league->fullname, "$league->fullname/edit",'edit league', array('weight' => 1, 'link' => "league/edit/$league->league_id"));
      if ( $league->schedule_type == "pyramid" ) {
         menu_add_child($league->fullname, "$league->fullname/rank",'adjust ranks', array('weight' => 1, 'link' => "league/rank/$league->league_id"));
      }
		menu_add_child($league->fullname, "$league->fullname/member",'add coordinator', array('weight' => 2, 'link' => "league/member/$league->league_id"));
	}

	if($lr_session->has_permission('league','view', $league->league_id, 'captain emails') ) {
		menu_add_child($league->fullname, "$league->fullname/captemail",'captain emails', array('weight' => 3, 'link' => "league/captemail/$league->league_id"));
	}

	if($lr_session->has_permission('league','view', $league->league_id, 'spirit') ) {
		menu_add_child($league->fullname, "$league->fullname/spirit",'spirit', array('weight' => 3, 'link' => "league/spirit/$league->league_id"));
	}
	if($lr_session->has_permission('league','edit', $league->league_id) ) {
      if ( $league->schedule_type == "pyramid" ) { //&& $lr_session->is_admin() ) {
         menu_add_child($league->fullname, "$league->fullname/status",'status report', array('weight' => 1, 'link' => "league/status/$league->league_id"));
      }
   }
}

/**
 * Generate view of leagues for initial login splash page.
 */
function league_splash ()
{
	global $lr_session;
	if( ! $lr_session->user->is_a_coordinator ) {
		return;
	}

	$header = array(
			array( 'data' => "Leagues Coordinated", 'colspan' => 4)
	);
	$rows = array();
			
	// TODO: For each league, need to display # of missing scores,
	// pending scores, etc.
	while(list(,$league) = each($lr_session->user->leagues)) {
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
	reset($lr_session->user->leagues);
			
	return table( $header, $rows );
}

/**
 * Periodic tasks to perform.  This should handle any internal checkpointing
 * necessary, as the cron task may be called more or less frequently than we
 * expect.
 */
function league_cron()
{
	$season = variable_get('current_season', 'fall');
	$result = db_query("SELECT distinct league_id from league where season = '%s'", $season);
	while( $foo = db_fetch_array($result)) {
		$id = $foo['league_id'];
		$league = league_load( array('league_id' => $id) );
	
		// Task #1: 
		// find all games older than our expiry time, and
		// finalize them
		$league->finalize_old_games();

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
		global $lr_session;
		return $lr_session->has_permission('league','create');
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
		global $lr_session;
		
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$this->league->set('name',$lr_session->attr_get('user_id'));
		$this->league->add_coordinator($lr_session->user);
		
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
		global $lr_session;
		return $lr_session->has_permission('league','edit',$this->league->league_id);
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
		
		$rows[] = array("Year:", form_textfield('', 'edit[year]', $formData['year'], 4,4, "Year of play."));
		
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

		$rows[] = array(" Pyramid - Games Before Repeat:",
			form_select("", "edit[games_before_repeat]", $formData['games_before_repeat'], getOptionsFromRange(0,9), "The number of games before two teams can be scheduled to play each other again (FOR PYRAMID LADDER SCHEDULING ONLY)."));

         /*
		$rows[] = array(" Pyramid - Scheduling Attempts:",
			form_select("", "edit[schedule_attempts]", $formData['schedule_attempts'], getOptionsFromRange(100,100), "The number of attempts to make at scheduling a set of pyramid games while enforcing the Games Before Repeat restriction. (FOR PYRAMID LADDER SCHEDULING ONLY)."));
         */

         /*
		$rows[] = array(" Pyramid - Relax Repeat Restriction:",
			form_select("", "edit[relax_repeat]", $formData['relax_repeat'], getOptionsFromEnum('league','relax_repeat'), "If true, the Games Before Repeat restriction will be relaxed when the number of scheduling attempts is reached. (FOR PYRAMID LADDER SCHEDULING ONLY)."));
         */

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
			
		$rows[] = array("Year:", 
			form_hidden('edit[year]', $edit['year']) . $edit['year']);
		
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

      if ($edit['schedule_type'] == 'pyramid') {
		   $rows[] = array("Pyramid - Games Before Repeat:",
			   form_hidden('edit[games_before_repeat]', $edit['games_before_repeat']) . $edit['games_before_repeat']);

            /*
		   $rows[] = array("Pyramid - Scheduling Attempts:",
			   form_hidden('edit[schedule_attempts]', $edit['schedule_attempts']) . $edit['schedule_attempts']);
            */

            /*
		   $rows[] = array("Pyramid - Relax Repeat Restriction:",
			   form_hidden('edit[relax_repeat]', $edit['relax_repeat']) . $edit['relax_repeat']);
            */
      }

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
		$this->league->set('year', $edit['year']);
		$this->league->set('season', $edit['season']);
		$this->league->set('tier', $edit['tier']);
		$this->league->set('ratio', $edit['ratio']);
		$this->league->set('current_round', $edit['current_round']);
		$this->league->set('schedule_type', $edit['schedule_type']);

      if ($edit['schedule_type'] == 'pyramid') {
		   $this->league->set('games_before_repeat', $edit['games_before_repeat']);
		   //$this->league->set('schedule_attempts', $edit['schedule_attempts']);
		   //$this->league->set('relax_repeat', $edit['relax_repeat']);
      }

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
			case 'pyramid':
            if ($edit['games_before_repeat'] == null || $edit['games_before_repeat'] == 0) {
               $errors .= "<li>Invalid 'Games Before Repeat' specified!";
            }
            /*
            if ($edit['schedule_attempts'] == null || $edit['schedule_attempts'] == 0) {
               $errors .= "<li>Invalid 'Schedule Attempts' specified!";
            }
            */
            break;
			default:
				$errors .= "<li>Values for allow schedule are none, roundrobin, ladder, and pyramid";
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
		global $lr_session;
		return $lr_session->has_permission('league','list');
	}
	
	function process ()
	{
		global $lr_session;

		$season = arg(2);
		if( ! $season ) {
			$season = strtolower(variable_get('current_season', "Summer"));
		}
		
		/* Fetch league names */
		$seasons = getOptionsFromEnum('league', 'season');
		
		$seasonLinks = array();
		while(list(,$curSeason) = each($seasons)) {
			$curSeason = strtolower($curSeason);
			if($curSeason == '---') {
				continue;
			}
			if($curSeason == $season) {
				$seasonLinks[] = $curSeason;
			} else {
				$seasonLinks[] = l($curSeason, "league/list/$curSeason");
			}
		}
		
		$this->setLocation(array(
			$season => "league/list/$season"
		));

		$output = para(theme_links($seasonLinks));

		$header = array( "Name", "&nbsp;") ;
		$rows = array();

		$leagues = league_load_many( array( 'season' => $season, '_order' => "FIELD(MAKE_SET((day & 62), 'BUG','Monday','Tuesday','Wednesday','Thursday','Friday'),'Monday','Tuesday','Wednesday','Thursday','Friday'), tier") );

		if ( $leagues ) {
		foreach ( $leagues as $league ) {
			$links = array();
			if($league->schedule_type != 'none') {
				$links[] = l('schedule',"schedule/view/$league->league_id");
				$links[] = l('standings',"league/standings/$league->league_id");
			}
			if( $lr_session->has_permission('league','delete', $league->league_id) ) {
				$links[] = l('delete',"league/delete/$league->league_id");
			}
			$rows[] = array(
				l($league->fullname,"league/view/$league->league_id"),
				theme_links($links));
		}

		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		}
		
		return $output;
	}
}

class LeagueStandings extends Handler
{
	var $league;

	function has_permission ()
	{	
		global $lr_session;
		return $lr_session->has_permission('league','view', $this->league->league_id);
	}

	function process ()
	{
		global $lr_session;
		
		$id = arg(2);
      $teamid = arg(3);
      $showall = arg(4);

		$this->title = "Standings";

		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}

		$round = $_GET['round'];
		if(! isset($round) ) {
			$round = $this->league->current_round;
		}
      // check to see if this league is on round 2 or higher...
      // if so, set the $current_round so that the standings table is split up
      if ($round > 1) {
         $current_round = $round;
      }
		
		$this->setLocation(array(
			$this->league->fullname => "league/view/$id",
			$this->title => 0,
		));
		
		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $current_round ));
		
      
      // if it's a pyramid, let's add "seed" into the mix:
      if ( $this->league->schedule_type == "pyramid" ) {
         $seeded_order = array();
         for ($i = 0; $i < count($order); $i++) {
            $seeded_order[$i+1] = $order[$i];
         }
         //reset($order);
         $order = $seeded_order;
      }
      
      // if this is a pyramid league and  we're asking for "team" standings, only show
      // the 5 teams above and 5 teams below this team ... don't bother if there are
      // 24 teams or less (24 is probably the largest fall league size)... and, if $showall
      // is set, don't remove items from $order.
      $more_before = 0;
      $more_after = 0;
      if ( ($showall == null || $showall == 0 || $showall == "") && $teamid != null && $teamid != "" && $this->league->schedule_type == "pyramid" && count($order) > 24) {
         $index_of_this_team = 0;
         foreach ($order as $i => $value) {
            if ($value == $teamid) {
               $index_of_this_team = $i;
               break;
            }
         }
         reset($order);
         $count = count($order);
         // use "unset($array[$index])" to remove unwanted elements of the order array
         for ($i = 1; $i < $count+1; $i++) {
            if ($i < $index_of_this_team - 5 || $i > $index_of_this_team + 5) {
               unset($order[$i]);
               if ($i < $index_of_this_team - 5) {
                  $more_before = 1;
               }
               if ($i > $index_of_this_team + 5) {
                  $more_after = 1;
               }
            }
         }
         reset($order);
      }

		/* Build up header */
		$header = array( array('data' => 'Seed', 'rowspan' => 2) );
		$header[] = array( 'data' => 'Team', 'rowspan' => 2 );
		$header[] = array('data' => "Rating", 'rowspan' => 2);

		$subheader = array();

		// Ladder leagues display standings differently.
		// Eventually this should just be a brand new object.
		if($this->league->schedule_type == "ladder" || $this->league->schedule_type == "pyramid") {
			$header[] = array('data' => 'Season To Date', 'colspan' => 7); 
			foreach(array("Win", "Loss", "Tie", "Dfl", "PF", "PA", "+/-") as $text) {
				$subheader[] = array('data' => $text, 'class'=>'subtitle', 'valign'=>'bottom');
			}
		} else {
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
		
		$header[] = array('data' => "Streak", 'rowspan' => 2);
		$header[] = array('data' => "Avg.<br>SOTG", 'rowspan' => 2);
		
		$rows[] = $subheader;

      if ($more_before) {
         $rows[] = array(array( 'data' => l("... ... ...", "league/standings/$id/$teamid/1"), 'colspan' => 13, 'align' => 'center'));
      }

		while(list($seed, $tid) = each($order)) {

         $rowstyle = "none";
         if ($teamid == $tid) {
            $rowstyle = "teamhighlight";
         }

         $row = array( array('data'=>"$seed", 'class'=>"$rowstyle"));
         $row[] = array( 'data'=>l($season[$tid]->name, "team/view/$tid"), 'class'=>"$rowstyle");

			// Don't need the current round for a ladder schedule.
			if ($this->league->schedule_type == "roundrobin") {
				if($current_round) {
               $old_rowstyle = $rowstyle;
               $rowstyle = "standings";
               if ($tid == $teamid) {
                  $rowstyle = "teamhighlight";
               }
               $row[] = array( 'data' => $round[$tid]->win, 'class'=>"$rowstyle");
               $row[] = array( 'data' => $round[$tid]->loss, 'class'=>"$rowstyle");
               $row[] = array( 'data' => $round[$tid]->tie, 'class'=>"$rowstyle");
               $row[] = array( 'data' => $round[$tid]->defaults_against, 'class'=>"$rowstyle");
               $row[] = array( 'data' => $round[$tid]->points_for, 'class'=>"$rowstyle");
               $row[] = array( 'data' => $round[$tid]->points_against, 'class'=>"$rowstyle");
               $row[] = array( 'data' => $round[$tid]->points_for - $round[$tid]->points_against, 'class'=>"$rowstyle");
               $rowstyle = $old_rowstyle;
				}
			}

         $row[] = array( 'data' => $season[$tid]->rating, 'class'=>"$rowstyle");
         $row[] = array( 'data' => $season[$tid]->win, 'class'=>"$rowstyle");
         $row[] = array( 'data' => $season[$tid]->loss, 'class'=>"$rowstyle");
         $row[] = array( 'data' => $season[$tid]->tie, 'class'=>"$rowstyle");
         $row[] = array( 'data' => $season[$tid]->defaults_against, 'class'=>"$rowstyle");
         $row[] = array( 'data' => $season[$tid]->points_for, 'class'=>"$rowstyle");
         $row[] = array( 'data' => $season[$tid]->points_against, 'class'=>"$rowstyle");
         $row[] = array( 'data' => $season[$tid]->points_for - $season[$tid]->points_against, 'class'=>"$rowstyle");
			
			if( count($season[$tid]->streak) > 1 ) {
            $row[] = array( 'data' => count($season[$tid]->streak) . $season[$tid]->streak[0], 'class'=>"$rowstyle");
			} else {
            $row[] = array( 'data' => '-', 'class'=>"$rowstyle");
			}
	
			// initialize the sotg to dashes!
			$sotg = "---";
			//if($season[$tid]->games < 3 && !($lr_session->has_permission('league','view',$this->league->league_id, 'spirit'))) {
				 //$sotg = "---";
			//} else if ($season[$tid]->games_with_sotg > 0) {
				$sotg = sprintf("%.2f", ($season[$tid]->spirit / $season[$tid]->games_with_sotg));
			//}
         $row[] = array( 'data' => $sotg, 'class'=>"$rowstyle");
			$rows[] = $row;
		}
		
      if ($more_after) {
         $rows[] = array(array( 'data' => l("... ... ...", "league/standings/$id/$teamid/1"), 'colspan' => 13, 'align' => 'center'));
      }

		return "<div class='listtable'>" . table($header, $rows) . "</div>";
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
		global $lr_session;
		return $lr_session->has_permission('league','view',$this->league->league_id);
	}
	
	function process ()
	{
		global $lr_session;
		
		$this->title = "View League";

		foreach( $this->league->coordinators as $c ) {
			$coordinator = l($c->fullname, "person/view/$c->user_id");
			if($lr_session->has_permission('league','edit',$this->league->league_id)) {
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

		if($this->league->year) {
			$rows[] = array("Year:", $this->league->year);
		}
		$rows[] = array("Season:", $this->league->season);
		if($this->league->day) {
			$rows[] = array("Day(s):", $this->league->day);
		}
		if($this->league->tier) {
			$rows[] = array("Tier:", $this->league->tier);
		}
		$rows[] = array("Type:", $this->league->schedule_type);

		// Certain things should only be visible for certain types of league.
		if($this->league->schedule_type != 'none') {
			$rows[] = array("League SBF:", $this->league->calculate_sbf());
		}

		if($this->league->schedule_type == 'roundrobin') {
			$rows[] = array("Current Round:", $this->league->current_round);
		}
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";

		$header = array( "Team Name", "Players", "Rating", "Avg. Skill", "&nbsp;",);
		if( $this->league->schedule_type == 'ladder' || $this->league->schedule_type == 'pyramid') {
			array_unshift($header, 'Rank');
			array_unshift($header, 'Seed');
		}

		$this->league->load_teams();

		if( $this->league->teams > 0 ) {
			$rows = array();
			list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));
			$counter = 0;
			foreach($season as $team) {
				$counter++;
				$team_links = array();
				if($team->status == 'open') {
					$team_links[] = l('join', "team/roster/$team->team_id/" . $lr_session->attr_get('user_id'));
				}
				if($lr_session->has_permission('league','edit',$this->league->league_id)) {
					$team_links[] = l('move', "team/move/$team->team_id");
				}
				if($this->league->league_id == 1 && $lr_session->has_permission('team','delete',$team->team_id)) {
					$team_links[] = l('delete', "team/delete/$team->team_id");
				}

				$row = array();
				if( $this->league->schedule_type == 'ladder' || $this->league->schedule_type == 'pyramid') {
					$row[] = $counter;
					$row[] = $team->rank;
				}
				
				$row[] = l($team->name, "team/view/$team->team_id");
				$row[] = $team->count_players();
				$row[] = $team->rating;
				$row[] = $team->avg_skill();
				$row[] = theme_links($team_links);
				
				$rows[] = $row;
			}
			
			$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		}
		
		$this->setLocation(array(
			$this->league->fullname => 'league/view/' . $this->league->league_id,
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
		global $lr_session;
		return $lr_session->has_permission('league','view',$this->league->league_id, 'captain emails');
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
				AND (r.status = 'coach' OR r.status = 'captain' OR r.status = 'assistant')",$this->league->league_id);
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

class LeagueApproveScores extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','approve scores',$this->league->league_id);
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
		global $lr_session;
		return $lr_session->has_permission('league','edit', $this->league->league_id);
	}

	function process ()
	{
		global $lr_session;
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

		if( !$lr_session->is_admin() && $player_id == $lr_session->attr_get('user_id') ) {
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

class LeagueRank extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit', $this->league->league_id);
	}

   function generateForm ( $data = '' ) 
   {
      $output = para("Use the links below to adjust a team's rank for 'better' or for 'worse'.  Alternatively, you can enter a new rank into the box beside each team then click 'Rank' below.  Multiple teams can (and likely should) have the same ranks");
      $output .= para("For the ranking values, a <b/>LOWER</b/> numbered ranking is <b/>BETTER</b/>, and a <b/>HIGHER</b/> numbered ranking is <b/>WORSE</b/>. (ie: team ranked '1' is better than team ranked '2')");
      $output .= para("<b/>WARNING: </b/> Adjusting rankings while the league is already under way is possible, but you'd better know what you are doing!!!");

      $header = array( "Rank", "Team Name", "Players", "Rating", "Avg.<br/>Skill", "New Rank",);
		$rows = array();

		$this->league->load_teams();
      list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));
		foreach($season as $team) {

			$row = array();
			$row[] = $team->rank;
			$row[] = check_form($team->name);
			$row[] = $team->count_players();
			$row[] = $team->rating;
			$row[] = $team->avg_skill();
         $row[] = "<font size='-4'><a href='#' onClick='document.forms[0].elements[\"edit[$team->team_id]\"].value--; return false'> better </a> " . 
            "<input type='text' size='3' name='edit[$team->team_id]' value='$team->rank' />" .
            "<a href='#' onClick='document.forms[0].elements[\"edit[$team->team_id]\"].value++; return false'> worse</a></font>";

			$rows[] = $row;
      }
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		$output .= form_hidden("edit[step]", 'perform');
      $output .= "<input type='reset' />&nbsp;<input type='submit' value='Adjust Ranks' /></div>";

      return form($output);
   }
   
	function process ()
	{
		$this->title = "League Rank Adjustment";

		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				$this->perform($edit);
				local_redirect(url("league/view/" . $this->league->league_id));
				break;
			default:
				$rc = $this->generateForm();
		}
      $this->setLocation(array( $this->league->name => "league/view/" . $this->league->league_id, $this->title => 0));

		return $rc;

   }

   function perform ( $edit )
   {
      // make sure the teams are loaded
      $this->league->load_teams();

      // go through what was submitted
      foreach ($edit as $key => $value) {
         if (is_numeric($key) && is_numeric($value)) {
            $team = $this->league->teams[$key];

            // TODO:  Move this logic to a function inside the league.inc file
            // update the database
            db_query( "UPDATE leagueteams SET rank = %d WHERE league_id = %d AND team_id = %d",
               $value + 1000, $this->league->league_id, $key);
         }
      }
      
      return true;
	}
}

class LeagueSpirit extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view', $this->league->league_id, 'spirit');
	}

	function process ()
	{
		global $lr_session;
		$this->title = "League Spirit";
		
		$this->setLocation(array(
			$this->league->fullname => "league/spirit/". $this->league->league_id,
			$this->title => 0));

		/*
		 * Grab schedule info 
		 */
		$games = game_load_many( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date,g.game_id') );

		if( !is_array($games) ) {
			error_exit("There are no games scheduled for this league");
		}

		$header = array( "Game", "Entry By", "Given To");
		$rows = array();

		$answer_values = array();
		$result = db_query("SELECT akey, value FROM multiplechoice_answers");
		while( $ary = db_fetch_array($result) ) {
			$answer_values[ $ary['akey'] ] = $ary['value'];
		}

		$question_sums = array();
		$num_games = 0;

		while(list(,$game) = each($games)) {
		
			$teams = array( $game->home_team => $game->home_name, $game->away_team => $game->away_name);
			while( list($giver,$giver_name) = each ($teams)) {

				$recipient = $game->get_opponent_id ($giver);
				$recipient_name = $teams[$recipient];
			
				# Fetch spirit answers for games
				$entry = $game->get_spirit_entry( $recipient );
				if( !$entry ) {
					continue;
				}
				$thisrow = array(
					
					l($game->game_id, "game/view/$game->game_id")
					   . " " .  strftime('%a %b %d %Y', $game->timestamp),
					l($giver_name, "team/view/$giver"),
					l($recipient_name, "team/view/$recipient")
				);
			
				if( !$num_games ) {
					$header[] = "Score";
				}
				$numeric = $game->get_spirit_numeric( $recipient );
				$thisrow[] = sprintf("%.2f",$numeric);
				$score_total += $numeric;

				while( list($qkey,$answer) = each($entry) ) {

					if( !$num_games ) {
						$header[] = $qkey;
					}
					if( $qkey == 'CommentsToCoordinator' ) {
						$thisrow[] = $answer;
						continue;
					}
					switch( $answer_values[$answer] ) {
						case -2:
							$thisrow[] = "<img src='/leaguerunner/misc/x.png' />";
							break;
						case -1:
							$thisrow[] = "-";
							break;
						case 0:
							$thisrow[] = "<img src='/leaguerunner/misc/check.png' />";
							break;
						default:
							$thisrow[] = "?";
					}
					$question_sums[ $qkey ] += $answer_values[ $answer ];
				}

				$num_games++;

				$rows[] = $thisrow;
			}
		}

		if( !$num_games ) {
			error_exit("No games played, cannot display spirit");
		}
	
		$thisrow = array(
			"Tier Avg","-","-"
		);

		$thisrow[] = sprintf("%.2f",$score_total / $num_games );

		reset($question_sums);
		foreach( $question_sums as $qkey => $answer) {
			$avg = ($answer / $num_games);
			if( $avg < -1.5 ) {
				$thisrow[] = "<img src='/leaguerunner/misc/x.png' />";
			} else if ( $avg < -0.5 ) {
				$thisrow[] = "-";
			} else {
				$thisrow[] = "<img src='/leaguerunner/misc/check.png' />";
			}
		}
		$rows[] = $thisrow;

		return "<style>#main table td { font-size: 80% } </style>" . table($header,$rows, array('alternate-colours' => true) );
	}
}

class LeagueStatusReport extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit', $this->league->league_id);
	}

   function generateForm ( $data = '' ) 
   {
      $output = para("Use the links below to adjust a team's rank for 'better' or for 'worse'.  Alternatively, you can enter a new rank into the box beside each team then click 'Rank' below.  Multiple teams can (and likely should) have the same ranks");
      $output .= para("For the ranking values, a <b/>LOWER</b/> numbered ranking is <b/>BETTER</b/>, and a <b/>HIGHER</b/> numbered ranking is <b/>WORSE</b/>. (ie: team ranked '1' is better than team ranked '2')");
      $output .= para("<b/>WARNING: </b/> Adjusting rankings while the league is already under way is possible, but you'd better know what you are doing!!!");

      $header = array( "Rank", "Team Name", "Players", "Rating", "Avg.<br/>Skill", "New Rank",);
		$rows = array();

		$this->league->load_teams();
      list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));
		foreach($season as $team) {

			$row = array();
			$row[] = $team->rank;
			$row[] = check_form($team->name);
			$row[] = $team->count_players();
			$row[] = $team->rating;
			$row[] = $team->avg_skill();
         $row[] = "<font size='-4'><a href='#' onClick='document.forms[0].elements[\"edit[$team->team_id]\"].value--; return false'> better </a> " . 
            "<input type='text' size='3' name='edit[$team->team_id]' value='$team->rank' />" .
            "<a href='#' onClick='document.forms[0].elements[\"edit[$team->team_id]\"].value++; return false'> worse</a></font>";

			$rows[] = $row;
      }
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		$output .= form_hidden("edit[step]", 'perform');
      $output .= "<input type='reset' />&nbsp;<input type='submit' value='Adjust Ranks' /></div>";

      return form($output);
   }
   
	function process ()
	{
		$this->title = "League Status Report";

		$rc = $this->generateStatusPage();

      $this->setLocation(array( $this->league->name => "league/status/" . $this->league->league_id, $this->title => 0));

		return $rc;
   }

   function generateStatusPage ( )
   {
      // make sure the teams are loaded
      $this->league->load_teams();

      list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));

      $fields = array();
      $field_results = field_query( array( '_order' => 'f.code') );
      while( $field = db_fetch_object( $field_results ) ) {
         $fields[$field->code] = $field->region;
      }

      //print_r($fields);
      //print "<br><br>";

         //print_r($season);
      $output = para("This is a general scheduling status report for pyramid ladder leagues.");

      $header[] = array('data' => "Rank", 'rowspan' => 2);
      $header[] = array('data' => "Team", 'rowspan' => 2);
      $header[] = array('data' => "Games", 'rowspan' => 2);
      $header[] = array('data' => "Home/Away", 'rowspan' => 2);
      $header[] = array('data' => "Region", 'colspan' => 4);
      $header[] = array('data' => "Opponents", 'rowspan' => 2);
      $header[] = array('data' => "Repeat Opponents", 'rowspan' => 2);

      $subheader[] = array('data' => "C", 'class' => "subtitle");
      $subheader[] = array('data' => "E", 'class' => "subtitle");
      $subheader[] = array('data' => "S", 'class' => "subtitle");
      $subheader[] = array('data' => "W", 'class' => "subtitle");

      $rows = array();
      $rows[] = $subheader;

      $rowstyle = "standings_light";

      // get the schedule
      $schedule = array();
      $games = game_query ( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date, g.game_start, field_code') );
      while($g = db_fetch_array($games)) {
         $g = game_load( array('game_id' => $g['game_id']) );
         $schedule[] = $g;
      }

		while(list(, $tid) = each($order)) {
         if ($rowstyle == "standings_light") {
            $rowstyle = "standings_dark";
         } else {
            $rowstyle = "standings_light";
         }
         $row = array( array('data'=>$season[$tid]->rank, 'class'=>"$rowstyle") );
         $row[] = array('data'=>l($season[$tid]->name, "team/view/$tid"), 'class'=>"$rowstyle");

         // count number of games for this team:
         //$games = game_load_many( array( 'either_team' => $this->team->team_id, '_order' => 'g.game_date,g.game_id') );
         $numgames = 0;
         $homegames = 0;
         $awaygames = 0;
         $regionCentral = 0;
         $regionEast = 0;
         $regionSouth = 0;
         $regionWest = 0;
         $opponents = array();

         // parse the schedule
         reset($schedule);
         while(list(,$game) = each($schedule)) {
            if ($game->home_team == $tid) {
               $numgames++;
               $homegames++;
               $opponents[$game->away_team]++;
            }
            if ($game->away_team == $tid) {
               $numgames++;
               $awaygames++;
               $opponents[$game->home_team]++;
            }
            if ($game->home_team == $tid || $game->away_team == $tid) {
               list($code, $num) = split(" ", $game->field_code);
               $region = $fields[$code];
               if ($region == "Central") {
                  $regionCentral++;
               } else if ($region == "East") {
                  $regionEast++;
               } else if ($region == "South") {
                  $regionSouth++;
               } else if ($region == "West") {
                  $regionWest++;
               }
            }
         }
         //reset($games);

         $row[] = array('data'=>$numgames, 'class'=>"$rowstyle", 'align'=>"center");
         $row[] = array('data'=>"$homegames / $awaygames", 'class'=>"$rowstyle", 'align'=>"center");

         // regions:
         if ($season[$tid]->region_preference != "---" && $season[$tid]->region_preference != "") {
            $pref = $season[$tid]->region_preference;
            if ($pref == "Central") {
               $regionCentral = "<b><font color='red'>$regionCentral</font></b>";
            } else if ($pref == "East") {
               $regionEast = "<b><font color='red'>$regionEast</font></b>";
            } else if ($pref == "South") {
               $regionSouth = "<b><font color='red'>$regionSouth</font></b>";
            } else if ($pref == "West") {
               $regionWest = "<b><font color='red'>$regionWest</font></b>";
            }
         }
         $row[] = array('data'=>"$regionCentral", 'class'=>"$rowstyle");
         $row[] = array('data'=>"$regionEast", 'class'=>"$rowstyle");
         $row[] = array('data'=>"$regionSouth", 'class'=>"$rowstyle");
         $row[] = array('data'=>"$regionWest", 'class'=>"$rowstyle");

         $row[] = array('data'=>count($opponents), 'class'=>"$rowstyle", 'align'=>"center");

         // figure out the opponent repeats
         $opponent_repeats="";
		   while(list($oid, $repeats) = each($opponents)) {
            if ($repeats > 2) {
               $opponent_repeats .= $season[$oid]->name . " (<font color='red'><b>$repeats</b></font>) <br>";
            } else if ($repeats > 1) {
               $opponent_repeats .= $season[$oid]->name . " (<b>$repeats</b>) <br>";
            }
         }
         $row[] = array('data'=>$opponent_repeats, 'class'=>"$rowstyle");

         $rows[] = $row;
      }

      //$output .= table($header, $rows);
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";

      return form($output);
	}
}

?>
