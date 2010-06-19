<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title>{ $title | default:$app_name }</title>
    {include file="components/css.tpl"}
    <link rel="shortcut icon" href="/favicon.ico" />
    <script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key={$gmaps_key}" type="text/javascript"></script>
    <script src="{$base_url}/js/map_common.js" type="text/javascript"></script>
    <script type="text/javascript">
    var base_url     = '{$base_url}';
    var center_point = new GLatLng({$leaguelat}, {$leaguelng});
    {literal}
    //<![CDATA[

	function load() {
		if ( ! GBrowserIsCompatible()) {
			return;
		}
		var map = new GMap2(document.getElementById("map"));

		// Need to center before adding points.  We adjust center/zoom later with
		// GLatLngBounds
		resizeMap();
		map.setCenter(center_point, 11);
		map.setUIToDefault();

		GDownloadUrl(base_url + "/gmaps/allfields", function(data, responseCode) {

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
{/literal}
	//]]>
    </script>
  </head>
  <body onresize="resizeMap()" onunload="GUnload()" onload="load()">
    <div id="map" style="width: 100%; height: 500px"></div>
  </body>
</html>
