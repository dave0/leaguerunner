<?php
class StatisticsHandler extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->is_admin();
	}
}
?>
