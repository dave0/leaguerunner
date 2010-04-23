<?php
require_once('Handler/LeagueHandler.php');
class schedule_view extends LeagueHandler
{
	function has_permission ()
	{
		return true;
	}

	function process ()
	{
		global $lr_session;
		$this->title = "View Schedule";

		$this->setLocation(array(
			$this->league->fullname => "league/view/".$this->league->league_id,
			$this->title => 0));

		/*
		 * Now, grab the schedule
		 */
		$sth = game_query ( array( 'league_id' => $this->league->league_id, '_order' => 'g.game_date, g.game_start, field_code') );

		$prevDayId = -1;
		$rows = array();
		while( $game = $sth->fetchObject('Game') ) {

			if( ! ($game->published || $lr_session->has_permission('league','edit schedule', $this->league->league_id) ) ) {
				continue;
			}

			if( $game->day_id != $prevDayId ) {
				$rows[] = $this->schedule_heading( 
					strftime('%a %b %d %Y', $game->timestamp),
					$lr_session->has_permission('league','edit schedule', $this->league->league_id),
					$game->day_id, $this->league->league_id );
				$rows[] = $this->schedule_subheading( );
			}

			$rows[] = $this->schedule_render_viewable($game);
			$prevDayId = $game->day_id;
		}
		$output .= "<div class='schedule'>" . table(null, $rows) . "</div>";
		return form($output);
	}

	function schedule_heading( $date, $canEdit = false, $dayId = 0, $leagueId = 0 )
	{
		$header = array(
			array('data' => $date, 'colspan' => ($canEdit ? 5 : 7), 'class' => 'gamedate')
		);

		if( $canEdit && $dayId ) {
			$day_links = array(
				l("fields", "league/slots/$leagueId/".strftime('%Y/%m/%d', $dayId)),
				l("edit week", "schedule/edit/$leagueId/$dayId"),
				l("reschedule", "game/reschedule/$leagueId/$dayId")
			);
			$header[] = array(
							'data' => theme_links($day_links) ,
							'class' => 'gamedate', 'colspan' => 2,
							'style' => 'text-align:right;'
			);
		}
		return $header;
	}

	function schedule_subheading( )
	{
		return array(
			array('data' => 'Game', 'class' => 'column-heading'),
			array('data' => 'Time/Place', 'colspan' => 2, 'class' => 'column-heading'),
			array('data' => 'Home', 'colspan' => 2, 'class' => 'column-heading'),
			array('data' => 'Away', 'colspan' => 2, 'class' => 'column-heading'),
		);
	}

	function schedule_render_viewable( &$game )
	{
		global $lr_session;
		if($game->home_name) {
			$short = display_short_name($game->home_name);
			$attr = array();
			if ($short != $game->home_name)
			{
				$attr['title'] = $game->home_name;
			}

			$homeTeam = l($short, "team/view/" . $game->home_id, $attr);
		} else {
			$homeTeam = "Not yet scheduled.";
		}
		if($game->away_name) {
			$short = display_short_name($game->away_name);
			$attr = array();
			if ($short != $game->away_name)
			{
				$attr['title'] = $game->away_name;
			}

			$awayTeam = l($short, "team/view/" . $game->away_id, $attr);
		} else {
			$awayTeam = "Not yet scheduled.";
		}

		$gameRow = array(
			l($game->game_id, 'game/view/' . $game->game_id),
			"$game->game_start - " . $game->display_game_end(),
			l( $game->field_code, "field/view/$game->fid"),
		);

		// If game is unpublished, hack in a yellow background
		if( ! $game->published ) {
			$gameRow[0] = "(unpublished) $gameRow[0]";
			for($i=0; $i < count($gameRow); $i++) {
				$gameRow[$i] = array( 'data' => "$gameRow[$i]", 'style' => 'background-color: yellow' );
			}
		}

		if($game->status == 'home_default') {
			$gameRow[] = array('data' => $homeTeam, 'style' => 'background-color: red');
			$gameRow[] = array('data' => $game->home_score . '(dfl)', 'style' => 'background-color: red');
		} else { 
			$gameRow[] = $homeTeam;
			$gameRow[] = $game->home_score;
		}

		if ($game->status == 'away_default') {
			$gameRow[] = array('data' => $awayTeam, 'style' => 'background-color: red');
			$gameRow[] = array('data' => $game->away_score . '(dfl)', 'style' => 'background-color: red');
		} else {
			$gameRow[] = $awayTeam;
			$gameRow[] = $game->away_score;
		}

		return $gameRow;
	}
}

?>
