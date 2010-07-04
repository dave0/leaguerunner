<?php

/**
 * return HTML suitable for an "Upcoming Games" box
 */
class api_1_my_upcoming extends Handler
{
	function has_permission ()
	{
		/* Everyone can view this box, but only active sessions get useful info */
		return true;
	}

	function process ()
	{
		$output = $this->box_output();
		print <<<END_HTML
<html><body><div id="lr_upcoming">
$output
</div></body></html>
END_HTML;
		exit; // Don't return -- we don't want themed HTML wrapped around our output
	}

	function box_output ()
	{
		global $lr_session, $dbh;

		if( ! $lr_session->is_loaded() ) {
			// No session
			# TODO: leave blank instead?
			return "No leaguerunner session";
		}

		if( ! $lr_session->user->status == 'active' ) {
			# TODO activation URL
			return "Please activate your account";
		}

		$output = '';

		# TODO: write fetch_upcoming_games, merge with game_splash() code
		$games = $lr_session->user->fetch_upcoming_games(3);

		if (count($games)) {

			$output .= "<ul>";

			foreach ($games as $game) {
				$minutesleft = ( $game->timestamp - time()) / 60;

				// Format the minutes left text
				if ( $minutesleft < 0  ) {
					$timeleft = 'already played';
				} else if ( $minutesleft < 90 ) {
					$timeleft = round($minutesleft) . " " . 'minutes';
				} else if ( $minutesleft < (2*24*60) ) {
					$timeleft = round($minutesleft/60) . " " . 'hours';
				} else {
					$timeleft = round($minutesleft/(24*60)) . " " . 'days';
				}

				$tmpDate = '';
				$tmpDate = date("F dS: g:i a", $game->timestamp) . "<br>(" . $timeleft . ")";
				$output .= "<li><b>$tmpDate</b><br><a href=\"/leaguerunner/game/view/$game->game_id\"><nobr>$game->home_name</nobr> vs. <nobr>$game->away_name</nobr></a> <nobr>at <a href=\"/leaguerunner/field/view/$game->fid\">$game->field_code</a></nobr>";
			}

			$output .= "</ul>";

			// If user has no games and is active simply indicate that they have no games
		} else {
			$output .= "No games scheduled";
		}

		return $output;
	}
}
?>
