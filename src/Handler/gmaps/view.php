<?php
require_once('Handler/FieldHandler.php');
class gmaps_view extends FieldHandler
{
	private $map_vars = array('fid', 'latitude', 'longitude', 'angle', 'width', 'length', 'zoom');

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field', 'view', $this->field->fid);
	}

	function process()
	{
		global $lr_session, $CONFIG;

		if (!$this->field->length) {
			return error_exit('That field has not yet been laid out');
		}

		$gmaps_key = variable_get('gmaps_key', '');
		$name = "{$this->field->name} ({$this->field->code}) {$this->field->num}";
		$address = "{$this->field->location_street}, {$this->field->location_city}";
		$full_address = "{$this->field->location_street}, {$this->field->location_city}, {$this->field->location_province}";

		// Build the list of variables to set for the JS.
		// The blank line before END_OF_VARIABLES is required.
		$variables = <<<END_OF_VARIABLES
lr_path = "{$CONFIG['paths']['base_url']}";
name = "$name";
address = "$address";
full_address = "$full_address";

END_OF_VARIABLES;

		foreach ($this->map_vars as $var) {
			$variables .= "$var = {$this->field->{$var}};\n";
		}

		// Handle parking
		if ($this->field->parking) {
			$parking = explode ('/', $this->field->parking);
			foreach ($parking as $i => $pt) {
				list($lat,$lng) = explode(',', $pt);
				$variables .= "parking[$i] = new GLatLng($lat, $lng);\n";
			}
		}

		// Find other fields at this site
		$sth = $this->field->find_others_at_site();
		while( $related = $sth->fetch(PDO::FETCH_OBJ)) {
			if ($related->fid != $this->field->fid && $related->length) {
				foreach ($this->map_vars as $var) {
					$variables .= "other_{$var}[$related->fid] = {$related->{$var}};\n";
				}
			}
		}

		$scripts = <<<END_OF_SCRIPTS
	<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=$gmaps_key" type="text/javascript"></script>
	<script src="{$CONFIG['paths']['base_url']}/js/map_common.js" type="text/javascript"></script>
	<script src="{$CONFIG['paths']['base_url']}/js/map_view.js" type="text/javascript"></script>
	<script type="text/javascript">
		//<![CDATA[
$variables
		//]]>
	</script>
END_OF_SCRIPTS;

		$home_addr = '';
		if ($lr_session->user) {
			$home_addr = "{$lr_session->user->addr_street}, {$lr_session->user->addr_city}, {$lr_session->user->addr_prov}";
		}

		print theme_header("{$this->field->name} ({$this->field->code}) {$this->field->num}", $scripts);
		print <<<END_OF_BODY
<body onresize="resizeMap()" onload="initialize_view()" onunload="GUnload()" style="padding: 0;">
<div id="map" style="margin: 0; padding: 0; width: 70%; height: 400px; float: left;"></div>
<div style="margin: 0; padding-left: 1em; width: 27%; float: left;">
	<h3>$name</h3>
	<p>$address</p>

	<p>Get directions to this field from:
	<form action="javascript:getDirections()">
	<input type="text" size=30 maxlength=50 name="saddr" id="saddr" value="$home_addr" /><br>
	<input value="Get Directions" type="submit"><br>
	Walking <input type="checkbox" name="walk" id="walk" /><br>
	Biking <input type="checkbox" name="highways" id="highways" />
	<div id="directions">
	</div>
</div>
</body>
</html>
END_OF_BODY;

		exit;
	}
}

?>
