#!/usr/bin/perl -w
use strict;
use warnings;
use lib qw( /opt/websites/www.ocua.ca/leaguerunner/scripts );
use DBI;
use Leaguerunner;
use DateTime;
use DateTime::Format::MySQL;
use DateTime::Format::Strptime;
use Getopt::Long;

my @field_codes;
my ($old_endstr, $new_endstr);
my $rc = GetOptions(
	'old=s'	=> \$old_endstr,
	'new=s'	=> \$new_endstr,
	'codes=s'	=> \@field_codes,
);

# Allow comma-separated field codes
@field_codes = split(/,/,join(',',@field_codes));

if( $old_endstr !~ m{^\d\d:\d\d$} ) {
	die q{--old must be in 24-hr hh:mm format};
}

if( $new_endstr !~ m{^\d\d:\d\d$} ) {
	die q{--new must be in 24-hr hh:mm format};
}

if( ! @field_codes ) {
	die q{--codes must specify at least one field};
}

if( grep { ! /^[A-Z]{3}$/ } @field_codes ) {
	die q{--codes must specify three-letter field codes only};
}

my $config = Leaguerunner::parseConfigFile("/opt/websites/www.ocua.ca/leaguerunner/src/leaguerunner.conf");

## Initialise database handle.
my $DB = DBI->connect( $config->{database}{dsn}, $config->{database}{username}, $config->{database}{password}, { RaiseError => 1, }) ||
	die("Error establishing database connect; $DBI::errstr\n");

my $strp = DateTime::Format::Strptime->new( pattern => "%H:%M" );
my $old_end   = $strp->parse_datetime( $old_endstr );
$old_endstr   = DateTime::Format::MySQL->format_time( $old_end );
my $new_end   = $strp->parse_datetime( $new_endstr );
$new_endstr   = DateTime::Format::MySQL->format_time( $new_end );

my $find_parent = $DB->prepare(q{SELECT fid FROM field WHERE code = ?});
my $update = $DB->prepare(q{UPDATE gameslot g, field f SET g.game_end = ? WHERE g.game_end = ? AND g.fid = f.fid AND (f.code = ? OR f.parent_fid = ?)});

foreach my $code (@field_codes) {
	print "Setting $code field to end time of $new_endstr from $old_endstr\n";
	$find_parent->execute( $code );
	my ($parent_fid) = $find_parent->fetchrow_array;

	print "   ... parent ID is $parent_fid\n";

	my $rc = $update->execute( 
		$new_endstr,
		$old_endstr,
		$code,
		$parent_fid
	);

	print "   ... update query returned code $rc\n";
}
