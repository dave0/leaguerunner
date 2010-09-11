<?php

/**
 * return HTML suitable for an "Upcoming Games" box
 */
class api_1_my_upcoming extends Handler
{
	function has_permission ()
	{
		/* Everyone can view this box, but only active sessions get useful info */
		return true;
	}

	function process ()
	{
		global $lr_session;

		$this->template_name = 'api/1/my/upcoming.tpl';

		if( ! $lr_session->is_loaded() ) {
			// No session
			# TODO: leave blank instead?
			$this->smarty->assign('error', "No leaguerunner session");
			return true;
		}

		if( ! $lr_session->user->status == 'active' ) {
			# TODO activation URL
			$this->smarty->assign('error', "Please activate your account");
			return true;
		}

		$this->smarty->assign('games', $lr_session->user->fetch_upcoming_games(3) );

		return true;
	}
}
?>
