{include file='header.tpl'}
<h1>{$title}</h1>
<p>
	Please respect the following rules for all fields.  Note that some
	sites also have additional restrictions listed on that field's page
	that must also be followed.</p>
<ul>
  <li>Garbage facilites do not exist at any site.  Do not leave any garbage
      behind when you leave -- even if it isn't yours.  Take extra care to
      remove any hazardous items (ie: bottlecaps, glass) to avoid injury to
      others.
  </li>
  <li>If dogs are not allowed at a particular field, you <b>must</b> respect
      this.  If dogs are permitted at a field, you must clean up after your pet
      and take the waste away with you.</li>
  <li>By law, alcohol is not permitted on any league field, including UPI. 
      Furthermore, posession of any alcoholic beverage at the following sites
      can and will lose us our ability to play there:  St. Paul's, Lynda Lane,
      Laurentian, Romulan, and any city or school field.  
  </li>
</ul> 
<p>Due to some individuals and
teams ignoring the rules, we are close to losing several of our fields.  If
fields are lost due to the actions of a particular player or team, they will
be <b>removed from the league</b>.
</p>
<div class="fieldlist">
<table>
{foreach from=$fields_by_region key=region item=fields}
<tr>
  <th>{$region|capitalize}</th>
  <td>
     {foreach from=$fields item=f}
     <a {if $f->status == 'closed'}class='closedfield'{/if} href="{lr_url path="field/view/`$f->fid`"}">{$f->name}</a>,&nbsp;
     {/foreach}
  </td>
</tr>
{/foreach}
</table>
</div>
{include file='footer.tpl'}
