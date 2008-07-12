#!/usr/bin/perl

package Leaguerunner;

use strict;
use warnings;
use PHPUnserialize;


sub parseConfigFile($) 
{
	my $inputFilename = shift;

	our($DB_DSN, $DB_USER, $DB_PASS, $APP_ADMIN_EMAIL);
	require $inputFilename;
	
	## Evil hack.  URI module can't parse mysql: urls correctly,
	## so we force parsing as http.
	my($method,$rest) = split(/:/,$DB_DSN,2);

	my %dsn_data = map { split(/=/, $_) } split(';', $rest);

	
	return {
		'db_scheme' => $method,
		'db_user' => $DB_USER,
		'db_password'   => $DB_PASS,
		'db_host' => $dsn_data{host},
		'db_name' => $dsn_data{dbname},
	};
}

sub loadVariables($)
{
	my $DB = shift;
	my $sth = $DB->prepare(q{SELECT name,value from variable});
	$sth->execute();
	my $variables = {};
	while(my $ary  = $sth->fetchrow_arrayref()) {
		$variables->{$ary->[0]} = unserialize($ary->[1]);
	}
	return $variables;
}
1;
