<?php

/*
 * Handlers for dealing with game slots
 */

function slot_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			# Creation of a booking slot for a game.  It represents
			# the availability of a particular field at a particular time.
			# Provided at create-time is:
			#   - site ID
			#   - field number
			# Form should present the following options:
			#   - start time
			#   - end time (TODO: handling the timecaps?)
			#   - checkboxes or multi-select pulldown for all leagues playing
			#     on this day.
			# Processing should:
			#   - check for overlaps with existing slots
			#   - insert into gameslot table
			#   - insert availability for gameslot into availability table.
			# this code probably belongs in slot.inc
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

class GameSlotAvailability extends Handler
{
	function initialize ()
	{
		$this->title = "View Availability";
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'allow'
		);
		
		$this->_permissions = array(
			'modify' => false,
		);
		
		return true;
	}
	
	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		}
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
			case 'form':
				# TODO Print form to make this field usable to any of the
				# leagues that play on this day
			case 'perform':
				# TODO Do it.
			default:
				return $this->show_availability( $slot );
		}
	}

	function show_availability( &$slot )
	{

		$output = "<p>Availability for $slot->game_date $slot->game_start</p>";
	
		$result = db_query("SELECT 
			l.*,
			IF(l.tier,CONCAT(l.name,' Tier ',l.tier),l.name) AS fullname
			FROM league_gameslot_availability a
				LEFT JOIN league l ON (a.league_id = l.league_id)
			WHERE a.slot_id = %d", $slot->slot_id);

		$header = array("League","Actions");
		$rows = array();
		while($league = db_fetch_object($result)) {
			$actions = array();
			$rows[] = array($league->fullname, theme_links($actions));
		}

		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";

		return $output;
	}
}

?>
