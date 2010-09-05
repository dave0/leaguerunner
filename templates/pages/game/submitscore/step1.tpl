{include file=header.tpl title=$title}
<h1>{$title}</h1>
<p>Submit the score for the {$game->game_date} {$game->game_start} at {$game->field_code}
between {$game->home_name} and {$game->away_name}
</p>
<p>
If your opponent has already entered a score, it will be displayed below.  If the score you enter does not agree with this score, posting of the score will be delayed until your coordinator can confirm the correct score.
</p>
<script type="text/javascript"> <!--
  default_winning_score = {$default_winning_score};
  default_losing_score = {$default_losing_score};
{literal}
  function defaultCheckboxChanged() {
	form = document.getElementById('score_form');
    if (form.elements['edit[defaulted]'][0].checked == true) {
        form.elements['edit[score_for]'].value = default_losing_score;
        form.elements['edit[score_for]'].disabled = true;
        form.elements['edit[score_against]'].value = default_winning_score;
        form.elements['edit[score_against]'].disabled = true;
        form.elements['edit[defaulted]'][1].disabled = true;
    } else if (form.elements['edit[defaulted]'][1].checked == true) {
        form.elements['edit[score_for]'].value = default_winning_score;
        form.elements['edit[score_for]'].disabled = true;
        form.elements['edit[score_against]'].value = default_losing_score;
        form.elements['edit[score_against]'].disabled = true;
        form.elements['edit[defaulted]'][0].disabled = true;
    } else {
        form.elements['edit[score_for]'].disabled = false;
        form.elements['edit[score_against]'].disabled = false;
        form.elements['edit[defaulted]'][0].disabled = false;
        form.elements['edit[defaulted]'][1].disabled = false;
    }
  }
// -->
{/literal}
</script>
<form id="score_form" method="post">
 <input type="hidden" name="edit[step]" value="fieldreport" />
 <div class="listtable">
 <table>
  <tr>
    <th>Team Name</th>
    <th>Defaulted?</th>
    <th>Your Score Entry</th>
    <th>Opponent's Score Entry</th>
  </tr>
  <tr>
    <td>{$team->name}</td>
    <td><input type='checkbox' name='edit[defaulted]' value='us' onclick='defaultCheckboxChanged()'></td>
    <td><input type="text" maxlength="2" name="edit[score_for]" size="2" /></td>
    <td>{$opponent_entry->score_against|default:"not yet entered"}{if $opponent_entry->defaulted == "them"} (defaulted){/if}</td>
  </tr>
  <tr>
    <td>{$opponent->name}</td>
    <td><input type='checkbox' name='edit[defaulted]' value='them' onclick='defaultCheckboxChanged()'></td>
    <td><input type="text" maxlength="2" name="edit[score_against]" size="2" /></td>
    <td>{$opponent_entry->score_for|default:"not yet entered" }{if $opponent_entry->defaulted == "us"} (defaulted){/if}</td>
  </tr>
</table>
<p>
  <input type="submit" name="submit" value="Next Step" />
  <input type="reset" name="reset" value="reset" />
</p>
</form>
{include file=footer.tpl}
