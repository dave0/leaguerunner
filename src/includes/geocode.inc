<?php
	function geocode_address ( $street, $city, $province )
	{
		$gmaps_key = variable_get('gmaps_key', '');
		if( ! $gmaps_key ) {
			return;
		}

		# http://maps.google.com/maps/geo?q=1600+Amphitheatre+Parkway,+Mountain+View,+CA&output=csv&key=abcdefg
		$uri = 'http://maps.google.com/maps/geo?q='
				. urlencode($street)
				. ','
				. urlencode($city)
				. ','
				. urlencode($province)
				. "&output=csv&key=$gmaps_key";

		#$body = http_parse_message(http_get($uri))->body; 
		$body = file_get_contents($uri); 

		$lines = preg_split("/\n/", $body);
		$best  = array(
			'accuracy' => 0,
		);
		foreach ($lines as $line) {
			list($status, $accuracy, $lat, $long) = preg_split('/,/', $line);
			if( $accuracy > $best['accuracy'] ) {
				$best['accuracy'] = $accuracy;
				$best['lat'] = $lat;
				$best['long'] = $long;
			}
		}

		if( $best['lat'] ) {
			return array( $best['lat'], $best['long']);
		}

		return array(0,0);
	}
?>
