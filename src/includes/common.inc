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

function arg($index) {

  static $arguments;

  if (empty($arguments)) {
    $arguments = explode("/", $_GET["q"]);
  }

  return $arguments[$index];
}

function array2object($ary) {

  if (is_array($ary)) {
    foreach ($ary as $key => $value) {
      $object->$key = $value; 
    }
  }
  else {  
    $object = $ary;
  }

  return $object;
}

function object2array($obj) {

  if (is_object($obj)) {
    foreach ($obj as $key => $value) {
      $array[$key] = $value;
    }
  }
  else {  
    $array = $obj;
  }

  return $array;
}

/*
 * Global configuration variables
 */
function variable_init( $conf = array() )
{
	$result = db_query("SELECT * from variable");
	while ($variable = db_fetch_object($result)) {
		if( !isset($conf[$variable->name]) ) {
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
	global $conf;
	db_query("DELETE FROM variable WHERE name = '%s'", $name);
	db_query("INSERT INTO variable (name, value) VALUES ('%s','%s')", $name, serialize($value));
	$conf[$name] = $value;
}

function variable_del($name) 
{
	global $conf;
	db_query("DELETE FROM variable WHERE name = '%s'", $name);
	unset($conf[$name]);
}

/*
 * HTML-generation functions
 */

function simple_tag($name, &$content, $attributes = array())
{
	$t = array();
	foreach ($attributes as $key => $value) {
		$t[] = "$key=\"$value\"";
	}

	return "<$name". (count($t) ? " " : "") . implode($t, " ") .">$content</$name>";
}

function h1($text, $attributes = array())
{
	return simple_tag("h1", $text, $attributes);
}

function para($text, $attributes = array())
{
	return simple_tag("p", $text, $attributes);
}

function pre($text, $attributes = array())
{
	return simple_tag("pre", $text, $attributes);
}

function div($text, $attributes = array())
{
	return simple_tag("div", $text, $attributes);
}

function table_cell($cell, $header = 0) {
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

function table($header, $rows, $attrArray = array()) {

  foreach ($attrArray as $key => $value) {
    $attributes .= " $key=\"$value\"";
  }

  $output = "<table$attributes>\n";

  /*
  ** Emit the table header:
  */

  if (is_array($header)) {
    $output .= " <tr>";
    foreach ($header as $cell) {
      if (is_array($cell) && $cell["field"]) {
        $cell = tablesort($cell, $header);
      }
      $output .= table_cell($cell, 1);
    }
    $output .= " </tr>\n";
  }

  /*
  ** Emit the table rows:
  */

  if (is_array($rows)) {
    foreach ($rows as $number => $row) {
	  if($attrArray['alternate-colours']) {
        if ($number % 2 == 1) {
          $output .= " <tr class=\"light\">";
        }
        else {
          $output .= " <tr class=\"dark\">";
        }
      } else {
        $output .= " <tr>";
	  }
      foreach ($row as $cell) {
        $output .= table_cell($cell, 0);
      }
      $output .= " </tr>\n";
    }
  }

  $output .= "</table>\n";

  return $output;
}



function l($text, $query = NULL, $attributes = array())
{
	if(0 < preg_match("/^(http|ftp|mailto):/", $query)) {
		$attributes['href'] = $query;
	} else {
		$attributes['href'] = url($query);
	}
	return simple_tag("a", check_form($text), $attributes);
}


function url($url = NULL, $query = NULL) {
  global $BASE_URL;
  static $script;

  $base_url = $BASE_URL;
  $cleanURL = variable_get('clean_url', 1);

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


function format_street_address( $street, $city, $province, $postalcode)
{
	$foo =  "$street<br />\n$city, $province<br />\n$postalcode";
	$prov_abbr = substr($province,0,2);
	$street_uri = strtr($street, array(' ' => '+'));
	$foo .= "<br />[&nbsp;<a href=\"http://maps.google.com?q=$street_uri,+$city,+$province&hl=en\">maps.google.com</a>&nbsp;|&nbsp;<a href=\"http://www.mapquest.com/maps/map.adp?country=ca&zoom=7&city=$city&state=$prov_abbr&address=$street_uri\">MapQuest</a>&nbsp;]";
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

function check_query($text) {
    return addslashes($text);
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
		$title = "<label>$title:</label><br />";
	} else {
		# Ensure that if $title == 0, we blank it instead.
		$title = "";
	}
	return "<div class=\"form-item\">". $title . $value . ($description ? "<div class=\"description\">$description</div>" : "") ."</div>\n";
}

function form_group($legend, $group, $description = NULL) 
{
	return "<fieldset>" . ($legend ? "<legend>$legend</legend>" : "") . $group . ($description ? "<div class=\"description\">$description</div>" : "") . "</fieldset>\n";
}
  

function form_radio($title, $name, $value = 1, $checked = 0, $description = 0) 
{
	return form_item(0, "<input type=\"radio\" class=\"form-radio\" name=\"$name\" value=\"". $value ."\"". ($checked ? " checked=\"checked\"" : "") ." /> $title", $description);
}

function form_checkbox($title, $name, $value = 1, $checked = 0, $description = 0) 
{
	return form_item(0, "<input type=\"checkbox\" class=\"form-checkbox\" name=\"$name\" value=\"". $value ."\"". ($checked ? " checked=\"checked\"" : "") ." /> $title", $description);
}

function form_textfield($title, $name, $value, $size, $maxlength, $description = 0) 
{
	$size = $size ? " size=\"$size\"" : "";
	return form_item($title, "<input type=\"text\" maxlength=\"$maxlength\" class=\"form-text\" name=\"$name\"$size value=\"". check_form($value) ."\" />", $description);
}

function form_password($title, $name, $value, $size, $maxlength, $description = 0) 
{
	$size = $size ? " size=\"$size\"" : "";
	return form_item($title, "<input type=\"password\" class=\"form-password\" maxlength=\"$maxlength\" name=\"$name\"$size value=\"". check_form($value) ."\" />", $description);
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
			$radio .= form_radio($choice,$name,$key, ($key == $value), '');
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

function form_select_date($title,$prefix,$value, $startYear, $endYear, $description)
{
	list($value_year, $value_month, $value_day) = split("-", $value);
	$allSelect = __form_select($prefix . '[year]', $value_year, 
		getOptionsFromRange($startYear, $endYear, 'reverse'));
	$allSelect .= __form_select($prefix . '[month]', $value_month, 
		getOptionsFromRange('1','12'));
	$allSelect .= __form_select($prefix . '[day]', $value_day, 
		getOptionsFromRange('1','31'));
		
	return form_item($title, $allSelect, $description);
}

function form_radios($title, $name, $value, $options, $description = 0) 
{
	if (count($options) > 0) {
		foreach ($options as $key=>$choice) {
			$output .= form_radio($choice, $name, $key, ($key == $value));
		}
		return form_item($title, $output, $description);
	}
}

function form_file($title, $name, $size, $description = 0) 
{
	return form_item($title, "<input type=\"file\" class=\"form-file\" name=\"$name\" size=\"$size\" />\n", $description);
}

function form_hidden($name, $value) 
{
	return "<input type=\"hidden\" name=\"$name\" value=\"". check_form($value) ."\" />\n";
}

function form_submit($value, $name = "submit", $javascript = "") 
{
	return "<input type=\"submit\" class=\"form-submit\" name=\"$name\" value=\"". check_form($value) ."\" $javascript/>\n";
}

function form_reset($value, $name = "reset") 
{
	return "<input type=\"reset\" class=\"form-reset\" name=\"$name\" value=\"". check_form($value) ."\" />\n";
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
    if( $country == 'CA' ) {
        return validate_ca_address( $street, $city, $prov, $postalcode );
    } else if( $country == 'US' ) {
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
        array_push( $errors, 'You must enter a valid Canadian postalcode');
    }

    if( count( $errors ) ) {
        return $errors;
    }
    return array();
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

    if( ! validate_state_abbrev( $state ) ) {
        array_push( $errors, 'You must select a valid US state or territory');
    }

    if( ! validate_us_zipcode( $zip, $state ) ) {
        array_push( $errors, 'You must enter a valid US Zip code');
    }
    
    if( count( $errors ) ) {
        return $errors;
    }
    return true;
}

/**
 * Validate Canadian provinces
 *
 * @param string $prov Province abbreviation
 */
function validate_province_abbrev( $prov )
{
        switch (strtoupper($prov)) {
            case 'AB':
            case 'BC':
            case 'MB':
            case 'NB':
            case 'NF':
            case 'NT':
            case 'NS':
            case 'NU':
            case 'ON':
            case 'PE':
            case 'QC':
            case 'SK':
            case 'YK':
                return true;
        }
        return false;
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
        CASE 'QUEBEC':
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

function validate_nonblank( $string )
{
	if( strlen(trim($string)) <= 0 ) {
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
 * Convert a numeric number to an English ordinal number for ease of reading.
 * Rule of thumb is:  If the 'tens' digit is 1, then add 'th'.  Otherwise,
 * check the last digit to determine which suffix to use.  1 == 'st', 2 ==
 * 'nd', 3 == 'rd', 4 through 0 == 'th'
 */
function numberToOrdinal ( $num )
{
	if( (floor( $num / 10 ) % 10) == 1 ) {
		return $num . "th";
	}

	switch($num % 10) {
		case 1:
			return $num . "st";
		case 2:
			return $num . "nd";
		case 3:
			return $num . "rd";
		default:
			return $num . "th";
	}
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
	$ary = array();
	while(list(,$name) = each($names)) {
		$ary[$name] = $name;
	}
	return $ary;
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
 * Helper fn to return full descriptions for numeric skill levels
 */
function getOptionsForSkill()
{
	return array(
		"10" => "10: High calibre competitive player (team was top 4 at nationals)",
		"9" => "9: Medium calibre competitive player",
		"8" => "8: Lower calibre competitive player",
		"7" => "7: Top tier Mon/Wed league player, minimal/no comp experience",
		"6" => "6: Mid to Upper tier Mon/Wed OR Top Tier Tue/Thu league player",
		"5" => "5: Mid tier league player",
		"4" => "4: Key player lower tier team, minimal/no higher tier experience",
		"3" => "3: Lower tier league player",
		"2" => "2: Beginner, minimal experience but athletic with sports background",
		"1" => "1: Absolute Beginner",
	);
}

function getRosterPositions()
{
	return array(
		'player'            => "regular player",
		'substitute'        => "substitute player",
		'captain_request'   => "request to join by captain",
		'player_request'    => "request to join by player",
		'coach'	            => "COACH",
		'captain'	        => "captain",
		'assistant'	        => "assistant captain",
		'none'	        	=> "not on team",
	);
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
function getOptionsFromTimeRange( $start, $finish, $increment )
{
	$result = array();
	$result["---"] = "---";
	for($hour = ($start / 100); $hour <= ($finish / 100); $hour++) {
		for($minute = 0; $minute < 60; $minute += $increment) {
			$time = sprintf("%02d:%02d", $hour, $minute);
			$result[$time] = $time;
		}
	}
	return $result;
}

/**
 * Helper fn to fetch 'allowed' values for a set or enum from a MySQL
 * database.
 */
function getOptionsFromEnum( $table, $col_name )
{
	$result = db_query("SHOW COLUMNS from $table LIKE '%s'", $col_name);
	$row = db_fetch_array($result);
		
	$str = preg_replace("/^(enum|set)\(/","",$row['Type']);
	$str = str_replace(")","",$str);
	$str = str_replace("'","",$str);
	$ary = preg_split("/,/",$str);

	$options = array();
	$options["---"] = "---";
	foreach($ary as $val) {
		$options[$val] = $val;
	}
	
	return $options;
}

/**
 * Fetch options using the given query.
 * TODO: Fix this!  It should instead take a $result arg produced by db_query,
 * not the raw sql.
 */
function getOptionsFromQuery( $sql, $data = array() )
{
	$result = db_query($sql,$data);

	$options[0] = "-- select from list --"	;
	while($row = db_fetch_array($result)) {
		$options[$row['theKey']] = $row['theValue'];	
	}
	return $options;
}

/**
 * Generate a calendar where individual days are clickable.  Calendar is
 * generated for $year and $month.  If given and > 0, $day is highlighted.
 * Next/prev month buttons are linked to $calendarOp followed by month and
 * year parameters.  Each day is linked to $dayOp followed by a day parameter.
 * Days of week (full columns) to highlight should be given in
 * $highlightWeekdays.
 */
function generateCalendar( $year, $month, $day, $calendarOp, $dayOp, $highlightWeekdays = array())
{
	$allDays = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');

	$max = sizeof($highlightWeekdays);
	for($i = 0; $i < $max; $i++) 
	{
		$highlightWeekdays[$i] = array_search($highlightWeekdays[$i], $allDays);
	}
	reset($highlightWeekdays);

    // HACK: need to put a fake number on the front of the highlight array or else
    // the array_search later will return index 0 for first day, and that  == false!
    array_unshift($highlightWeekdays, 99);

	$startOfMonth = mktime(6, 0, 0, $month, 1, $year);
	$firstDayOfWeek = date("w", $startOfMonth);
	$numberOfDays = date("t", $startOfMonth);
	
	if($month == 1) {
		$nextMonth = $month + 1;
		$nextYear  = $year;
		$prevMonth = 12;
		$prevYear  = $year - 1;
	} else if ($month == 12) {
		$nextMonth = 1;
		$nextYear  = $year + 1;
		$prevMonth = $month - 1;
		$prevYear  = $year;
	} else {
		$nextMonth = $month + 1;
		$nextYear  = $year;
		$prevMonth = $month - 1;
		$prevYear  = $year;
	}

	$output .= "\n<!-- calendar -->\n";
	$output .= "<div class='calendar'>";
	$output .= "<table border='1' cellspacing='0'>\n";
	$output .= "<tr>" 
		. "<td colspan='7' class='header_month'>"
		. l("<<<", "$calendarOp/$prevYear/$prevMonth")
		. " " . date("F Y", $startOfMonth)
		. " " . l(">>>", "$calendarOp/$nextYear/$nextMonth")
		. "</td></tr>";

	/* Generate days of the week headers 
	 * The year 1995 was chosen because January 1995 starts on a Monday
	 */
	$firstcolumn = mktime(6, 0, 0, 1, 1, 1995);
	$output .= " <tr class=\"header-week\">";
	
	foreach($allDays as $dayName) {
		$output .= "<td>" . substr($dayName,0,2) . "</td>";
	}
	$output .= "</tr>\n";

	$output .= "<tr class='row-week'>\n";
	$output .= "<td class='day_blank' colspan='$firstDayOfWeek'>&nbsp;</td>";

	$curColumn = $firstDayOfWeek;
	$curDate  = 1;
	while($curDate <= $numberOfDays) {

		if( $curColumn == 0) {
			$output .= "<tr class='row-week'>\n";
		}

		/* Check for days to highlight */
		if(!(false == array_search($curColumn, $highlightWeekdays))) {
			$attributes = "class='highlighted-day'";
		} else {
			$attributes = '';
		}
		
		$output .= "<td $attributes>"
		. l($curDate, "$dayOp/$year/$month/$curDate")
		. "</td>";
		
		if($curColumn == 6) {
			$output .= "</tr>\n";
		}

		$curColumn = (++$curColumn % 7);
		$curDate++;
	}

	if($curColumn != 0) {
		$output .= "<td class='day_blank' colspan='"
		.  (7 - $curColumn)
		. "'>&nbsp;</td>";
	}
	$output .= "</table></div>\n\n";

	return $output;
}

/* vim: set sw=4 ts=4 et: */
?>
