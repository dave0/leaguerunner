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

    /*
     * It is advised to use "local_redirect()" instead of PHP's
     * "header()" as "local_redirect()" will append the user's session
     * ID to the URI when PHP is compiled with "--enable-trans-sid".
     */
    if (!ini_get("session.use_trans_sid") || !session_id() || strstr($url, session_id())) {
        header("Location: $url");
    } else { 
	    $sid = session_name() . "=" . session_id();

        if (strstr($url, "?") && !strstr($url, $sid)) {
            header("Location: $url&". $sid);
        } else {  
            header("Location: $url?". $sid);
        }
    }

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

function l($text, $query = NULL, $attributes = array())
{
	$t = array();
	foreach ($attributes as $key => $value) {
		$t[] = "$key=\"$value\"";
	}

	return "<a href=\"". url($query) ."\" ". implode($t, " ") .">$text</a>";
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
	return form_item(0, "<input type=\"radio\" class=\"form-radio\" name=\"edit[$name]\" value=\"". $value ."\"". ($checked ? " checked=\"checked\"" : "") ." /> $title", $description);
}

function form_checkbox($title, $name, $value = 1, $checked = 0, $description = 0) 
{
	return form_hidden($name, 0) . form_item(0, "<input type=\"checkbox\" class=\"form-checkbox\" name=\"edit[$name]\" value=\"". $value ."\"". ($checked ? " checked=\"checked\"" : "") ." /> $title", $description);
}

function form_textfield($title, $name, $value, $size, $maxlength, $description = 0) 
{
	$size = $size ? " size=\"$size\"" : "";
	return form_item($title, "<input type=\"text\" maxlength=\"$maxlength\" class=\"form-text\" name=\"edit[$name]\"$size value=\"". check_form($value) ."\" />", $description);
}

function form_password($title, $name, $value, $size, $maxlength, $description = 0) 
{
	$size = $size ? " size=\"$size\"" : "";
	return form_item($title, "<input type=\"password\" class=\"form-password\" maxlength=\"$maxlength\" name=\"edit[$name]\"$size value=\"". check_form($value) ."\" />", $description);
}

function form_textarea($title, $name, $value, $cols, $rows, $description = 0) 
{
	$cols = $cols ? " cols=\"$cols\"" : "";
	return form_item($title, "<textarea wrap=\"virtual\"$cols rows=\"$rows\" name=\"edit[$name]\" id=\"edit[$name]\">". check_form($value) ."</textarea>", $description);
}

function form_select($title, $name, $value, $options, $description = 0, $extra = 0, $multiple = 0) 
{
	if (count($options) > 0) {
		foreach ($options as $key=>$choice) {
			$select .= "<option value=\"$key\"". (is_array($value) ? (in_array($key, $value) ? " selected=\"selected\"" : "") : ($value == $key ? " selected=\"selected\"" : "")) .">". check_form($choice) ."</option>";
		}
		return form_item($title, "<select name=\"edit[$name]". ($multiple ? "[]" : "") ."\"". ($multiple ? " multiple " : "") . ($extra ? " $extra" : "") .">$select</select>", $description);
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
	return form_item($title, "<input type=\"file\" class=\"form-file\" name=\"edit[$name]\" size=\"$size\" />\n", $description);
}

function form_hidden($name, $value) 
{
	return "<input type=\"hidden\" name=\"edit[$name]\" value=\"". check_form($value) ."\" />\n";
}

function form_submit($value, $name = "op") 
{
	return "<input type=\"submit\" class=\"form-submit\" name=\"$name\" value=\"". check_form($value) ."\" />\n";
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


/* vim: set sw=4 ts=4 et: */
?>
