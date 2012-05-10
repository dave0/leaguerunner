{include file='header.tpl'}
<h1>{$title}</h1>
<script type="text/javascript">
{literal}
	$(document).ready(function() {
		$('#open_date').datepicker({
			changeMonth: true,
			dateFormat: 'yy-mm-dd'
		});
		$('#close_date').datepicker({
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

<label for="edit[name]">Name</label>
	<input id="edit[name]" type="text" maxlength="200" name="edit[name]" size="35" value="" />
	<div class="description">The full name of this registration event.</div>

<label for="edit[description]">Description</label>
	<textarea wrap="virtual" cols="70" rows="5" name="edit[description]" ></textarea><div class="description">Complete description of the event.  HTML is allowed</div>

<label for="edit[season_id]">Season</label>
	{html_options name="edit[season_id]" options=$seasons}<div class="description">Season for which this registration applies.</div>

<label for="edit[type]">Event Type</label>
	{html_radios name="edit[type]" options=$event_types}<div class="description">Team registrations will prompt registrant to choose an existing team, or create a new team before completing registration</div>

<label for="edit[currency_code]">Currency</label>
	{html_options name="edit[currency_code]" options=$currency_codes}
	<div class="description">The time at which registration will open.</div>

<label for="edit[cost]">Cost</label>
	<input id="edit[cost]" type="text" maxlength="10" name="edit[cost]" size="10" value="" />
	<div class="description">Cost of this event.  May be zero.  <b>SHOULD NOT INCLUDE TAXES</b></div>

<label for="edit[cost]">GST</label>
	<input id="edit[gst]" type="text" maxlength="10" name="edit[gst]" size="10" value="" />
	<div class="description">GST</div>

<label for="edit[cost]">PST</label>
	<input id="edit[pst]" type="text" maxlength="10" name="edit[pst]" size="10" value="" />
	<div class="description">PST</div>

<label for="edit[open_date]">Open date</label>
	<input type="text" maxlength="15" name="edit[open_date]" size="15" value="" id="open_date" />
	<div class="description">The date on which registration for this event will open.</div>

<label for="edit[open_time]">Open time</label>
	{html_options name="edit[open_time]" options=$time_choices}
	<div class="description">The time at which registration will open.</div>

<label for="edit[close_date]">Close date</label>
	<input type="text" maxlength="15" name="edit[close_date]" size="15" value="" id="close_date" />
	<div class="description">The date on which registration for this event will close.</div>

<label for="edit[close_time]">Close time</label>
	{html_options name="edit[close_time]" options=$time_choices}
	<div class="description">The time at which registration will close.</div>

<label for="edit[cap_male]">Male cap</label>
	<input id="edit[cap_male]" type="text" maxlength="10" name="edit[cap_male]" size="10" value="" />
	<div class="description">Number of male players allowed (if individual registration).  Use -1 for no limit.</div>

<label for="edit[cap_female]">Female cap</label>
	<input id="edit[cap_female]" type="text" maxlength="10" name="edit[cap_female]" size="10" value="" />
	<div class="description">Number of female players allowed (if individual registration).  Use -1 for no limit, -2 to use male cap as a combined limit for both genders.</div>

<label for="edit[multiple]">Allow multiple registrations</label>
	{html_radios name="edit[multiple]" options=$yes_no}
	<div class="description">Can a single user register multiple times for this event?</div>

<label for="edit[anonymous]">Anonymous statistics</label>
	{html_radios name="edit[anonymous]" options=$yes_no}
	<div class="description">Will results from this event's survey be kept anonymous?</div>
{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
