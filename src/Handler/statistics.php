<?php
class statistics extends Handler
{
	function __construct ( $type )
	{
		if ( ! module_hook($type,'statistics') ) {
			error_exit('Operation not found');
		}
		$this->type = $type;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->is_admin();
	}

	function process ()
	{
		$this->title = ucfirst($this->type) . ' Statistics';
		$this->setLocation(array($this->title => 0));
		return module_invoke($this->type, 'statistics');
	}
}

?>
