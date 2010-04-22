<?php

require_once('Handler/TeamHandler.php');

/**
 * RMK April 2008
 * Team schedule as ical handler
 */
class team_ical extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view schedule', $this->team->team_id);
	}

	// Does not return, as we don't want normal LR theme output
	// Will output in target format (ical)
	function process ()
	{
		global $CONFIG;
		$timezone = 'TZID=' .  $CONFIG['localization']['local_tz'];

		$my_team = $this->team->name;

		/*
		 * Grab schedule info
		 */
		$games = game_load_many( array( 'either_team' => $this->team->team_id, 'published' => 1, '_order' => 'g.game_date DESC,g.game_start,g.game_id') );

		// We'll be outputting an ical
		header('Content-type: text/calendar; charset=UTF-8');
		// Prevent caching
		header("Cache-Control: no-cache, must-revalidate");

		// get league name for iCalendar name
		$short_league_name = variable_get('app_org_short_name', 'League');

		// get domain URL for signing games
		$arr = split('@',variable_get('app_admin_email',"@$short_league_name"));
		$domain_url = $arr[1];

		// ical header
		print utf8_encode("BEGIN:VCALENDAR
PRODID:-//Leaguerunner//Team Schedule//EN
VERSION:2.0
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:$my_team schedule from $short_league_name
");

		// TODO: add VTIMEZONE group


		while(list(,$game) = each($games)) {
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
				$opponent_colour = "";
			} else {
				// look up opponent's shirt colour
				$opponent_team = team_load( array('team_id' => $opponent_id) );
				$opponent_colour = $opponent_team->shirt_colour;
			}

			// encode game start and end times
			$game_date = strftime('%Y%m%d', $game->timestamp); // from date type
			$game_start = $game_date . 'T'
			  . join(explode(':', $game->game_start)) // from 'hh:mm' string
			  . '00';
			$game_end = $game_date . 'T'
					. join(explode(':', $game->display_game_end()))  // from 'hh:mm' string
					. '00';

			// date stamp this file
			$now = gmstrftime('%Y%m%dT%H%M%SZ'); // MUST be in UTC

			// generate field url
			$field_url = url("field/view/$game->fid");

			// look up field's full name
			$field = field_load(array('fid' => $game->fid));

			// output game
			// TODO: need to track when games are created/modified
			// TODO: possible bug; Google Calendar tries to
			// generate a Google Maps link from the data in
			// LOCATION, which will always be wrong.  Is this
			// Google being stupid, or are we violating the spec by
			// not using something address-ish in that field
			// TODO: What's with the X- fields?
			print utf8_encode("BEGIN:VEVENT
UID:$game->game_id@$domain_url
DTSTAMP:$now
CREATED:20090101T000000Z
LAST-MODIFIED:20090101T000000Z
DTSTART;$timezone:$game_start
DTEND;$timezone:$game_end
LOCATION:$field->fullname ($game->field_code)
X-LOCATION-URL:$field_url
SUMMARY:$my_team vs. $opponent_name
DESCRIPTION:Game $game->game_id: $my_team vs. $opponent_name at $field->fullname ($game->field_code) on ".strftime('%a %b %d %Y', $game->timestamp)." $game->game_start to " . $game->display_game_end()
. ($opponent_colour ? " (they wear $opponent_colour)" : "") . "
X-OPPONENT-COLOUR:$opponent_colour
STATUS:CONFIRMED
TRANSP:OPAQUE
END:VEVENT
");

		}

		print "END:VCALENDAR\n";
		exit; // don't return, as we don't want the HTML printed
	}
}
?>
