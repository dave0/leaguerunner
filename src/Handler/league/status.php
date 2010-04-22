<?php
require_once('Handler/LeagueHandler.php');
class league_status extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit', $this->league->league_id);
	}

	function process ()
	{
		$this->title = "League Status Report";

		$rc = $this->generateStatusPage();

		$this->setLocation(array( $this->league->name => "league/status/" . $this->league->league_id, $this->title => 0));

		return $rc;
	}

	function generateStatusPage ( )
	{
		// make sure the teams are loaded
		$this->league->load_teams();

		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));

		$fields = array();
		$sth = field_query( array( '_extra' => '1 = 1', '_order' => 'f.code') );
		while( $field = $sth->fetchObject('Field') ) {
			$fields[$field->code] = $field->region;
		}

		$output = para("This is a general scheduling status report for rating ladder leagues.");

		$header[] = array('data' => "Rating", 'rowspan' => 2);
		$header[] = array('data' => "Team", 'rowspan' => 2);
		$header[] = array('data' => "Games", 'rowspan' => 2);
		$header[] = array('data' => "Home/Away", 'rowspan' => 2);
		$header[] = array('data' => "Region", 'colspan' => 4);
		$header[] = array('data' => "Region Pct", 'rowspan' => 2);
		$header[] = array('data' => "Opponents", 'rowspan' => 2);
		$header[] = array('data' => "Repeat Opponents", 'rowspan' => 2);

		$subheader[] = array('data' => "C", 'class' => "subtitle");
		$subheader[] = array('data' => "E", 'class' => "subtitle");
		$subheader[] = array('data' => "S", 'class' => "subtitle");
		$subheader[] = array('data' => "W", 'class' => "subtitle");

		$rows = array();
		$rows[] = $subheader;

		$rowstyle = "standings_light";

		// get the schedule
		$schedule = array();
		$sth = game_query ( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date, g.game_start, field_code') );
		while($g = $sth->fetchObject('Game') ) {
			$schedule[] = $g;
		}

		while(list(, $tid) = each($order)) {
			if ($rowstyle == "standings_light") {
				$rowstyle = "standings_dark";
			} else {
				$rowstyle = "standings_light";
			}
			$row = array( array('data'=>$season[$tid]->rating, 'class'=>"$rowstyle") );
			$row[] = array('data'=>l($season[$tid]->name, "team/view/$tid"), 'class'=>"$rowstyle");

			// count number of games for this team:
			//$games = game_load_many( array( 'either_team' => $this->team->team_id, '_order' => 'g.game_date,g.game_id') );
			$numgames = 0;
			$homegames = 0;
			$awaygames = 0;

			$region = array(
				'Central' => 0,
				'East' => 0,
				'South' => 0,
				'West' => 0,
			);

			$opponents = array();

			// parse the schedule
			reset($schedule);
			while(list(,$game) = each($schedule)) {
				if ($game->home_team == $tid) {
					$numgames++;
					$homegames++;
					$opponents[$game->away_team]++;
				}
				if ($game->away_team == $tid) {
					$numgames++;
					$awaygames++;
					$opponents[$game->home_team]++;
				}
				if ($game->home_team == $tid || $game->away_team == $tid) {
					list($code, $num) = split(" ", $game->field_code);
					$region[$fields[$code]]++;
				}
			}
			//reset($games);

			$row[] = array('data'=>$numgames, 'class'=>"$rowstyle", 'align'=>"center");
			$row[] = array('data'=> _ratio_helper( $homegames, $numgames), 'class'=>$rowstyle, 'align'=>"center");

			// regions:
			$pref = '---';
			$region_count = 0;
			if ($season[$tid]->region_preference != "---" && $season[$tid]->region_preference != "") {
				$pref = $season[$tid]->region_preference;
				$region_count  = $region[$pref];
				$region[$pref] = "<b><font color='blue'>$region_count</font></b>";
			} else {
				// No region preference means they're always happy :)
				$region_count = $numgames;
			}
			$row[] = array('data'=>$region['Central'], 'class'=>"$rowstyle");
			$row[] = array('data'=>$region['East'], 'class'=>"$rowstyle");
			$row[] = array('data'=>$region['South'], 'class'=>"$rowstyle");
			$row[] = array('data'=>$region['West'], 'class'=>"$rowstyle");
			$row[] = array('data'=> _ratio_helper( $region_count, $numgames), 'class' => $rowstyle);

			$row[] = array('data'=>count($opponents), 'class'=>"$rowstyle", 'align'=>"center");

			// figure out the opponent repeats
			$opponent_repeats="";
			while(list($oid, $repeats) = each($opponents)) {
				if ($repeats > 2) {
					$opponent_repeats .= $season[$oid]->name . " (<font color='red'><b>$repeats</b></font>) <br>";
				} else if ($repeats > 1) {
					$opponent_repeats .= $season[$oid]->name . " (<b>$repeats</b>) <br>";
				}
			}
			$row[] = array('data'=>$opponent_repeats, 'class'=>"$rowstyle");

			$rows[] = $row;
		}

		//$output .= table($header, $rows);
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";

		return form($output);
	}
}

function _ratio_helper( $count, $total )
{
	$ratio = 0;
	if( $total > 0 ) {
		$ratio = $count / $total;
	}
	$output = sprintf("%.3f (%d/%d)", $ratio, $count, $total);

	// For odd numbers of games, don't warn if we're just under an
	// impossible-to-reach 50%.
	$check_ratio = $ratio;
	if( $total % 2 ) {
		$check_ratio = ($count+1)/($total+1);
	}

	if( $check_ratio < 0.5 ) {
		$output = "<font color='red'><b>$output</b></font>";
	}
	return $output;
}


?>
