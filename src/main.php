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

require("lib/variables.php");

$APP_PAGE_MAP = array();

require_once 'DB.php';
require_once "UserSession.php";
require_once "Handler.php";
require_once "Smarty.class.php";

/* Connect to the database */
$dsn = "mysql://$APP_DB_USER:$APP_DB_PASS@$APP_DB_HOST/$APP_DB_NAME";
$DB = DB::connect($dsn, true);
if (DB::isError($DB)) {
    die ($DB->getMessage());
}
	

$session = new UserSession;

/* Cookie comes in as $ocua_session */
if( isset($ocua_session) ) {
	$session->create_from_cookie($ocua_session);
}

/* 
 * TODO: allow modification of current_language in user prefs
 */
$current_language = $APP_DEFAULT_LANGUAGE;

/* 
 * If we're not attempting to log in, try to resume the session using
 * the saved token.
 */
if( !($session->is_valid() && isset($op)) ) {
	$op = 'login';
}

/* Instantiate a handler of the appropriate class to handle this 
 * operation 
 */
$handler = get_page_handler($op);

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
		$handler->display_error();
	}
} else {
	$handler->display_error();
}

/*
 * Perform necessary cleanup, and save our session info for the next operation
 */
$handler->end_page();

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
		$class = $APP_PAGE_MAP[$op];	
	} else {
		$class = $APP_PAGE_MAP['notfound'];	
	}

	return new $class;
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
