<?php
require_once('Handler/GameHandler.php');
/*
 * To remove just the results of a game... ie: teams enter wrong scores
 * This will UNDO any change to rank, ratings, wins/losses/ties, goals for
 * goals against, SOTG, etc.....
 * After this, the game can be re-entered since the game itself is not deleted.
 */
class game_removeresults extends GameHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('game','edit', $this->game);
	}

	function process ()
	{
		$this->title = "Game {$this->game->game_id} &raquo; Remove Results";

		$this->template_name = 'pages/game/removeresults.tpl';

		switch($_POST['step']) {
			case 'perform':
				if ( ! $this->game->removeresults() ) {
					error_exit("Could not successfully remove results for the game");
				}
				local_redirect(url("schedule/view/" . $this->league->league_id));
				break;
			default:
				$this->smarty->assign('game', $this->game);
				return true;
		}

		return $rc;
	}
}

?>
