<?php
class season_list extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('season','list');
	}

	function process ()
	{
		global $lr_session;

		$current_season_id = strtolower(variable_get('current_season', 'ongoing'));

		$this->title = "Season List";
		$this->template_name = 'pages/season/list.tpl';

		$seasons = Season::load_many();
		$this->smarty->assign('current_season', $current_season_id);
		$this->smarty->assign('seasons', $seasons);

		return true;
	}
}

?>
