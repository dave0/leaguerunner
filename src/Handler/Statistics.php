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
		global $lr_session;
		return $lr_session->is_admin();
	}

	function process ()
	{
		$mod = arg(1);

		$this->title = ucfirst($mod) . ' Statistics';
		$this->setLocation(array($this->title => 0));
		return module_invoke($mod, 'statistics');
	}
}

?>
