<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title>{$name}</title>
    {include file="components/css.tpl"}
    <link rel="shortcut icon" href="/favicon.ico" />
    <script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key={$gmaps_key}" type="text/javascript"></script>
    <script src="{$base_url}/js/map_common.js" type="text/javascript"></script>
    <script src="{$base_url}/js/map_view.js" type="text/javascript"></script>
    <script type="text/javascript">
    //<![CDATA[
    var lr_path      = '{$base_url}';
    var name         = '{$name}';
    var address      = '{$address}';
    var full_address = '{$full_address}';
    var fid          = {$fid};
    var num          = {$num};
    var latitude     = {$latitude};
    var longitude    = {$longitude};
    var angle        = {$angle};
    var width        = {$width};
    var length       = {$length};
    var zoom         = {$zoom};

    {$otherfields}
    //]]>
    </script>
  </head>
<body onresize="resizeMap()" onload="initialize_view()" onunload="GUnload()" style="padding: 0;">
<div id="map" style="margin: 0; padding: 0; width: 70%; height: 400px; float: left;"></div>
    <div style="margin: 0; padding-left: 1em; width: 27%; float: left;">
	<h3>{$name}</h3>
	<p>{$address}</p>

	<p>Get directions to this field from:
	<form action="javascript:getDirections()">
	<input type="text" size=30 maxlength=50 name="saddr" id="saddr" value="{$home_addr}" /><br>
	<input value="Get Directions" type="submit"><br>
	Walking <input type="checkbox" name="walk" id="walk" /><br>
	Biking <input type="checkbox" name="highways" id="highways" />
	<div id="directions">
	</div>
    </div>
</body>
</html>
