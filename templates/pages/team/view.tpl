{include file=header.tpl}
<h1>{$title}</h1>
<table>
  <tr><td>
  <div class='pairtable'><table>
    {if $team->website}<tr><td>Website:</td><td><a href="{$team->website|escape}">{$team->website|escape}</a></td></tr>{/if}
    <tr><td>Shirt Colour:</td><td>{$team->shirt_colour|escape}</td></tr>
    <tr><td>League/Tier:</td><td><a href="{lr_url path="league/view/`$team->league_id`"}">{$team->league_name|escape}</a></td></tr>
    {if $home_field}
	<tr><td>Home Field:</td><td><a href="{lr_url path="field/view/`$home_field->fid`"}">{$home_field->fullname|escape}</a></td></tr>
    {/if}
    <tr><td>Region Preference:</td><td>{$team->region_preference|default:"None"}</td></tr>
    <tr><td>Team Status:</td><td>{$team->status}</td></tr>

    <tr><td>Team SBF:</td><td>{$team_sbf} {if $league_sbf}(league {$league_sbf}){/if}</td></tr>
    <tr><td>Rating:</td><td>{$team->rating}</td></tr>
  </table></div>
  </td><td>
    <table id="roster">
       <thead>
	<tr><th>Name</th><th>Position</th><th>Gender</th><th>Rating</th>{if $display_shirts}<th>Shirt Size</th>{/if}<th>Date Joined</th></tr>
       </thead>
       <tbody>
       {foreach from=$team->roster item=p}
		<tr>
		  <td><a href="{lr_url path="person/view/`$p->id`"}">{$p->fullname}</a>
		    {if $p->roster_conflict}<div class='roster_conflict'>(roster conflict)</div>{/if}
		    {if $p->player_status == "inactive"}<div class='roster_conflict'>(account inactive)</div>{/if}
		  </td>
		  <td>{if $p->_modify_status}<a href="{lr_url path="team/roster/`$team->team_id`/`$p->id`"}">{$p->status}</a>{else}{$p->status}{/if}</td>
		  <td>{$p->gender}</td>
		  <td>{$p->skill_level}</td>
		  {if $display_shirts}<td>{$p->shirtsize}</td>{/if}
		  <td>{$p->date_joined}</td>
		</tr>
       {/foreach}
	</tbody>
	<tfoot>
	<tr><th colspan="3">Average Skill Rating</th><th></th>{if $display_shirts}<th></th>{/if}<th></th></tr>
	</tfoot>
</table>
</td></tr>
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
		bFilter: false,
		bInfo: false,
		bJQueryUI: true,
		aaSorting: [[ 1, "desc" ],[2,"desc"], [0, "asc"] ],
		aoColumns: [
			{ "sType" : "html" },
			{ "sType" : "roster-position"  },
			null,
			null,
{/literal}{if $display_shirts}
			null,
{/if}{literal}
			{ "sType" : "string" }
		],
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
	} );
})
{/literal}
</script>
{include file=footer.tpl}
