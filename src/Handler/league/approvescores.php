<?php
require_once('Handler/LeagueHandler.php');
class league_approvescores extends LeagueHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','approve scores',$this->league->league_id);
	}

	function process ()
	{
		global $CONFIG, $dbh;

		$this->title = "Approve Scores";

		$local_adjust_secs = $CONFIG['localization']['tz_adjust'] * 60;

		/* Fetch games in need of verification */
		$game_sth = $dbh->prepare( "SELECT DISTINCT
			se.game_id,
			UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) + ($local_adjust_secs) as timestamp,
			s.home_team AS home_id,
			h.name AS home_name,
			s.away_team AS away_id,
			a.name AS away_name
			FROM
				schedule s,
				score_entry se,
				gameslot g,
				team h,
				team a
			WHERE
				s.league_id = ?
				AND (g.game_date < CURDATE()
					OR (
						g.game_date = CURDATE()
						AND g.game_start < CURTIME()
					)
				)
				AND se.game_id = s.game_id
				AND g.game_id = s.game_id
				AND h.team_id = s.home_team
				AND a.team_id = s.away_team
			ORDER BY
				timestamp
		");
		$game_sth->execute( array($this->league->league_id) );

		$header = array(
			'Game Date',
			array('data' => 'Home Team Submission', 'colspan' => 2),
			array('data' => 'Away Team Submission', 'colspan' => 2),
			'&nbsp;'
		);
		$rows = array();

		$se_sth = $dbh->prepare('SELECT score_for, score_against FROM score_entry WHERE team_id = ? AND game_id = ?');

		$time_format = '%A %B %d %Y, %H%Mh';

		while($game = $game_sth->fetchObject('Game') ) {
			$rows[] = array(
				array('data' => strftime($time_format, $game->timestamp),'rowspan' => 3),
				array('data' => $game->home_name, 'colspan' => 2),
				array('data' => $game->away_name, 'colspan' => 2),
				array('data' => l("approve score", "game/approve/$game->game_id"))
			);


			$se_sth->execute( array( $game->home_id, $game->game_id ) );
			$home = $se_sth->fetch(PDO::FETCH_ASSOC);

			if(!$home) {
				$home = array(
					'score_for' => 'not entered',
					'score_against' => 'not entered',
				);
			}

			$se_sth->execute( array( $game->away_id, $game->game_id ) );
			$away = $se_sth->fetch(PDO::FETCH_ASSOC);
			if(!$away) {
				$away = array(
					'score_for' => 'not entered',
					'score_against' => 'not entered',
				);
			}

			$list = player_rfc2822_address_list($game->get_captains(), true);
			$rows[] = array(
				"Home Score:", $home['score_for'], "Home Score:", $away['score_against'],
				l('email captains', "mailto:$list")
			);

			$rows[] = array(
				"Away Score:", $home['score_against'], "Away Score:", $away['score_for'], ''
			);

			$rows[] = array( '&nbsp;' );

		}

		$output = para("The following games have not been finalized.");
		$output .= "<div class='listtable'>" . table( $header, $rows ) . "</div>";
		return $output;
	}
}

?>
