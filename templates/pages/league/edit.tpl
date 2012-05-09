{include file='header.tpl'}
<h1>{$title}</h1>
<script type="text/javascript">
{literal}
	$(document).ready(function() {
		$('#datepicker').datepicker({
			changeMonth: true,
			dateFormat: 'yy-mm-dd'
		});
	});
{/literal}
</script>
<form method="POST">
{fill_form_values}
{if $formErrors}
<p>
The following errors were encountered in your submission:
</p>
<ul class="error"></ul>
{/if}

<label for="edit[name]">League Name</label>
	<input id="edit[name]" type="text" maxlength="200" name="edit[name]" size="35" value="" />
	<div class="description">The full name of the league.  Tier numbering will be automatically appended.</div>

<label for="edit[status]">Status</label>
	{html_options id="edit[status]" name="edit[status]" options=$status}<div class="description">Teams in closed leagues are locked and can be viewed only in historical modes</div>

<label for="edit[season]">Season</label>
	{html_options name="edit[season]" options=$seasons}<div class="description">Season of play for this league. Choose 'Ongoing' for administrative groupings and comp teams.</div>

{if $allevents}
<label for="edit[events][]">Registration Events</label>
	<select name="edit[events][]" multiple >{html_options options=$allevents}</select>
	<div class="description">Select all required Registration Events a player must sign up for to be eligible to play in this league.</div>
{/if}

<label for="edit[day][]">Day(s) of play</label>
	<select name="edit[day][]" multiple >{html_options options=$days}</select>
	<div class="description">Day, or days, on which this league will play.</div>

<label for="edit[roster_deadline]">Roster deadline</label>
	<input type="text" maxlength="15" name="edit[roster_deadline]" size="15" value="" id="datepicker" />
	<div class="description">The date after which teams are no longer allowed to edit their rosters.</div>

<label for="edit[min_roster_size]">Minimum Roster size</label>
	<input type="text" maxlength="5" name="edit[min_roster_size]" size="5" value="12"/>
	<div class="description">The minimum number of players required for a team to be considered valid.</div>

<label for="edit[tier]">Tier</label>
	{html_options name="edit[tier]" options=$tiers}
	<div class="description">Tier number.  Choose 0 to not have numbered tiers.</div>


<label for="edit[ratio]">Gender Ratio</label>
	{html_options name="edit[ratio]" options=$ratios}
	<div class="description">Gender format for the league.</div>

<label for="edit[current_round]">Current Round</label>
	{html_options name="edit[current_round]" options=$rounds}
	<div class="description">New games will be scheduled in this round by default.</div>

<label for="edit[schedule_type]">Scheduling Type</label>
	{html_options name="edit[schedule_type]" options=$schedule_types}
	<div class="description">What type of scheduling to use.  This affects how games are scheduled and standings displayed.</div>

<label for="edit[games_before_repeat]">Ratings - Games Before Repeat</label>
	{html_options name="edit[games_before_repeat]" options=$games_before_repeat}
	<div class="description">The number of games before two teams can be scheduled to play each other again (FOR PYRAMID/RATINGS LADDER SCHEDULING ONLY).</div>

<label for="edit[display_sotg]">SOTG Display</label>
	{html_options name="edit[display_sotg]" options=$display_sotg}
	<div class="description">Control SOTG display.  "all" shows numeric scores and survey answers to any player.  "symbols_only" shows only star, check, and X, with no numeric values attached.  "coordinator_only" restricts viewing of any per-game information to coordinators only.</div>

<label for="edit[coord_list]">League Coordinator Email List</label>
	<input type="text" maxlength="200" name="edit[coord_list]" size="35" value="" />
	<div class="description">An email alias for all coordinators of this league (can be a comma separated list of individual email addresses)</div>


<label for="edit[capt_list]">League Captain Email List</label>
	<input type="text" maxlength="200" name="edit[capt_list]" size="35" value="" />
	<div class="description">An email alias for all captains of this league</div>

<label for="edit[excludeTeams]">Allow exclusion of teams during scheduling?</label>
	{html_options name="edit[excludeTeams]" options=$excludeTeams}
	<div class="description">Allows coordinators to exclude teams from schedule generation.</div>

<label for="edit[finalize_after]">Game finalization delay</label>
	<input type="text" maxlength="5" name="edit[finalize_after]" size="5" value="36" />
	<div class="description">Games which haven't been scored will be automatically finalized after this many hours, no finalization if 0</div>

{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
