<?php
/*
 * Web-based league management software
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
 *          Mackenzie King <mackking@canada.com>
 * 
 */
require_once("includes/compatibility.inc");

/* 
 * First, read in the configuration data.
 * The data is in a file formatted as PHP/Perl variable assignments.
 * It's done this way to allow the same configfile to be used for both
 * PHP and Perl without:
 *   - writing Yet Another Config Parser
 *   - requiring extra unnecessary library dependancies from CPAN 
 *     or PEAR
 */
$phpCode = file_get_contents("./leaguerunner.conf");
eval($phpCode);

require_once("includes/common.inc");
require_once("includes/module.inc");
require_once("includes/menu.inc");
require_once("includes/theme.inc");
require_once("includes/database.inc");

if(!valid_input_data($_REQUEST)) {
	die("terminated request due to suspicious input data");
}

require_once "Handler.php";
require_once "UserSession.php";

/* Build menus */
menu_build();

/* Instantiate a handler of the appropriate class to handle this 
 * operation 
 */
$mod = arg(0);
if(isset($mod) && module_hook($mod, 'dispatch')) {
	$handler = module_invoke($mod, 'dispatch');
} else {
	$handler = new Login;
}

if(is_null($handler)) {
	$handler = new Handler;
	$handler->error_exit("No handler exists for that operation in $mod");
	return;
}

/* 
 * See if something else needs to be processed before this handler
 * (Account revalidation, etc)
 */
$pickledQuery = queryPickle($_SERVER['QUERY_STRING']);
$possibleRedirect = $handler->checkPrereqs( $pickledQuery );
if($possibleRedirect) {
	local_redirect($possibleRedirect);
}

/* Set any necessary options for the handler */
if($handler->initialize()) {
	/* Ensure we have permission */
	if($handler->has_permission()) {
	
		/* Process the action */
		$result = $handler->process();
		if($result === false) {
			$handler->error_exit("Uncaught failure in $mod, performing " . arg(1));
		}

		print theme_header($handler->title, $handler->breadcrumbs);
		print "<h1>$handler->title</h1>";
		print $result;
		print theme_footer();
		
	} else {
		$handler->error_exit("You do not have permission to perform that operation");
	}
} else {
	$handler->error_exit("Failed to initialize handler for $op");
}

/* And, that's all, folks.  */
?>
