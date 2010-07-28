<?php
class league_list extends Handler
{
	private $season;

	function __construct ( $season = null)
	{
		parent::__construct( );

		if( ! $season ) {
			$season = strtolower(variable_get('current_season', "Summer"));
		}

		$this->season = $season;

	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','list');
	}

	function process ()
	{
		global $lr_session;

		$this->title = "Leagues &raquo; {$this->season}";
		$this->template_name = 'pages/league/list.tpl';

		$seasons = getOptionsFromEnum('league', 'season');
		$maybe_dash = array_shift($seasons);
		if( $maybe_dash != '---' ) {
			array_unshift($seasons, $maybe_dash);
		}
		$this->smarty->assign('seasons', $seasons);

		$leagues = League::load_many( array( 'season' => $this->season, 'status' => 'open', '_order' => "year,FIELD(MAKE_SET((day & 62), 'BUG','Monday','Tuesday','Wednesday','Thursday','Friday'),'Monday','Tuesday','Wednesday','Thursday','Friday'), tier, league_id") );
		$this->smarty->assign('leagues', $leagues);

		return true;
	}
}

?>
