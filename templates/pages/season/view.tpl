{include file=header.tpl}
<h1>{$title}</h1>
  <div class='pairtable'>
    <table>
	<tr><td>Season:</td><td>{$season->season}</td></tr>
	<tr><td>Year:</td><td>{$season->year}</td></tr>
     </table>
    </div>
    <table id="leagues">
        <thead>
           <tr>
                <th>League Name</th>
                <th>Day</th>
                <th>Ratio</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        {foreach from=$leagues item=i}
            <tr>
                <td><a href="{lr_url path="league/view/`$i->league_id`}">{$i->fullname}</a></td>
                <td>{$i->day}</td>
                <td>{$i->ratio}</td>
                <td>{$i->status}</td>
            </tr>
        {/foreach}
	</tbody>
    </table>
<script type="text/javascript">
{literal}
var daySort = {
	'Sunday'    : 1,
	'Monday'    : 2,
	'Tuesday'   : 3,
	'Wednesday' : 4,
	'Thursday'  : 5,
	'Friday'    : 6,
	'Saturday'  : 7,
};
jQuery.fn.dataTableExt.oSort['day-of-week-desc']  = function(a,b) {
	var x = daySort[ a.replace(/^Sunday,/g,"").replace(/,Saturday$/,"")+'' ];
	var y = daySort[ b.replace(/^Sunday,/g,"").replace(/,Saturday$/,"")+'' ];
	return ((x < y) ? -1 : ((x > y) ?  1 : 0));
};

jQuery.fn.dataTableExt.oSort['day-of-week-asc']  = function(a,b) {
	var x = daySort[ a.replace(/^Sunday,/g,"").replace(/,Saturday$/,"")+'' ];
	var y = daySort[ b.replace(/^Sunday,/g,"").replace(/,Saturday$/,"")+'' ];
	return ((x < y) ? 1 : ((x > y) ?  -1 : 0));
};
$(document).ready(function() {
	$('#leagues').dataTable( {
		bPaginate: false,
		bAutoWidth: false,
		sDom: 'lfrtip',
		bFilter: false,
		bInfo: false,
		bJQueryUI: true,
		aaSorting: [[ 1, "asc" ]],
		aoColumns: [
			{ "sType" : "html" },
			{ "sType" : "day-of-week" },
			null,
			null
		]
	} );
})
{/literal}
</script>
{include file=footer.tpl}
