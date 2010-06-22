<?php

class docs_help extends Handler
{
	function has_permission ()
	{
		return true;
	}

	function process ()
	{
		$this->title = 'Leaguerunner Online Documentation';
		$this->template_name = 'pages/docs/help.tpl';

		return true;
	}
}
?>
