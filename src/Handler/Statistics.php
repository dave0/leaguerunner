<?php
/*
 * Handlers for leaguerunner statistics
 */
function statistics_dispatch() 
{
	$mod = arg(1);

	if (module_hook($mod,'statistics')) {
		return new StatisticsHandler;
	}
	return null;
}

function statistics_menu()
{
	global $session;
	if($session->is_admin()) {
		menu_add_child('_root','statistics','Statistics');
	}
}

class StatisticsHandler extends Handler
{
	function initialize ()
	{
		$this->title = 'Statistics';
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		return true;
	}

	function process ()
	{
		$mod = arg(1);
		return module_invoke($mod, 'statistics');
	}
}

?>
