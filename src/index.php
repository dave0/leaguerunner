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
	print <<<END
<html><head><title>Leaguerunner Error</title></head><body>Couldn't find a leaguerunner.conf file anywhere.  Please check your configuration.</body></html>
END;
	exit;
}

putenv("TZ=" . $CONFIG['localization']['local_tz']);

error_reporting(E_ALL& ~E_NOTICE); //


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
		PDO::MYSQL_ATTR_FOUND_ROWS => true,	// PHP 5.3
		PDO::ATTR_EMULATE_PREPARES         => true,
	)
	);
} catch (PDOException $e) {
	print "Error!: " . $e->getMessage() . "<br/>";
	die();
}

require_once("includes/smarty.php");
require_once("includes/KLogger.php");
require_once("includes/common.php");
require_once("includes/menu.php");
require_once("includes/permissions.php");
require_once("includes/mail.php");

// Initialise configuration variables and Logger
$conf = variable_init();
if (variable_get('log_messages', 0)) {
	if (!isset($log)) {
		$log = new KLogger($_SERVER['DOCUMENT_ROOT'].'/'.$CONFIG['paths']['base_url'].'/logs', variable_get('log_threshold',Klogger::DEBUG));
	}
}


/*
 * PayPal IPN transactions are purely B2B, so most of the leaguerunner codebase isn't needed.
 * The main functions are:
 *  - Update registration payments
 *  - ensure registrations/payments are matched up for players
 */

// Check Request for IPN
if ($_GET['q'] == 'ipn') {
	require_once("Handler/PaypalHandler.php");

	// PayPal has its own logger
	$paypal_log = new KLogger($_SERVER['DOCUMENT_ROOT'].'/'.$CONFIG['paths']['base_url'].'/logs/paypal', Klogger::DEBUG);
	$paypal_log->logInfo('Received IPN Request');

	$handler = new PaypalHandler();
	$status = $handler->process();

	// success could have multiple payments to log
	if($status['status'] == true) {
		foreach($status['message'] as $payment) {
			$paypal_log->logInfo('Registration #'.$payment->order_id.' - '.$payment->payment_amount.' paid in full');
		}
	} else {
		$paypal_log->logError($status['message']);
		$paypal_log->logDebug(print_r(debug_backtrace(), true));
	}

	// no more IPN processing required
	exit;
}

	// Set some template defaults
global $smarty;
$smarty->assign('app_name', variable_get('app_name', 'Leaguerunner'));
$smarty->assign('app_admin_name', variable_get('app_admin_name', 'Leaguerunner Admin'));
$smarty->assign('app_admin_email', variable_get('app_admin_email', 'webmaster@localhost'));

$smarty->assign('app_version', '2.8.5');
$smarty->assign('base_url', $CONFIG['paths']['base_url']);
$smarty->assign('site_name', 'Your Ultimate Club');
$smarty->assign('site_slogan', 'Slogan about Your Ultimate Club');

//TODO NOTE: Testing Only, MUST BE REMOVED for Production
//$smarty->force_compile = true;

require_once("classes/lrobject.php");
require_once("classes/field.php");
require_once("classes/person.php");
require_once("classes/league.php");
require_once("classes/team.php");
require_once("classes/game.php");
require_once("classes/slot.php");
require_once("classes/event.php");
require_once("classes/registration.php");
require_once("classes/registration_payment.php");
require_once("classes/formbuilder.php");
require_once("classes/session.php");
require_once("classes/spirit.php");
require_once("classes/field_report.php");
require_once("classes/note.php");
require_once("classes/season.php");

if(!valid_input_data($_REQUEST)) {
	die("terminated request due to suspicious input data");
}

require_once "Handler.php";

// configure sessions
lr_configure_sessions();
/* TODO Hack! */
$smarty->assign('session_valid', $lr_session->is_valid() );
$smarty->assign('session_fullname', $lr_session->attr_get('fullname') );
$smarty->assign('session_userid', $lr_session->attr_get('user_id'));

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
	if( ! preg_match('/[^a-z0-9\/]/', $possible ) ) {
		$handler_file  = "$handler_dir/${possible}.php";
		if( file_exists( $handler_file ) ) {
			$handler_class = preg_replace('|/|', '_', $possible);
			break;
		}
	}

	array_unshift( $handler_args, array_pop( $dispatch_path ) );
}

if( ! $handler_class ) {
	// Adds 404 header to error page
	//$details = array('header'=>"HTTP/1.0 404 Not Found");
	error_exit("Leaguerunner could not find the requested file");
}
require_once($handler_file);

try {
	$reflector = new ReflectionClass( $handler_class );
	$handler   = $reflector->newInstanceArgs( $handler_args );
} catch (ReflectionException $e) {
	# Internal error
	error_exit("Couldn't construct $handler_class:" . $e->getMessage());
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
$handler->smarty = &$smarty;
if($handler->initialize()) {
	/* Ensure we have permission */
	if($handler->has_permission()) {

		/* Process the action */
		$result = $handler->process();
		if($result === false) {
			error_exit("Uncaught failure in $handler_class");
		}
		/* TODO: This is evil, needs cleanup */
		$smarty->assign('title', $handler->title);
		$smarty->assign('menu', menu_render('_root') );
		$smarty->assign('request_uri', request_uri() );
		if( $handler->template_name ) {
			// This handler is using templates correctly.  No need for backwards-compat
			$smarty->display( $handler->template_name );
		} else {
			// Backwards-compatibility until everything is fully templated
			$smarty->assign('content', $result);
			$smarty->display('backwards_compatible.tpl');
		}
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

function error_exit($error = NULL, $options = array())
{
	global $smarty;
	global $log;
	if(isset($options['header'])) {
		header($options['header']);
		$smarty->assign('title', 'Page not Found');
		$smarty->assign('error', 'Page not Found');
		$smarty->display('404.tpl');
		exit;
	}
	$smarty->assign('title', 'Error' );
	$smarty->assign('menu', menu_render('_root') );

	$error = $error ? $error : "An unknown error has occurred.";
	if(isset($log))
	{
		$log->logError($error);
		$log->logDebug(print_r(debug_backtrace(), true));
	}
	$smarty->assign('error', $error);
	$smarty->display('error.tpl');
	exit;
}

/* And, that's all, folks.  */
?>
