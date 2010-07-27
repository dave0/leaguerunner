<?php
class league_list extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','list');
	}

	function process ()
	{
		global $lr_session;

		$current_season_id = $_GET['season'];
		if( !$current_season_id ) {
			$current_season_id = strtolower(variable_get('current_season', 'ongoing'));
		}

		$current_season = Season::load(array( 'id' => $current_season_id ));
		if( !$current_season) {
			$current_season_id = 'ongoing';
			$current_season = Season::load(array( 'id' => $current_season_id ));
		}

		$this->title = "{$current_season->display_name} Leagues";
		$this->template_name = 'pages/league/list.tpl';

		$season_obj = Season::load_many();
		$seasons = array();
		foreach($season_obj as $s) {
			$seasons[$s->id] = $s->display_name;
		}

		$this->smarty->assign('current_season', $current_season);
		$this->smarty->assign('seasons', $seasons);

		$leagues = League::load_many( array( 'season' => $current_season_id, '_order' => "year,FIELD(MAKE_SET((day & 62), 'BUG','Monday','Tuesday','Wednesday','Thursday','Friday'),'Monday','Tuesday','Wednesday','Thursday','Friday'), tier, league_id") );
		$this->smarty->assign('leagues', $leagues);

		return true;
	}
}

?>
