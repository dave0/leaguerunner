<?php

class SlotHandler extends Handler
{
	protected $slot;

	function __construct ( $id )
	{
		$this->slot = GameSlot::load( array('slot_id' => $id) );

		if(!$this->slot) {
			error_exit("That gameslot does not exist");
		}
	}
}
?>
