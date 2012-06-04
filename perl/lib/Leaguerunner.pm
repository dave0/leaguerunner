package Leaguerunner;
use strict;
use warnings;
use 5.8.1;

=head1 NAME

Leaguerunner - Perl module for Leaguerunner admin and db-access tasks

=head1 VERSION

2.7

=cut

our $VERSION = "2.7";

=head1 SYNOPSIS

This module provides access to the Leaguerunner configuration file, and to the
PHP-serialized values in the 'variable' table in the Leaguerunner database.

=cut

use PHPUnserialize;
use Config::Tiny;

=head1 SUBROUTINES

=head2 parseConfigFile ( $filename )

Parse the leaguerunner.conf (INI-style) configuration file.

Returns a hashref containing configfile sections as keys.  Each configfile
section is another hashref of key-value configuration options.

=cut

sub parseConfigFile
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
	$dsn_data{database} = delete $dsn_data{dbname};
	$config->{database}{dsn} = join(':',
		'DBI',
		$method,
		map { "$_=$dsn_data{$_}" } keys %dsn_data);


	return $config;
}

=head2 loadVariables ( $dbh )

Given a DBI database handle, queries the database and returns all of the
name-value pairs from the "variable" table of the database as a hashref.

=cut

sub loadVariables
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

=head1 AUTHOR

Dave O'Neill, C<< <dmo at dmo.ca> >>

=head1 LICENSE AND COPYRIGHT

Copyright 2009 Dave O'Neill.

This program is free software; you can redistribute it and/or modify it
under the terms of either: the GNU General Public License as published
by the Free Software Foundation; or the Artistic License.

See http://dev.perl.org/licenses/ for more information.

=cut

1;
