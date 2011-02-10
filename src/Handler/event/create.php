<?php
require_once('Handler/event/edit.php');
class event_create extends event_edit
{
	function __construct ( )
	{
		$this->title = "Create Event";
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','create');
	}

	function process ()
	{
		$this->event = new Event;
		return parent::process();
	}
}

?>
