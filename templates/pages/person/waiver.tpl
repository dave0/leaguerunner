{include file=header.tpl}
<h1>{$title}</h1>
<form method="post">
  <input type="hidden" name="next" value="{$next_page}" />
  <input type="hidden" name="edit[step]" value="perform" />
  {include file=$waiver_text}
  <p>
    <input type="radio" name="edit[signed]" value="yes" /> I agree to the above conditions<br />
    <input type="radio" name="edit[signed]" value="no" /> I DO NOT agree to the above conditions
  </p>
  <input type="submit" name="submit" value="Submit" />
  <input type="reset" name="reset" value="Reset" />
</form>
{include file=footer.tpl}
