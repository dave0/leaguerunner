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

$CONFIG = parse_ini_file('./leaguerunner.conf', true);
if( ! $CONFIG ) {
	error_exit("Could not read leaguerunner.conf file");
}

putenv("TZ=" . $CONFIG['localization']['local_tz']);

error_reporting(E_ALL & ~E_NOTICE);


/* Flag for PDO::FETCH_CLASS usage.  Use this to prevent constructor from
 * loading all related class data
 */
define('LOAD_OBJECT_ONLY', 0);
define('LOAD_RELATED_DATA', 1);

// Configure database
try {
	$dbh = new PDO($CONFIG['database']['dsn'], $CONFIG['database']['username'], $CONFIG['database']['password'],
	array(
		PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
		PDO::ATTR_EMULATE_PREPARES         => true,
	)
	);
} catch (PDOException $e) {
	print "Error!: " . $e->getMessage() . "<br/>";
	die();
}

require_once("includes/common.inc");
require_once("includes/menu.inc");
require_once("includes/permissions.inc");
require_once("includes/theme.inc");
require_once("includes/mail/php.inc");

// Initialise configuration variables
$conf = variable_init();

require_once("classes/lrobject.inc");
require_once("classes/field.inc");
require_once("classes/person.inc");
require_once("classes/league.inc");
require_once("classes/team.inc");
require_once("classes/game.inc");
require_once("classes/slot.inc");
require_once("classes/event.inc");
require_once("classes/registration.inc");
require_once("classes/formbuilder.inc");
require_once("classes/session.inc");
require_once("classes/spirit.php");
require_once("classes/field_report.php");


// Maybe include registration payment module
if( variable_get('registration', 0) &&
	variable_get('online_payments', 1) )
{
	$file = variable_get('payment_implementation', NULL);
	if ($file)
	{
		require_once("includes/payment/$file.inc");
	}
}

if(!valid_input_data($_REQUEST)) {
	die("terminated request due to suspicious input data");
}

require_once "Handler.php";

// configure sessions
lr_configure_sessions();

// Headers have not been sent yet
global $headers_sent;
$headers_sent = 0;

/* Build menus */
menu_build();

if( array_key_exists('q', $_GET) ) {
	$q = $_GET['q'];
} else {
	$q = 'login';
}

$dispatch_path = explode('/', $q);

$handler_dir   = 'Handler';
$handler_class = null;
$handler_args  = array();

while( count( $dispatch_path ) > 0 ) {
	$possible      = join('/', $dispatch_path );

	# Skip it if path contains anything other than lowercase alpha characters and /
	if( ! preg_match('/[^a-z\/]/', $possible ) ) {
		$handler_file  = "$handler_dir/${possible}.php";
		if( file_exists( $handler_file ) ) {
			$handler_class = preg_replace('|/|', '_', $possible);
			break;
		}
	}

	array_unshift( $handler_args, array_pop( $dispatch_path ) );
}

if( ! $handler_class ) {
	# TODO: should 404
	error_exit("Not found");
}
require_once($handler_file);

try {
	$reflector = new ReflectionClass( $handler_class );
	$handler   = $reflector->newInstanceArgs( $handler_args );
} catch (ReflectionException $e) {
	# Internal error
	error_exit("Couldn't construct $handler_class");
}

/*
 * See if something else needs to be processed before this handler
 * (Account revalidation, etc)
 */
$pickledQuery = queryPickle($_GET['q']);
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
			error_exit("Uncaught failure in $handler_class");
		}
		print theme_header($handler->title);
		print theme_body($handler->breadcrumbs);
		print "<h1>$handler->title</h1>";
		print $result;
		print theme_footer();

	} else {
		if( ! $lr_session || !$lr_session->user ) {
			local_redirect( url('login', "next=$pickledQuery") );
		} else {
			error_exit("You do not have permission to perform that operation");
		}
	}
} else {
	error_exit("Failed to initialize handler for $handler_class");
}

function error_exit($error = NULL)
{
	$title = "Error";

	$error = $error ? $error : "An unknown error has occurred.";

	print theme_header($title);
	print theme_body();
	print "<h1>$title</h1>";
	print theme_error( $error );
	print theme_footer();
	exit;
}

/* And, that's all, folks.  */
?>
