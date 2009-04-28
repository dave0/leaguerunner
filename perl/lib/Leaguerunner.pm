#!/usr/bin/perl

package Leaguerunner;

use strict;
use warnings;
use PHPUnserialize;
use Config::Tiny;

sub parseConfigFile($)
{
	my ($filename) = @_;

	my $config = Config::Tiny->read( $filename );
	if( ! $config ) {
		die("Couldn't read config file $filename: " . Config::Tiny->errstr);
	}

	# Strip leading/trailing quotes
	for my $section (keys %$config) {
		for my $key (keys %{$config->{$section}}) {
			$config->{$section}{$key} =~ s/^['"]//;
			$config->{$section}{$key} =~ s/['"]$//;
		}
	}

	# Clean up DSN
	my($method,$rest) = split(/:/,$config->{database}{dsn},2);
	my %dsn_data = map { split(/=/, $_) } split(';', $rest);
	$config->{database}{dsn} = join(':',
		'DBI',
		$method,
		'database=' . $dsn_data{dbname},
		'host=' . $dsn_data{host});


	return $config;
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
