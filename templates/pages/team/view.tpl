{include file='header.tpl'}
<h1>{$title}</h1>
<div class='pairtable'><table>
    {if $team->website}<tr><td>Website:</td><td><a href="{$team->website|escape}">{$team->website|escape}</a></td></tr>{/if}
    <tr><td>Shirt Colour:</td><td>{$team->shirt_colour|escape}</td></tr>
    <tr><td>League/Tier:</td><td><a href="{lr_url path="league/view/`$team->league_id`"}">{$team->league_name|escape}</a></td></tr>
    {if $home_field}
	<tr><td>Home Field:</td><td><a href="{lr_url path="field/view/`$home_field->fid`"}">{$home_field->fullname|escape}</a></td></tr>
    {/if}
    <tr><td>Team Status:</td><td>{$team->status}</td></tr>

    <tr><td>Team SBF:</td><td>{$team_sbf} {if $league_sbf}(league {$league_sbf}){/if}</td></tr>
    <tr><td>Rating:</td><td>{$team->rating}</td></tr>
</table></div>
{if $display_roster_note}
<p class='error'>
Your team currently has only {$team->roster_count} full-time players with active accounts listed.
</p>
<p>
Your team roster must have a minimum of {$roster_requirement} <b>regular player</b>, <b>assistant</b>, or <b>captain</b> players with active accounts by the roster deadline of {$team->roster_deadline|date_format:"%Y-%m-%d"}.<br />
If you have players showing <b>account inactive</b> or <b>request to join by captain</b>, you should contact them to update their status.
</p>
{/if}
<table id="roster">
   <thead>
      <tr>
        <th>Name</th>
        <th>Position</th>
        <th>Gender</th>
        {if $display_rating}<th>Rating</th>{/if}
        {if $display_shirts}<th>Shirt Size</th>{/if}
        <th>Date Joined</th></tr>
    </thead>
    <tbody>
    {foreach from=$team->roster item=p}
	<tr>
	  <td><img align="left" src="{$p->get_gravatar(16)}" width="16" height="16" /><a href="{lr_url path="person/view/`$p->id`"}"><a href="{lr_url path="person/view/`$p->id`"}">{$p->fullname}</a>
	    {if $p->roster_conflict}<div class='roster_conflict'>(roster conflict)</div>{/if}
	    {if $p->player_status == "inactive"}<div class='roster_conflict'>(account inactive)</div>{/if}
	  </td>
	  <td>{if $p->_modify_status}<a href="{lr_url path="team/roster/`$team->team_id`/`$p->id`"}">{$p->status}</a>{else}{$p->status}{/if}</td>
	  <td>{$p->gender}</td>
	  {if $display_rating}<td>{$p->skill_level}</td>{/if}
	  {if $display_shirts}<td>{$p->shirtsize}</td>{/if}
	  <td>{$p->date_joined}</td>
	</tr>
    {/foreach}
    </tbody>
    {if $display_rating}
    <tfoot>
        <tr>
        <th colspan="3">Average Skill Rating</th>
        {if $display_rating}<th></th>{/if}
        {if $display_shirts}<th></th>{/if}
        <th></th></tr>
    </tfoot>
    {/if}
</table>
<table>
  {if session_perm("person/view/`$person->user_id`/notes")}
  <tr>
    <td>Notes:</td>
    <td>
      <table class="baretable">
	{foreach item=n from=$team->get_notes()}
        <tr>
	   <td><a href="{lr_url path="note/view/`$n->id`"}">{$n->created}</a></td><td>{$n->note}</td>
        </tr>
        <tr>
           <td></td>
	   <td>(note added by {$n->creator->fullname} )</td>
	</tr>
	{foreachelse}
	<tr><td colspan='4'>No notes</td></tr>
	{/foreach}
      </table>
    </td>
  </tr>
  {/if}
</table>
<script type="text/javascript">
{literal}
var positionSort = {
{/literal}
	'{$roster_positions.captain}'         : 1,
	'{$roster_positions.assistant}'       : 2,
	'{$roster_positions.player}'          : 3,
	'{$roster_positions.substitute}'      : 4,
	'{$roster_positions.player_request}'  : 5,
	'{$roster_positions.captain_request}' : 6,
	'{$roster_positions.coach}'           : 0
{literal}
};
jQuery.fn.dataTableExt.oSort['roster-position-desc']  = function(a,b) {
	var x = positionSort[ a.replace(/\n/g," ").replace( /<.*?>/g, "" )+'' ];
	var y = positionSort[ b.replace(/\n/g," ").replace( /<.*?>/g, "" )+'' ];
	return ((x < y) ? -1 : ((x > y) ?  1 : 0));
};

jQuery.fn.dataTableExt.oSort['roster-position-asc']  = function(a,b) {
	var x = positionSort[a.replace(/\n/g," ").replace( /<.*?>/g, "" )+''];
	var y = positionSort[b.replace(/\n/g," ").replace( /<.*?>/g, "" )+''];
	return ((x < y) ? 1 : ((x > y) ?  -1 : 0));
};
$(document).ready(function() {
	$('#roster').dataTable( {
		bPaginate: false,
		bAutoWidth: false,
		sDom: 'lfrtip',
		bFilter: false,
		bInfo: false,
		bJQueryUI: true,
		aaSorting: [[ 1, "desc" ],[2,"desc"], [0, "asc"] ],
		aoColumns: [
			{ "sType" : "html" },
			{ "sType" : "roster-position"  },
			null,
{/literal}{if $display_rating}
			null,
{/if}{literal}
{/literal}{if $display_shirts}
			null,
{/if}{literal}
			{ "sType" : "string" }
		]
{/literal}{if $display_rating}
{literal},
		"fnFooterCallback": function ( nRow, aaData, iStart, iEnd, aiDisplay ) {
			var totalskill = 0;
			var numplayers = 0;
			for ( var i=0 ; i<aaData.length ; i++ )
			{
				totalskill += aaData[i][3]*1;
				numplayers++;
			}

			var nCells = nRow.getElementsByTagName('th');
			nCells[1].innerHTML = (totalskill/numplayers).toFixed(2);
		}
{/literal}{/if}
{literal}
	} );
})
{/literal}
</script>
{include file='footer.tpl'}
