<?php

/*
 * Handlers for dealing with fields
 * TODO: Roll all this into gameslot instead?
 */

function field_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'view':
			return new FieldView;
	}
	return null;
}

/**
 * Field viewing handler
 */
class FieldView extends Handler
{
	function initialize ()
	{
		$this->title = 'View Field';
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'allow',
		);
		$this->_permissions = array(
			'field_admin'		=> false,
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

		global $session;

		$site_id = arg(2);
		$field_num = arg(3);

		$site = site_load( array('site_id' => $site_id) );
		if(!$site) {
			$this->error_exit("That site does not exist");
		}
		if( !validate_number($field_num) ) {
			$this->error_exit("That field does not exist");
		}
		
		$this->setLocation(array(
			"$site->name $field_num" => "field/view/$site_id/$field_num",
			$this->title => 0
		));

		$result = db_query("SELECT 
			g.*
			FROM gameslot g
			WHERE site_id = %d AND field_num = %d ORDER BY g.game_date, g.game_start", $site->site_id, $field_num);

		$header = array("Date","Start Time","End Time","Booking", "Actions");
		$rows = array();
		while($slot = db_fetch_object($result)) {
			$booking = '';
			if( $this->_permissions['field_admin'] ) {
				$actions = array(
					l('set avail', "slot/availability/$slot->slot_id"),
					l('delete', "slot/delete/$slot->slot_id")
				);
			} else {
				$actions = array();
			}
			if($slot->game_id) {
				$game = game_load( array('game_id' => $slot->game_id) );
				$booking = l($game->league_name,"game/view/$game->game_id");
				if( $session->is_coordinator_of($game->league_id) ) {
					$actions[] = l('postpone game', "game/reschedule/$game->game_id");
				}
			}
			$rows[] = array($slot->game_date, $slot->game_start, $slot->game_end, $booking, theme_links($actions));
		}

		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";

		site_add_to_menu($site);
		menu_add_child($site->name, "$site->name $field_num", "$site->name $field_num", array('link' => "field/view/$site->site_id/$field_num"));
		if($this->_permissions['field_admin']) {
			menu_add_child("$site->name $field_num", "$site->name $field_num gameslot", 'new gameslot', array('link' => "slot/create/$site->site_id/$field_num"));
		}

		return $output;
	}
}
?>
