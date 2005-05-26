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

?>
<html>
<!-- thank you to follower for http://stuff.rancidbacon.com/google-maps-embed-how-to/ -->
<head>
<title>OCUA Fields</title>

<script src="http://google.com/maps?file=js&hl=en" type="text/javascript">
</script>


<script type="text/javascript">

_mSiteName = 'OCUA Fields';

_mUsePrintLink="";

function initMap() {
  //
  // frb
  //

  myMapApp = new _MapsApplication(document.getElementById("container_frb"),
				  document.getElementById("panel"),
				  document.getElementById("metapanel"),
				  document.getElementById("permalink"),
				  document.getElementById("toggle"),
				  document.getElementById("printheader"));
				  
  myMapApp.loadMap();

  /* Note: All XML & XSL files must be on same domain.
  */
  _loadXmlFileFromURL("/leaguerunner/gmaps/allfields", myMapApp);
  //_loadXmlFileFromURL("/leaguerunner/data/demo2.xml", myMapApp);

}

t.dump = function(a) {alert(a);} //debugging

function _loadXmlFileFromURL(url, mapApp) {
    //
    // Loads the specified external XML file into the map view.
    //
    //  frb
    //
    // NOTE: URL must be on same domain as page.
    
    var  _getter = _XMLHttp.create();

    _getter.open("GET", url);

    _getter.onreadystatechange=function() {
      if (_getter.readyState==4) {
           mapApp.loadXML(_getter.responseText);
      }
    }

    _getter.send(null); // Whoops, IE *needs* this to be last, Moz is lenient.
}


</script>

<style type="text/css">
#panel table {
  border:solid 1px grey;
  width:50%;
  margin-bottom: 5px;
}

#panel table td:first-child  {
  width: 24px;
}

.label {
  font-size:smaller;
  font-family:sans-serif;
};
</style>

</head>

<body onload="initMap()">

<!---->

<!-- Note: Map height always maximum? -->
<div style="position:absolute;left:5px;top:10px;right:5px;border:solid thin grey;">
  <div id="container_frb" style="float:right;width:60%;" ></div>
  <div style="float:left;position:relative;left:10px;width:35%">
    <div id="toggle" style="position:absolute;top:0px:left:10px;font-family:sans-serif;font-size:smaller;">&nbsp;</div>
    <div style="position:absolute;top:30px;left:5px;">
      <div id="panel" style="height:90%;width:100%;"> </div>
      <div id="metapanel"></div>
      <div id="permalink"></div>
      <div id="printheader"></div>
    </div>
  </div>
</div>

<!--

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
  <request></request>
  <center lat="45.247528" lng="-75.618293" />
  <span lat="0.3" lng="0.3"/>
  <overlay panelStyle="/leaguerunner/data/fields-sidepanel.xsl">
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
   <location infoStyle="/leaguerunner/data/fields-geocodeinfo.xsl" id="<?php print $location->id ?>">
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

		$rating_to_marker = array (
			'A' => 'markerA.png',
			'B' => 'markerB.png',
			'C' => 'markerC.png',
			'D' => 'markerD.png',
			'' => 'marker.png',
			'?' => 'marker.png',
		);
		while( $field = db_fetch_object( $result ) ) {
			if($field->location_street == '' || !$field->latitude || !$field->longitude) {
				continue;
			}
			$location->id = $field->fid;
			$location->name = $field->name;
			$location->street = $field->location_street;
			$location->city = $field->location_city;
			$location->province = $field->location_province;
			$location->latitude = $field->latitude;
			$location->longitude = $field->longitude;
			$location->image = "http://maps.google.com/mapfiles/" . $rating_to_marker[$field->rating];
			$this->render_location( $location );
		}
		$this->render_footer();
		exit(); // To prevent header/footer being displayed.
	}
	
}

?>
