<?php
require_once('Handler/FieldHandler.php');

class field_bookings extends FieldHandler
{
	function has_permission()
	{
		global $lr_session;

		if ($this->field->status != 'open') {
			error_exit("That field is closed");
		}

		return $lr_session->has_permission('field','view', $this->field->fid);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "Bookings: {$this->field->fullname}";

		$sth = Slot::query( array('fid' => $this->field->fid,
				'_extra' => 'DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AND DATE_ADD(CURDATE(), INTERVAL 1 YEAR)',
				'_order' => 'g.game_date, g.game_start'));

		$header = array("Date","Start Time","End Time","Booking", "Actions");
		$rows = array();
		while($slot = $sth->fetchObject('GameSlot') ) {
			$booking = '';
			$actions = array();
			if( $lr_session->has_permission('gameslot','edit', $slot->slot_id)) {
				$actions[] = l('change avail', "slot/availability/$slot->slot_id");
			}
			if( $lr_session->has_permission('gameslot','delete', $slot->slot_id)) {
				$actions[] = l('delete', "slot/delete/$slot->slot_id");
			}
			if($slot->game_id) {
				$game = Game::load( array('game_id' => $slot->game_id) );
				$booking = l($game->league_name,"game/view/$slot->game_id");
				if( $lr_session->has_permission('game','reschedule', $slot->game_id)) {
					$actions[] = l('reschedule/move', "game/reschedule/$slot->game_id");
				}
			}
			$rows[] = array($slot->game_date, $slot->game_start, $slot->display_game_end(), $booking, implode(' | ', $actions));
		}

		return "<div class='listtable'>" . table($header, $rows) . "</div>";
	}
}
?>
