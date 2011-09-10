BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Leaguerunner//Team Schedule//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:{$team->name|utf8} schedule from {$short_league_name}
BEGIN:VTIMEZONE
TZID:US/Eastern
LAST-MODIFIED:20070101T000000Z
BEGIN:DAYLIGHT
DTSTART:20070301T020000
RRULE:FREQ=YEARLY;BYDAY=2SU;BYMONTH=3
TZOFFSETFROM:-0500
TZOFFSETTO:-0400
TZNAME:EDT
END:DAYLIGHT
BEGIN:STANDARD
DTSTART:20071101T020000
RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=11
TZOFFSETFROM:-0400
TZOFFSETTO:-0500
TZNAME:EST
END:STANDARD
END:VTIMEZONE
{foreach from=$games item=game}
BEGIN:VEVENT
UID:{$game->game_id}-{$domain_url}
DTSTAMP:{$now}
{* TODO: created and last-modified are bullshit *}
CREATED:{$now}
LAST-MODIFIED:{$now}
DTSTART;TZID=US/Eastern:{$game->iso8601_local_game_start()}
DTEND;TZID=US/Eastern:{$game->iso8601_local_game_end()}
{*
	TODO: possible bug; Google Calendar tries to generate a Google Maps
	link from the data in LOCATION, which will always be wrong.  Is this
	Google being stupid, or are we violating the spec by not using
	something address-ish in that field

	TODO: What's with the X- fields?
*}
LOCATION:{$game->field->fullname} ({$game->field_code})
X-LOCATION-URL:{lr_url path="field/view/`$game->fid`}
SUMMARY:{$game->home_name|utf8} (home) vs. {$game->away_name|utf8} (away)
DESCRIPTION:Game {$game->game_id}: {$game->home_name|utf8} (home) vs. {$game->away_name|utf8} (away) at {$game->field->fullname} ({$game->field_code}) on {$game->timestamp|date_format:"%a %b %d %Y"} {$game->game_start} to {$game->display_game_end()}{if $game->opponent->shirt_colour}(they wear {$game->opponent->shirt_colour|utf8})
{else}
{/if}
X-OPPONENT-COLOUR: {$game->opponent->shirt_colour|utf8}
END:VEVENT
{/foreach}
END:VCALENDAR
