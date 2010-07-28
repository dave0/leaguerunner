<?php
require_once('Handler/LeagueHandler.php');
// RK: print a field distribution report for this league
// for field balancing
class league_fields extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit', $this->league->league_id);
	}

	function process ()
	{
		$this->title = "{$this->league->fullname} &raquo; Field Distribution Report";

		$rc = $this->generateStatusPage();

		return $rc;
	}

	function generateStatusPage ( )
	{
		global $dbh;

		// make sure the teams are loaded
		$this->league->load_teams();

		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));

		$fields = array();
		$sth = Field::query( array( '_order' => 'f.code') );
		while( $field = $sth->fetchObject('Field') ) {
			$fields[$field->code] = $field->region;
		}

		$output = para("This is a general field scheduling balance report for the league.");

		$num_teams = sizeof($order);

		$header[] = array('data' => "Rating", 'rowspan' => 2);
		$header[] = array('data' => "Team", 'rowspan' => 2);
		$header[] = array('data' => "Region", 'rowspan' => 2);

		// now gather all possible fields this league can use
		$sth = $dbh->prepare('SELECT
				DISTINCT IF(f.parent_fid, pf.code, f.code) AS field_code,
				TIME_FORMAT(g.game_start, "%H:%i") as game_start,
				IF(f.parent_fid, pf.region, f.region) AS field_region,
				IF(f.parent_fid, pf.fid, f.fid) AS fid,
				IF(f.parent_fid, pf.name, f.name) AS name
			FROM league_gameslot_availability a
			INNER JOIN gameslot g ON (g.slot_id = a.slot_id)
			LEFT JOIN field f ON (f.fid = g.fid)
			LEFT JOIN field pf ON (pf.fid = f.parent_fid)
			WHERE a.league_id = ?
			ORDER BY field_region DESC, field_code, game_start');
		$sth->execute( array ($this->league->league_id) );
		$last_region = "";
		$field_region_count = 0;
		while($row = $sth->fetch(PDO::FETCH_OBJ)) {
			$field_list[] = "$row->field_code $row->game_start";
			$subheader[] = array('data' => l($row->field_code, "field/view/$row->fid",
							 array('title'=> $row->name)) . " $row->game_start",
					     'class' => "subtitle");
			if ($last_region == $row->field_region) {
				$field_region_count++;
			} else {
				if ($field_region_count > 0) {
					$header[] = array('data' => $last_region,
							  'colspan' => $field_region_count);
				}
				$last_region = $row->field_region;
				$field_region_count = 1;
			}
		}
		// and make the last region header too
		if ($field_region_count > 0) {
			$header[] = array('data' => $last_region,
					  'colspan' => $field_region_count);
		}
		$header[] = array('data' => "Games", 'rowspan' => 2);

		$rows = array();
		$rows[] = $subheader;

		$rowstyle = "standings_light";

		// get the schedule
		$schedule = array();
		$sth = Game::query ( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date, g.game_start, field_code') );
		while($g = $sth->fetchObject('Game') ) {
			$schedule[] = $g;
		}

		// we'll cache these results, so we can compute avgs and highlight numbers too far from average
		$cache_rows = array();
		while(list(, $tid) = each($order)) {
			if ($rowstyle == "standings_light") {
				$rowstyle = "standings_dark";
			} else {
				$rowstyle = "standings_light";
			}
			$row = array( array('data'=>$season[$tid]->rating, 'class'=>"$rowstyle") );
			$row[] = array('data'=>l($season[$tid]->name, "team/view/$tid"), 'class'=>"$rowstyle");
			$row[] = array('data'=>$season[$tid]->region_preference, 'class'=>"$rowstyle");

			// count number of games per field for this team:
			$numgames = 0;
			$count = array();

			// parse the schedule
			reset($schedule);
			while(list(,$game) = each($schedule)) {
				if ($game->home_team == $tid || $game->away_team == $tid) {
					$numgames++;
					list($code, $num) = explode(' ', $game->field_code);
					$count["$code $game->game_start"]++;
				}
			}

			foreach ($field_list as $f) {
				if ($count[$f]) {
					$row[] = array('data'=> $count[$f], 'class'=>"$rowstyle", 'align'=>'center');
					$total_at_field[$f] += $count[$f];
				} else {
					$row[] = array('data'=> "0", 'class'=>"$rowstyle", 'align'=>'center');
				}
			}

			$row[] = array('data'=>$numgames, 'class'=>"$rowstyle", 'align'=>"center");

			$cache_rows[] = $row;
		}

		// pass through cached rows and highlight entries far from avg
		foreach ($cache_rows as $row) {
			$i = 3;  // first data column
			foreach ($field_list as $f) {
				$avg = $total_at_field[$f] / $num_teams;
				// we'll consider more than 1.5 game from avg too much
				if ($avg - 1.5 > $row[$i]['data'] || $row[$i]['data'] > $avg + 1.5) {
					$row[$i]['data'] = "<b><font color='red'>". ($row[$i]['data']) ."</font></b>";
				}
				$i++; // move to next column in cached row
			}
			$rows[] = $row;
		}

		// output totals line
		$row = array(array('data' => "Total games:", 'colspan' => 3, 'align' => 'right'));
		foreach ($field_list as $f) {
			if ($total_at_field[$f]) {
				$row[] = array('data'=> $total_at_field[$f], 'align'=>'center');
			} else {
				$row[] = array('data'=> "0", 'align'=>'center');
			}
		}
		$rows[] = $row;

		$row = array(array('data' => "Average:", 'colspan' => 3, 'align' => 'right'));
		foreach ($field_list as $f) {
			if ($total_at_field[$f]) {
				$row[] = array('data'=> sprintf('%.1f', $total_at_field[$f] / $num_teams), 'align'=>'center');
			} else {
				$row[] = array('data'=> "0", 'align'=>'center');
			}
		}
		$rows[] = $row;


		//$output .= table($header, $rows);
		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";

		return form($output);
	}
}

?>
