<?php
/**
 * Handle running periodic tasks
 */
function cron_dispatch()
{
	return new CronHandler;
}

class CronHandler extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'allow'
		);
		return true;
	}

	function process ()
	{
		return join("", module_invoke_all('cron'));
	}
}

?>
