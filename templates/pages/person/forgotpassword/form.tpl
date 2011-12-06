{include file='header.tpl'}
<h1>{$title}</h1>
<p>
	If you'd like to reset your password, please enter ONLY ONE OF:
</p>
{* TODO: need lr_url plugin that autopopulates path to be current page iff it's not provided *}
<form action="{lr_url path="person/forgotpassword"}" method="post">
<input type="hidden" name="edit[step]" value="perform" />
<table>
<tr>
  <td><b>Username</b></td>
  <td><input type="text" maxlength="100" name="edit[username]" size="30" /></td>
  <td><input type="submit" name="submit" value="Submit" /></td>
</tr>
</form>
<form action="{lr_url path="person/forgotpassword"}" method="post">
<input type="hidden" name="edit[step]" value="perform" />
<tr>
  <td><b>Email Address</b></td>
  <td><input type="text" maxlength="100" name="edit[email]" size="30" /></td>
  <td><input type="submit" name="submit" value="Submit" /></td>
</tr>
</table>

</form>
<p></p>
<p>
If the information you provide matches an account, an email will be sent to the
address on file, containing login information and a new password.  If you don't
receive an email within a few hours, you may not have remembered your
information correctly.
</p>
<p>
If you really can't remember any of these, you can mail <a
href="mailto:{$admin_addr}">{$admin_addr}</a> for support.  <b>DO NOT CREATE A
NEW ACCOUNT!</b>
</p>
{include file='footer.tpl'}
