<?php
require_once('Handler/StatisticsHandler.php');
class statistics_team extends StatisticsHandler
{
	function process ()
	{
		global $dbh;

		$this->title = 'Team Statistics';

		$rows = array();

		$current_season = variable_get('current_season', 'Summer');

		$sth = $dbh->prepare('SELECT COUNT(*) FROM team');
		$sth->execute();
		$rows[] = array("Number of teams (total):", $sth->fetchColumn() );

		$sth = $dbh->prepare('SELECT l.season, COUNT(*) FROM leagueteams t, league l WHERE t.league_id = l.league_id AND l.status = "open" GROUP BY l.season');
		$sth->execute();
		$sub_table = array();
		while($row = $sth->fetch(PDO::FETCH_ASSOC) ) {
			$sub_table[] = $row;
		}
		$rows[] = array("Teams by season:", table(null, $sub_table));

		$sth = $dbh->prepare("SELECT t.team_id,t.name, COUNT(r.player_id) as size 
		FROM teamroster r, league l, leagueteams lt
		LEFT JOIN team t ON (t.team_id = r.team_id) 
		WHERE
			lt.team_id = r.team_id
			AND l.league_id = lt.league_id
					AND l.status = 'open'
			AND l.schedule_type != 'none'
					AND l.season = ?
			AND (r.status = 'player' OR r.status = 'captain' OR r.status = 'assistant')
		GROUP BY t.team_id
		HAVING size < 12
		ORDER BY size desc, t.name");
		$sth->execute( array($current_season) );
		$sub_table = array();
		$sub_sth = $dbh->prepare("SELECT COUNT(*) FROM teamroster r WHERE r.team_id = ? AND r.status = 'substitute'");
		while($row = $sth->fetch() ) {
			if( $row['size'] < 12 ) {
				$sub_sth->execute( array($row['team_id']) );
				$substitutes = $sub_sth->fetchColumn();
				if( ($row['size'] + floor($substitutes / 3)) < 12 ) {
					$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), ($row['size'] + floor($substitutes / 3)));
				}
			}
		}
		$rows[] = array("$current_season teams with too few players:", table(null, $sub_table));

		$sth = $dbh->prepare("SELECT t.team_id, t.name, t.rating
			FROM team t, league l, leagueteams lt
			WHERE
				lt.team_id = t.team_id
				AND l.league_id = lt.league_id
				AND l.status = 'open'
				AND l.schedule_type != 'none'
				AND l.season = ?
			ORDER BY t.rating DESC LIMIT 10");
		$sth->execute( array( $current_season ) );
		$sub_table = array();
		while($row = $sth->fetch() ) {
			$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), $row['rating']);
		}
		$rows[] = array("Top-rated $current_season teams:", table(null, $sub_table));

		$sth = $dbh->prepare("SELECT t.team_id, t.name, t.rating
			FROM team t, league l, leagueteams lt
			WHERE
				lt.team_id = t.team_id
				AND l.league_id = lt.league_id
				AND l.status = 'open'
				AND l.schedule_type != 'none'
				AND l.season = ?
			ORDER BY t.rating ASC LIMIT 10");
		$sth->execute( array($current_season) );
		$sub_table = array();
		while($row = $sth->fetch() ) {
			$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), $row['rating']);
		}
		$rows[] = array("Lowest-rated $current_season teams:", table(null, $sub_table));

		$sth = $dbh->prepare("SELECT COUNT(*) AS num,
				IF(s.status = 'home_default',s.home_team,s.away_team) AS team_id
			FROM schedule s, league l
			WHERE
				s.league_id = l.league_id
				AND l.status = 'open'
				AND l.season = ?
				AND (s.status = 'home_default' OR s.status = 'away_default')
			GROUP BY team_id ORDER BY num DESC");
		$sth->execute( array($current_season) );
		$sub_table = array();
		while($row = $sth->fetch()) {
			$team = Team::load( array('team_id' => $row['team_id']) );
			$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['num']);
		}
		$rows[] = array("Top defaulting $current_season teams:", table(null, $sub_table));

		$sth = $dbh->prepare("SELECT COUNT(*) AS num,
				IF(s.approved_by = -3,s.home_team,s.away_team) AS team_id
			FROM schedule s, league l
			WHERE
				s.league_id = l.league_id
				AND l.status = 'open'
				AND l.season = ?
				AND (s.approved_by = -2 OR s.approved_by = -3)
			GROUP BY team_id ORDER BY num DESC");
		$sth->execute( array($current_season) );
		$sub_table = array();
		while($row = $sth->fetch() ) {
			$team = Team::load( array('team_id' => $row['team_id']) );
			$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['num']);
		}
		$rows[] = array("Top non-score-submitting $current_season teams:", table(null, $sub_table));

		$sotg_query = "SELECT
				ROUND( AVG( COALESCE(
					s.entered_sotg,
					s.score_entry_penalty + s.timeliness + s.rules_knowledge + s.sportsmanship + s.rating_overall )
				), 2) AS avgspirit,
				s.tid AS team_id
			FROM league l, leagueteams lt, spirit_entry s
			WHERE
				lt.league_id = l.league_id
				AND lt.team_id = s.tid
				AND l.status = 'open'
				AND l.season = ?
			GROUP BY team_id";

		$sth = $dbh->prepare( $sotg_query . " ORDER BY avgspirit DESC LIMIT 10");
		$sth->execute ( array ($current_season) );
		$sub_table = array();
		while($row = $sth->fetch() ) {
			$team = Team::load( array('team_id' => $row['team_id']) );
			$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['avgspirit']);
		}
		$rows[] = array("Best spirited $current_season teams:", table(null, $sub_table));

		$sth = $dbh->prepare( $sotg_query . " ORDER BY avgspirit ASC LIMIT 10");
		$sth->execute ( array ($current_season) );
		$sub_table = array();
		while($row = $sth->fetch() ) {
			$team = Team::load( array('team_id' => $row['team_id']) );
			$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['avgspirit']);
		}
		$rows[] = array("Lowest spirited $current_season teams:", table(null, $sub_table));

		$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
		return form_group("Team Statistics", $output);
	}
}
?>
