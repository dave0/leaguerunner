<?php
require_once('Handler/GameHandler.php');
class game_delete extends GameHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('game','delete', $this->game);
	}

	function process()
	{
		$this->title = "Delete Game: {$this->game->name}";
		$this->template_name = 'pages/game/delete.tpl';

		$this->smarty->assign('game', $this->game);

		if( $_POST['submit'] == 'Delete' ) {
			if( ! $this->game->delete() ) {
				error_exit('Failure deleting game');
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}

?>
