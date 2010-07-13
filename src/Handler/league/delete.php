<?php
require_once('Handler/LeagueHandler.php');
class league_delete extends LeagueHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','delete',$this->league->league_id);
	}

	function process()
	{
		$this->title = "Delete League: {$this->league->fullname}";
		$this->template_name = 'pages/league/delete.tpl';

		$this->smarty->assign('league', $this->league);

		if( $_POST['submit'] == 'Delete' ) {
			if( ! $this->league->delete() ) {
				error_exit('Failure deleting league');
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}

?>
