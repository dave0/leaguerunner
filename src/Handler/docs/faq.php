<?php

class docs_faq extends Handler
{
	function has_permission ()
	{
		return true;
	}

	function process ()
	{
		$this->title = 'Leaguerunner Frequently Asked Questions';
		$this->template_name = 'pages/docs/faq.tpl';

		return true;
	}
}
?>
