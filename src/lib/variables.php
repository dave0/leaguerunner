<?php
/*
 * Configuration variables
 */

$GLOBALS['APP_NAME'] = "OCUA Leaguerunner";

$GLOBALS['APP_COOKIE_NAME']   = "ocua_session";
$GLOBALS['APP_COOKIE_DOMAIN'] = ".evilplot.org";
$GLOBALS['APP_COOKIE_PATH'] = "/";

$GLOBALS['APP_DB_HOST'] = "localhost";
$GLOBALS['APP_DB_USER'] = "ocuaweb";
$GLOBALS['APP_DB_PASS'] = "ocuaweb";
$GLOBALS['APP_DB_NAME'] = "ocua";

$GLOBALS['APP_ADMIN_NAME'] = "OCUA Webmaster";
$GLOBALS['APP_ADMIN_EMAIL'] = "dmo@acm.org";

$GLOBALS['APP_SERVER'] = $HTTP_SERVER_VARS["HTTP_HOST"];

$GLOBALS['APP_DIR_WEBFACING'] = "/php-engine/"; # was $server_root_remote
$GLOBALS['APP_DIR_GRAPHICS']  = "$APP_DIR_WEBFACING/graphics/"; 
$GLOBALS['APP_DIR_INTERNAL']  = "/home/projects/ocua/ocua-engine/"; # was $server_root_local
$GLOBALS['APP_CGI_LOCATION']  = "http://$APP_SERVER/$APP_DIR_WEBFACING/main.php";

$GLOBALS['APP_STYLESHEET'] = "http://$APP_SERVER/$APP_DIR_WEBFACING/style.css";

$GLOBALS['APP_DEFAULT_LANGUAGE'] = "en_US";

?>
