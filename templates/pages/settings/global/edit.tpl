{include file='header.tpl'}
<h1>{$title}</h1>
<script type="text/javascript">
{literal}
<!--
	$(document).ready(function() {
		$('#edit\\[app_org_country\\]').change(function() {
			switch($(this).val().toLowerCase()) {
				case "canada":
					$('#edit\\[app_org_province\\]').html('{/literal}{{html_options options=$province_names}|regex_replace:"/[\r\t\n]/":""}{literal}');
					break;
				case "united states":
					$('#edit\\[app_org_province\\]').html('{/literal}{{html_options options=$state_names}|regex_replace:"/[\r\t\n]/":""}{literal}');
					break;
			}
		});

		// update the province/state list and select the correct one
		$('#edit\\[app_org_country\\]').change();
		$('#edit\\[app_org_province\\]').val('{/literal}{$settings.app_org_province}{literal}');
	});
// -->
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
<fieldset>
	<legend>Organization Details</legend>

	<label for="edit[app_org_name]">Organization name</label>
	<input id="edit[app_org_name]" name="edit[app_org_name]" maxlength="120" size="50" value="" type="text" /><div class="description">Your organization's full name.</div>

	<label for="edit[app_org_short_name]">Organization short name</label>
	<input id="edit[app_org_short_name]" name="edit[app_org_short_name]" maxlength="120" size="50" value="" type="text" /><div class="description">Your organization's abbreviated name or acronym.</div>

	<label for="edit[app_org_address]">Address</label>
	<input id="edit[app_org_address]" name="edit[app_org_address]" maxlength="120" size="50" value="" type="text" /><div class="description">Your organization's street address.</div>

	<label for="edit[app_org_address2]">Unit</label>
	<input id="edit[app_org_address2]" name="edit[app_org_address2]" maxlength="120" size="50" value="" type="text" /><div class="description">Your organization's unit number, if any.</div>

	<label for="edit[app_org_city]">City</label>
	<input id="edit[app_org_city]" name="edit[app_org_city]" maxlength="120" size="50" value="" type="text" /><div class="description">Your organization's city.</div>

	<label for="edit[app_org_province]">Province/State</label>
	{html_options id="edit[app_org_province]" name="edit[app_org_province]" options=$province_names}<div class="description">Your organization's province or state.</div>

	<label for="edit[app_org_country]">Country</label>
	{html_options id="edit[app_org_country]" name="edit[app_org_country]" options=$country_names}<div class="description">Your organization's country.</div>

	<label for="edit[app_org_postal]">Postal code</label>
	<input id="edit[app_org_postal]" name="edit[app_org_postal]" maxlength="120" size="7" value="" type="text" /><div class="description">Your organization's postal code.</div>

	<label for="edit[app_org_phone]">Phone</label>
	<input id="edit[app_org_phone]" name="edit[app_org_phone]" maxlength="120" size="50" value="" type="text" /><div class="description">Your organization's phone number.</div>

	<label for="edit[app_admin_name]">Administrator name</label>
	<input id="edit[app_admin_name]" name="edit[app_admin_name]" maxlength="120" size="50" value="" type="text" /><div class="description">The name (or descriptive role) of the system administrator. Mail from Leaguerunner will come from this name.</div>

	<label for="edit[app_admin_email]">Administrator e-mail address</label>
	<input id="edit[app_admin_email]" name="edit[app_admin_email]" maxlength="120" size="50" value="" type="text" /><div class="description">The e-mail address of the system administrator.  Mail from Leaguerunner will come from this address.</div>
</fieldset>

<fieldset>
	<legend>Location Details</legend>

	<label for="edit[location_latitude]">Latitude</label>
	<input id="edit[location_latitude]" name="edit[location_latitude]" maxlength="10" size="50" value="" type="text" /><div class="description">Latitude in decimal degrees for game location (center of city).  Used for calculating sunset times.</div>

	<label for="edit[location_longitude]">Longitude</label>
	<input id="edit[location_longitude]" name="edit[location_longitude]" maxlength="10" size="50" value="" type="text" /><div class="description">Longitude in decimal degrees for game location (center of city).  Used for calculating sunset times.</div>
</fieldset>

<fieldset>
	<legend>Site configuration</legend>

	<label for="edit[app_name]">Name of application</label>
	<input id="edit[app_name]" name="edit[app_name]" maxlength="120" size="50" value="" type="text" /><div class="description">The name this application will be known as to your users.</div>

	<label for="edit[items_per_page]">Items per page</label>
	<input id="edit[items_per_page]" name="edit[items_per_page]" maxlength="10" size="10" value="" type="text" /><div class="description">The number of items that will be shown per page on long reports, 0 for no limit (not recommended).</div>

	<label for="edit[days_between_waiver]">Days between Waiver Signing</label>
	<input id="edit[days_between_waiver]" name="edit[days_between_waiver]" maxlength="10" size="10" value="" type="text" /><div class="description">The number of days that a waiver agreement stays valid.  After this time, users will be forced to sign the waiver again.</div>

	<label for="edit[league_file_base]">Base location of static league files (filesystem)</label>
	<input id="edit[league_file_base]" name="edit[league_file_base]" maxlength="120" size="50" value="" type="text" /><div class="description">The filesystem location where files for permits, exported standings, etc, shall live.</div>

	<label for="edit[league_url_base]">Base location of static league files (URL)</label>
	<input id="edit[league_url_base]" name="edit[league_url_base]" maxlength="120" size="50" value="" type="text" /><div class="description">The web-accessible URL where files for permits, exported standings, etc, shall live.</div>

	<label for="edit[privacy_policy]">Location of privacy policy (URL)</label>
	<input id="edit[privacy_policy]" name="edit[privacy_policy]" maxlength="120" size="50" value="" type="text" /><div class="description">The web-accessible URL where the organization\'s privacy policy is located. Leave blank if you don\'t have an online privacy policy.</div>

	<label for="edit[password_reset]">Location of password reset (URL)</label>
	<input id="edit[password_reset]" name="edit[password_reset]" maxlength="120" size="50" value="" type="text" /><div class="description">The web-accessible URL where the password reset page is located.</div>

	<label for="edit[gmaps_key]">Google Maps API Key</label>
	<input id="edit[gmaps_key]" name="edit[gmaps_key]" maxlength="120" size="50" value="" type="text" /><div class="description">An API key for the <a href="http://www.google.com/apis/maps/signup.html" target="_blank">Google Maps API</a>. Required for rendering custom Google Maps</div>
</fieldset>

<fieldset>
	<legend>Season Information</legend>

	<label for="edit[current_season]">Current Season</label>
	{html_options id="edit[current_season]" name="edit[current_season]" options=$seasons}<div class="description">Season of play currently in effect</div>
</fieldset>

<fieldset>
	<legend>Game Finalization</legend>

	<label for="edit[missing_score_spirit_penalty]">Spirit penalty for not entering score</label>
	<input id="edit[missing_score_spirit_penalty]" name="edit[missing_score_spirit_penalty]" maxlength="120" size="50" value="" type="text" />

	<label for="edit[default_winning_score]">Winning score to record for defaults</label>
	<input id="edit[default_winning_score]" name="edit[default_winning_score]" maxlength="10" size="10" value="" type="text" />

	<label for="edit[default_losing_score]">Losing score to record for defaults</label>
	<input id="edit[default_losing_score]" name="edit[default_losing_score]" maxlength="10" size="10" value="" type="text" />

	<label for="edit[default_transfer_ratings]">Transfer ratings points for defaults</label>
	{html_radios name="edit[default_transfer_ratings]" options=$enable_disable labels=FALSE}

	<label for="edit[spirit_questions]">Spirit Questions</label>
	{html_options id="edit[spirit_questions]" name="edit[spirit_questions]" options=$questions}
</fieldset>
{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
