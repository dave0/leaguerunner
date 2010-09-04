{include file=header.tpl}
<h1>{$title}</h1>
<p>
	You are changing the password for '{$person->fullname}' (username "{$person->username}").

</p>
<form method="POST">
<input type="hidden" name="edit[step]" value="perform" />
<div class="pairtable">
<table>
<tr>
  <td><b>New Password:</b></td>
  <td><input type="password" maxlength="100" class="form-text" name="edit[password_one]" size="30" /></td>
</tr>
<tr>
  <td><b>New Password (confirm):</b></td>
  <td><input type="password" maxlength="100" class="form-text" name="edit[password_two]" size="30" /></td>
</tr>
</table>
</div>
<input type="submit" class="form-submit" name="submit" value="Submit" />
<input type="reset" class="form-reset" name="reset" value="Reset" />
</form>
{include file=footer.tpl}
