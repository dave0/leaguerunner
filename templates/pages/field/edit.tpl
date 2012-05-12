{include file='header.tpl'}
<h1>{$title}</h1>
<script type="text/javascript">
{literal}
<!--
	$(document).ready(function() {
		$('#edit\\[parent_fid\\]').change(function() {
			if($(this).val() != 0) {
				$('#noparent').hide();
			} else {
				$('#noparent').show();
			}
		});

		$('#edit\\[location_country\\]').change(function() {
			switch($(this).val().toLowerCase()) {
				case "canada":
					$('#edit\\[location_province\\]').html('{/literal}{{html_options options=$province_names}|regex_replace:"/[\r\t\n]/":""}{literal}');
					break;
				case "united states":
					$('#edit\\[location_province\\]').html('{/literal}{{html_options options=$state_names}|regex_replace:"/[\r\t\n]/":""}{literal}');
					break;
			}
		});
		
		// update the form based on the current parent id
		$('#edit\\[parent_fid\\]').change();

		// update the province/state list and select the correct one
		$('#edit\\[location_country\\]').change();
		$('#edit\\[location_province\\]').val('{/literal}{$field->location_province}{literal}');
	});
// -->
{/literal}
</script>
<form method="POST">
{fill_form_values}
{if $formErrors}
<p>
The following errors were encountered in your submission:
</p>
<ul class="error"></ul>
{/if}
	<label for="edit[num]">Field Identification</label>
	<input id="edit[num]" name="edit[num]" type="text" maxlength="15" size="15" value="" /><div class="description">Location of this field at the given site; cannot be 0</div>

	<label for="edit[status]">Field Status</label>
	{html_options id="edit[status]" name="edit[status]" options=$field_statuses}

	<label for="edit[rating]">Field Rating</label>
	{html_options id="edit[rating]" name="edit[rating]" options=$ratings}<div class="description">Rate this field on the scale provided</div>

	<label for="edit[parent_fid]">Parent Field</label>
	{html_options id="edit[parent_fid]" name="edit[parent_fid]" options=$parents}<div class="description">Inherit location and name from other field</div>

	<div id="noparent">

	<label for="edit[name]">Field Name</label>
	<input id="edit[name]" name="edit[name]" type="text" maxlength="255" size="35" value="" /><div class="description">Name of field (do not append number)</div>

	<label for="edit[code]">Field Code</label>
	<input id="edit[code]" name="edit[code]" type="text" maxlength="3" size="3" value="" /><div class="description">Three-letter abbreviation for field site</div>

	<label for="edit[region]">Region</label>
	{html_options id="edit[region]" name="edit[region]" options=$regions}<div class="description">Area of city this field is located in</div>

	<label for="edit[is_indoor]">Is indoor</label>
	{html_options id="edit[is_indoor]" name="edit[is_indoor]" options=$noyes}<div class="description">Is this an indoor field</div>

	<label for="edit[location_street]">Street and Number</label>
	<input id="edit[location_street]" name="edit[location_street]" type="text" maxlength="100" size="25" value="" /><div class="description">Three-letter abbreviation for field site</div>

	<label for="edit[location_city]">City</label>
	<input id="edit[location_city]" name="edit[location_city]" type="text" maxlength="100" size="25" value="" /><div class="description">Name of city</div>

	<label for="edit[location_province]">Province/State</label>
	{html_options id="edit[location_province]" name="edit[location_province]" options=$province_names}<div class="description">Select a province or state from the list</div>

	<label for="edit[location_country]">Country</label>
	{html_options id="edit[location_country]" name="edit[location_country]" options=$country_names}<div class="description">Select a country from the list</div>

	<label for="edit[location_postalcode]">Postal Code</label>
	<input id="edit[location_postalcode]" name="edit[location_postalcode]" type="text" maxlength="50" size="25" value="" /><div class="description">Postal code</div>

	<label for="edit[location_url]">Location Map</label>
	<input id="edit[location_url]" name="edit[location_url]" type="text" maxlength="255" size="50" value="" /><div class="description">URL for image that shows how to reach the field</div>

	<label for="edit[layout_url]">Layout Map</label>
	<input id="edit[layout_url]" name="edit[layout_url]" type="text" maxlength="255" size="50" value="" /><div class="description">URL for image that shows how to set up fields at the site</div>

	<label for="edit[driving_directions]">Driving Directions</label>
	<textarea wrap="virtual" cols="60" rows="5" name="edit[driving_directions]" ></textarea>

	<label for="edit[parking_details]">Parking Directions</label>
	<textarea wrap="virtual" cols="60" rows="5" name="edit[parking_details]" ></textarea>

	<label for="edit[transit_directions]">Transit Directions</label>
	<textarea wrap="virtual" cols="60" rows="5" name="edit[transit_directions]" ></textarea>

	<label for="edit[biking_directions]">Biking Directions</label>
	<textarea wrap="virtual" cols="60" rows="5" name="edit[biking_directions]" ></textarea>

	<label for="edit[washrooms]">Public Washrooms</label>
	<textarea wrap="virtual" cols="60" rows="5" name="edit[washrooms]" ></textarea>

	<label for="edit[public_instructions]">Special Instructions</label>
	<textarea wrap="virtual" cols="60" rows="5" name="edit[public_instructions]" ></textarea><div class="description">Specific instructions for this site that don't fit any other category.</div>

	<label for="edit[site_instructions]">Private Instructions</label>
	<textarea wrap="virtual" cols="60" rows="5" name="edit[site_instructions]" ></textarea><div class="description">Instructions for this site that should be shown only to logged-in members.</div>

	<label for="edit[sponsor]">Sponsorship</label>
	<textarea wrap="virtual" cols="60" rows="5" name="edit[sponsor]" ></textarea>

	</div>

{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
