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
    <legend>User email settings</legend>

	<label for="edit[person_mail_approved_subject]">Subject of account approval e-mail</label>
	<input id="edit[person_mail_approved_subject]" name="edit[person_mail_approved_subject]" maxlength="180" size="50" value="" type="text" /><div class="description">Customize the subject of your approval e-mail, which is sent after account is approved. Available variables are: %username, %site, %url.</div>

	<label for="edit[person_mail_approved_body_player]">Body of account approval e-mail (player)</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[person_mail_approved_body_player]" ></textarea><div class="description">Customize the body of your approval e-mail, to be sent to players after accounts are approved. Available variables are: %fullname, %memberid, %adminname, %username, %site, %url.</div>

	<label for="edit[person_mail_approved_body_visitor]">Body of account approval e-mail (visitor)</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[person_mail_approved_body_visitor]" ></textarea><div class="description">Customize the body of your approval e-mail, to be sent to a non-player visitor after account is approved. Available variables are: %fullname, %adminname, %username, %site, %url.</div>

	<label for="edit[person_mail_member_letter_subject]">Subject of membership letter e-mail</label>
	<input id="edit[person_mail_member_letter_subject]" name="edit[person_mail_member_letter_subject]" maxlength="180" size="50" value="" type="text" /><div class="description">Customize the subject of your membership letter e-mail, which is sent annually after membership is paid for. Available variables are: %fullname, %firstname, %lastname, %site, %year.</div>

	<label for="edit[person_mail_member_letter_body]">Body of membership letter e-mail (player)</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[person_mail_member_letter_body]" ></textarea><div class="description">Customize the body of your membership letter e-mail, which is sent annually after membership is paid for. If registrations are disabled, or this field is empty, no letters will be sent. Available variables are: %fullname, %firstname, %lastname, %adminname, %site, %year.</div>

	<label for="edit[person_mail_password_reset_subject]">Subject of password reset e-mail</label>
	<input id="edit[person_mail_password_reset_subject]" name="edit[person_mail_password_reset_subject]" maxlength="180" size="50" value="" type="text" /><div class="description">Customize the subject of your password reset e-mail, which is sent when a user requests a password reset. Available variables are: %site.</div>

	<label for="edit[person_mail_password_reset_body]">Body of password reset e-mail</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[person_mail_password_reset_body]" ></textarea><div class="description">Customize the body of your password reset e-mail, which is sent when a user requests a password reset. Available variables are: %fullname, %adminname, %username, %password, %site, %url.</div>

	<label for="edit[person_mail_dup_delete_subject]">Subject of duplicate account deletion e-mail</label>
	<input id="edit[person_mail_dup_delete_subject]" name="edit[person_mail_dup_delete_subject]" maxlength="180" size="50" value="" type="text" /><div class="description">Customize the subject of your account deletion mail, sent to a user who has created a duplicate account. Available variables are: %site.</div>

	<label for="edit[person_mail_dup_delete_body]">Body of duplicate account deletion e-mail</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[person_mail_dup_delete_body]" ></textarea><div class="description">Customize the body of your account deletion e-mail, sent to a user who has created a duplicate account. Available variables are: %fullname, %adminname, %existingusername, %existingemail, %site, %passwordurl.</div>

	<label for="edit[person_mail_dup_merge_subject]">Subject of duplicate account merge e-mail</label>
	<input id="edit[person_mail_dup_merge_subject]" name="edit[person_mail_dup_merge_subject]" maxlength="180" size="50" value="" type="text" /><div class="description">Customize the subject of your account merge mail, sent to a user who has created a duplicate account. Available variables are: %site.</div>

	<label for="edit[person_mail_dup_merge_body]">Body of duplicate account merge e-mail</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[person_mail_dup_merge_body]" ></textarea><div class="description">Customize the body of your account merge e-mail, sent to a user who has created a duplicate account. Available variables are: %fullname, %adminname, %existingusername, %existingemail, %site, %passwordurl.</div>

	<label for="edit[person_mail_captain_request_subject]">Subject of captain request e-mail</label>
	<input id="edit[person_mail_captain_request_subject]" name="edit[person_mail_captain_request_subject]" maxlength="180" size="50" value="" type="text" /><div class="description">Customize the subject of your captain request mail, sent to a user who has been invited to join a team. Available variables are: %site, %fullname, %captain, %team, %league, %day, %adminname.</div>

	<label for="edit[person_mail_captain_request_body]">Body of captain request e-mail</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[person_mail_captain_request_body]" ></textarea><div class="description">Customize the body of your captain request e-mail, sent to a user who has been invited to join a team. Available variables are: %site, %fullname, %captain, %team, %teamurl, %league, %day, %adminname.</div>

	<label for="edit[person_mail_player_request_subject]">Subject of player request e-mail</label>
	<input id="edit[person_mail_player_request_subject]" name="edit[person_mail_player_request_subject]" maxlength="180" size="50" value="" type="text" /><div class="description">Customize the subject of your player request mail, sent to captains when a player asks to join their team. Available variables are: %site, %fullname, %team, %league, %day, %adminname.</div>

	<label for="edit[person_mail_player_request_body]">Body of player request e-mail</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[person_mail_player_request_body]" ></textarea><div class="description">Customize the body of your player request e-mail, sent to captains when a player asks to join their team. Available variables are: %site, %fullname, %captains, %team, %teamurl, %league, %day, %adminname.</div>
</fieldset>
{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
