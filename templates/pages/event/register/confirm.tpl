{include file=header.tpl}
<h1>{$title}</h1>
<h2>Step 2: Confirm your information</h2>
<p></p>
<p>
   Please review the information from the previous form and ensure it is
   correct.  If so, use the "Submit" button at the bottom to complete the
   online portion of your registration.
</p>
{$formbuilder_viewable}
<form method="POST">
  <input type="hidden" name="edit[step]" value="submit" />
  {$formbuilder_hidden}
  <input type="submit" class="form-submit" name="submit" value="Submit" />
</form>
{include file=footer.tpl}
