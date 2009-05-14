<?php
include_once ("/usr/share/jpgraph/jpgraph.php");
include_once ("/usr/share/jpgraph/jpgraph_line.php");
include_once ("/usr/share/jpgraph/jpgraph_pie.php");

/*
 * Graphing for Leaguerunner
 * 
 * For this to work well, you need to have caching enabled.  This means
 * setting:
 *     DEFINE("USE_CACHE",true); 
 *     DEFINE("CACHE_DIR","/tmp/jpgraph_cache/");
 * in /usr/share/jpgraph/jpg-config.php (or wherever the config file lives on
 * your system.
 */
define("LR_GRAPH_TIMEOUT",240);   # timeout is 4 hours per image

function graph_dispatch() 
{
	$op = arg(1);

	switch($op) {
		case 'teamspirit':
			$obj = new GraphTeamSpirit;
			break;
		case 'playerskill':
			$obj = new GraphPlayerSkill;
			break;
		case 'rostersize':
			$obj = new GraphRosterSize;
			break;
		default:
			$obj = null;
	}
	return $obj;
}

class GraphPlayerSkill extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view');
	}

	function process ()
	{
		global $dbh;
		$graph = new PieGraph(300,200,'auto',LR_GRAPH_TIMEOUT);
		$graph->title->Set("Player Skill Distribution");

		$sth = $dbh->prepare("SELECT skill_level, COUNT(*) AS count FROM person GROUP BY skill_level");
		$data = array();
		$legend = array();
		$sth->execute();
		while($row = $sth->fetch() ) {
			$legend[] = $row['skill_level'];
			$data[] = $row['count'];
		}
		$plot = new PiePlot($data);
		$plot->SetLabelType(PIE_VALUE_ABS);
		$plot->value->SetFormat('%d');
		$plot->SetLegends($legend);
		$plot->SetCenter(0.4);
		$graph->Add($plot);
		header("Content-type: image/png");
		$graph->Stroke();
		exit;
	}
}

class GraphRosterSize extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('statistics','roster size');
	}

	function process ()
	{
		global $dbh;
		$current_season = arg(2);
		if(!$current_season) {
			$current_season = variable_get('current_season', 'Summer');
		}
		$graph = new PieGraph(300,200,'auto',LR_GRAPH_TIMEOUT);
		$graph->title->Set("Team Roster Size ($current_season)");

		$sth = $dbh->prepare("SELECT COUNT(r.player_id) as size 
   	     	FROM teamroster r , league l, leagueteams lt
    	    LEFT JOIN team t ON (t.team_id = r.team_id) 
       		WHERE 
                lt.team_id = r.team_id
                AND l.league_id = lt.league_id 
                AND l.schedule_type != 'none' 
				AND l.season = ?
                AND (r.status = 'player' OR r.status = 'captain' OR r.status = 'assistant')
        	GROUP BY t.team_id 
        	ORDER BY size desc");
		$sth->execute(array($current_season));
		$sizes = array();
		while($size = $sth->fetchColumn() ) { 
			$sizes[$size]++;
		}
		$plot = new PiePlot(array_values($sizes));
		$plot->SetLegends(array_keys($sizes));
		$plot->SetCenter(0.4);
		$graph->Add($plot);
		header("Content-type: image/png");
		$graph->Stroke();
		exit;
	}
}

?>
