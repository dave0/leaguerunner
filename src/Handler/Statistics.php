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

function statistics_permissions( &$user, $action )
{
	if( $user->is_admin() ) {
		return true;
	}
	return false;
}

class StatisticsHandler extends Handler
{
	function has_permission()
	{
		global $session;
		return $session->is_admin();
	}

	function process ()
	{
		$mod = arg(1);
		$this->title = 'Statistics';
		return module_invoke($mod, 'statistics');
	}
}

?>
