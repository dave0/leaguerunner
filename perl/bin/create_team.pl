#!/usr/bin/perl -w
use strict;
use DBI;
use POSIX;
use Leaguerunner;
use Getopt::Mixed;
#use Data::Dumper;

our($opt_debug);
$opt_debug = 0;

our($league_id, @colours, $captain_id);

Getopt::Mixed::init("league=i colours=s captain=i debug");
my $optarg;
while( ($_, $optarg) = Getopt::Mixed::nextOption()) {
	/^debug$/ && do {
		$opt_debug = 1;
	};
	/^league$/ && do {
		$league_id = $optarg;
	};
	/^colours$/ && do {
		@colours = split(/,/,$optarg);
	};
	/^captain$/ && do {
		$captain_id = $optarg;
	};
}
Getopt::Mixed::cleanup();

if( !$league_id || !scalar(@colours) || !$captain_id ) {
	print "Usage: $0 --league=<league_id> --captain=<captain_id> --colours=Red,Green,Blue\n";
	exit 1;
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

my $league = $DB->selectrow_hashref(q{SELECT * FROM league WHERE league_id = ?}, undef, $league_id);
#print Dumper $league;
defined($league) or die "No league!";

my $captain = $DB->selectrow_hashref(q{SELECT * FROM person WHERE user_id = ?}, undef, $captain_id);
#print Dumper $captain;
defined($captain) or die "No captain!";


my $league_name = $league->{name};
if( $league->{tier} ) {
	$league_name .= sprintf(" Tier %02d", $league->{tier});
}

print "League name is $league_name\n" if $opt_debug;

my $team_create_sth = $DB->prepare(q{INSERT INTO team (name,shirt_colour,status) VALUES (?,?,'closed')});
my $team_id_sth = $DB->prepare(q{SELECT LAST_INSERT_ID() from team});
my $league_add_sth = $DB->prepare(q{INSERT INTO leagueteams (league_id, team_id) VALUES (?,?)});
my $add_captain_sth = $DB->prepare(q{INSERT INTO teamroster (team_id, player_id, status, date_joined) VALUES(?,?,'captain', NOW())});
foreach my $col (@colours) {
	$team_create_sth->execute("$league_name $col", $col);
	
	$team_id_sth->execute();
	my ($team_id) = $team_id_sth->fetchrow_array();
	$team_id > 0 or die "Invalid team ID retrieved";
	print "Newly created team has id of $team_id\n" if $opt_debug;

	$league_add_sth->execute($league->{league_id}, $team_id);

	$add_captain_sth->execute($team_id, $captain_id);
}
