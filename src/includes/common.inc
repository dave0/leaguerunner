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
    $url = str_replace("&amp;", "&", $url);
	if(preg_match("/^(http|ftp|mailto):/", $url) > 0) {
		$url = $url;
	} else {
		$url = url($url);
	}

    header("Location: $url");

    /*
     * The "Location" header sends a REDIRECT status code to the http
     * daemon.  In some cases this can go wrong, so we make sure none of
     * the code /below/ gets executed when we redirect.
     */
    exit();
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

function blockquote($text, $attributes = array())
{
	return simple_tag("blockquote", $text, $attributes);
}

function tr($text, $attributes = array())
{
	return simple_tag("tr",$text, $attributes);
}

function td($text, $attributes = array())
{
	return simple_tag("td",$text, $attributes);
}

function simple_row($left, $right)
{
	return tr(
		td($left, array('class' => 'row_title', 'valign' => 'top')) . td($right, array('class' => 'row_data')));
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

function url($query = NULL) 
{
    $base_url = $_SERVER['PHP_SELF'];

    if (isset($query)) {
        return "$base_url?$query";
    } else {
        return "$base_url";
    }
}

function format_street_address( $street, $city, $province, $postalcode)
{
	return "$street<br />\n$city, $province<br />\n$postalcode";
}

function check_form($input, $quotes = ENT_QUOTES)
{
	return htmlspecialchars($input, $quotes);
}

/*
 * Form-generation functions
 */
			
function form($form, $method = "post", $action = 0, $options = 0)
{
	if (!$action) {
		$action = $_SERVER['PHP_SELF'];
	}
	return "<form action=\"$action\" method=\"$method\"". ($options ? " $options" : "") .">\n$form\n</form>\n";
}

/* Displays a form item.  Called by other form_ functions */
function form_item($title, $value, $description = 0) 
{
	return "<div class=\"form-item\">". ($title ? "<div class=\"title\">$title:</div>" : "") . $value . ($description ? "<div class=\"description\">$description</div>" : "") ."</div>\n";
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

function form_select($title, $name, $value, $options, $description = 0, $extra = 0, $multiple = 0) 
{
	if (count($options) > 0) {
		foreach ($options as $key=>$choice) {
			$select .= "<option value=\"$key\"". (is_array($value) ? (in_array($key, $value) ? " selected=\"selected\"" : "") : ($value == $key ? " selected=\"selected\"" : "")) .">". check_form($choice) ."</option>";
		}
		return form_item($title, "<select name=\"$name". ($multiple ? "[]" : "") ."\"". ($multiple ? " multiple " : "") . ($extra ? " $extra" : "") .">$select</select>", $description);
	}
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

function form_submit($value, $name = "submit") 
{
	return "<input type=\"submit\" class=\"form-submit\" name=\"$name\" value=\"". check_form($value) ."\" />\n";
}

function form_reset($value, $name = "reset") 
{
	return "<input type=\"reset\" class=\"form-reset\" name=\"$name\" value=\"". check_form($value) ."\" />\n";
}

/*
 * DB helper functions
 */

/**
 * Check for a database error
 */
function isDatabaseError( &$res ) 
{
	if(DB::isError($res)) {
		return $res->getMessage() . ": " . $res->getUserinfo();
	}
	
	return false;
}


/*
 * 
 * Miscellaneous stuff. 
 * Most of this is old code that might be best removed entirely.
 * 
 */

function array_stripslashes(&$array) {
	if( !is_array($array)) {
		return;
	}
	while (list($key) = each($array)) {
		if (is_array($array[$key])) {
			array_stripslashes($array[$key]);
		} else {
			$array[$key] = stripslashes($array[$key]);
		}
	}
	reset($array);
}

/* 
 * To be safe, PHP's auto-global-variable stuff should be turned off, so we
 * will use the functions below to access GET, POST and cookie variables.
 */
/**
 * Get variable from cookie
 * @param string $name name of variable we're looking for
 * @return mixed
 */
function var_from_cookie($name) 
{
	global $_COOKIE;
	if(isset($_COOKIE[$name])) {
		return $_COOKIE[$name];
	}
	return null;
}

/**
 * Get variable from POST submission.
 * @param string $name name of variable we're looking for
 * @return mixed
 */
function var_from_post($name)
{
	global $_SERVER, $_POST;
	
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		if(isset($_POST[$name])) {
			if(is_array($_POST[$name])) {
				return $_POST[$name];
			} else {
				return stripslashes($_POST[$name]);
			}
		}
	} 
	return null;
}

/**
 * Get variable from either a GET or a POST submission.
 *
 * We could use the PHP magic array $_REQUEST, but it also includes cookie
 * data, which can confuse things.  We just want GET and POST values, so we'll
 * do it ourselves.
 *
 * TODO: modify function to take optional $flags arg, with available flags:
 * 	ALLOW_HTML
 * 	ALLOW_JAVASCRIPT
 * and add code to strip out otherwise.
 * 
 * @param string $name name of variable we're looking for
 * @return mixed
 */
function var_from_getorpost($name)
{
	/* Don't want to use $_REQUEST, since that can contain cookie info */
	global $_SERVER, $_GET, $_POST;
	if($_SERVER['REQUEST_METHOD'] == 'GET') {
		$vars = &$_GET[$name];
	} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		$vars = &$_POST[$name];
	} 
	if(isset($vars)) {
		return $vars;
	}
	return null;
}

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

/*
 * TODO: This should go away once Smarty stuff is removed
 */
function map_callback($item)
{
	return array("output" => $item, "value" => $item);
}

/** 
 * Helper fn to generate an option-listable sequence of numbers
 */
function get_numeric_options ( $start, $finish )
{
	/* Yuck */
	$foo = array();
	for($i = $start; $i <= $finish; $i++) {
		$foo[] = $i;
	}
	return array_map("map_callback", $foo);
}

/** 
 * Helper fn to set up allowed values for an option list.
 * Use in preference to get_numeric_options, as that fn is deprecated
 */
function getOptionsFromRange( $start, $finish )
{
	$result = array();
	$result["---"] = "---";
	for($i = $start; $i <= $finish; $i++) {
		$result[$i] = $i;
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
	global $DB;
	
	$row = $DB->getRow("SHOW COLUMNS from $table LIKE ?",
		array($col_name),
		DB_FETCHMODE_ASSOC);
	if(isDatabaseError($row)) {
		return $result;
	}
		
	$str = preg_replace("/^(enum|set)\(/","",$row['Type']);
	$str = str_replace(")","",$str);
	$str = str_replace("'","",$str);
	$ary = preg_split("/,/",$str);

	$result = array();
	foreach($ary as $val) {
		$result[$val] = $val;
	}
	
	return $result;
}

function getOptionsFromQuery( $sql, $data = array() )
{
	global $DB;

	$result = array();
	$res = $DB->query($sql, $data);
	if(isDatabaseError($res)) {
		return $result;
	}

	$result[0] = "-- select from list --"	;
	while($row = $res->fetchRow()) {
		$result[$row[0]] = $row[1];	
	}
	return $result;
}

/**
 * Deprecated version of get_enum_options. 
 * TODO Remove when Smarty is gone
 */
function get_enum_options ( $table, $col_name ) 
{
	$ary = getOptionsFromEnum($table, $col_name);
	return array_map("map_callback", array_keys($ary));
}


function getWardName($id)
{
	global $DB;
	return $DB->getOne("SELECT CONCAT(name, ' (', city, ' Ward ', num, ')') FROM ward WHERE ward_id = ?", array($id));
}


/* vim: set sw=4 ts=4 et: */
?>
