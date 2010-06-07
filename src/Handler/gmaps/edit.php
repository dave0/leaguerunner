<?php
require_once('Handler/gmaps/view.php');
class gmaps_edit extends gmaps_view
{
	private $map_vars = array('fid', 'latitude', 'longitude', 'angle', 'width', 'length', 'zoom');

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field', 'edit', $this->field->fid);
	}

	function process()
	{
		if (! empty ($_POST)) {
			$this->save ($_POST);
			local_redirect(url("field/view/{$this->field->fid}"));
		} else {
			$this->generate_edit_map();
		}
	}

	function save($edit)
	{
		foreach ($this->map_vars as $var) {
			$this->field->set($var, $edit[$var]);
		}

		if( !$this->field->save() ) {
			return error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function generate_edit_map()
	{
		global $CONFIG;
		$gmaps_key = variable_get('gmaps_key', '');
		$name = "{$this->field->name} ({$this->field->code}) {$this->field->num}";
		$address = "{$this->field->location_street}, {$this->field->location_city}";
		$full_address = "{$this->field->location_street}, {$this->field->location_city}, {$this->field->location_province}";

		// We use these as last-ditch emergency values, if the field has neither
		// a valid lat/long or an address that Google can find.
		$leaguelat = variable_get('location_latitude', 0);
		$leaguelng = variable_get('location_longitude', 0);

		// Build the list of variables to set for the JS.
		// The blank line before END_OF_VARIABLES is required.
		$form = '';
		$variables = <<<END_OF_VARIABLES
lr_path = "{$CONFIG['paths']['base_url']}";
leaguelat = $leaguelat;
leaguelng = $leaguelng;
drag = true;
name = "$name";
address = "$address";
full_address = "$full_address";

END_OF_VARIABLES;

		foreach ($this->map_vars as $var) {
			if ($this->field->{$var})
				$variables .= "$var = {$this->field->{$var}};\n";
			$form .= form_hidden($var, $this->field->{$var});
		}

		// Find other fields at this site
		$sth = $this->field->find_others_at_site();
		while( $related = $sth->fetch(PDO::FETCH_OBJ)) {
			if ($related->fid != $this->field->fid && $related->length) {
				foreach ($this->map_vars as $var) {
					if ($related->{$var})
						$variables .= "other_{$var}[$related->fid] = {$related->{$var}};\n";
				}
			}
		}

		$form = form ($form . para(form_submit('Save Changes', 'submit', 'onclick="return check()"')), 'post', null, 'name="layout"');

		$scripts = <<<END_OF_SCRIPTS
	<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=$gmaps_key" type="text/javascript"></script>
	<script src="{$CONFIG['paths']['base_url']}/js/map_common.js" type="text/javascript"></script>
	<script src="{$CONFIG['paths']['base_url']}/js/map_view.js" type="text/javascript"></script>
	<script src="{$CONFIG['paths']['base_url']}/js/map_edit.js" type="text/javascript"></script>
	<script type="text/javascript">
		//<![CDATA[
$variables
		//]]>
	</script>
END_OF_SCRIPTS;

		print theme_header("Field Editor: {$this->field->name} ({$this->field->code}) {$this->field->num}", $scripts);

		print <<<END_OF_BODY
<body onresize="resizeMap()" onload="initialize_edit()" onunload="GUnload()">

<div id="map" style="margin: 0; padding: 0; width: 70%; height: 400px; float: left;"></div>
<div id="form" style="margin: 0; padding-left: 1em; width: 27%; float: left;">
	<h3>$name</h3>
	<p>$address</p>

	<p>Angle:
	<span id="angle"></span>
	<input type="submit" onclick="return update_angle(10)" value="+10">
	<input type="submit" onclick="return update_angle(1)" value="+">
	<input type="submit" onclick="return update_angle(-1)" value="-">
	<input type="submit" onclick="return update_angle(-10)" value="-10">
	</p>

	<p>Width:
	<span id="width"></span>
	<input type="submit" onclick="return update_width(1)" value="+">
	<input type="submit" onclick="return update_width(-1)" value="-">
	</p>

	<p>Length:
	<span id="length"></span>
	<input type="submit" onclick="return update_length(2)" value="+">
	<input type="submit" onclick="return update_length(-2)" value="-">
	</p>

	<p>Playing Field Proper:
	<span id="field"></span>
	</p>

	<p>End zone:
	<span id="endzone">25</span>
	</p>

	$form
</div>
</body>
</html>
END_OF_BODY;

		exit;

	}
}

?>
