<?php
/*
 * Web-based Ultimate league management software
 *
 * Copyright (c) 2002 Dave O'Neill
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of version 2 of the  GNU General Public License as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.  
 *	
 * You should have received a copy of the GNU General Public License along
 * with this library; if not, write to the Free Software Foundation, Inc., 59
 * Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * Authors: Dave O'Neill <dmo@acm.org>
 * 
 */
require_once("lib/variables.php");

$APP_PAGE_MAP = array();

require_once 'DB.php';

require_once "UserSession.php";
require_once "Handler.php";
require_once "Smarty.class.php";
require_once "lib/smarty_extensions.php";

/* 
 * TODO: allow modification of current_language in user prefs later.
 */
$current_language = $APP_DEFAULT_LANGUAGE;

/* Connect to the database */
#$dsn = "mysql://$APP_DB_USER:$APP_DB_PASS@$APP_DB_HOST/$APP_DB_NAME";
$dsn = "mysql://leaguerunner:ocuaweb@unix(/var/run/mysqld/mysqld.sock)/leaguerunner";
$DB = DB::connect($dsn, true);
if (DB::isError($DB)) {
	die($DB->getMessage());
}

$session = new UserSession;

/* Grab the variables we care about right now */
$session_cookie = var_from_cookie($APP_COOKIE_NAME);
$op = var_from_getorpost('op');

if( isset($session_cookie) ) {
	$session->create_from_cookie($session_cookie, $_SERVER['REMOTE_ADDR']);
}

if( is_null($op) ) {
	$op = 'login';
}

/* Instantiate a handler of the appropriate class to handle this 
 * operation 
 */
$handler = get_page_handler($op);
if(is_null($handler)) {
	$handler = new Handler;
	$handler->error_text = gettext("Sorry, that operation does not exist");
	$handler->display_error();
	return;
}

/* Set any necessary options for the handler */
if($handler->initialize()) {
	/* Ensure we have permission */
	if($handler->has_permission()) {
		/* Process the action */
		if($handler->process()) {
			/* 
			 * Display the appropriate output.  This uses the template filled
			 * by process()
			 */
			$handler->display();
		} else {
			$handler->display_error();
		}
	} else {
		if( !isset($handler->error_text) ) {
			$this->error_text = gettext("You do not have permission to perform that operation");
		}
		$handler->display_error();
	}
} else {
	$handler->display_error();
}

$DB->disconnect();
/* And, that's all, folks.  */

/**
 * Return instance of a Handler subclass to handle the given operation
 *
 * This creates the appropriate subclass of Handler, based on the operation
 * given.  
 * 
 * @access	public
 * @param	string	$op	The operation
 */
function get_page_handler( $op ) 
{
	global $APP_PAGE_MAP;
	if(isset($APP_PAGE_MAP[$op])) {
		return new $APP_PAGE_MAP[$op];	
	} 
	
	return null;
}

/**
 * Register a page handler with the handler generator
 *
 * Registers a handler so that it can be generated with the handler generator
 * at call-time.
 *
 * @access public
 * @param	string	$op  The operation
 * @param 	string  $class The class name to associate with the operation.
 */
function register_page_handler($op, $class)
{
	global $APP_PAGE_MAP;
	if(isset($APP_PAGE_MAP[$op])) {
		/* TODO: Warn here that an existing handler is being overridden? */
	}
	$APP_PAGE_MAP[$op] = $class;
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
		if(isset($_GET[$name])) {
			if(is_array($_GET[$name])) {
				return $_GET[$name];
			} else {
				return stripslashes($_GET[$name]);
			}
		}
	} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
 * Set variable for both GET and POST
 *
 * This is used for overriding certain values when necessary.
 *
 */
function set_getandpost($name, $value)
{
	$_POST[$name] = $value;
	$_GET[$name] = $value;
}
?>
