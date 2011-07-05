<?php
require_once('Handler/FieldHandler.php');
// TODO: this is really a site thing, not a field thing.
class field_rankings extends FieldHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','view rankings', $this->field->fid);
	}

	function process ()
	{
		global $dbh;
		$this->title = "Rankings &raquo; " . $this->field->name;

		$this->template_name = 'pages/field/rankings.tpl';

		$this->smarty->assign('field', $this->field);

		$current_season_id = $_GET['season'];
		if( !$current_season_id ) {
			$current_season_id = strtolower(variable_get('current_season', 1 ));
		}
		$this->smarty->assign('seasons', getOptionsFromQuery(
			"SELECT id AS theKey, display_name AS theValue FROM season ORDER BY year, id")
		);
		$this->smarty->assign('current_season_id', $current_season_id);

		$sth = $dbh->prepare('SELECT t.team_id, t.name, l.league_id, l.name AS league_name, r.rank FROM team_site_ranking r, team t, leagueteams lt, league l WHERE r.team_id = t.team_id AND lt.team_id = t.team_id AND l.league_id = lt.league_id AND l.season = :season AND r.site_id = :fid ORDER BY r.rank');
		if( $this->field->parent_fid ) {
			$sth->execute(array( 'fid' => $this->field->parent_fid, 'season' => $current_season_id  ) );
		} else {
			$sth->execute(array( 'fid' => $this->field->fid, 'season' => $current_season_id  ) );
		}
		$teams = array();
		while( $t = $sth->fetch(PDO::FETCH_OBJ) ) {
			$teams[] = $t;
		}

		$this->smarty->assign('teams', $teams );
		return true;
	}
}
?>
