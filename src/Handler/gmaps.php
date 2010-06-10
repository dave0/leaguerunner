<?php
class gmaps extends Handler
{
	function __construct ( )
	{
		$this->template_name = 'pages/gmaps.tpl';
	}

	function has_permission()
	{
		return true;
	}

	function process ()
	{
		$this->smarty->assign('gmaps_key', variable_get('gmaps_key', '') );
		$this->smarty->assign('leaguelat', variable_get('location_latitude', 0));
		$this->smarty->assign('leaguelng', variable_get('location_longitude', 0));
	}
}
?>
