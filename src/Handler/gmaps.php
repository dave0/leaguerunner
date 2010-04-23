<?php
class gmaps extends Handler
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
?>
