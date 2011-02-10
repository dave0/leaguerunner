<?php
require_once('Handler/SeasonHandler.php');
class season_view extends SeasonHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('season','view',$this->season->id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = $this->season->display_name;
		$this->template_name = 'pages/season/view.tpl';

		$this->smarty->assign('season', $this->season);

		$this->season->load_leagues();
		$this->smarty->assign('leagues', $this->season->leagues);

		return true;
	}
}

?>
