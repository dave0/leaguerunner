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
?>
