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

	if(!$_GET['image'] == 'yes') {
		return new GraphWrapper;	
	}
	switch($op) {
		case 'teamrank':
			$obj = new GraphTeamRank;
			break;
		case 'leaguerank':
			$obj = new GraphLeagueRank;
			$obj->league = league_load( array( 'league_id' => arg(2)));
			break;
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

class GraphWrapper extends Handler
{
	function has_permission()
	{
		return true;
	}

	function process ()
	{
		$op = arg(1);
		switch($op) {
			case 'leaguerank':
				$league = league_load( array( 'league_id' => arg(2)));
				league_add_to_menu( $league );
				break;	
			case 'teamrank':
				$team_ids = explode("/", $_GET["q"]);
				array_shift($team_ids);  // Remove handler
				array_shift($team_ids);  // remove op
				foreach($team_ids as $id) {
					$team = team_load( array('team_id' => $id) );
					if( !$team ) {
						continue;
					}
					team_add_to_menu($team);
				}
				break;
			default:
				break;
		}
	
		$uri = request_uri();
		if(preg_match("/\?/", $uri) ) {
			$uri .= "&image=yes";
		} else {
			$uri .= "?image=yes";
		}
		return "<img src='$uri' />";
	}
}

class GraphTeamRank extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view');
	}

	function process ()
	{
		global $lr_session;

		$graph = new Graph(600,400,'auto', LR_GRAPH_TIMEOUT);
		$graph->SetScale("intint");
		$graph->yscale->SetGrace(10);
		$graph->img->SetMargin(40,20,20,40);
		$graph->legend->SetPos(0.5,0.99,'center','bottom');
		$graph->legend->SetLayout(LEGEND_HOR);

		$graph->title->Set("Team Ranking by Week");
#		$graph->xaxis->title->Set("Week");
#		$graph->xaxis->SetPos('max');
		$graph->xaxis->Hide();
		$graph->yaxis->title->Set("Rank");
		$graph->yaxis->SetLabelFormatCallback('_cb_invert');

		$team_ids = explode("/", $_GET["q"]);
		array_shift($team_ids);  // Remove handler
		array_shift($team_ids);  // remove op

		foreach($team_ids as $id) {
			$team = team_load( array('team_id' => $id) );
			if( !$team ) {
				continue;
			}
			$this->add_to_graph($graph, $team);
		}
		header("Content-type: image/png");
		$graph->Stroke();
		exit;
	}
	
	function add_to_graph( &$graph, &$team) 
	{
		// Get games
		$games = game_load_many( array('either_team' => $team->team_id) );
		if($games) {
			$ydata = array();
			foreach($games as $game) {
				if( ! $game->is_finalized() ) {
					continue;
				}
				if( !$game->home_dependant_rank || !$game->away_dependant_rank) {
					continue;
				}
				if( $game->home_team == $team->team_id ) {
					$ydata[] = round(-$game->home_dependant_rank);
				} else {
					$ydata[] = round(-$game->away_dependant_rank);
				}
			}
			// Add current rank
			$ydata[] = round(-$team->rank);

			
			// Create the linear plot
			$plot = new LinePlot($ydata);
			$plot->SetColor(getcolour($team->rank));
			$plot->mark->SetType(getsymbol($team->rank));
			$plot->mark->SetColor(getcolour($team->rank));
#			$plot->value->SetFormatCallback('_cb_invert');
#			$plot->value->Show();
#$plot->SetColor('red');
#$plot->SetWeight(3);
			$plot->SetLegend($team->name);
			$graph->Add($plot);
		}
	}

}

class GraphLeagueRank extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		if( !$this->league ) {
			error_exit("That is not a valid league");
		}
		return $lr_session->has_permission('team','view');
	}

	function process ()
	{
		global $lr_session;

		$graph = new Graph(600,400,'auto',LR_GRAPH_TIMEOUT);
		$graph->SetScale("intint");
		$graph->yscale->SetGrace(10);
		$graph->img->SetMargin(40,20,20,40);
		$graph->title->Set("League Movement for " . $this->league->name);
#		$graph->xaxis->title->Set("Week");
#		$graph->xaxis->SetPos('max');
		$graph->xaxis->Hide();
		$graph->yaxis->title->Set("Rank");
		$graph->yaxis->SetLabelFormatCallback('_cb_invert');

		$this->league->load_teams();

		while( list($idx, $team) = each($this->league->teams)) {
		
			$this->add_to_graph($graph, $idx, $team);
		}
		header("Content-type: image/png");
		$graph->Stroke();
		exit;
	}
	
	function add_to_graph( &$graph, $idx, &$team) 
	{
		// Get games
		$games = game_load_many( array('either_team' => $team->team_id) );
		if($games) {
			$ydata = array();
			foreach($games as $game) {
				if( ! $game->is_finalized() ) {
					continue;
				}
				if( !$game->home_dependant_rank || !$game->away_dependant_rank) {
					continue;
				}
				if( $game->home_team == $team->team_id ) {
					$ydata[] = round(-$game->home_dependant_rank);
				} else {
					$ydata[] = round(-$game->away_dependant_rank);
				}
			}
			// Add current rank
			$ydata[] = round(-$team->rank);

			
			// Create the linear plot
			$plot = new LinePlot($ydata);
			$plot->SetColor( 'red' );
			$plot->SetWeight(3);
			$graph->Add($plot);
		}
	}

}

class GraphTeamSpirit extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view');
	}

	function process ()
	{
		global $lr_session;
		
		$graph = new Graph(600,400,'auto', LR_GRAPH_TIMEOUT);
		$graph->SetScale("intint");
		$graph->yscale->SetGrace(10);
		$graph->img->SetMargin(40,20,20,40);
		$graph->legend->SetPos(0.5,0.99,'center','bottom');
		$graph->legend->SetLayout(LEGEND_HOR);

		$graph->title->Set("Team Spirit by Week");
#		$graph->xaxis->title->Set("Week");
#		$graph->xaxis->SetPos('max');
		$graph->xaxis->Hide();
		$graph->yaxis->title->Set("SOTG");

		$team_ids = explode("/", $_GET["q"]);
		array_shift($team_ids);  // Remove handler
		array_shift($team_ids);  // remove op

		foreach($team_ids as $id) {
			$team = team_load( array('team_id' => $id) );
			if( !$team ) {
				error_exit("That is not a valid team ID");
			}
			if( ! $lr_session->is_coordinator_of($team->league_id) ) {
				error_exit("You do not have permission to view that team's spirit");
			}
			$this->add_to_graph($graph, $team);
		}
		header("Content-type: image/png");
		$graph->Stroke();
		exit;
	}
	
	function add_to_graph( &$graph, &$team) 
	{
		// Get games
		$games = game_load_many( array('either_team' => $team->team_id) );
		if($games) {
			$ydata = array();
			$y2data= array();
			$running_total = 0;
			$count = 0;
			foreach($games as $game) {
				if( ! $game->is_finalized() ) {
					continue;
				}
	#			if( $game->status != 'normal') {
	#				continue;
	#			}
				$count++;
				$sotg = $game->get_spirit_numeric($team->team_id);
				$running_total += $sotg;
				$ydata[] = $sotg;
				$y2data[] = ($running_total / $count);
		#		$y3data[] = abs($game->home_score - $game->away_score);
			}
			
			// Create the linear plot
			$plot = new LinePlot($ydata);
			$plot->SetColor(getcolour($team->rank));
			$plot->mark->SetType(getsymbol($team->rank));
			$plot->mark->SetColor(getcolour($team->rank));
			$plot->value->Show();
			$plot->SetLegend($team->name . " Per-Game");
			$graph->Add($plot);
			
			$plot2 = new LinePlot($y2data);
			$plot2->SetColor(getcolour($team->rank));
			$plot2->SetWeight(3);
			$plot2->SetLegend($team->name . " Average");
			$graph->Add($plot2);
			
	#		$plot3 = new LinePlot($y3data);
	#		$plot3->mark->SetType(getsymbol($team->rank));
	#		$plot3->mark->SetColor(getcolour($team->rank));
	#		$plot3->SetLegend($team->name . " ScoreDiff");
	#		$graph->Add($plot3);
		}
	}

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

function getcolour ( $id )
{
	$colours = array('blue','green','red','black');
	return $colours[ $id % count($colours) ];
}
function getsymbol ( $id )
{
	$symbols = array(MARK_SQUARE,MARK_UTRIANGLE,MARK_DTRIANGLE,MARK_DIAMOND, MARK_CIRCLE, MARK_FILLEDCIRCLE, MARK_CROSS, MARK_STAR, MARK_X, MARK_LEFTTRIANGLE,MARK_RIGHTTRIANGLE);
	return $symbols[ $id % count($symbols) ];
}
function _cb_invert($value)
{
	return sprintf('%d', round(-$value));
#	return round(-$value);
}
?>
