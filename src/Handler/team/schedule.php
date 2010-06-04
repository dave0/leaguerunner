<?php
require_once('Handler/TeamHandler.php');
class team_schedule extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view schedule', $this->team->team_id);
	}

	function process ()
	{
		global $lr_session, $CONFIG;
		$this->title = "Schedule";
		$this->setLocation(array(
			$this->team->name => "team/view/" . $this->team->team_id,
			$this->title => 0));

		/*
		 * Grab schedule info
		 */
		$games = game_load_many( array( 'either_team' => $this->team->team_id, 'published' => 1, '_order' => 'g.game_date,g.game_start,g.game_id') );

		if( !count($games) ) {
			error_exit('This team does not yet have any games scheduled');
		}

		$header = array(
			"Game",
			"Date",
			"Start",
			"End",
			"Opponent",
			array('data' => "Location",'colspan' => 2),
			array('data' => "Score",'colspan' => 2)
		);
		$rows = array();

		$empty_row_added = 0;
		while(list(,$game) = each($games)) {
			$space = '&nbsp;';
			$dash = '-';
			if($game->home_id == $this->team->team_id) {
				$opponent_id = $game->away_id;
				$opponent_name = $game->away_name;
				$home_away = '(home)';
			} else {
				$opponent_id = $game->home_id;
				$opponent_name = $game->home_name;
				$home_away = '(away)';
			}

			if ($opponent_name == "") {
				$opponent_name = "(to be determined)";
			} else {
				$opponent_name = l($opponent_name, "team/view/$opponent_id");
			}

			$game_score = $space;
			$score_type = $space;

			if($game->is_finalized()) {
				/* Already entered */
				$score_type = '(accepted final)';
				if($game->home_id == $this->team->team_id) {
					$game_score = "$game->home_score - $game->away_score";
				} else {
					$game_score = "$game->away_score - $game->home_score";
				}
			} else {
				/* Not finalized yet, so we will either:
				 *   - display entered score if present
				 *   - display score entry link if game date has passed
				 *   - display a blank otherwise
				 */
				$entered = $game->get_score_entry( $this->team->team_id );
				if($entered) {
					$score_type = '(unofficial, waiting for opponent)';
					$game_score = "$entered->score_for - $entered->score_against";
				} else if($lr_session->has_permission('game','submit score', $game, $this->team)
					&& ($game->timestamp < time()) ) {
						$score_type = l("submit score", "game/submitscore/$game->game_id/" . $this->team->team_id);
				} else {
					$score_type = "&nbsp;";
				}
			}
			if($game->status == 'home_default' || $game->status == 'away_default') {
				$score_type .= " (default)";
			}

			$field = field_load(array('fid' => $game->fid));
			$rows[] = array(
				l($game->game_id, "game/view/$game->game_id"),
				strftime('%a %b %d %Y', $game->timestamp),
				$game->game_start,
				$game->display_game_end(),
				$opponent_name,
				l($game->field_code, "field/view/$game->fid", array('title' => $field->fullname)),
				$home_away,
				$game_score,
				$score_type
			);
		}
		// add another row of dashes when you're done.
		$rows[] = array($dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash);

		// add iCal link
		$ical_url = url("team/ical/".$this->team->team_id);
		$icon_url = $CONFIG['paths']['base_url'] . '/image/icons';
		return "<div class='schedule'>" . table($header,$rows, array('alternate-colours' => true) ) . "</div>"
		  . para("Get your team schedule in <a href=\"$ical_url/team.ics\"><img style=\"display: inline\" src=\"$icon_url/ical.gif\" alt=\"iCal\" /></a> format");
	}
}
?>
