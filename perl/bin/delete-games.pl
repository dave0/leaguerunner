#!/usr/bin/perl -w
use strict;
use warnings;
use DBI;
use Getopt::Long;
use Term::Prompt;

use Cwd qw(abs_path);
use FindBin;
use lib abs_path("$FindBin::Bin/../lib");

use Leaguerunner;

my @league_ids;
my $rc = GetOptions(
	'ids=s'	=> \@league_ids,
);

# Allow comma-separated field codes
@league_ids = split(/,/,join(',',@league_ids));

if( ! @league_ids ) {
	die q{--ids must specify at least one league id};
}

if( grep { /\D/ } @league_ids ) {
	die q{--ids must only contain numbers};
}

my $config = Leaguerunner::parseConfigFile("/opt/websites/www.ocua.ca/leaguerunner/src/leaguerunner.conf");
## Initialise database handle.
my $DB = DBI->connect( $config->{database}{dsn}, $config->{database}{username}, $config->{database}{password}, { RaiseError => 1, }) ||
	die("Error establishing database connect; $DBI::errstr\n");


my @queries = (
	'DELETE FROM score_entry USING score_entry, schedule WHERE score_entry.game_id = schedule.game_id AND schedule.league_id = ?',
#	'DELETE FROM score_reminder USING score_reminder,schedule WHERE score_reminder.game_id = schedule.game_id AND schedule.league_id = ?',
	'DELETE FROM spirit_entry USING spirit_entry,schedule WHERE spirit_entry.gid = schedule.game_id AND schedule.league_id = ?',
	'DELETE FROM gameslot USING gameslot, schedule WHERE gameslot.game_id = schedule.game_id AND schedule.league_id = ?',
	'DELETE FROM league_gameslot_availability WHERE league_id = ?',
	'DELETE FROM field_ranking_stats USING field_ranking_stats, schedule WHERE field_ranking_stats.game_id = schedule.game_id AND schedule.league_id = ?',
	'DELETE FROM schedule WHERE league_id = ?',
);

my $find_league = $DB->prepare(q{SELECT l.*, s.year FROM league l LEFT JOIN season s ON (l.season = s.id) WHERE league_id = ?});

foreach my $id (@league_ids) {

	$find_league->execute( $id );
	my $league = $find_league->fetchrow_hashref;
	my $name = "$league->{name} $league->{year}";
	if( $league->{tier} ) {
		$name .= " Tier $league->{tier}";
	}

	my $ok = prompt('y', "Delete all games from league $id ($name)", "y to delete, n to skip", 0);
	if( ! $ok ) {
		print "Skipping league $id ($name)\n";
		next;
	}

	printf "Deleting games for league $id ($name):\n";

	foreach my $query (@queries) {
		print "\t$query... ";
		my $sth = $DB->prepare($query);	
		my $rows = $sth->execute( $id );
		print "affected $rows row(s)\n";
	}
}
