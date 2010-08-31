<?php
require_once('Handler/TeamHandler.php');

class team_delete extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','delete',$this->team->team_id);
	}

	function process ()
	{
		$this->title = "{$this->team->name} &raquo; Delete";
		$this->template_name = 'pages/team/delete.tpl';

		$this->smarty->assign('team', $this->team);

		if( $_POST['submit'] == 'Delete' ) {
			if( ! $this->team->delete() ) {
				error_exit('Failure deleting team');
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}

?>
