<?php
require_once('Handler/SlotHandler.php');
class slot_delete extends SlotHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('gameslot','delete', $this->slot->slot_id);
	}

	function process()
	{
		$this->title = "{$this->slot->field->fullname} &raquo; Delete Gameslot {$this->slot->slot_id}";

		$this->template_name = 'pages/slot/delete.tpl';

		$this->smarty->assign('slot', $this->slot);

		// Check that the slot has no games scheduled
		if ($this->slot->game_id) {
			error_exit("Cannot delete a gameslot with a currently-scheduled game");
		}

		if( $_POST['submit'] == 'Delete' ) {
			if( ! $this->slot->delete() ) {
				error_exit('Failure deleting slot');
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}
?>
