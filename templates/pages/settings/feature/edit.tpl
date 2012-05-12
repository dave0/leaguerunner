{include file='header.tpl'}
<h1>{$title}</h1>
<form method="POST">
{fill_form_values}
{if $formErrors}
<p>
The following errors were encountered in your submission:
</p>
<ul class="error"></ul>
{/if}
<fieldset>
    <legend>Feature configuration</legend>

	<label for="edit[registration]">Handle registration</label>
	{html_radios name="edit[registration]" options=$enable_disable labels=FALSE}<div class="description">Enable or disable processing of registrations</div>

	<label for="edit[dog_questions]">Dog questions</label>
	{html_radios name="edit[dog_questions]" options=$enable_disable labels=FALSE}<div class="description">Enable or disable questions and options about dogs</div>

	<label for="edit[clean_url]">Clean URLs</label>
	{html_radios name="edit[clean_url]" options=$enable_disable labels=FALSE}<div class="description">Enable or disable clean URLs. If enabled, you'll need <code>ModRewrite</code> support. See also the <code>.htaccess</code> file in Leaguerunner's top-level directory.</div>

	<label for="edit[session_requires_ip]">Lock sessions to initiating IP address</label>
	{html_radios name="edit[session_requires_ip]" options=$enable_disable labels=FALSE}<div class="description">If enabled, session cookies are only accepted if they come from the same IP as the initial login. This adds a bit of security against cookie theft, but causes problems for users behind a firewall that routes HTTP requests out through multiple IP addresses. Recommended setting is to enable unless you notice problems. This setting is ignored if Zikula authentication is enabled.</div>

	<label for="edit[force_roster_request]">Force roster request responses</label>
	{html_radios name="edit[force_roster_request]" options=$enable_disable labels=FALSE}<div class="description">Should players be forced to respond to roster requests immediately?</div>

	<label for="edit[log_messages]">Log Messages</label>
	{html_radios name="edit[log_messages]" options=$enable_disable labels=FALSE}<div class="description">Enable or disable System Logs. This will record any messages meeting the set threshold or higher to the logs subfolder.</div>

	<label for="edit[log_threshold]">Log Threshold</label>
	{html_options id="edit[log_threshold]" name="edit[log_threshold]" options=$log_levels labels=FALSE separator="<br />"}<div class="description">Threshold for storing Log Messages.  Messages at this level and above will be logged.</div>
</fieldset>
{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
