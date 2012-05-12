{include file='header.tpl'}
<h1>{$title}</h1>
  <div class='pairtable'><table>
	<tr><td>Field Name:</td><td>{$field->name}</td></tr>
	<tr><td>Field Code:</td><td>{$field->code}</td></tr>
	<tr><td>Field Status:</td><td>{$field->status}</td></tr>
	<tr><td>Field Rating:</td><td>{$field->rating_description}</td></tr>
	<tr><td>Number:</td><td>{$field->num}</td></tr>
	<tr><td>Field Region:</td><td>{$field->region}</td></tr>
	<tr><td>Is indoor:</td><td>{if $field->is_indoor}yes{else}no{/if}</td></tr>
	{if $field->location_street}
	<tr><td>Street Address:</td><td>
		{include file='components/street_address.tpl'
			street=$field->location_street
			city=$field->location_city
			province=$field->location_province
			country=$field->location_country
			postalcode=$field->location_postalcode}
	</td></tr>
	{/if}

	<tr><td>Map:</td>
	<td>
	{if $field->length}
		{* It's drawn on Google Maps *}
		<a href="{lr_url path="gmaps/view/`$field->fid`"}" target="_new">Click for Google map of field layout and location</a>
	{elseif $field->location_url}
		{* It's an old-skool image *}
		<a href="{$field->location_url}" target="_new">Click for map in new window</a>
	{else}
		N/A
	{/if}
	</td>
	</tr>

	{if $field->layout_url}
	<tr><td>Layout:</td><td><a href="{$field->layout_url}" target="_new">Click for field layout diagram in new window</a></td></tr>
	{/if}

	{if $field->permit_url}
	<tr><td>Field Permit:</td><td>{$field->permit_url}</td></tr>
	{/if}

	{if $field->driving_directions}
	<tr><td>Driving Directions:</td><td>{$field->driving_directions}</td></tr>
	{/if}

	{if $field->parking_details}
	<tr><td>Parking Details:</td><td>{$field->parking_details}</td></tr>
	{/if}

	{if $field->transit_directions}
	<tr><td>Transit Directions:</td><td>{$field->transit_directions}</td></tr>
	{/if}

	{if $field->biking_directions}
	<tr><td>Biking Directions:</td><td>{$field->biking_directions}</td></tr>
	{/if}

	{if $field->washrooms}
	<tr><td>Public Washrooms:</td><td>{$field->washrooms}</td></tr>
	{/if}

	{if $field->public_instructions}
	<tr><td>Special Instructions:</td><td>{$field->public_instructions}</td></tr>
	{/if}

	{if $field->site_instructions}
	{if session_perm("field/view/`$field->fid`/site_instructions")}
	<tr><td>Private Instructions:</td><td>{$field->site_instructions}</td></tr>
	{/if}
	{/if}

	{if $other_fields}
	<tr>
		<td>Other fields at this site:</td>
		<td>
			<table id="other_fields" class="listtable">
				<tr>
					<th>Fields</th>
					<th>&nbsp;</th>
				</tr>
				{foreach from=$other_fields item=f}
				<tr><td>{$field->code} {$f->num}</td><td><a href="{lr_url path="field/view/`$f->fid`"}">view details</a></td></tr>
				{/foreach}
			</table>
    		</td>
	</tr>
	{/if}
     </table>
    </div>
    {if $field->sponsor}
    <div class="sponsor">
    {$field->sponsor}
    </div>
    {/if}
{include file='footer.tpl'}
