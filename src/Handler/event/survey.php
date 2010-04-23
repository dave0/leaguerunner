<?php
require_once('Handler/EventHandler.php');
class event_survey extends EventHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','edit');
	}

	function process ()
	{
		$this->title = 'Maintain Event Survey';
		$rc = formbuilder_maintain( $this->event->formkey() );
		$this->setLocation(array($this->title => 0));
		return $rc;
	}
}

?>
