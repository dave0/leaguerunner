<?php

function schedule_dispatch() 
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'day':
			$obj = new ScheduleViewDay;
			break;
		case 'edit':
			$obj = new ScheduleEdit;
			$obj->league = league_load( array( 'league_id' => $id ) );
			break;
		case 'view':
			$obj = new ScheduleView;
			$obj->league = league_load( array( 'league_id' => $id ) );
			break;
		default:
			$obj = null;
	}
	if($obj->league) {
		league_add_to_menu($obj->league);
	}
	return $obj;
}

/**
 * View all games on a single day
 */
class ScheduleViewDay extends Handler
{
	function has_permission ()
	{
		// Everyone gets to see the schedule
		return true;
	}

	function process ()
	{
		$this->title = "View Day";

		$today = getdate();

		$year  = arg(2);
		$month = arg(3);
		$day   = arg(4);

		if(! validate_number($month)) {
			$month = $today['mon'];
		}

		if(! validate_number($year)) {
			$year = $today['year'];
		}

		if( $day ) {
			if( !validate_date_input($year, $month, $day) ) {
				return "That date is not valid";
			}
			$formattedDay = strftime("%A %B %d %Y", mktime (6,0,0,$month,$day,$year));
			$this->setLocation(array(
				"$this->title &raquo; $formattedDay" => 0));
			return $this->displayGamesForDay( $year, $month, $day );
		} else {
			$this->setLocation(array( "$this->title" => 0));
			$output = para("Select a date below on which to view all scheduled games");
			$output .= generateCalendar( $year, $month, $day, 'schedule/day', 'schedule/day');
			return $output;
		}
	}

	/**
	 * List all games on a given day.
	 */
	function displayGamesForDay ( $year, $month, $day )
	{
		$sth = game_query ( array(
			'game_date' => sprintf('%d-%d-%d', $year, $month, $day),
			'published' => true,
			'_order' => 'g.game_start, field_code') );

		$rows = array( 
			schedule_heading(strftime('%a %b %d %Y',mktime(6,0,0,$month,$day,$year))),
			schedule_subheading( ),
		);
		while($g = $sth->fetchObject('Game') ) {
			$rows[] = schedule_render_viewable($g);
		}
		$output .= "<div class='schedule'>" . table($header, $rows) . "</div>";
		return $output;
	}
}

/**
 * Edit league schedule
 */
class ScheduleEdit extends Handler
{
	var $league;

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit schedule',$this->league->league_id);
	}

	function process ()
	{
		$dayId = arg(3);
		$edit = $_POST['edit'];
		$this->title = "Edit Schedule";

		if( ! $this->league ) {
			error_exit("That league does not exist");
		}

		switch($edit['step']) {
			case 'perform':
				$this->perform($dayId, $edit);
				local_redirect(url("schedule/view/" . $this->league->league_id));
				break;
			case 'confirm':
				$rc = $this->generateConfirm($dayId, $edit );
				break;
			default:
				$rc = $this->generateForm($dayId);
				break;
		}
		$this->setLocation(array(
			$this->league->fullname => "league/view/" .$this->league->league_id,
			$this->title => 0));
		return $rc;
	}

	function generateForm ($timestamp)
	{
		// Grab data for pulldowns if we need an edit form
		$teams = $this->league->teams_as_array();
		if( ! count($teams) ) {
			error_exit("There may be no teams in this league");
		}
		$teams[0] = "---";

		$this->league->teams = $teams;

		// get the game slots for this league and this day
		$gameslots = $this->league->get_gameslots($timestamp);
		if( count($gameslots) <= 1 ) {
			error_exit("There are no fields assigned to this league");
		}
		$this->league->gameslots = $gameslots;

		$this->league->rounds = $this->league->rounds_as_array();

		$sth = game_query ( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date, g.game_start, field_code') );

		$prevDayId = -1;
		$rows = array();
		$should_publish = 1;
		/* For each game in the schedule for this league */
		while($game = $sth->fetchObject('Game') ) {
			if( $game->day_id != $prevDayId ) {
				if( $timestamp == $prevDayId) {
					/* ensure we add the submit buttons for schedule editing */
					$rows[] = array(
						array('data' => para( form_checkbox("Set as published for player viewing?", 'edit[published]', 'yes', $should_publish, '') ), 'colspan' => 9)
					);
					$rows[] = array(
						array('data' => para( form_hidden('edit[step]', 'confirm') . form_submit('submit') . form_reset('reset')), 'colspan' => 9)
					);
				}

				$rows[] = schedule_heading( 
					strftime('%a %b %d %Y', $game->timestamp),
					false,
					$game->day_id, $this->league->league_id );

				if($timestamp == $game->day_id) {
					$rows[] = schedule_edit_subheading();
				} else {
					$rows[] = schedule_subheading( );
				}
			}

			if($timestamp == $game->day_id) {
				$rows[] = schedule_render_editable($game, $this->league);
			} else {
				$rows[] = schedule_render_viewable($game);
			}
			$prevDayId = $game->day_id;

			$should_publish = $game->published;
		}
		if( $timestamp == $prevDayId ) {
			/* ensure we add the submit buttons for schedule editing */
			$rows[] = array(
				array('data' => para( form_checkbox("Set as published for player viewing?", 'edit[published]', 'yes', $should_publish, '') ), 'colspan' => 9)
			);
			$rows[] = array(
				array('data' => para( form_hidden('edit[step]', 'confirm') . form_submit('submit') . form_reset('reset')), 'colspan' => 9)
			);
		}

		$output .= "<div class='schedule'>" . table(null, $rows) . "</div>";

		return form($output);
	}

	function isDataInvalid ($games) 
	{
		if(!is_array($games) ) {
			return "Invalid data supplied for games";
		}

		$rc = true;
		$seen_slot = array();
		$seen_team = array();
		foreach($games as $game) {

			if( !validate_number($game['game_id']) ) {
				return "Game entry missing a game ID";
			}
			if( !validate_number($game['slot_id']) ) {
				return "Game entry missing field ID";
			}
			if ($game['slot_id'] == 0) {
				return "You cannot choose the '---' as the game time/place!";
			}

			if(in_array($game['slot_id'], $seen_slot) ) {
				return "Cannot schedule the same gameslot twice";
			} else {
				$seen_slot[] = $game['slot_id'];
			}

			$seen_team[$game['home_id']]++;
			$seen_team[$game['away_id']]++;

			if( !validate_number($game['home_id'])) {
				return "Game entry missing home team ID";
			}
			if( !validate_number($game['away_id'])) {
				return "Game entry missing away team ID";
			}
			if ( ($seen_team[$game['home_id']] > 1) || ($seen_team[$game['away_id']] > 1) ) {
				// TODO: Needs to be fixed to deal with doubleheader games.
				return "Cannot schedule a team to play two games at the same time";
			}
			if( $game['home_id'] != 0 && ($game['home_id'] == $game['away_id']) ) {
				return "Cannot schedule a team to play themselves.";
			}

			// TODO Check the database to ensure that no other game is
			// scheduled on this field for this timeslot
		}

		return false;
	}

	function generateConfirm ($dayId, $edit )
	{
		global $dbh;
		$dataInvalid = $this->isDataInvalid( $edit['games'] );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$gameslots = $this->league->get_gameslots($dayId);
		if( count($gameslots) <= 1 ) {
			error_exit("There are no fields assigned to this league!");
		}

		$output = para("Confirm that the changes below are correct, and click 'Submit' to proceed.");

		if( $edit['published'] == 'yes' ) {
			$output .= para("Games will be made available for player viewing.")
				. form_hidden('edit[published]', 'yes');
		} else {
			$output .= para("Games will be hidden from player view until you choose to publish them.")
				. form_hidden('edit[published]', 'no');
		}

		$output .= form_hidden('edit[step]', 'perform');

		$header = array(
			"Game ID", "Round", "Game Slot", "Home", "Away",
		);
		$rows = array();

		while (list ($game_id, $game_info) = each ($edit['games']) ) {
			reset($game_info);

			$slot = slot_load( array('slot_id' => $game_info['slot_id']) );

			$team_sth = $dbh->prepare('SELECT name FROM team WHERE team_id = ?');

			$team_sth->execute( array($game_info['home_id']) );
			$home_name = $team_sth->fetchColumn();

			$team_sth->execute( array($game_info['away_id']) );
			$away_name = $team_sth->fetchColumn();

			$rows[] = array(
				form_hidden("edit[games][$game_id][game_id]", $game_id) . $game_id,
				form_hidden("edit[games][$game_id][round]", $game_info['round']) . $game_info['round'],
				form_hidden("edit[games][$game_id][slot_id]", $game_info['slot_id']) . $gameslots[$game_info['slot_id']],
				form_hidden("edit[games][$game_id][home_id]", $game_info['home_id']) . $home_name,
				form_hidden("edit[games][$game_id][away_id]", $game_info['away_id']) . $away_name,
			);
		}

		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function perform ($dayId, $edit)
	{
		global $dbh;

		$dataInvalid = $this->isDataInvalid( $edit['games'] );
		if($dataInvalid) {
			error_exit($dataInvalid);
		}
		$should_publish = ($edit['published'] == 'yes' ? 1 : 0);

		while (list ($game_id, $game_info) = each ($edit['games']) ) {
			$game = game_load( array('game_id' => $game_id) );
			if( !$game ) {
				error_exit("Attempted to edit game info for a nonexistant game!");
			}

			if ($this->league->schedule_type == "roundrobin") {
				$game->set('round', $game_info['round']);
			}

			$game->set('home_team', $game_info['home_id']);
			$game->set('away_team', $game_info['away_id']);

			// find the old slot id!
			$old_slot_id = $game->slot_id;

			$game->set('slot_id', $game_info['slot_id']);

			$game->set('published', $should_publish);

			if( !$game->save() ) {
				error_exit("Couldn't save game information!");
			}

			// there can be more gameslots than games, so it is important
			// to clear out the old game slot!
			// (if the old slot id is blank, it means that this game's slot has already been overwritten)
			if ($old_slot_id && $old_slot_id != $game->slot_id) {
				$sth = $dbh->prepare('UPDATE gameslot SET game_id = NULL WHERE slot_id = ?');
				$sth->execute(array($old_slot_id));
			}
		}

		return true;
	}
}

/**
 * League schedule viewing handler
 */
class ScheduleView extends Handler
{
	function has_permission ()
	{
		return true;
	}

	function process ()
	{
		global $lr_session;
		$this->title = "View Schedule";

		if( !$this->league ) {
			error_exit("That league does not exist");
		}

		$this->setLocation(array(
			$this->league->fullname => "league/view/".$this->league->league_id,
			$this->title => 0));

		/* 
		 * Now, grab the schedule
		 */
		$sth = game_query ( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date, g.game_start, field_code') );

		$prevDayId = -1;
		$rows = array();
		while( $game = $sth->fetchObject('Game') ) {

			if( ! ($game->published || $lr_session->has_permission('league','edit schedule', $this->league->league_id) ) ) {
				continue;
			}

			if( $game->day_id != $prevDayId ) {
				$rows[] = schedule_heading( 
					strftime('%a %b %d %Y', $game->timestamp),
					$lr_session->has_permission('league','edit schedule', $this->league->league_id),
					$game->day_id, $this->league->league_id );
				$rows[] = schedule_subheading( );
			}

			$rows[] = schedule_render_viewable($game);
			$prevDayId = $game->day_id;
		}
		$output .= "<div class='schedule'>" . table(null, $rows) . "</div>";
		return form($output);
	}
}

function schedule_heading( $date, $canEdit = false, $dayId = 0, $leagueId = 0 )
{
	$header = array(
		array('data' => $date, 'colspan' => ($canEdit ? 5 : 7), 'class' => 'gamedate')
	);

	if( $canEdit && $dayId ) {

		$day_links = array(l("fields", "league/slots/$leagueId/".strftime('%Y/%m/%d', $dayId)),
						   l("edit week", "schedule/edit/$leagueId/$dayId"));

		$header[] = array(
						  'data' => theme_links($day_links) ,
						  'class' => 'gamedate', 'colspan' => 2,
						  'style' => 'text-align:right;'
		);
	}
	return $header;
}

function schedule_subheading( )
{
	return array(
		array('data' => 'Game', 'class' => 'column-heading'),
		array('data' => 'Time/Place', 'colspan' => 2, 'class' => 'column-heading'),
		array('data' => 'Home', 'colspan' => 2, 'class' => 'column-heading'),
		array('data' => 'Away', 'colspan' => 2, 'class' => 'column-heading'),
	);
}

function schedule_edit_subheading( )
{
	return array(
		array( 'data' => 'Round' , 'class' => 'column-heading'),
		array( 'data' => 'Time/Place', 'colspan' => 2, 'class' => 'column-heading'),
		array( 'data' => 'Home', 'colspan' => 2, 'class' => 'column-heading'),
		array( 'data' => 'Away', 'colspan' => 2, 'class' => 'column-heading'),
		''
	);
}

function schedule_render_editable( &$game, &$league )
{

	// Ensure the given teams are listed in pulldown
	$league->teams[$game->home_id] = $game->home_name;
	$league->teams[$game->away_id] = $game->away_name;

	if ($league->schedule_type == "roundrobin") {
		$form_round = form_select('',"edit[games][$game->game_id][round]", $game->round, $league->rounds);
	} else {
		$form_round = '';
	}
	$form_gameslot = 
		array( 
			'data' => form_select('',"edit[games][$game->game_id][slot_id]", $game->slot_id, $league->gameslots), 
			'colspan' => 2
		);
	$form_home = form_select('',"edit[games][$game->game_id][home_id]", $game->home_id, $league->teams);
	$form_away = form_select('',"edit[games][$game->game_id][away_id]", $game->away_id, $league->teams);

	return array(
		form_hidden("edit[games][$game->game_id][game_id]", $game->game_id) . $form_round,
		$form_gameslot,
		array(
			'data' => $form_home . form_hidden("edit[games][$game->game_id][home_text]", $form_home),
			'colspan' => 2
		),
		array(
			'data' => $form_away . form_hidden("edit[games][$game->game_id][away_text]", $form_away),
			'colspan' => 2
		),
	);
}

function schedule_render_viewable( &$game )
{
	global $lr_session;
	if($game->home_name) {
		$short = display_short_name($game->home_name);
		$attr = array();
		if ($short != $game->home_name)
		{
			$attr['title'] = $game->home_name;
		}

		$homeTeam = l($short, "team/view/" . $game->home_id, $attr);
	} else {
		$homeTeam = "Not yet scheduled.";
	}
	if($game->away_name) {
		$short = display_short_name($game->away_name);
		$attr = array();
		if ($short != $game->away_name)
		{
			$attr['title'] = $game->away_name;
		}

		$awayTeam = l($short, "team/view/" . $game->away_id, $attr);
	} else {
		$awayTeam = "Not yet scheduled.";
	}

	$gameRow = array(
		l($game->game_id, 'game/view/' . $game->game_id),
		"$game->game_start - " . $game->display_game_end(),
		l( $game->field_code, "field/view/$game->fid"),
	);

	if($game->status == 'home_default') {
		$gameRow[] = array('data' => $homeTeam, 'style' => 'background-color: red');
		$gameRow[] = array('data' => $game->home_score . '(dfl)', 'style' => 'background-color: red');
	} else { 
		$gameRow[] = $homeTeam;
		$gameRow[] = $game->home_score;
	}

	if ($game->status == 'away_default') {
		$gameRow[] = array('data' => $awayTeam, 'style' => 'background-color: red');
		$gameRow[] = array('data' => $game->away_score . '(dfl)', 'style' => 'background-color: red');
	} else {
		$gameRow[] = $awayTeam;
		$gameRow[] = $game->away_score;
	}

	return $gameRow;
}
?>
