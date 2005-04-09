<?php

function gmaps_dispatch()
{
	$op = arg(1);

	switch($op) {
		case 'allfields':
			$obj = new GoogleMapsAllFields;
			break;
		case 'getfile':
			$obj = new GoogleMapsPassthru;
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

class GoogleMapsPassthru extends Handler
{
	function has_permission()
	{
		return true;
	}

	function process ()
	{
		$file = $_GET['q'];
		$ary = split("\/",$file, 3);

		if( substr($ary[2],0,7) != 'http://' ) {
			exit;
		}

		$chunksize = 1024;
		$handle = fopen($ary[2], 'rb');
		$buffer = '';
		if ($handle === false) {
			exit;
		}
		
		$buffer = fread($handle, $chunksize);
		if (substr($buffer,0,19) == '<?xml version="1.0"') {
			header("Content-type: text/xml");
			print $buffer;
			while (!feof($handle)) {
				$buffer = fread($handle, $chunksize);
				print $buffer;
			}
		}
		
		fclose($handle);
		exit;
	}
}

class GoogleMapsHTMLPage extends Handler
{
	function has_permission()
	{
		return true;
	}

	function process ()
	{

		$basepath = 'http://testing.ocua.ca/leaguerunner/misc';
?>
<html>
<!-- Based on original Google Maps page -->
<head>

<style type="text/css">

img {
border: 0;
}

.noscreen {
display: none;

}
</style>


<script type="text/javascript" src="http://maps.google.com/maps?file=js">
</script>

<script type="text/javascript" src="<?php print $basepath ?>/gmaps-original-5.js">
</script>

<script type="text/javascript" src="<?php print $basepath ?>/gmaps-standalone.js">
</script>

</head>
<body onLoad="_initStandAlone()">
<div id="toggle" style="font-family:sans-serif;text-align:right;font-size:smaller;border: thin solid lightblue;border-bottom-style: none;padding:2px;">&nbsp;</div>
<div id="page">

  <div id="map"></div>

    <div id="rhs" style="display:none;">
	    <div id="metapanel"></div>
		    <div id="panel"></div>
			  </div>

			  </div>

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
		print '<?';
?>
xml version="1.0" encoding="ISO-8859-1" ?>
<page>
  <title>Ottawa-Carleton Ultimate Association -- Fields</title>
  <query>OCUA Fields</query>
  <center lat="45.247528" lng="-75.618293" />
  <span lat="0.3" lng="0.3"/>
  <overlay panelStyle="http://maps.google.com/maps?file=lp&amp;hl=en">
<?php
	}

	function render_footer()
	{
		print  "\n  </overlay>\n</page>";
	}

	function render_location( $location )
	{
 #  <location infoStyle="http://maps.google.com/maps?file=lp&amp;hl=en">
?>
   <location infoStyle="http://maps.google.com/mapfiles/geocodeinfo.xsl">
	  <point lat="<?php print $location->latitude ?>" lng="<?php print $location->longitude ?>" />
	  <icon image="<?php print $location->image ?>" class="local" />
	  <info>
	    <title xml:space="preserve">
		  <?print $location->name ?>
		</title>
	    <address>
		  <line><?php print $location->street ?></line>
		  <line><?php print $location->city . ", " . $location->province ?>, Canada</line>
	    </address>
	  </info>
<?php
?>
    </location>
<?php
	}
	
	function process()
	{
		$this->render_header();
		$result = field_query( array( '_extra' => 'ISNULL(parent_fid)', '_order' => 'f.fid') );
		while( $field = db_fetch_object( $result ) ) {
			if($field->location_street == '') {
				continue;
			}
			$location->name = $field->name;
			$location->street = $field->location_street;
			$location->city = $field->location_city;
			$location->province = $field->location_province;
			$location->latitude = $field->latitude;
			$location->longitude = $field->longitude;

			$rating_to_marker = array (
				'A' => 'markerA.png',
				'B' => 'markerB.png',
				'C' => 'markerC.png',
				'D' => 'markerD.png',
				'' => 'marker.png',
				'?' => 'marker.png',
			);
			$location->image = "http://maps.google.com/mapfiles/" . $rating_to_marker[$field->rating];
			$this->render_location( $location );
		}
		$this->render_footer();
		exit(); // To prevent header/footer being displayed.
	}
	
}

?>
