#!/usr/bin/perl -w
use strict;
use warnings;
use DBI;
use POSIX qw( EXIT_SUCCESS EXIT_FAILURE );
use Getopt::Long;
use Pod::Usage;

use lib qw(../lib);
use Leaguerunner;
use Leaguerunner::DBInit;

=head1 NAME

db-init-or-upgrade -- Initialize or upgrade a Leaguerunner database

=head1 SYNOPSIS

 # Simple install
 db-init-or-upgrade --config=/path/to/leaguerunner.conf --action=install

 # Simple upgrade
 db-init-or-upgrade --config=/path/to/leaguerunner.conf --action=upgrade

=head1 DESCRIPTION

This script allows easy installation or upgrading of a Leaguerunner
installation's database schema.

It requires that you have DBI and the DBD::mysql modules installed, as well as
base Perl.

=head1 OPTIONS

=over 4

=item --config <filename>

Full path to config file to use for connecting to the database.

=item --action <action>

Action to perform.  Valid actions are:

=over 4

=item install

Install a new Leaguerunner database.  Will not install over an existing installation.

=item upgrade

Upgrades a Leaguerunner database to the latest schema

=item detect

Display the current database schema version number, and exit.

=back

=item --ignore-errors

Ignore any SQL errors encountered during the upgrade or install.  This is,
generally, a very bad idea, so don't use this option unless you're willing to
accept the risk of an unusable database.

=item --clobber

Allow new installation over an existing database.  This will drop tables before
recreating them.  DO NOT RUN IF YOU WISH TO KEEP YOUR DATA.

=item --help

This help

=item --man

Full manpage

=back

=head1 FAQ

=over 4

=item Q: Why Perl?  The rest of Leaguerunner is PHP!

Well, I've grown to hate PHP over the years.   Perl is so much easier to write
and to maintain.  This upgrade tool would be much uglier and harder to maintain
in PHP.

=back

=head1 LICENCE AND COPYRIGHT

Copyright (C) 2009 Dave O'Neill.

Released under the terms of the GNU General Public License, version 2.

=cut

my $config_path   = '../src/leaguerunner.conf';
my $action        = undef;
my $ignore_errors = 0;
my $clobber       = 0;

my $rc = GetOptions(
	'config=s'       => \$config_path,
	'action=s'       => \$action,
	'ignore-errors!' => \$ignore_errors,
	'clobber!'       => \$clobber,
	'help'    => sub { pod2usage( -exitval => EXIT_SUCCESS, -verbose => 1 ) },
	'man'     => sub { pod2usage( -exitval => EXIT_SUCCESS, -verbose => 2 ) },
);

if(!$rc) {
	pod2usage(-verbose => 0, -exitval => EXIT_FAILURE);
}

if(!$action) {
	pod2usage(-message => '--action is required', -verbose => 1, -exitval => EXIT_FAILURE);
}

my @valid_actions = qw( detect upgrade install );
if(!grep { $action eq $_ } @valid_actions) {
	pod2usage(-message => "--action $action not supported", -verbose => 1, -exitval => EXIT_FAILURE);
}

my $config = Leaguerunner::parseConfigFile($config_path);

## Initialise database handle.

my $dbh = DBI->connect( $config->{database}{dsn}, $config->{database}{username}, $config->{database}{password}, { RaiseError => 1, });

die("Error establishing database connect; $DBI::errstr\n") unless $dbh;

my $init = Leaguerunner::DBInit->new({ dbh => $dbh,  ignore_errors => $ignore_errors, });

my $current_version = $init->detect_schema_version();

if($action eq 'detect') {
	print $current_version ? "Database version is $current_version\n" : "No version detected\n";
	$rc = 1;
} elsif($current_version && $action eq 'upgrade') {
	$rc = $init->upgrade_from($current_version);
} elsif($action eq 'install') {
	if( $current_version && ! $clobber ) {
		pod2usage( -message => 'Current database already exists; cannot --action=install without --clobber', -verbose => 0, -exitval => EXIT_FAILURE);
	}
	$rc = $init->fresh_install();
}

exit($rc ? EXIT_SUCCESS : EXIT_FAILURE);
