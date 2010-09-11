{include file=header.tpl}
<h1>{$title}</h1>
<script type="text/javascript">
{literal}
<!--
	$(document).ready(function() {
		$('#datepicker').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'yy-mm-dd',
			yearRange: '-90:+0'
		});
	});

	function popup(url)
	{
		newwindow=window.open(url,'Leaguerunner Skill Rating Form','height=350,width=400,resizable=yes,scrollbars=yes')
		if (window.focus) {newwindow.focus()}
		return false;
	}

	function doNothing() {}
// -->
{/literal}
</script>
<p>
{$instructions}
</p>
<p>
Note that email and phone publish settings below only apply to regular players.
Captains will always have access to view the phone numbers and email addresses
of their confirmed players.  All Team Captains will also have their email
address viewable by other players
</p>
{if $privacy_url}
<p>If you have concerns about the data {$app_org_short_name} collects, please see our <b><a href="{$privacy_url}" target="_new">Privacy Policy</a>.</b>
</p>
{/if}
<form id="player_form" method="POST">

{fill_form_values}
{if $formErrors}
<p>
The following errors were encountered in your submission:
</p>
<ul class="error"></ul>
{/if}

<fieldset>
    <legend>Identity</legend>

    <label for="edit[firstname]">First Name</label>
	    <input id="edit[firstname]" type="text" maxlength="100" name="edit[firstname]" size="25" value="" />
	    <div class="description">First (and, if desired, middle) name.</div>

    <label for="edit[lastname]">Last Name</label>
	    <input id="edit[lastname]" type="text" maxlength="100" name="edit[lastname]" size="25" value="" />
	    <div class="description">Full last name.</div>

    <label for="edit[username]">System Username</label>
    {if session_perm("person/edit/`$person->user_id`/username")}
	<input type="text" maxlength="100"  name="edit[username]" size="25" value="" />
    {else}
	{$edit.username}
    {/if}
	<div class="description">Desired login name.</div>

    {if session_perm("person/edit/`$person->user_id`/username")}
    <label for="edit[password_once]">Password</label>
	<input type="password" maxlength="100" name="edit[password_once]" size="25" value="" />
	<div class="description">Enter your desired password.</div>

    <label for="edit[password_twice]">Re-enter Password</label>
	<input type="password" maxlength="100" name="edit[password_twice]" size="25" value="" />
	<div class="description">Enter your desired password a second time to confirm it.</div>
    {/if}


    <label for="edit[gender]">Gender</label>
	<select name="edit[gender]">
		<option value="Male">Male</option>
		<option value="Female">Female</option>
	</select>
	<div class="description">Select your gender</div>
</fieldset>

<fieldset>
    <legend>Online Contact</legend>

    <label for="edit[email]">Email Address</label>
	<input type="text" maxlength="100"  name="edit[email]" size="25" value="" />
	<div class="description">Enter your preferred email address.  This will be used by {$app_org_short_name} to correspond with you on league matters.</div>

    <div>
	<input type="checkbox" class="form-checkbox" name="edit[allow_publish_email]" value="Y" /> Allow other players to view my email address
    </div>
</fieldset>

<fieldset>
    <legend>Street Address</legend>


    <label>Street and Number</label>
	<input type="text" maxlength="100"  name="edit[addr_street]" size="25" value="" />
	<div class="description">Number, street name, and apartment number if necessary</div>

    <label>City</label>
	<input type="text" maxlength="100"  name="edit[addr_city]" size="25" value="" />
	<div class="description">Name of city</div>

    <label>Province</label>
	{html_options id="edit[addr_prov]" name="edit[addr_prov]" options=$province_names}
	<div class="description">Select a province/state from the list</div>

    <label>Country</label>
	{html_options id="edit[addr_country]" name="edit[addr_country]" options=$country_names}
	<div class="description">Select a country from the list</div>

    <label>Postal Code</label>
	<input type="text" maxlength="7"  name="edit[addr_postalcode]" size="8" value="" />
	<div class="description">Please enter a correct postal code matching the address above. {$app_org_short_name} uses this information to help locate new fields near its members.</div>
</fieldset>

<fieldset>
    <legend>Telephone Numbers</legend>

    <label>Home</label>
	<input type="text" maxlength="100"  name="edit[home_phone]" size="25" value="" />
	<div class="description">Enter your home telephone number</div>

    <div><input type="checkbox" class="form-checkbox" name="edit[publish_home_phone]" value="Y" /> Allow other players to view home number</div>

    <label>Work</label>
	<input type="text" maxlength="100"  name="edit[work_phone]" size="25" value="" />
	<div class="description">Enter your work telephone number (optional)</div>

    <div><input type="checkbox" class="form-checkbox" name="edit[publish_work_phone]" value="Y" /> Allow other players to view work number</div>

    <label>Mobile</label>
	<input type="text" maxlength="100"  name="edit[mobile_phone]" size="25" value="" />
	<div class="description">Enter your cell or pager number (optional)</div>

    <div><input type="checkbox" class="form-checkbox" name="edit[publish_mobile_phone]" value="Y" /> Allow other players to view mobile number</div>

</fieldset>

<fieldset>
    <legend>Account Information</legend>

    <label for="edit[class]">Account Type</label>
	{html_options name="edit[class]" options=$player_classes}
	<div class="description">Account type determines access level of this account</div>


    {if session_perm("person/edit/`$person->user_id`/status") }
    <label for="edit[status]">Account Status</label>
	{html_options name="edit[status]" options=$player_statuses}
	<div class="description">Account status determines login ability</div>
    {/if}
</fieldset>

<fieldset>
    <legend>Player and Skill Information</legend>

    <label for="edit[skill_level]">Skill Level</label>
	{html_options name="edit[skill_level]" options=$skill_levels}
	<div class="description">Please use the questionnaire to <a href="{$base_url}/data/rating.html" target='_new'>calculate your rating</a></div>

    <label for="edit[year_started]">Year Started</label>
	{html_options name="edit[year_started]" options=$start_years}
	<div class="description">The year you started playing Ultimate in this league.</div>

    <label for="edit[birthdate]">Birthdate</label>
        <input type="text" maxlength="15" name="edit[birthdate]" size="15" value="" id="datepicker" />
        <div class="description">Please enter a correct birthdate; having accurate information is important for insurance purposes</div>

    <label for="edit[height]">Height</label>
	<input type="text" maxlength="4"  name="edit[height]" size="4" />
	<div class="description">Please enter your height in inches (5 feet is 60 inches; 6 feet is 72 inches).  This is used to help generate even teams for hat leagues, and assist captains in finding "equal" substitutes.</div>

    <label for="edit[shirtsize]">Shirt Size</label>
	{html_options name="edit[shirtsize]" options=$shirt_sizes}
	<div class="description">You may optionally enter your shirt size. This is to assist captains when ordering team shirts.</div>

<fieldset>
    <legend>Administrative Information</legend>

    {if $dog_questions}
    <label class="unformatted" for="edit[has_dog]">Do you plan to bring a dog to any games?</label>
	<select name="edit[has_dog]">
		<option value="">---</option>
		<option value="Y">Yes</option>
		<option value="N">No</option>
	</select>
    <div class="description">If you plan to bring your dog to games, select "Yes" and you will be prompted for the dog waiver form</div>
    {/if}

    <label class="unformatted" for="edit[willing_to_volunteer]">Can {$app_org_short_name} contact you with a survey about volunteering?</label>
	<select name="edit[willing_to_volunteer]">
		<option value="">---</option>
		<option value="Y">Yes</option>
		<option value="N">No</option>
	</select>
	<div class="description">{$app_org_short_name} periodically contacts members to ask questions about volunteering for the league. Choose "No" if you do not wish to be contacted.</div>

    <label class="unformatted" for="edit[contact_for_feedback]">Can {$app_org_short_name} contact you to solicit feedback on our programs or offer information on new programs?</label>
	<select name="edit[contact_for_feedback]">
		<option value="">---</option>
		<option value="Y">Yes</option>
		<option value="N">No</option>
	</select>
	<div class="description">From time to time, {$app_org_short_name} would like to contact members with information on our programs and to solicit feedback.  Choose "No" if you do not wish to be contacted.</label>
</fieldset>

{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file=footer.tpl}
