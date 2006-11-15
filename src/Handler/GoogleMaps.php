<?php

function gmaps_dispatch()
{
    $op = arg(1);

    switch($op) {
        case 'allfields':
            $obj = new GoogleMapsAllFields;
            break;
        default:
            $obj = new GoogleMapsHTMLPage;
    }
    
    return $obj;
}

function gmaps_permissions()
{
    return true;
}

class GoogleMapsHTMLPage extends Handler
{
    function has_permission()
    {
        return true;
    }

    function process ()
    {
		$gmaps_key = variable_get('gmaps_key', 'No google maps key found');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
 <html xmlns="http://www.w3.org/1999/xhtml">
 <head>
 <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
 <title>Fields</title>
 <script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=<? echo $gmaps_key ?>" type="text/javascript"></script>
<script type="text/javascript">
//<![CDATA[

function load() {
    if ( ! GBrowserIsCompatible()) { 
        return;
    }
    var map = new GMap2(document.getElementById("map"));

    // Need to center before adding points.  We adjust center/zoom later with
    // GLatLngBounds
    map.setCenter(new GLatLng(45.247528,-75.618293), 13);
    map.addControl(new GSmallMapControl());
    map.addControl(new GMapTypeControl());

    GDownloadUrl("/leaguerunner/gmaps/allfields", function(data, responseCode) { 
    
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
</head>

<body onunload="GUnload()" onload="load()">
<div id="map" style="width: 800px; height: 500px"></div>
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
        print "<balloon><![CDATA[<a href=\"" . url('field/view/'. $field->fid) . "\">$field->name</a> ($field->code)]]></balloon>\n";
        print "<tooltip>$field->name ($field->code)</tooltip>\n";
        print "<image>" . url('image/pins/' . $field->code . '.png') . "</image>";
        print "</marker>\n";
    }

    function process()
    {
        $this->render_header();
        $result = field_query( array( '_extra' => 'ISNULL(parent_fid)', '_order' => 'f.fid') );

        while( $field = db_fetch_object( $result ) ) {
            if(!$field->latitude || !$field->longitude) {
                continue;
            }
            $this->render_field( $field );
        }
        
        $this->render_footer();
        exit(); // To prevent header/footer being displayed.
    }
    
}

?>
