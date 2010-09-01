<?php
require_once('Handler/LeagueHandler.php');
// TODO: Common email-list displaying, should take query as argument, return
// formatted list.
class league_captemail extends LeagueHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view',$this->league->league_id, 'captain emails');
	}

	function process ()
	{
		$this->title = "{$this->league->fullname} &raquo; Captain Emails";
		$this->template_name = 'pages/league/captemail.tpl';
		$captains = $this->league->get_captains();
		if( ! count( $captains ) ) {
			error_exit("That league contains no teams.");
		}
		$this->smarty->assign('list', player_rfc2822_address_list($captains, true) );

		return true;
	}
}

?>
