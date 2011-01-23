{include file=header.tpl}
<h1>{$title}</h1>
<fieldset>
  <legend>Registration Statistics (<a href="{lr_url path="event/downloadsurvey/`$event->registration_id`"}" style="font-size: 0.8em">download survey spreadsheet</a>)</legend>
  <div class="pairtable"><table>
  {if $gender_counts}
    <tr>
       <td>By Gender:</td>
       <td>
         <table>
           {foreach key=gender item=count from=$gender_counts}
           <tr><td>{$gender}</td><td>{$count}</td></tr>
           {/foreach}
         </table>
       <td>
    </tr>
  {/if}
  {if $payment_counts}
    <tr>
       <td>By Payment:</td>
       <td>
         <table>
           {foreach key=payment item=count from=$payment_counts}
           <tr><td>{$payment}</td><td>{$count}</td></tr>
           {/foreach}
         </table>
       <td>
    </tr>
  {/if}
  {if $survey_questions}
    {foreach key=question item=question_counts from=$survey_questions}
    <tr>
      <td>{$question}</td>
      <td>
        <table>
           {foreach key=answer item=count from=$question_counts}
           <tr><td>{$answer}</td><td>{$count}</td></tr>
           {/foreach}
        </table>
      </td>
    {/foreach}
  {/if}
  </table></div>
</fieldset>
<fieldset>
  <legend>Registration List (<a href="{lr_url  path="event/download/`$event->registration_id`"}" style="font-size: 0.8em">download spreadsheet</a>)</legend>
  <table id="players" style="width: 100%">
    <thead>
      <tr>
        <th>Order ID</th>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Date Registered</th>
        <th>Registration Status</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$registrations item=r}
      <tr>
        <td><a href="{lr_url path="registration/view/`$r.order_id`"}">{$r.order_id|string_format:"`$order_id_format`"}</a></td>
        <td><a href="{lr_url path="person/view/`$r.user_id`}">{$r.firstname}</a></td>
        <td><a href="{lr_url path="person/view/`$r.user_id`}">{$r.lastname}</a></td>
        <td>{$r.time}</td>
        <td>{$r.payment}</td>
      </tr>
      {/foreach}
    </tbody>
  </table>
</fieldset>
<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#players').dataTable( {
		bFilter: false,
		bJQueryUI: true,
		iDisplayLength: 50,
		sPaginationType: "full_numbers",
		aaSorting: [[ 0, "asc" ]],
		aoColumns: [
			{ "sType" : "html" },
			{ "sType" : "html" },
			{ "sType" : "html" },
                        null,
                        null
		]
	} );
})
{/literal}
</script>
{include file=footer.tpl}
