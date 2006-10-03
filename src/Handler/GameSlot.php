<?php

/*
 * Handlers for dealing with game slots
 */

function slot_dispatch() 
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'create':
			$obj = new GameSlotCreate;
			break;
		case 'delete':
			$obj = new GameSlotDelete;
			$obj->slot = slot_load( array('slot_id' => $id) );
			break;
		case 'availability':
			$obj = new GameSlotAvailability;
			$obj->slot = slot_load( array('slot_id' => $id) );
			break;
		case 'day':
			$obj = new GameSlotListDay;
			break;
/*
		case 'edit':
			# TODO: allow admin to manually edit gameslots + times
			#       Necessary for editing end times (?)
			$obj = new GameSlotEdit;
			$obj->slot = slot_load( array('slot_id' => $id) );
 */
		default:
			$obj = null;;
	}
	return $obj;
}

function slot_permissions ( &$user, $action, $sid )
{
	switch($action)
	{
		case 'create':
		case 'edit':
		case 'delete':
		case 'availability':
			// admin-only
			break;
	}
	return false;
}

class GameSlotCreate extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('slot','create');
	}

	function process()
	{
		$this->title = "Create Game Slot";
		$fid = arg(2);
		$year = arg(3);
		$month = arg(4);
		$day = arg(5);

		$field = field_load( array('fid' => $fid) );
		if(!$field) {
			error_exit("That field does not exist");
		}
		
		if ( $day ) {
			if ( ! validate_date_input($year, $month, $day) ) {
				error_exit("That date is not valid");
			}
			$datestamp = mktime(6,0,0,$month,$day,$year);
		} else {
			return $this->datePick($field, $year, $month, $day);
		}

		$edit = &$_POST['edit'];
		switch($edit['step']) {
			case 'perform':
			# Processing should:
			#   - check for overlaps with existing slots
			#   - insert into gameslot table
			#   - insert availability for gameslot into availability table.
			# the overlap-checking probably belongs in slot.inc
				if ( $this->perform( $field, $edit, $datestamp) ) {
					local_redirect(url("field/view/$field->fid"));	
				} else {
					error_exit("Aieee!  Bad things happened in gameslot create");
				}
				break;
			case 'confirm':
				$this->setLocation(array( 
					"$field->fullname $field_num" => "field/view/$field->fid",
					$this->title => 0
				));
				return $this->generateConfirm($field, $edit, $datestamp);
				break;
			default:
				$this->setLocation(array( 
					$field->fullname => "field/view/$field->fid",
					$this->title => 0
				));
				return $this->generateForm($field, $datestamp);
				break;
		}
		error_exit("Error: This code should never be reached.");
	}

	function datePick ( &$field, $year, $month, $day)
	{
		$output = para("Select a date below to start adding gameslots.");

		$today = getdate();
	
		if(! validate_number($month)) {
			$month = $today['mon'];
		}

		if(! validate_number($year)) {
			$year = $today['year'];
		}

		$output .= generateCalendar( $year, $month, $day, "slot/create/$field->fid", "slot/create/$field->fid");

		return $output;
	}

	# TODO: Processing should:
	#   - check for overlaps with existing slots
	#   - insert into gameslot table
	#   - insert availability for gameslot into availability table.
	# the overlap-checking probably belongs in slot.inc
	function perform ( &$field, $edit, $datestamp )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		for( $i = 0; $i < $edit['repeat_for']; $i++) {
			$slot = new GameSlot;
			$slot->set('fid', $field->fid);
			$slot->set('game_date', strftime("%Y-%m-%d",$datestamp));
			$slot->set('game_start', $edit['start_time']);
			if( $edit['end_time'] != '---' ) {
				$slot->set('game_end', $edit['end_time']);
			}

			foreach($edit['availability'] as $league_id) {
				$slot->add_league($league_id);
			}

			if ( ! $slot->save() ) {
				// if we fail, bail
				return false;
			}
			$datestamp += (7 * 24 * 60 * 60);  // advance by a week
		}

		return true;
	}

	function generateForm ( &$field, $datestamp )
	{
	
		$output = form_hidden('edit[step]', 'confirm');
		
		$group = form_item("Date", strftime("%A %B %d %Y", $datestamp));
		$group .= form_select('Game Start Time','edit[start_time]', '18:30', getOptionsFromTimeRange(0000,2400,5), 'Time for games in this timeslot to start');
		$group .= form_select('Game Timecap','edit[end_time]', '---', getOptionsFromTimeRange(0000,2400,5), 'Time for games in this timeslot to end.  Choose "---" to assign the default timecap (dark) for that week.');
		$output .= form_group("Gameslot Information", $group);
		
		$weekday = strftime("%A", $datestamp);
		$leagues = array();
		$result = db_query("SELECT 
			l.league_id,
			l.name,
			l.tier
			FROM league l
			WHERE l.schedule_type != 'none' AND (FIND_IN_SET('%s', l.day) > 0) ORDER BY l.day,l.name,l.tier", $weekday);
			
		while($league = db_fetch_object($result)) {
			if( $league->tier ) {
				$league->fullname = sprintf("$league->name Tier %02d", $league->tier);
			} else {
				$league->fullname = $league->name;
			}
			$chex .= form_checkbox($league->fullname, 'edit[availability][]', $league->league_id, $league->selected);
		}
		$output .= form_group('Make Gameslot Available To:', $chex);
	
		$group = form_select('Weeks to repeat', 'edit[repeat_for]', '1', getOptionsFromRange(1,24),'Number of weeks to repeat this gameslot');
		$output .= form_group("Repetition", $group);

		$output .= form_submit('submit') . form_reset('reset');

		return form($output);
	}
	
	function generateConfirm ( &$field, &$edit, $datestamp )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = form_hidden('edit[step]', 'perform');

		$output .= para("Please confirm that this information is correct");
		
		$group = form_item("Date", strftime("%A %B %d %Y", $datestamp));
		$group .= form_item('Game Start Time',
			form_hidden('edit[start_time]', $edit['start_time']) . $edit['start_time']);
			$group .= form_item('Game Timecap',
			form_hidden('edit[end_time]', $edit['end_time']) . $edit['end_time']);
		$output .= form_group("Gameslot Information", $group);

		$group = '';
		foreach( $edit['availability'] as $league_id ) {
			$league = league_load( array('league_id' => $league_id) );
			$group .= $league->fullname . form_hidden('edit[availability][]', $league_id) . "<br />";
		}
		$output .= form_group('Make Gameslot Available To:', $group);
	
		$group = form_item('Weeks to repeat', form_hidden('edit[repeat_for]', $edit['repeat_for']) . $edit['repeat_for']);
		$output .= form_group("Repetition", $group);

		$output .= form_submit('submit');

		return form($output);
	}

	function isDataInvalid ( $edit = array() )
	{;
		$errors = "";
		if($edit['repeat_for'] > 52) {
			$errors .= "\n<li>You cannot repeat a schedule for more than 52 weeks.";
		}

		if( !is_array($edit['availability']) ) {
			$errors .= "\n<li>You must make this gameslot available to at least one league.";
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

class GameSlotDelete extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('slot','delete', $this->slot->slot_id);
	}

	function process()
	{
		$this->title = "Delete Game Slot";
		
		if(!$this->slot) {
			error_exit("That game slot does not exist");
		}
		
		$this->setLocation(array( 
			$slot->field->fullname => "field/view/" . $slot->field->fid,
			$this->title => 0
		));


		switch($_POST['edit']['step']) {
			case 'perform':
				$fid = $this->slot->fid;
				if ( $this->slot->delete() ) {
					local_redirect(url("field/view/$fid"));
				} else {
					error_exit("Failure deleting gameslot");
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
		// Check that the slot has no games scheduled
		if ($this->slot->game_id) {
			error_exit("Cannot delete a gameslot with a currently-scheduled game");
		}
		
		// Print confirmation info
		$output = form_hidden('edit[step]', 'perform');
		
		$group = form_item("Date", strftime("%A %B %d %Y", $this->slot->date_timestamp));
		$group .= form_item('Game Start Time', $this->slot->game_start);
		$group .= form_item('Game End Time', $this->slot->game_end);
		$output .= form_group("Gameslot Information", $group);

		$group = '';
		foreach( $this->slot->leagues as $l ) {
			$league = league_load( array('league_id' => $l->league_id) );
			$group .= $league->fullname . "<br />";
		}
		$output .= form_group('Available To:', $group);
	
		$output .= form_submit('submit');

		return form($output);
	}
}

/**
 * Set this gameslot as being available to the given leagues
 */
class GameSlotAvailability extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('slot', 'availability', $this->slot->slot_id);
	}

	function process()
	{
		if(!$this->slot) {
			error_exit("That gameslot does not exist");
		}
		
		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				foreach( $this->slot->leagues as $league) { 
					if( !count( $edit['availability']) || !in_array( $league->league_id, $edit['availability']) ) {
						$this->slot->remove_league( $league );
					}
				}
				if( count($edit['availability']) > 0 ) {
					foreach ( $edit['availability'] as $league ) {
						$this->slot->add_league($league);
					}
				}
				if( $this->slot->save() ) {
				} else {
					error_exit("Internal error: couldn't save gameslot");
				}
				local_redirect(url("slot/availability/" . $this->slot->slot_id));
				break;
			default:
				$this->setLocation(array(
					$slot->field->fullname => "field/view/" . $this->slot->fid,
					$this->title => 0
				));
				return $this->generateForm( $slot );
		}
	}

	function generateForm()
	{

		# What day is this weekday?
		$weekday = strftime("%A", $this->slot->date_timestamp);

		$output = "<p>Availability for " . $this->slot->game_date . " " . $this->slot->game_start. "</p>";

		$leagues = array();
		
		$result = db_query("SELECT 
			l.league_id,
			l.name,
			l.tier
			FROM league l
			WHERE l.schedule_type != 'none' AND (FIND_IN_SET('%s', l.day) > 0)", $weekday);
			
		while($league = db_fetch_object($result)) {
			if( $league->tier ) {
				$league->fullname = sprintf("$league->name Tier %02d", $league->tier);
			} else {
				$league->fullname = $league->name;
			}
			$league->selected = false;
			$leagues[$league->league_id] = $league;
		}
	
		foreach($this->slot->leagues as $league) {
			$leagues[$league->league_id]->selected = true;
		}

		while(list($id, $league) = each($leagues) ) {
			$chex .= form_checkbox($league->fullname, 'edit[availability][]', $id, $league->selected);
		}

		$chex .= form_submit('submit') . form_reset('reset');
		$output .= form_hidden('edit[step]', 'perform');

		$output .= form_group('Make Gameslot Available To:', $chex);

		return form($output);
	}
}

class GameSlotListDay extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('slot', 'day');
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
			return $this->display_for_day( $year, $month, $day );
		} else {
			$this->setLocation(array( "$this->title" => 0));
			$output = para("Select a date below on which to view all available fields");
			$output .= generateCalendar( $year, $month, $day, 'slot/day', 'slot/day');
			return $output;
		}
	}
	
	function display_for_day ( $year, $month, $day )
	{
		global $lr_session;
		$result = slot_query ( array( 'game_date' => sprintf('%d-%d-%d', $year, $month, $day), '_order' => 'g.game_start, field_code, field_num') );
		
		if( ! $result ) {
			error_exit("Nothing available on that day");
		}

		$header = array("Field","Start Time","End Time","Booking", "Actions");
		$rows = array();
		while($slot = db_fetch_object($result)) {
			$booking = '';

			$field = field_load( array('fid' => $slot->fid));
			
			$actions = array();
			if( $lr_session->has_permission('gameslot','edit', $slot->slot_id)) {
				$actions[] = l('change avail', "slot/availability/$slot->slot_id");
			}
			if( $lr_session->has_permission('gameslot','delete', $slot->slot_id)) {
				$actions[] = l('delete', "slot/delete/$slot->slot_id");
			}
			if($slot->game_id) {
				$game = game_load( array('game_id' => $slot->game_id) );
				$booking = l($game->league_name,"game/view/$game->game_id");
				if( $lr_session->has_permission('game','reschedule', $game->game_id)) {
					$actions[] = l('reschedule/move', "game/reschedule/$game->game_id");
				}
			}
			$rows[] = array(l("$field->code $field->num","field/view/$field->fid"), $slot->game_start, $slot->game_end, $booking, theme_links($actions));
		}
		return "<div class='listtable'>" . table($header, $rows) . "</div>";
	}
}

?>
