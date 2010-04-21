<?php

class cron extends Handler
{
	function has_permission ()
	{
		// Always have permission to run cron.  In the future,we may want to
		// restrict this to 127.0.0.1 or something.
		return true;
	}

	function process ()
	{
		return join("", module_invoke_all('cron'));
	}
}

?>
