<?php
require_once('Handler/SeasonHandler.php');
class season_delete extends SeasonHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('season','delete',$this->season->id);
	}

	function process()
	{
		$this->title = "Delete Season: {$this->season->display_name}";
		$this->template_name = 'pages/season/delete.tpl';

		$this->smarty->assign('season', $this->season);

		if( $_POST['submit'] == 'Delete' ) {
			if( ! $this->season->delete() ) {
				error_exit('Failure deleting season');
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}

?>
