<?php
require_once('Handler/EventHandler.php');
class event_delete extends EventHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('event','delete',$this->event->registration_id);
	}

	function process()
	{
		$this->title = "Delete Event: {$this->event->name}";
		$this->template_name = 'pages/event/delete.tpl';

		$this->smarty->assign('event', $this->event);

		if( $_POST['submit'] == 'Delete' ) {
			if( ! $this->event->delete() ) {
				error_exit('Failure deleting event');
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}

?>
