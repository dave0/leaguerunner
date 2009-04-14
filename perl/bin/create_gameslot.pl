#!/usr/bin/perl -w
## Create gameslots in bulk

use strict;
use DBI;
use POSIX;
use Leaguerunner;
use Getopt::Mixed;

our($opt_debug);
$opt_debug = 0;

our($field_id, @assign, $date, $start, $end);

Getopt::Mixed::init("fieldid=s assign=s date=s start=s end=s debug");
my $optarg;
while( ($_, $optarg) = Getopt::Mixed::nextOption()) {
	/^debug$/ && do {
		$opt_debug = 1;
	};
	/^fieldid$/ && do {
		$field_id = $optarg;
	};
	/^assign$/ && do {
		@assign = split(/,/,$optarg);
	};
	/^date$/ && do {
		$date = $optarg;
	};
	/^start$/ && do {
		$start = $optarg;
	};
	/^end$/ && do {
		$end = $optarg;
	};
}
Getopt::Mixed::cleanup();

if( !$field_id || !scalar(@assign) || !$date || !$start || !$end ) {
	print "Usage: $0 --field_id=<fid> --assign=<leagueid>,<leagueid> --date=YYYY-MM-DD --start=HH:MM --end=HH:MM\n";
	exit 1;
}
if( $start !~ /:/ or $end !~ /:/ ) {
	die "HH:MM format needed for start/end";
}

my $config = Leaguerunner::parseConfigFile("../src/leaguerunner.conf");

## Initialise database handle.
my $dsn = join("",
	"DBI:mysql:database=", $config->{db_name}, 
	":host=", $config->{db_host});

my $DB = DBI->connect($dsn, $config->{db_user}, $config->{db_password}) || die("Error establishing database connect; $DBI::errstr\n");

$DB->{RaiseError} = 1;

# We must remember to disconnect on exit.  Use the magical END sub.
sub END { $DB->disconnect() if defined($DB); }

my $variables = Leaguerunner::loadVariables($DB);

my $field = $DB->selectrow_hashref(q{SELECT * FROM field WHERE fid = ?}, {}, $field_id);

if( ! $field ) {
	print "No such team exists.\n";
	exit(1);
}

print "Found field $field->{fid}\n" if $opt_debug;


print "Creating gameslot for $date, $start -> $end at field $field_id\n" if $opt_debug;

my $rc = $DB->do("INSERT into gameslot (fid, game_date, game_start, game_end) VALUES(?,?,?,?)", {}, $field_id, $date, "$start:00", "$end:00");
if( !$rc )  {
	die "DB error";
}
my ($slot_id) = $DB->selectrow_array("SELECT LAST_INSERT_ID() FROM gameslot");
foreach my $league_id (@assign) {
	print "Assigning gameslot $slot_id to league $league_id\n" if $opt_debug;
	$rc = $DB->do("INSERT INTO league_gameslot_availability (slot_id, league_id) VALUES (?,?)", {}, $slot_id, $league_id);
	if( !$rc ) {
		die "DB error";
	}
}
