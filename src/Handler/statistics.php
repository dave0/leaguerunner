<?php
class statistics extends Handler
{
	function __construct ( $type )
	{
		if ( ! module_hook($type,'statistics') ) {
			error_exit('Operation not found');
		}
		$this->type = $type;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->is_admin();
	}

	function process ()
	{
		$this->title = ucfirst($this->type) . ' Statistics';
		$this->setLocation(array($this->title => 0));
		return module_invoke($this->type, 'statistics');
	}
}

function person_statistics ( )
{
	global $dbh;
	$rows = array();

	$sth = $dbh->prepare('SELECT status, COUNT(*) FROM person GROUP BY status');
	$sth->execute();

	$sub_table = array();
	$sum = 0;
	while($row = $sth->fetch(PDO::FETCH_NUM)) {
		$sub_table[] = $row;
		$sum += $row[1];
	}
	$sub_table[] = array('Total', $sum);
	$rows[] = array('Players by account status:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT class, COUNT(*) FROM person GROUP BY class');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by account class:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT gender, COUNT(*) FROM person GROUP BY gender');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by gender:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT FLOOR((YEAR(NOW()) - YEAR(birthdate)) / 5) * 5 as age_bucket, COUNT(*) AS count FROM person GROUP BY age_bucket');
	$sth->execute();
	$sub_table = array();
	while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
		$sub_table[] = array($row['age_bucket'] . ' to ' . ($row['age_bucket'] + 4), $row['count']);
	}
	$rows[] = array('Players by age:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT addr_city, COUNT(*) AS num FROM person GROUP BY addr_city HAVING num > 2 ORDER BY num DESC');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by city:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT skill_level, COUNT(*) FROM person GROUP BY skill_level');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by skill level:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT year_started, COUNT(*) FROM person GROUP BY year_started');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by starting year:', table(null, $sub_table));

	if (variable_get('dog_questions', 1)) {
		$sth = $dbh->prepare("SELECT COUNT(*) FROM person where has_dog = 'Y'");
		$sth->execute();
		$rows[] = array('Players with dogs :', $sth->fetchColumn());
	}

	$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
	return form_group('Player Statistics', $output);
}

function team_statistics ( )
{
	global $dbh;
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
		$team = team_load( array('team_id' => $row['team_id']) );
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
		$team = team_load( array('team_id' => $row['team_id']) );
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
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['avgspirit']);
	}
	$rows[] = array("Best spirited $current_season teams:", table(null, $sub_table));

	$sth = $dbh->prepare( $sotg_query . " ORDER BY avgspirit ASC LIMIT 10");
	$sth->execute ( array ($current_season) );
	$sub_table = array();
	while($row = $sth->fetch() ) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['avgspirit']);
	}
	$rows[] = array("Lowest spirited $current_season teams:", table(null, $sub_table));

	$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
	return form_group("Team Statistics", $output);
}



?>
