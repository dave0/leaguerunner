BEGIN:VCALENDAR
PRODID:-//Leaguerunner//Team Schedule//EN
VERSION:2.0
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:{$team->name|utf8} schedule from {$short_league_name}
{* TODO: add VTIMEZONE group *}

{foreach from=$games item=game}
BEGIN:VEVENT
UID:{$game->game_id}@{$domain_url}
DTSTAMP:{$now}
{* TODO: created and last-modified are bullshit *}
CREATED:20090101T000000Z
LAST-MODIFIED:20090101T000000Z
{* TODO: do we need tzid in here? *}
DTSTART;TZID={$timezone}:{$game->timestamp|date_format:"%Y%m%d"}T{$game->game_start|replace:':':''}00
DTEND;TZID={$timezone}:{$game->timestamp|date_format:"%Y%m%d"}T{$game->display_game_end()|replace:':':''}00
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
STATUS:CONFIRMED
TRANSP:OPAQUE
END:VEVENT
{/foreach}

END:VCALENDAR
