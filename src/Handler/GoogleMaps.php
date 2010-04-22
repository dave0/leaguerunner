<?php

function gmaps_dispatch()
{
	$op = arg(1);

	switch($op) {
		case 'allfields':
			$obj = new GoogleMapsAllFields;
			break;
		case 'view':
			$obj = new GoogleMapsView;
			$obj->field = field_load( array('fid' => arg(2)) );
			break;
		case 'edit':
			$obj = new GoogleMapsEdit;
			$obj->field = field_load( array('fid' => arg(2)) );
			break;
		default:
			$obj = new GoogleMapsHTMLPage;
	}

	return $obj;
}

class GoogleMapsHTMLPage extends Handler
{
	function has_permission()
	{
		return true;
	}

	function process ()
	{
		$gmaps_key = variable_get('gmaps_key', '');
		global $CONFIG;

		$leaguelat = variable_get('location_latitude', 0);
		$leaguelng = variable_get('location_longitude', 0);

		$scripts = <<<END_OF_SCRIPTS
	<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=$gmaps_key" type="text/javascript"></script>
	<script src="{$CONFIG['paths']['base_url']}/js/map_common.js" type="text/javascript"></script>
	<script type="text/javascript">
	//<![CDATA[

	function load() {
		if ( ! GBrowserIsCompatible()) {
			return;
		}
		var map = new GMap2(document.getElementById("map"));

		// Need to center before adding points.  We adjust center/zoom later with
		// GLatLngBounds
		resizeMap();
		map.setCenter(new GLatLng($leaguelat, $leaguelng), 11);
		map.setUIToDefault();

		GDownloadUrl("{$CONFIG['paths']['base_url']}/gmaps/allfields", function(data, responseCode) {

			var bounds = new GLatLngBounds;

			var xml = GXml.parse(data);
			var markers = xml.documentElement.getElementsByTagName("marker");
			for (var i = 0; i < markers.length; i++) {
				var point = new GLatLng(
					parseFloat(markers[i].getAttribute("lat")),
					parseFloat(markers[i].getAttribute("lng"))
				);

				var balloon = markers[i].getElementsByTagName("balloon")[0].firstChild.data;
				var tooltip = markers[i].getElementsByTagName("tooltip")[0].firstChild.data;

				var icon = new GIcon(G_DEFAULT_ICON);
				icon.image = markers[i].getElementsByTagName("image")[0].firstChild.data;

				bounds.extend(point);

				map.addOverlay(createFieldMarker( point, balloon, tooltip, icon ));

			}
			map.setCenter(bounds.getCenter())
			map.setZoom(map.getBoundsZoomLevel(bounds));
		});
	}

	function createFieldMarker( point, balloon, tooltip, newicon )
	{
		var marker = new GMarker(point, { icon : newicon, title : tooltip });

		GEvent.addListener(marker, "click", function() {
			marker.openInfoWindow(balloon);
		});

		return marker;
	}

	//]]>
	</script>

END_OF_SCRIPTS;

		print theme_header('Fields', $scripts);
?>
<body onresize="resizeMap()" onunload="GUnload()" onload="load()">
<div id="map" style="width: 100%; height: 500px"></div>
</body>
</html>
<?php
        exit;
    }
}


class GoogleMapsAllFields extends Handler
{
	function has_permission()
	{
		return true;
	}

	function render_header()
	{
		header("Content-type: text/xml");
?>
<markers>
<?php
	}

	function render_footer()
	{
		print  "\n</markers>";
	}

	function render_field( $field )
	{
		print "<marker lat=\"$field->latitude\" lng=\"$field->longitude\" fid=\"$field->fid\">\n";
		print "<balloon><![CDATA[<a href=\"" . url('field/view/'. $field->fid) . "\">$field->name</a> ($field->code)";
		if ($field->length) {
			print "<br/><a href=\"" . url('gmaps/view/'. $field->fid) . "\">Field map and layout</a>";
		}
		print "]]></balloon>\n";
		print "<tooltip>" . htmlentities($field->name) . " ($field->code)</tooltip>\n";
		print "<image>" . url('image/pins/' . $field->code . '.png') . "</image>";
		print "</marker>\n";
	}

	function process()
	{
		$this->render_header();
		$sth = field_query( array( '_extra' => 'ISNULL(f.parent_fid) AND f.status = "open"', '_order' => 'f.fid') );

		while( $field = $sth->fetchObject('Field') ) {
			if(!$field->latitude || !$field->longitude) {
				continue;
			}
			$this->render_field( $field );
		}

		$this->render_footer();
		exit(); // To prevent header/footer being displayed.
	}

}

class GoogleMapsView extends Handler
{
	var $field;
	var $map_vars = array('fid', 'latitude', 'longitude', 'angle', 'width', 'length', 'zoom');

	function has_permission()
	{
		global $lr_session;
		if (!$this->field) {
			return error_exit("That field does not exist");
		}
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

class GoogleMapsEdit extends GoogleMapsView
{
	function has_permission()
	{
		global $lr_session;
		if (!$this->field) {
			return error_exit("That field does not exist");
		}
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
