#!/usr/bin/perl -w
use strict;
use warnings;
use lib qw( /opt/websites/www.ocua.ca/leaguerunner/perl/lib );
use DBI;
use Leaguerunner;
use DateTime;
use DateTime::Format::MySQL;
use DateTime::Format::Strptime;
use Getopt::Long;

my @field_codes;
my ($old_startstr, $new_startstr) = ('', '');
my $date;
my $rc = GetOptions(
	'old=s'	 => \$old_startstr,
	'new=s'	 => \$new_startstr,
	'date=s' => \$date,
	'codes=s'=> \@field_codes,
);

# Allow comma-separated field codes
@field_codes = split(/,/,join(',',@field_codes));

if( $old_startstr !~ m{^\d\d:\d\d$} ) {
	die q{--old must be in 24-hr hh:mm format};
}

if( $new_startstr !~ m{^\d\d:\d\d$} ) {
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
my $old_start   = $strp->parse_datetime( $old_startstr );
$old_startstr   = DateTime::Format::MySQL->format_time( $old_start );
my $new_start   = $strp->parse_datetime( $new_startstr );
$new_startstr   = DateTime::Format::MySQL->format_time( $new_start );

my $find_parent = $DB->prepare(q{SELECT fid FROM field WHERE code = ?});
my $update;
my @update_args;
if($date) {
	$update = $DB->prepare(q{UPDATE gameslot g, field f SET g.game_start = ? WHERE g.game_date = ? AND g.game_start = ? AND g.fid = f.fid AND (f.code = ? OR f.parent_fid = ?)});
	@update_args = ($new_startstr, $date, $old_startstr);
} else {
	$update = $DB->prepare(q{UPDATE gameslot g, field f SET g.game_start = ? WHERE g.game_start = ? AND g.fid = f.fid AND (f.code = ? OR f.parent_fid = ?)});
	@update_args = ($new_startstr, $old_startstr);
}

foreach my $code (@field_codes) {
	print "Setting $code field to start time of $new_startstr from $old_startstr\n";
	$find_parent->execute( $code );
	my ($parent_fid) = $find_parent->fetchrow_array;

	print "   ... parent ID is $parent_fid\n";

	my $rc = $update->execute(
		@update_args,
		$code,
		$parent_fid
	);

	print "   ... update query returned code $rc\n";
}
