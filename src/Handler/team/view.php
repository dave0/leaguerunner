<?php
require_once('Handler/TeamHandler.php');

class team_view extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view', $this->team->team_id);
	}

	function process ()
	{
		global $lr_session, $dbh;

		// Team names might have HTML in them, so we need to nuke it.
		$team_name = check_form($this->team->name, ENT_NOQUOTES);
		$this->setLocation(array(
			$team_name => "team/view/" . $this->team->team_id,
			"View Team" => 0));

		// Now build up team data
		$rows = array();
		if($this->team->website) {
			$rows[] = array("Website:", l($this->team->website, $this->team->website));
		}
		$rows[] = array("Shirt Colour:", check_form($this->team->shirt_colour, ENT_NOQUOTES));
		$rows[] = array("League/Tier:", l($this->team->league_name, "league/view/" . $this->team->league_id));

		if($this->team->home_field) {
			$field = field_load(array('fid' => $this->team->home_field));
			$rows[] = array("Home Field:", l($field->fullname,"field/view/$field->fid"));
		}

		if($this->team->region_preference) {
			$rows[] = array("Region preference:", $this->team->region_preference);
		}

		$rows[] = array("Team Status:", $this->team->status);

		/* Spence Balancing Factor:
		 * Average of all score differentials.  Lower SBF means more
		 * evenly-matched games.
		 */
		$teamSBF = $this->team->calculate_sbf( );
		if( $teamSBF ) {
			$league = league_load( array('league_id' => $this->team->league_id) );
			$leagueSBF = $league->calculate_sbf();
			if( $leagueSBF ) {
				$teamSBF .= " (league $leagueSBF)";
			}
			$rows[] = array("Team SBF:", $teamSBF);
		}
		$rows[] = array("Rating:", $this->team->rating);

		$teamdata = "<div class='pairtable'>" . table(null, $rows) . "</div>";

		$header = array( 'Name', 'Position', 'Gender','Rating' );
		if( $lr_session->has_permission('team','player shirts', $this->team->team_id) ) {
			array_push($header, 'Shirt Size');
		}
		array_push($header, 'Date Joined');
		$rows = array();
		$totalSkill = 0;
		$skillCount = 0;
		$rosterCount = 0;
		$rosterPositions = getRosterPositions();

		$this->team->get_roster();
		foreach ($this->team->roster as $player) {
			/*
			 * Now check for conflicts.  Players who are subs get
			 * conflicts ignored, but not others.
			 *
			 * TODO: This is time-consuming and resource-inefficient.
			 * TODO: Turn this into $team->check_roster_conflicts()
			 */
			$c_sth = $dbh->prepare("SELECT COUNT(*) from
					league l, leagueteams t, teamroster r
				WHERE
					l.year = ? AND l.season = ? AND l.day = ?
					AND r.status != 'substitute'
					AND l.schedule_type != 'none'
					AND l.league_id = t.league_id
					AND l.status = 'open'
					AND t.team_id = r.team_id
					AND r.player_id = ?");
			$c_sth->execute(array(
				$this->team->league_year,
				$this->team->league_season,
				$this->team->league_day,
				$player->id
			));

			if($c_sth->fetchColumn() > 1) {
				$conflictText = "(roster conflict)";
			} else {
				$conflictText = null;
			}

			if($player->player_status == "inactive" ) {
				if($conflictText) {
					$conflictText .= "<br />(account inactive)";
				} else {
					$conflictText .= "(account inactive)";
				}
			}

			$player_name = l($player->fullname, "person/view/$player->id");
			if( $conflictText ) {
				$player_name .= "<div class='roster_conflict'>$conflictText</div>";
			}

			if($lr_session->has_permission('team','player status', $this->team->team_id, $player->id) ) {
				$roster_info = l($rosterPositions[$player->status], "team/roster/" . $this->team->team_id . "/$player->id");
			} else {
				$roster_info = $rosterPositions[$player->status];
			}
			if( $player->status == 'captain' ||
				$player->status == 'assistant' ||
				$player->status == 'player'
			) {
				++$rosterCount;
			}

			$row = array(
				$player_name,
				$roster_info,
				$player->gender,
				$player->skill_level
			);
			if( $lr_session->has_permission('team','player shirts', $this->team->team_id) ) {
				array_push($row, $player->shirtsize);
			}
			array_push($row, $player->date_joined);
			$rows[] = $row;

			$totalSkill += $player->skill_level;
			if ($player->skill_level) {
				$skillCount ++;
			}
		}

		if($skillCount > 0) {
			$avgSkill = sprintf("%.2f", ($totalSkill / $skillCount));
		} else {
			$avgSkill = 'N/A';
		}
		$rows[] = array(
			array('data' => 'Average Skill Rating', 'colspan' => 3),
			$avgSkill
		);

		$rosterdata = "<div class='listtable'>" . table($header, $rows) . "</div>";

		if( variable_get('narrow_display', '0') ) {
			$rc = $teamdata . '<p />' . $rosterdata;
		} else {
			$rc = table(null, array(
				array( $teamdata, $rosterdata ),
			));
		}

		if( $rosterCount < 12 && $lr_session->is_captain_of($this->team->team_id) && $this->team->roster_deadline > 0 ) {
			$rc .= "<p><p class='error'>Your team currently has only $rosterCount full-time players listed. Your team roster must be completed (minimum of 12 rostered players) by the team roster deadline (" . strftime ('%Y-%m-%d', $this->team->roster_deadline) . "), and all team members must be listed as a 'regular player'.  If an individual has not replied promptly to your request to join, we suggest that you contact them to remind them to respond.</p>";
		}

		return $rc;
	}
}
?>
