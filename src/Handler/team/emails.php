<?php
require_once('Handler/TeamHandler.php');

class team_emails extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','email',$this->team->team_id);
	}

	function process ()
	{
		$this->title = "{$this->team->name} &raquo; Emails";

		$this->template_name = 'pages/team/emails.tpl';

		$this->team->get_roster();

		$this->smarty->assign('list',
			player_rfc2822_address_list($this->team->roster, true) );

		return true;
	}
}

?>
