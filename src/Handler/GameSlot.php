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
		case 'edit':
			# TODO: allow admin to manually edit gameslots + times?
			#       is this necessary?
			return new GameSlotEdit;
		case 'view':
			# TODO: is this necessary
			return new GameSlotView;
			
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
		$site_id = arg(2);
		$field_num = arg(3);

		$site = site_load( array('site_id' => $site_id) );
		if(!$site) {
			$this->error_exit("That site does not exist");
		}
		if( !validate_number($field_num) ) {
			$this->error_exit("That field does not exist");
		}

		$edit = &$_POST['edit'];
		switch($edit['step']) {
			case 'perform':
			# Processing should:
			#   - check for overlaps with existing slots
			#   - insert into gameslot table
			#   - insert availability for gameslot into availability table.
			# the overlap-checking probably belongs in slot.inc
				break;
			case 'confirm':
				return $this->generateConfirm($site, $field_num, $edit);
				break;
			default:
			# Creation of a booking slot for a game.  It represents
			# the availability of a particular field at a particular time.
			# Provided to handler is:
			#   - site ID
			#   - field number
			# Form should present the following options:
			#   - start time
			#   - end time (TODO: handling the timecaps?)
			#   - checkboxes or multi-select pulldown for all leagues playing
			#     on this day.
				return $this->generateForm($site, $field_num);
				break;
		}

		return 'TODO';
	}

	function generateForm ( &$site, $field_num )
	{
		die("Not implemented yet");	
	}
	
	function generateConfirm ( &$site, $field_num, &$edit )
	{
		die("Not implemented yet");	
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
		
		$this->setLocation(array(
			$slot->site->name . ' ' . $slot->field_num => "field/view/$slot->site_id/$field_num",
			$this->title => 0
		));

		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				foreach( $slot->leagues as $league) { 
					if( ! in_array( $league->league_id, $edit['availability'] ) ) {
						$slot->remove_league( $league );
					}
				}
				foreach ( $edit['availability'] as $league ) {
					$slot->add_league($league);
				}
				if( $slot->save() ) {
					return $this->availability_form( $slot );
				} else {
					$this->error_exit("Internal error: couldn't save gameslot");
				}
				break;
			default:
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

		$output .= form_group('Make Timeslot Available To:', $chex);

		return form($output);
	}
}

?>
