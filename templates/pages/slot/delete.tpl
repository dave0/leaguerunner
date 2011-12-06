{include file='header.tpl'}
<h1>{$title}</h1>
{if $successful}
You have successfully removed the gameslot {$slot->name}
{else}
<p>Please confirm that you wish to delete this gameslot:</p>
<div class='pairtable'><table>
  <tr>
    <td>Date and Time:</td>
    <td>{$slot->date_timestamp|date_format:"%Y-%m-%d"}, {$slot->game_start} until {$slot->game_end}</td>
  </tr>
  <tr>
    <td>Field:</td>
    <td>{$slot->field->fullname}</td>
  </tr>
</table></div>
<form method="POST">
  <input type="submit" name="submit" value="Delete" />
</form>
{/if}
{include file='footer.tpl'}
