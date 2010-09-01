<?php
require_once('Handler/FieldHandler.php');

class field_bookings extends FieldHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','view', $this->field->fid);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "{$this->field->fullname} &raquo; Bookings";

		$this->template_name = 'pages/field/bookings.tpl';

		if ($this->field->status != 'open') {
			error_exit("That field is closed");
		}

		$sth = GameSlot::query( array('fid' => $this->field->fid,
				'_extra' => 'DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AND DATE_ADD(CURDATE(), INTERVAL 1 YEAR)',
				'_order' => 'g.game_date, g.game_start'));

		$slots = array();
		while($slot = $sth->fetchObject('GameSlot') ) {
			if($slot->game_id) {
				$slot->game = Game::load( array('game_id' => $slot->game_id) );
			}
			$slots[] = $slot;
		}

		$this->smarty->assign('slots', $slots);

		return true;
	}
}
?>
