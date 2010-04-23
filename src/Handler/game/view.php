<?php
require_once('Handler/game/edit.php');
class game_view extends game_edit
{
	function has_permission ()
	{
		global $lr_session;

		$this->can_edit = false;

		return $lr_session->has_permission('game','view', $this->game);
	}
}
?>
