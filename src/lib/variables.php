<?php
/*
 * Configuration variables
 */

$GLOBALS['APP_NAME'] = "OCUA Leaguerunner";

$GLOBALS['APP_COOKIE_DOMAIN'] = "localhost.localdomain";
$GLOBALS['APP_COOKIE_PATH'] = "/";

$GLOBALS['APP_ADMIN_NAME'] = "OCUA Webmaster";
$GLOBALS['APP_ADMIN_EMAIL'] = "dmo@acm.org";

$GLOBALS['APP_SERVER'] = $HTTP_SERVER_VARS["HTTP_HOST"];

$GLOBALS['APP_DIR_GRAPHICS']  = dirname($_SERVER["PHP_SELF"]) . "/graphics/"; 

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
