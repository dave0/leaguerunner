<?xml version="1.0" encoding="ISO-8859-1"?>
<?xml-stylesheet type="text/xsl" href="{lr_url path="data/ocuasportsml2html.xsl"}" ?>
<sports-content>
  <sports-metadata>
    <sports-title>{ $league->fullname | escape }</sports-title>
  </sports-metadata>
  {if $need_standings}
  <standing content-label="{$league->fullname | escape }">
    <standing-metadata date-coverage-type="season-regular" date-coverage-value="{$league->year}" />
    {foreach from=$teams item=team}
    <team>
        <team-metadata>
            <name full="{$team->name|escape}" />
        </team-metadata>
        <team-stats standing-points="{$team->standing_points}">
            <outcome-totals wins="{$team->win}" losses="{$team->loss}" ties="{$team->tie}" points-scored-for="{$team->points_for}" points-scored-against="{$team->points_against}" />
            <team-stats-ultimate>
	    {if $league->display_numeric_sotg}
                <stats-ultimate-spirit value="{$team->numeric_sotg}" />
	    {/if}
                <stats-ultimate-miscellaneous defaults="{$team->defaults_against}" plusminus="{$team->plusminus}" />
            </team-stats-ultimate>
            <rank competition-scope="tier" value="{$team->rank}" />
        </team-stats>
    </team>
    {/foreach}
  </standing>
  {/if}
  {if $need_schedule}
  <schedule content-label="{$league->fullname|escape}">
    <schedule-metadata team-coverage-type="multi-team" date-coverage-type='season-regular' date-coverage-value="{$league->year}" />
    {foreach from=$games item=game}
    <sports-event>
	<event-metadata
		site-name="{$game->field_code}"
		site-id="{$game->field_code}"
		start-date-time="{$game->timestamp|date_format:"%Y-%m-%dT%H:%M"}"
		event-status="{$game->event_status}"
	/>
	<team>
       		<team-metadata alignment="home">
        	 	<name full="{$game->home_name|escape}" />
  	     	</team-metadata>
    	   	<team-stats score="{$game->home_score}" />
	</team>
	<team>
       		<team-metadata alignment="away">
           		<name full="{$game->away_name|escape}" />
       		</team-metadata>
        	<team-stats score="{ $game->away_score }" />
	</team>
	</sports-event>
    {/foreach}
  </schedule>
  {/if}
</sports-content>
