<?php

/*
 * Handlers for dealing with game slots
 */

function slot_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new GameSlotCreate;
		case 'delete':
			return new GameSlotDelete;
		case 'edit':
			# TODO: allow admin to manually edit gameslots + times
			#       Necessary for editing end times (?)
			return new GameSlotEdit;
		case 'availability':
			return new GameSlotAvailability;
	}
	return null;
}

class GameSlotCreate extends Handler
{
	function initialize ()
	{
		$this->title = "Create Game Slot";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		
		return true;
	}

	function process()
	{
		$fid = arg(2);
		$year = arg(3);
		$month = arg(4);
		$day = arg(5);

		$field = field_load( array('fid' => $fid) );
		if(!$field) {
			$this->error_exit("That field does not exist");
		}
		
		if ( $day ) {
			if ( ! validate_date_input($year, $month, $day) ) {
				$this->error_exit("That date is not valid");
			}
			$datestamp = mktime(0,0,0,$month,$day,$year);
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
					$this->error_exit("Aieee!  Bad things happened in gameslot create");
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
		$this->error_exit("Error: This code should never be reached.");
	}

	function datePick ( &$field, $year, $month, $day)
	{
		$output = para("Select a date below to start adding gameslots.");

		$today = getdate();
	
		if(! ctype_digit($month)) {
			$month = $today['mon'];
		}

		if(! ctype_digit($year)) {
			$year = $today['year'];
		}

		$output .= generateCalendar( $year, $month, $day, "slot/create/$ield->fid", "slot/create/$field->fid");

		return $output;
	}

	# TODO: Processing should:
	#   - check for overlaps with existing slots
	#   - insert into gameslot table
	#   - insert availability for gameslot into availability table.
	# the overlap-checking probably belongs in slot.inc
	function perform ( &$field, $edit, $datestamp )
	{
		if($edit['repeat_for'] > 52) {
			$this->error_exit("You cannot repeat a schedule for more than 52 weeks.");
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
			IF(l.tier,CONCAT(l.name,' Tier ',l.tier),l.name) AS fullname
			FROM league l
			WHERE l.allow_schedule = 'Y' AND (FIND_IN_SET('%s', l.day) > 0)", $weekday);
			
		while($league = db_fetch_object($result)) {
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
		$output = form_hidden('edit[step]', 'perform');
		
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
}

class GameSlotDelete extends Handler
{
	function initialize ()
	{
		$this->title = "Delete Game Slot";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		
		return true;
	}

	function process()
	{
		$slot_id = arg(2);

		$slot = slot_load( array('slot_id' => $slot_id) );
		if(!$slot) {
			$this->error_exit("That game slot does not exist");
		}
		
		$this->setLocation(array( 
			$slot->field->fullname => "field/view/$field->fid",
			$this->title => 0
		));


		switch($_POST['edit']['step']) {
			case 'perform':
				$fid = $slot->fid;
				if ( $slot->delete() ) {
					local_redirect(url("field/view/$fid"));
				} else {
					$this->error_exit("Failure deleting gameslot");
				}
				break;
			case 'confirm':
			default:
				return $this->generateConfirm($slot);
				break;
		}
		$this->error_exit("Error: This code should never be reached.");
	}

	function generateConfirm ( &$slot )
	{
		// Check that the slot has no games scheduled
		if ($slot->game_id) {
			$this->error_exit("Cannot delete a gameslot with a currently-scheduled game");
		}
		
		// Print confirmation info
		$output = form_hidden('edit[step]', 'perform');
		
		$group = form_item("Date", strftime("%A %B %d %Y", $slot->date_timestamp));
		$group .= form_item('Game Start Time', $slot->game_start);
		$group .= form_item('Game End Time', $slot->game_end);
		$output .= form_group("Gameslot Information", $group);

		$group = '';
		foreach( $slot->leagues as $l ) {
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
	function initialize ()
	{
		$this->title = "View Availability";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		
		return true;
	}

	function process()
	{
		$id = arg(2);
		
		$slot = slot_load( array('slot_id' => $id) );
		if(!$slot) {
			$this->error_exit("That gameslot does not exist");
		}
		
		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				foreach( $slot->leagues as $league) { 
					if( !count( $edit['availability']) || !in_array( $league->league_id, $edit['availability']) ) {
						$slot->remove_league( $league );
					}
				}
				if( count($edit['availability']) > 0 ) {
					foreach ( $edit['availability'] as $league ) {
						$slot->add_league($league);
					}
				}
				if( $slot->save() ) {
				} else {
					$this->error_exit("Internal error: couldn't save gameslot");
				}
				local_redirect(url("slot/availability/$slot->slot_id"));
				break;
			default:
				$this->setLocation(array(
					$slot->field->fullname => "field/view/$slot->fid",
					$this->title => 0
				));
				return $this->generateForm( $slot );
		}
	}

	function generateForm( &$slot )
	{

		# What day is this weekday?
		$weekday = strftime("%A", $slot->date_timestamp);

		$output = "<p>Availability for $slot->game_date $slot->game_start</p>";

		$leagues = array();
		
		$result = db_query("SELECT 
			l.league_id,
			IF(l.tier,CONCAT(l.name,' Tier ',l.tier),l.name) AS fullname
			FROM league l
			WHERE l.allow_schedule = 'Y' AND (FIND_IN_SET('%s', l.day) > 0)", $weekday);
			
		while($league = db_fetch_object($result)) {
			$league->selected = false;
			$leagues[$league->league_id] = $league;
		}
	
		foreach($slot->leagues as $league) {
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

?>
