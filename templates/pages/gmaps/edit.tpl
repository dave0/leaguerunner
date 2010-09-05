<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title>{ $title | default:$app_name }</title>
    {include file="components/css.tpl"}
    <link rel="shortcut icon" href="/favicon.ico" />
    <script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key={$gmaps_key}" type="text/javascript"></script>
    <script src="{$base_url}/js/map_common.js" type="text/javascript"></script>
    <script src="{$base_url}/js/map_view.js" type="text/javascript"></script>
    <script src="{$base_url}/js/map_edit.js" type="text/javascript"></script>
    <script type="text/javascript">
    //<![CDATA[
    var lr_path      = '{$base_url}';
    var leaguelat    = {$leaguelat};
    var leaguelng    = {$leaguelng};
    var drag         = true;
    var name         = '{$name}';
    var address      = '{$address}';
    var full_address = '{$full_address}';
    var fid          = {$fid};
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
<body onresize="resizeMap()" onload="initialize_edit()" onunload="GUnload()" style="padding: 0;">
<div id="map" style="margin: 0; padding: 0; width: 70%; height: 400px; float: left;"></div>
    <div id="form" style="margin: 0; padding-left: 1em; width: 27%; float: left;">
	<h3>{$name}</h3>
	<p>{$address}</p>

	<p>Angle:
	<span id="angle"></span>
	<input type="submit" onclick="return update_angle(-10)" value="-10">
	<input type="submit" onclick="return update_angle(-1)" value="-">
	<input type="submit" onclick="return update_angle(1)" value="+">
	<input type="submit" onclick="return update_angle(10)" value="+10">
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


	<form action="{$request_uri}" method="post" name="layout">
	<input type="hidden" name="fid" value="{$fid}" />
	<input type="hidden" name="latitude" value="{$latitude}" />
	<input type="hidden" name="longitude" value="{$longitude}" />
	<input type="hidden" name="angle" value="{$angle}" />
	<input type="hidden" name="width" value="{$width}" />
	<input type="hidden" name="length" value="{$length}" />
	<input type="hidden" name="zoom" value="{$zoom}" />
	<p><input type="submit" name="submit" value="Save Changes" onclick="return check()"/>

    </div>
</body>
</html>
