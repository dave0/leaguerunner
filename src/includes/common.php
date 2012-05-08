<?php
/*
 * Common code for use throughout Leaguerunner.
 * Some of these functions are borrowed and adapted from Drupal
 * (http://www.drupal.org/) -- credit where credit is due.
 */

/*
 * HTTP-mangling
 */
function local_redirect($url)
{
    global $headers_sent;
    $url = str_replace("&amp;", "&", $url);
	if(preg_match("/^(http|ftp|mailto):/", $url) > 0) {
		$url = $url;
	} else {
		$url = url($url);
	}

    if ($headers_sent == 0) {
        header("Location: $url");

        /*
        * The "Location" header sends a REDIRECT status code to the http
        * daemon.  In some cases this can go wrong, so we make sure none of
        * the code /below/ gets executed when we redirect.
        */
        exit();
    }
    else {
        print "<html><body>";
        print "<center>";
        print "Your results are ready.  Please click the following link. <br> ";
        print "<a href=\"$url\">$url</a>";
        print "</center>";
        // Tony turned off automatic redirect so that coordinators get a chance to read the text
        // and, this does NOT wait 30 seconds, rather it's closer to 3 seconds...
        //print "<script language=\"javascript\">";
        //print "setTimeout('location.href=\"$url\"', 30000);";  // Redirect them in 30 seconds
        //print "</script>";
        print "</body></html>";
        exit();
    }
}

function valid_input_data($data) {

  if (is_array($data) || is_object($data)) {
    /*
    ** Form data can contain a number of nested arrays.
    */

    foreach ($data as $key => $value) {
      if (!valid_input_data($value)) {
        return 0;
      }
    }
  }
  else {
    /*
    ** Detect evil input data.
    */

    // check strings:
    $match  = preg_match("/\Wjavascript\s*:/i", $data);
    $match += preg_match("/\Wexpression\s*\(/i", $data);
    $match += preg_match("/\Walert\s*\(/i", $data);

    // check attributes:
    $match += preg_match("/\W(dynsrc|datasrc|data|lowsrc|on[a-z]+)\s*=[^>]+?>/i", $data);


    // check tags:
    $match += preg_match("/<\s*(applet|script|object|style|embed|form|blink|meta|html|frame|iframe|layer|ilayer|head|frameset|xml)/i", $data);

    if ($match) {
      return 0;
    }
  }

  return 1;
}


function queryPickle( $q )
{
	return base64_encode($q);
}
function queryUnpickle( $q )
{
	return base64_decode($q);
}

function request_uri() {
  /*
  ** Since request_uri() is only available on Apache, we generate
  ** equivalent using other environment vars.
  */

  if (isset($_SERVER["REQUEST_URI"])) {
    $uri = $_SERVER["REQUEST_URI"];
  }
  else {
    $uri = $_SERVER["PHP_SELF"] ."?". $_SERVER["QUERY_STRING"];
  }

  return check_url($uri);
}

/*
 * Global configuration variables
 */
function variable_init( $conf = array() )
{
	global $conf,$dbh;
	$sth = $dbh->prepare('SELECT * FROM variable');
	$sth->execute();
	while ($variable = $sth->fetchObject()) {
		if( !isset($conf[$variable->name]) && $variable->name != '_SchemaVersion' ) {
			$conf[$variable->name] = unserialize($variable->value);
		}
	}
	return $conf;
}

function variable_get($name, $default)
{
	global $conf;
	if( isset($conf[$name]) ) {
		return $conf[$name];
	} else {
		return $default;
	}
}

function variable_set($name, $value)
{
	global $conf,$dbh;

	// TODO: These queries should be wrapped in a transaction
	$sth = $dbh->prepare('DELETE FROM variable WHERE name = ?');
	$sth->execute(array($name));


	$sth = $dbh->prepare('INSERT INTO variable (name, value) VALUES (?, ?)');
	$sth->execute(array($name, serialize($value)));
	$conf[$name] = $value;
}

function variable_del($name)
{
	global $conf,$dbh;
	$sth = $dbh->prepare('DELETE FROM variable WHERE name = ?');
	$sth->execute(array($name));
	unset($conf[$name]);
}

/*
 * HTML-generation functions
 */

function simple_tag($name, $content, $attributes = array())
{
	$t = array();
	foreach ($attributes as $key => $value) {
		$t[] = "$key=\"$value\"";
	}

	return "<$name". (count($t) ? " " : "") . implode($t, " ") .">$content</$name>";
}

function para($text, $attributes = array())
{
	return simple_tag("p", $text, $attributes);
}

function pre($text, $attributes = array())
{
	return simple_tag("pre", $text, $attributes);
}

function table_cell($cell, $header = 0) {
  $attributes = '';
  if (is_array($cell)) {
    $data = $cell["data"];
    foreach ($cell as $key => $value) {
      if ($key != "data")  {
        $attributes .= " $key=\"$value\"";
      }
    }
  }
  else {
    $data = $cell;
  }

  if ($header) {
    $output = "<th$attributes>$data</th>";
  }
  else {
    $output = "<td$attributes>$data</td>";
  }

  return $output;
}

function table($header, $rows, $attrArray = array())
{

  $attributes = '';
  foreach ($attrArray as $key => $value) {
    $attributes .= " $key=\"$value\"";
  }

  $output = "<table$attributes>\n";

  /*
  ** Emit the table header:
  */

  if (is_array($header)) {
    $output .= "<tr>";
    foreach ($header as $cell) {
      if (is_array($cell) && $cell["field"]) {
        $cell = tablesort($cell, $header);
      }
      $output .= table_cell($cell, 1);
    }
    $output .= "</tr>\n";
  }

  /*
  ** Emit the table rows:
  */

  if (is_array($rows)) {
    foreach ($rows as $number => $row) {
	  if(array_key_exists( 'alternate-colours', $attrArray) && $attrArray['alternate-colours']) {
        if ($number % 2 == 1) {
          $output .= "<tr class=\"light\">";
        }
        else {
          $output .= "<tr class=\"dark\">";
        }
      } else {
        $output .= "<tr>";
	  }
      foreach ($row as $cell) {
        $output .= table_cell($cell, 0);
      }
      $output .= "</tr>\n";
    }
  }

  $output .= "</table>\n";

  return $output;
}



function l($text, $query = NULL, $attributes = array())
{
	if(0 < preg_match("/^(http|ftp|mailto):/", $query)) {
		$attributes['href'] = $query;
	} else if(substr($query,0,1) == '/') {
		$attributes['href'] = $query;
	} else {
		$attributes['href'] = url($query);
	}
	return simple_tag("a", check_form($text), $attributes);
}


function url($url = NULL, $query = NULL) {
  global $CONFIG;
  static $script;

  $base_url = 'http://' . $_SERVER["HTTP_HOST"] . $CONFIG['paths']['base_url'];
  $cleanURL = variable_get('clean_url', 0);

  if (empty($script)) {
    /*
    ** On some webservers such as IIS we can't omit "index.php".  As such we
    ** generate "index.php?q=foo" instead of "?q=foo" on anything that is not
    ** Apache.
    */
    $script = (strpos($_SERVER["SERVER_SOFTWARE"], "Apache") === false) ? "index.php" : "";
  }

  if (!$cleanURL) {
    if (isset($url)) {
      if (isset($query)) {
        return "$base_url/$script?q=$url&amp;$query";
      }
      else {
        return "$base_url/$script?q=$url";
      }
    }
    else {
      if (isset($query)) {
        return "$base_url/$script?$query";
      }
      else {
        return "$base_url/";
      }
    }
  }
  else {
    if (isset($url)) {
      if (isset($query)) {
        return "$base_url/$url?$query";
      }
      else {
        return "$base_url/$url";
      }
    }
    else {
      if (isset($query)) {
        return "$base_url/$script?$query";
      }
      else {
        return "$base_url/";
      }
    }
  }
}

// TODO: replaced by components/street_address.tpl
function format_street_address( $street, $city, $province, $country, $postalcode)
{
	$arr = array ($city, $province);
	if (! empty ($country))
		$arr[] = $country;
	$foo =  "$street<br />\n" . join (', ', $arr) . "<br />\n$postalcode";
	$prov_abbr = substr($province,0,2);
	$street_uri = strtr($street, array(' ' => '+'));
	$foo .= "<br />[&nbsp;<a target=\"_blank\" href=\"http://maps.google.com?q=$street_uri,+$city,+$province&hl=en\">maps.google.com</a>&nbsp;|&nbsp;<a target=\"_blank\" href=\"http://www.mapquest.com/maps/map.adp?zoom=7&city=$city&state=$prov_abbr&address=$street_uri\">MapQuest</a>&nbsp;]";
	return $foo;
}

function check_url($uri) {
  $uri = check_form($uri, ENT_QUOTES);

  /*
  ** We replace ( and ) with their entity equivalents to prevent XSS
  ** attacks.
  */

  $uri = strtr($uri, array("(" => "&040;", ")" => "&041;"));

  return $uri;
}

function check_form($input, $quotes = ENT_QUOTES)
{
	return htmlspecialchars($input, $quotes);
}

/* return array from key matches instead of element matches */
function preg_grep_keys( $pattern, $input, $flags = 0 )
{
	$keys = preg_grep( $pattern, array_keys( $input ), $flags );
	$vals = array();
	foreach ( $keys as $key )
	{
		$vals[$key] = $input[$key];
	}
	return $vals;
}

/*
 * Form-generation functions
 */

function form($form, $method = "post", $action = 0, $options = 0)
{
	if (!$action) {
		$action = request_uri();
	}
	return "<form action=\"$action\" method=\"$method\"". ($options ? " $options" : "") .">\n$form\n</form>\n";
}

/* Displays a form item.  Called by other form_ functions */
function form_item($title, $value, $description = 0)
{
	if ($title) {
		$title = "<label>$title</label>";
	} else {
		# Ensure that if $title == 0, we blank it instead.
		$title = "";
	}
	return $title . $value . ($description ? "<div class=\"description\">$description</div>" : "") ."\n";
}

function form_group($legend, $group, $description = NULL)
{
	return "<fieldset>" . ($legend ? "<legend>$legend</legend>" : "") . $group . ($description ? "<div class=\"description\">$description</div>" : "") . "</fieldset>\n";
}

function form_radio($title, $name, $value = 1, $checked = 0, $description = 0)
{
	return form_item(0, "<input type=\"radio\" name=\"$name\" value=\"". $value ."\"". ($checked ? " checked=\"checked\"" : "") ." /> $title", $description);
}

function form_checkbox($title, $name, $value = 1, $checked = 0, $description = 0)
{
	return form_item(0, "<input type=\"checkbox\" name=\"$name\" value=\"". $value ."\"". ($checked ? " checked=\"checked\"" : "") ." /> $title", $description);
}

function form_textfield($title, $name, $value, $size, $maxlength, $description = 0)
{
	$size = $size ? " size=\"$size\"" : "";
	return form_item($title, "<input type=\"text\" maxlength=\"$maxlength\" name=\"$name\"$size value=\"". check_form($value) ."\" />", $description);
}

function form_password($title, $name, $value, $size, $maxlength, $description = 0)
{
	$size = $size ? " size=\"$size\"" : "";
	return form_item($title, "<input type=\"password\" maxlength=\"$maxlength\" name=\"$name\"$size value=\"". check_form($value) ."\" />", $description);
}

function form_textarea($title, $name, $value, $cols, $rows, $description = 0)
{
	$cols = $cols ? " cols=\"$cols\"" : "";
	return form_item($title, "<textarea wrap=\"virtual\"$cols rows=\"$rows\" name=\"$name\" id=\"$name\">". check_form($value) ."</textarea>", $description);
}

function form_radiogroup($title, $name, $value, $options, $description = 0)
{
	$radio = "";
	if (count($options) > 0) {
		foreach ($options as $key=>$choice) {
			$radio .= form_radio($choice,$name,$key, ($key == $value), '') . '<br />';
		}
		return form_item($title, $radio, $description);
	}
}

function __form_select($name, $value, $options, $extra = 0, $multiple = 0)
{
	if (count($options) > 0) {
		foreach ($options as $key=>$choice) {
			$select .= "<option value=\"$key\"". (is_array($value) ? (in_array($key, $value) ? " selected=\"selected\"" : "") : ($value == $key ? " selected=\"selected\"" : "")) .">". check_form($choice) ."</option>";
		}
		return "<select name=\"$name". ($multiple ? "[]" : "") ."\"". ($multiple ? " multiple " : "") . ($extra ? " $extra" : "") .">$select</select>";
	}
}


function form_select($title, $name, $value, $options, $description = 0, $extra = 0, $multiple = 0)
{
	if (count($options) > 0) {
		return form_item($title, __form_select($name, $value, $options, $extra, $multiple), $description);
	}
}

function form_radios($title, $name, $value, $options, $description = 0)
{
	if (count($options) > 0) {
		$output = '';
		foreach ($options as $key=>$choice) {
			$output .= form_radio($choice, $name, $key, ($key == $value));
		}
		return form_item($title, $output, $description);
	}
}

function form_hidden($name, $value)
{
	return "<input type=\"hidden\" name=\"$name\" value=\"". check_form($value) ."\" />\n";
}

function form_submit($value, $name = "submit", $javascript = "")
{
	return "<input type=\"submit\" name=\"$name\" value=\"". check_form($value) ."\" $javascript/>\n";
}

function form_reset($value, $name = "reset")
{
	return "<input type=\"reset\" name=\"$name\" value=\"". check_form($value) ."\" />\n";
}

/**
 **  Data Validation
 **/
function validate_nonhtml ( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	if ( preg_match("/</", $string) ) {
		return false;
	}
	return true;
}

function validate_yyyymmdd_input ( $date )
{
	list( $year, $month, $day) = preg_split("/[\/-]/", $date);
	return validate_date_input($year, $month, $day);
}

function validate_date_input ( $year, $month, $day )
{
	if( !(validate_nonblank($year) && validate_nonblank($month) && validate_nonblank($day)) ) {
		return false;
	}

	$current = localtime(time(),1);
	$this_year = $current['tm_year'] + 1900;

	/* Checkdate doesn't check that the year is sane, so we have to
	 * do it ourselves.  Our sanity window is that anything earlier
	 * than 80 years ago, and anything 5 years in the future must be
	 * bogus.
	 */
	if( ($year < $this_year - 80) || ($year > $this_year + 5) ) {
		return false;
	}

	if(!checkdate($month, $day, $year) ) {
		return false;
	}
	return true;
}

function validate_number ( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	return is_numeric($string);
}

function validate_score_value ( $string )
{
	if( ! validate_number( $string ) ) {
		return false;
	}

	return $string >= 0;
}

function validate_name_input ( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	if ( ! preg_match("/^[\w-\. ']+$/", $string) ) {
		return false;
	}
	return true;
}

function validate_telephone_input( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	if ( ! preg_match("/^\(?\d{3}\)?\s*[-.]?\s*\d{3}\s*[-.]?\s*\d{4}\s*([ext\.]*\s*\d+)?$/", $string) ) {
		return false;
	}
	return true;
}

function validate_email_input ( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	if ( ! preg_match("/^[\w-\.\+\']+\@([\w-]+\.)+[\w-]+$/", $string) ) {
		return false;
	}
	return true;
}

/**
 * Validates an address
 *
 * @param string $street Street address (incl street name, house number,etc)
 * @param string $city   City name
 * @param string $prov   Province/State/Territory abbreviation (2 letter)
 * @param string $postalcode Postal or Zip code
 * @param string $country Country abbreviation
 */
function validate_address ( $street, $city, $prov, $postalcode, $country )
{
    if( $country == 'Canada' ) {
        return validate_ca_address( $street, $city, $prov, $postalcode );
    } else if( $country == 'United States' ) {
        return validate_us_address( $street, $city, $prov, $postalcode );
    } else {
        return array( "That is not a valid country" );
    }
}

/**
 *
 * Validates a Canadian address
 * @param string $street Street address (incl street name, house number,etc)
 * @param string $city   City name
 * @param string $prov   Province/Territory abbreviation (2 letter)
 * @param string $postalcode Postal code
 */
function validate_ca_address ( $street, $city, $prov, $postalcode )
{
    $errors = array();

    # Street and city must only be non-HTML
    if( ! validate_nonhtml( $street ) ) {
        array_push( $errors, 'You must supply a valid street address');
    }

    if( ! validate_nonhtml( $city ) ) {
        array_push( $errors, 'You must supply a city');
    }

    if( ! validate_province_full( $prov ) ) {
        array_push( $errors, 'You must select a valid Canadian province or territory');
    }

    if( ! validate_canadian_postalcode( $postalcode, $prov ) ) {
        array_push( $errors, "You must enter a valid Canadian postalcode for $prov");
    }

	return $errors;
}

/**
 *
 * Validates a USA address
 * @param string $street Street address (incl street name, house number,etc)
 * @param string $city   City name
 * @param string $state  State/Territory abbreviation (2 letter)
 * @param string $zip    Zip code
 */
function validate_us_address ( $street, $city, $state, $zip)
{
    $errors = array();

    # Street and city must only be non-HTML
    if( ! validate_nonhtml( $street ) ) {
        array_push( $errors, 'You must supply a valid street address');
    }

    if( ! validate_nonhtml( $city ) ) {
        array_push( $errors, 'You must supply a city');
    }

    if( ! validate_state_full( $state ) ) {
        array_push( $errors, 'You must select a valid US state or territory');
    }

    if( ! validate_us_zipcode( $zip, $state ) ) {
        array_push( $errors, 'You must enter a valid US Zip code');
    }

	return $errors;
}

/**
 * Validate Canadian provinces
 *
 * @param string $prov Full province name
 */
function validate_province_full( $prov )
{
        switch (strtolower($prov)) {
            case 'alberta':
            case 'british columbia':
            case 'manitoba':
            case 'new brunswick':
            case 'newfoundland':
            case 'newfoundland and labrador':
            case 'northwest territories':
            case 'nova scotia':
            case 'nunavut':
            case 'ontario':
            case 'prince edward island':
            case 'quebec':
            case 'saskatchewan':
            case 'yukon':
                return true;
        }
        return false;
}

/**
 * Validate a Canadian postalcode
 *
 * Code borrowed from the BSD-licensed PEAR package 'Validate', which is too
 * large and bloated to be used here.
 */
function validate_canadian_postalcode ( $postalcode, $prov )
{

	if( !validate_nonblank($postalcode) ) {
		return false;
	}

    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    switch (strtoupper($prov)) {
        case 'NF':          // Newfoundland
        case 'NEWFOUNDLAND':
        case 'NEWFOUNDLAND AND LABRADOR':
            $sRegExp = 'A';
            break;
        case 'NS':          // Nova Scotia
        case 'NOVA SCOTIA':
            $sRegExp = 'B';
            break;
        case 'PE':          // Prince Edward Island
        case 'PRINCE EDWARD ISLAND':
            $sRegExp = 'C';
            break;
        case 'NB':          // New Brunswick
        case 'NEW BRUNSWICK':
            $sRegExp = 'E';
            break;
        case 'QC':          // Quebec
        case 'QUEBEC':
            $sRegExp = '[GHJ]';
            break;
        case 'ON':          // Ontario
        case 'ONTARIO':
            $sRegExp = '[KLMNP]';
            break;
        case 'MB':          // Manitoba
        case 'MANITOBA':
            $sRegExp = 'R';
            break;
        case 'SK':          // Saskatchewan
        case 'SASKATCHEWAN':
            $sRegExp = 'S';
            break;
        case 'AB':          // Alberta
        case 'ALBERTA':
            $sRegExp = 'T';
            break;
        case 'BC':          // British Columbia
        case 'BRITISH COLUMBIA':
            $sRegExp = 'V';
            break;
        case 'NT':          // Northwest Territories
        case 'NORTHWEST TERRITORIES':
        case 'NU':          // Nunavut
        case 'NUNAVUT':
            $sRegExp = 'X';
            break;
        case 'YK':          // Yukon Territory
        case 'YUKON':
            $sRegExp = 'Y';
            break;
        default:
            return false;
    }

    $sRegExp .= '[0-9][' . $letters . '][ \t-]*[0-9][ ' . $letters . '][0-9]';
    $sRegExp = '/^' . $sRegExp . '$/';


    return (bool) preg_match($sRegExp, strtoupper($postalcode));
}

/**
 * Validate US states
 *
 * @param string $prov Full state name
 */
function validate_state_full( $prov )
{
        switch (strtolower($prov)) {
			case 'alabama':
			case 'alaska':
			case 'arizona':
			case 'arkansas':
			case 'california':
			case 'colorado':
			case 'connecticut':
			case 'delaware':
			case 'florida':
			case 'georgia':
			case 'hawaii':
			case 'idaho':
			case 'illinois':
			case 'indiana':
			case 'iowa':
			case 'kansas':
			case 'kentucky':
			case 'louisiana':
			case 'maine':
			case 'maryland':
			case 'massachusetts':
			case 'michigan':
			case 'minnesota':
			case 'mississippi':
			case 'missouri':
			case 'montana':
			case 'nebraska':
			case 'nevada':
			case 'new hampshire':
			case 'new jersey':
			case 'new mexico':
			case 'new york':
			case 'north carolina':
			case 'north dakota':
			case 'ohio':
			case 'oklahoma':
			case 'oregon':
			case 'pennsylvania':
			case 'rhode island':
			case 'south carolina':
			case 'south dakota':
			case 'tennessee':
			case 'texas':
			case 'utah':
			case 'vermont':
			case 'virginia':
			case 'washington':
			case 'west virginia':
			case 'wisconsin':
			case 'wyoming';
                return true;
        }
        return false;
}

function validate_us_zipcode ( $zipcode, $state )
{
	// TODO: Real validation
	return true;
}

function validate_nonblank( $string )
{
	if( strlen(trim($string)) <= 0 ) {
		return false;
	}
	return true;
}

function validate_numeric_sotg ( $string ) {

	$int = intval($string);
	if( $int != $string ) {
		return false;
	}

	if( $int < 0 || $int > 10) {
		return false;
	}

	return true;
}

/*
 * Clean up a telephone number so that it's in a common format
 * Assumption: phone number has passed validate_telephone_input()
 */
function clean_telephone_number( $string )
{
	$matches = array();
	preg_match("/^\(?(\d{3})\)?\s*[-.]?\s*(\d{3})\s*[-.]?\s*(\d{4})\s*(?:[ext\.]*\s*(\d+))?$/", $string, $matches);

	$clean = "(" . $matches[1] . ") " . $matches[2] . "-" . $matches[3];
	if(count($matches) == 5) {
		$clean .= " x" . $matches[4];
	}

	return $clean;
}

/**
 ** Miscellaneous stuff.
 ** TODO: Some of this is old code that might be best removed entirely.
 */

/*
 * Generate a random password
 */
function generate_password()
{
	// Note that 0 and 1 are intentionally left out to prevent confusion with
	// the letters O and l in certain fonts.
	$chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789";
	$pass = '';
	for($i=0;$i<8;$i++) {
		$pass .= $chars{mt_rand(0,strlen($chars)-1)};
	}
	return $pass;
}

/**
 * Helper fn to get names of provinces for use in a select list
 */
function getProvinceNames()
{
	$names = array('Ontario','Quebec','Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland','Northwest Territories','Nunavut','Nova Scotia','Prince Edward Island','Saskatchewan','Yukon');
	$names2 = array('Alabama','Arizona','Arkansas','California','Colorado','Connecticut','Delaware','District of Columbia','Florida','Georgia','Guam','Hawaii','Idaho','Illinois','Indiana','Iowa','Kansas','Kentucky','Louisiana','Maine','Maryland','Massachusetts','Michigan','Minnesota','Mississippi','Missouri','Montana','Nebraska','Nevada','New Hampshire','New Jersey','New Mexico','New York','North Carolina','North Dakota','Northern Marianas Islands','Ohio','Oklahoma','Oregon','Pennsylvania','Puerto Rico','Rhode Island','South Carolina','South Dakota','Tennessee','Texas','Utah','Vermont','Virginia','Virgin Islands','Washington','West Virginia','Wisconsin','Wyoming');

	$ary = array();
	sort($names);
	while(list(,$name) = each($names)) {
		$ary[$name] = $name;
	}
	sort($names2);
	while(list(,$name) = each($names2)) {
		$ary[$name] = $name;
	}
	return $ary;
}

function GetCountryNames()
{
	$names = array('Canada', 'United States');
	$ary = array();
	while(list(,$name) = each($names)) {
		$ary[$name] = $name;
	}
	return $ary;
}

function GetCountryCode($string)
{
	switch($string)
	{
		case "Canada":
			return "CA";
		case "United States":
			return "US";
	}
}

/**
 * Helper fn to get shirt sizes for pulldown list
 */
function getShirtSizes()
{
    $sizes = array('Unknown','Womens XSmall', 'Womens Small', 'Womens Medium', 'Womens Large', 'Womens XLarge','Mens Small', 'Mens Medium', 'Mens Large', 'Mens XLarge');
	$ary = array();
	while(list(,$name) = each($sizes)) {
		$ary[$name] = $name;
	}
	return $ary;
}

/**
 * Helper fn to set up allowed values for an option list.
 * Use in preference to get_numeric_options, as that fn is deprecated
 */
function getOptionsFromRange( $start, $finish, $reverse = 0 )
{
	$result = array();
	if(!$reverse) {
		$result["---"] = "---";
	}
	for($i = $start; $i <= $finish; $i++) {
		$result[$i] = $i;
	}
	if($reverse) {
		$result["---"] = "---";
		$result = array_reverse($result,true);
	}

	return $result;
}

/**
 * Helper fn for generating time ranges
 */
function getMinutesFromTime( $time )
{
	$hour = floor( $time / 100 );
	$min = $time - $hour * 100;
	return $hour * 60 + $min;
}

function getOptionsFromTimeRange( $start, $finish, $increment )
{
	$result = array();
	$result["---"] = "---";
	for( $min = getMinutesFromTime( $start );
		 $min <= getMinutesFromTime( $finish );
		 $min += $increment )
	{
		$hour = floor( $min / 60 );
		$minute = $min - $hour * 60;
		$time = sprintf("%02d:%02d", $hour, $minute);
		$result[$time] = $time;
	}
	return $result;
}

/**
 * Helper fn to fetch 'allowed' values for a set or enum from a MySQL
 * database.
 */
function getOptionsFromEnum( $table, $col_name )
{
	global $dbh;
	$sth = $dbh->prepare("SHOW COLUMNS FROM $table LIKE ?");
	$sth->execute(array($col_name));
	$row = $sth->fetch();

	$str = preg_replace("/^(enum|set)\(/","",$row['Type']);
	$str = preg_replace("/\)$/","",$str);
	$str = str_replace("'","",$str);
	$ary = explode(',',$str);

	$options = array();
	$options["---"] = "---";
	foreach($ary as $val) {
		$options[$val] = $val;
	}

	return $options;
}

/**
 * Fetch options using the given query.
 * TODO: Fix this!  It should instead take a prepared $sth
 * not the raw sql.
 */
function getOptionsFromQuery( $sql, $data = array() )
{
	global $dbh;

	$sth = $dbh->prepare($sql);
	$sth->execute($data);

	$options[0] = "-- select from list --"	;
	while($row = $sth->fetch() ) {
		$options[$row['theKey']] = $row['theValue'];
	}
	return $options;
}

// Not needed for PHP 5.1 and higher, which includes this function
if ( !function_exists( 'fputcsv' ) )
{
	function fputcsv($filePointer, $dataArray, $delimiter = ',', $enclosure = '"')
	{
		// Write a line to a file
		// $filePointer = the file resource to write to
		// $dataArray = the data to write out
		// $delimeter = the field separator

		// Build the string
		$string = "";

		// No leading delimiter
		$writeDelimiter = FALSE;
		foreach($dataArray as $dataElement)
		{
			// Replaces a double quote with two double quotes
			$dataElement=str_replace("\"", "\"\"", $dataElement);

			// Adds a delimiter before each field (except the first)
			if($writeDelimiter)
				$string .= $delimiter;

			// Encloses each field with $enclosure and adds it to the string
			$string .= $enclosure . $dataElement . $enclosure;

			// Delimiters are used every time except the first.
			$writeDelimiter = TRUE;
		}

		// Append new line
		$string .= "\n";

		// Write the string to the file
		fwrite($filePointer,$string);
	}
}

/**
 * Calculate local sunset time for a timestamp, using system-wide location.
 */
function local_sunset_for_date( $timestamp )
{
	/*
	 * value of 90 degrees 50 minutes is the angle at which
	 * the sun is below the horizon.  This is the official
	 * sunset time.  Do not use "civil twilight" zenith
	 * value of 96 degrees. It's normally about 30 minutes
	 * later in the evening than official sunset, and there
	 * is some light until then, but it's too dark for safe
	 * play.
	 */
	$zenith = 90 + (50/60);

	/* TODO: eventually, use field's actual location rather than a
	 *       system-wide location?  This would be more correct in cities
	 *       with a large east/west spread, but might be confusing to some
	 */
	$lat      = variable_get('location_latitude', 45.42102);
	$long     = variable_get('location_longitude', -75.69525);

	$end_timestamp = date_sunset( $timestamp, SUNFUNCS_RET_TIMESTAMP, $lat, $long, $zenith, date('Z') / 3600);

	# Round down to nearest 5 minutes
	$end_timestamp = floor( $end_timestamp / 300 ) * 300;
	return strftime('%H:%M', $end_timestamp);
}

/**
 * Message text for account-related emails
 */
function _person_mail_text($messagetype, $variables = array() )
{
	// Check if the default has been overridden by the DB
	if( $override = variable_get('person_mail_' . $messagetype, false) ) {
		return strtr($override, $variables);
	} else {
		switch($messagetype) {
			case 'approved_subject':
				return strtr("%site Account Activation for %username", $variables);
			case 'approved_body_player':
				return strtr("Dear %fullname,\n\nYour %site account has been approved.\n\nYour new permanent member number is\n\t%memberid\nThis number will identify you for member services, discounts, etc, so please write it down in a safe place so you'll remember it.\n\nYou may now log in to the system at\n\t%url\nwith the username\n\t%username\nand the password you specified when you created your account.  You will be asked to confirm your account information and sign a waiver form before your account will be activated.\n\nThanks,\n%adminname", $variables);
			case 'approved_body_visitor':
				return strtr("Dear %fullname,\n\nYour %site account has been approved.\n\nYou may now log in to the system at\n\t%url\nwith the username\n\t%username\nand the password you specified when you created your account.  You will be asked to confirm your account information and sign a waiver form before your account will be activated.\n\nThanks,\n%adminname", $variables);
			case 'member_letter_subject':
				return strtr("%site %year Membership",$variables);
			case 'member_letter_body':
				return strtr("Dear %fullname,\n\nThank you for confirming your membership in the %site for %year. You are now eligible to be added to team rosters and enjoy all the other benefits of membership in the %site.\n\nThanks,\n%adminname", $variables);
			case 'password_reset_subject':
				return strtr("%site Password Reset",$variables);
			case 'password_reset_body':
				return strtr("Dear %fullname,\n\nSomeone, probably you, just requested that your password for the account\n\t%username\nbe reset.  Your new password is\n\t%password\nSince this password has been sent via unencrypted email, you should change it as soon as possible.\n\nIf you didn't request this change, don't worry.  Your account password can only ever be mailed to the email address specified in your %site system account.  However, if you think someone may be attempting to gain unauthorized access to your account, please contact the system administrator.", $variables);
			case 'dup_delete_subject':
				return strtr("%site Account Update", $variables);
			case 'dup_delete_body':
				return strtr("Dear %fullname,\n\nYou seem to have created a duplicate %site account.  You already have an account with the username\n\t%existingusername\ncreated using the email address\n\t%existingemail\nYour second account has been deleted.  If you cannot remember your password for the existing account, please use the 'Forgot your password?' feature at\n\t%passwordurl\nand a new password will be emailed to you.\n\nIf the above email address is no longer correct, please reply to this message and request an address change.\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);
			case 'dup_merge_subject':
				return strtr("%site Account Update", $variables);
			case 'dup_merge_body':
				return strtr("Dear %fullname,\n\nYou seem to have created a duplicate %site account.  You already had an account with the username\n\t%existingusername\ncreated using the email address\n\t%existingemail\nTo preserve historical information (registrations, team records, etc.) this old account has been merged with your new information.  You will be able to access this account with your newly chosen user name and password.\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);
			case 'captain_request_subject':
			case 'player_request_subject':
				return strtr("%site Request to Join Team", $variables);
			case 'captain_request_body':
				return strtr("Dear %fullname,\n\nYou have been invited to join the roster of the %site team %team playing on %day in the '%league' league.  We ask that you please accept or decline this invitation at your earliest convenience.  More details about %team may be found at\n%teamurl\n\nIf you accept the invitation, you will be added to the team's roster and your contact information will be made available to the team captain.  If you decline the invitation you will be removed from this team's roster and your contact information will not be made available to the captain.  This protocol is in accordance with the %site Privacy Policy.\n\nPlease be advised that players are NOT considered a part of a team roster until they have accepted a captain's request to join.  Your team's roster must be completed (minimum of 12 rostered players) by the team roster deadline, and all team members must be listed as a 'regular player' (accepted the captain request).\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);
			case 'player_request_body':
				return strtr("Dear %captains,\n\n%fullname has requested to join the roster of the %site team %team playing on %day in the '%league' league.  We ask that you please accept or decline this request at your earliest convenience.  Your team roster may be accessed at\n%teamurl\n\nIf you accept the invitation, %fullname will be added to the team's roster in whatever capacity you assign.  If you decline the invitation they will be removed from this team's roster.\n\nPlease be advised that players are NOT considered a part of a team roster until their request to join has been accepted by a captain.  Your team's roster must be completed (minimum of 12 rostered players) by the team roster deadline, and all team members must be listed as a 'regular player' (accepted by the captain).\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);

			default:
				return "Unknown message type '$messagetype'!";
		}
	}
}

function fatal_sql_error ( $sth )
{
	list($sqlstate, $driver_err, $driver_str) = $sth->errorInfo();
	error_exit("Database error: $sqlstate $driver_str");
}

/* vim: set sw=4 ts=4 et: */
?>
