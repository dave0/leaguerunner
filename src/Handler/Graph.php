<?php
include_once ("/usr/share/jpgraph/jpgraph.php");
include_once ("/usr/share/jpgraph/jpgraph_line.php");
include_once ("/usr/share/jpgraph/jpgraph_pie.php");

function graph_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'teamrank':
			$obj = new GraphTeamRank;
			break;
		case 'playerskill':
			$obj = new GraphPlayerSkill;
			break;
		default:
			$obj = null;
	}
	return $obj;
}

class GraphTeamRank extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','view');
	}

	function process ()
	{
		global $session;

		$graph = new Graph(600,400,'auto');
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
			$this->add_to_graph($graph, $id);
		}
		header("Content-type: image/png");
		$graph->Stroke();
		exit;
	}
	
	function add_to_graph( &$graph, $team_id) 
	{
		$team = team_load( array('team_id' => $team_id) );
		if( !$team ) {
			return;
		}
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
#			$plot->value->SetFormat('%d','%d');
			$plot->value->SetFormatCallback('_cb_invert');
			$plot->value->Show();
			$plot->SetLegend($team->name);
			$graph->Add($plot);
		}
	}

}

class GraphPlayerSkill extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','view');
	}

	function process ()
	{
		$graph = new PieGraph(300,200);
		$graph->title->Set("Player Skill Distribution");

		$result = db_query("SELECT skill_level, COUNT(*) AS count FROM person GROUP BY skill_level");
		$data = array();
		$legend = array();
		while($row = db_fetch_array($result)) {
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

function getcolour ( $id )
{
	$colours = array('blue','green','red','orange','black');
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
