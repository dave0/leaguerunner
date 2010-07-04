{include file=header.tpl}
<h1>{$title}</h1>
<h2>Step 1: Answer registration questions</h2>
<form method="POST">
  <input type="hidden" name="edit[step]" value="confirm" />
  {$formbuilder_editable}
  <input type="submit" class="form-submit" name="submit" value="Proceed to confirmation" />
  <input type="reset" class="form-reset" name="reset" value="reset" />
</form>
{include file=footer.tpl}
