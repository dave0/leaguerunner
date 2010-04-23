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

		/* Fetch league names */
		$seasons = getOptionsFromEnum('league', 'season');

		$seasonLinks = array();
		foreach($seasons as $curSeason) {
			$curSeason = strtolower($curSeason);
			if($curSeason == '---') {
				continue;
			}
			if($curSeason == $this->season) {
				$seasonLinks[] = $curSeason;
			} else {
				$seasonLinks[] = l($curSeason, "league/list/$curSeason");
			}
		}

		$this->setLocation(array(
			$this->season => "league/list/$this->season"
		));

		$output = para(theme_links($seasonLinks));

		$header = array( "Name", "&nbsp;") ;
		$rows = array();

		$leagues = league_load_many( array( 'season' => $this->season, 'status' => 'open', '_order' => "year,FIELD(MAKE_SET((day & 62), 'BUG','Monday','Tuesday','Wednesday','Thursday','Friday'),'Monday','Tuesday','Wednesday','Thursday','Friday'), tier, league_id") );

		if ( $leagues ) {
			foreach ( $leagues as $league ) {
				$links = array();
				if($league->schedule_type != 'none') {
					$links[] = l('schedule',"schedule/view/$league->league_id");
					$links[] = l('standings',"league/standings/$league->league_id");
				}
				if( $lr_session->has_permission('league','delete', $league->league_id) ) {
					$links[] = l('delete',"league/delete/$league->league_id");
				}
				$rows[] = array(
					l($league->fullname,"league/view/$league->league_id"),
					theme_links($links));
			}

			$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		}

		return $output;
	}
}

?>
