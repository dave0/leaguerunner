<?php
/*
 * Configuration variables
 */

$GLOBALS['PRODUCT_NAME'] = "Leaguerunner";

$GLOBALS['APP_NAME'] = "OCUA Leaguerunner";

$GLOBALS['APP_COOKIE_NAME']   = "leaguerunner_session";
$GLOBALS['APP_COOKIE_DOMAIN'] = ".evilplot.org";
$GLOBALS['APP_COOKIE_PATH'] = "/";

$GLOBALS['APP_DB_HOST'] = "localhost";
$GLOBALS['APP_DB_USER'] = "ocuaweb";
$GLOBALS['APP_DB_PASS'] = "ocuaweb";
$GLOBALS['APP_DB_NAME'] = "ocua";

$GLOBALS['APP_ADMIN_NAME'] = "OCUA Webmaster";
$GLOBALS['APP_ADMIN_EMAIL'] = "dmo@acm.org";

$GLOBALS['APP_SERVER'] = $HTTP_SERVER_VARS["HTTP_HOST"];

$GLOBALS['APP_DIR_WEBFACING'] = "/leaguerunner/"; # was $server_root_remote
$GLOBALS['APP_DIR_GRAPHICS']  = "$APP_DIR_WEBFACING/graphics/"; 
$GLOBALS['APP_DIR_INTERNAL']  = "/home/projects/ocua/leaguerunner/"; # was $server_root_local
$GLOBALS['APP_CGI_LOCATION']  = "http://$APP_SERVER/$APP_DIR_WEBFACING/main.php";

$GLOBALS['APP_STYLESHEET'] = "http://$APP_SERVER/$APP_DIR_WEBFACING/style.css";

$GLOBALS['APP_DEFAULT_LANGUAGE'] = "en_US";


/*
 * TODO: this crap belongs in the database on a per-tier basis
 */
/*
 * When we start playing (hour, in 24-hour time)
 */
$GLOBALS['LEAGUE_START_HOUR'] = 9;

/* 
 * Increment value for game start times.
 */
$GLOBALS['LEAGUE_TIME_INCREMENT'] = 15;

/*
 * Max number of rounds for the league
 */
$GLOBALS['LEAGUE_MAX_ROUNDS'] = 3;


?>
