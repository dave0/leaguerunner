#!/usr/bin/perl -w
## Delete a given team from the database
## Dave O'Neill <dmo@dmo.ca> Wed, 19 May 2004 23:23:25 -0400 

use strict;
use DBI;
use POSIX;
use Getopt::Mixed;

use Cwd qw(abs_path);
use FindBin;
use lib abs_path("$FindBin::Bin/../lib");

use Leaguerunner;

our($opt_debug);
$opt_debug = 0;

my $team_id = undef;

Getopt::Mixed::init("teamid=s debug");
my $optarg;
while( ($_, $optarg) = Getopt::Mixed::nextOption()) {
	/^debug$/ && do {
		$opt_debug = 1;
	};
	/^teamid$/ && do {
		$team_id = $optarg;
	};
}
Getopt::Mixed::cleanup();

if( !$team_id ) {
	print "Usage: $0 --teamid [season]\n";
	exit 1;
}

my $config = Leaguerunner::parseConfigFile("../src/leaguerunner.conf");

## Initialise database handle.
my $DB = DBI->connect( $config->{database}{dsn}, $config->{database}{username}, $config->{database}{password}, { RaiseError => 1, }) || die("Error establishing database connect; $DBI::errstr\n");

# We must remember to disconnect on exit.  Use the magical END sub.
sub END { $DB->disconnect() if defined($DB); }

my $variables = Leaguerunner::loadVariables($DB);

my $team = $DB->selectrow_hashref(q{SELECT * FROM team WHERE team_id = ?}, {}, $team_id);

if( ! $team ) {
	print "No such team exists.\n";
	exit(1);
}

print "Found team $team->{name}\n" if $opt_debug;

my ($league_id) = $DB->selectrow_array(q{SELECT league_id FROM leagueteams where team_id = ?}, {}, $team_id);

if( $league_id != 1 ) {
	print "Team is not inactive; cannot delete\n";
	exit(1);
}

print "Checking if team has any scheduled games\n" if $opt_debug;

my ($num_games) = $DB->selectrow_array(q{SELECT COUNT(*) from schedule where home_team = ? OR away_team = ?}, {}, $team_id, $team_id);

if( $num_games > 0 ) {
	print "Team is scheduled for games; cannot delete\n";
	exit(1);
}

# OK, team has no scheduled games, and is inactive, so it's safe to
# delete

# First, remove players from roster
print "Removing all players from roster of $team->{name}\n" if $opt_debug;

my $rc = $DB->do("DELETE FROM teamroster WHERE team_id = ?",{},$team_id);
if( defined($rc) ) {
	print "Removed $rc players from $team->{name} roster\n" if $opt_debug;
} else {
	print "Error removing players\n";
	exit(1);
}

$rc = $DB->do("DELETE FROM leagueteams WHERE team_id = ?",{},$team_id);
if( defined($rc) ) {
	print "Removed $team->{name} from league\n" if $opt_debug;
} else {
	print "Error removing team from league\n";
	exit(1);
}

$rc = $DB->do("DELETE FROM team WHERE team_id = ?",{},$team_id);
if( defined($rc) ) {
	print "Removed $team->{name} from Leaguerunner system\n" if $opt_debug;
} else {
	print "Error removing team from system\n";
	exit(1);
}
