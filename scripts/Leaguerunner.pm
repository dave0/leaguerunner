#!/usr/bin/perl

package Leaguerunner;

use strict;
use warnings;
use URI;

sub parseConfigFile($) 
{
	my $inputFilename = shift;

	our($DB_URL, $APP_ADMIN_EMAIL);
	require $inputFilename;
	
	## Evil hack.  URI module can't parse mysql: urls correctly,
	## so we force parsing as http.
	my($method,$rest) = split(/:/,$DB_URL,2);
	$DB_URL = 'http:' . $rest;

	my $u = URI->new($DB_URL);
	
	my ($user,$pass) = split(/:/,$u->userinfo, 2);
	
	return {
		'db_scheme' => $method,
		'db_user' => $user,
		'db_password'   => $pass,
		'db_host' => $u->host,
		'db_name' => substr($u->path, 1),
		'admin_email' => $APP_ADMIN_EMAIL,
	};
}

1;
