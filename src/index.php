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
ini_set('include_path','.:/usr/share/pear:/usr/local/lib/php:./lib/smarty');

require_once("includes/config.inc");
require_once("includes/common.inc");
require_once("includes/theme.inc");

require_once("lib/variables.php"); // TODO: Deprecate me.
require_once("lib/common.inc");  // TODO: Deprecate me.

$APP_PAGE_MAP = array();

require_once 'DB.php';
/* Connect to the database */
$DB = DB::connect($DB_URL, true);
if (DB::isError($DB)) {
	die($DB->getMessage());
}

require_once "UserSession.php";
require_once "Handler.php";
require_once "Smarty.class.php";
require_once "lib/smarty_extensions.php";

set_magic_quotes_runtime(0);
if (get_magic_quotes_gpc ()) {
	if($_SERVER['REQUEST_METHOD'] == 'GET') {
		array_stripslashes($_GET);
	} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		array_stripslashes($_POST);
	} 
	array_stripslashes($_COOKIES);
}

$op = var_from_getorpost('op');
if( is_null($op) ) {
	$op = 'login';
}

/* Instantiate a handler of the appropriate class to handle this 
 * operation 
 */
$handler = get_page_handler($op);
if(is_null($handler)) {
	$handler = new Handler;
	$handler->error_exit("No handler exists for $op");
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
			$handler->error_exit("Uncaught failure performing $op");
		}
	} else {
		$handler->error_exit("You do not have permission to perform that operation");
	}
} else {
	$handler->error_exit("Failed to initialize handler for $op");
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

?>
