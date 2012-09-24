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
use Data::Dumper;

my ($date);
my $rc = GetOptions(
	'date=s' => \$date,
);

if( $date !~ m/^\d{4}-\d{2}-\d{2}$/ ) {
	die q{--date must be YYYY-MM-DD};
}

my $config = Leaguerunner::parseConfigFile("/opt/websites/www.ocua.ca/leaguerunner/src/leaguerunner.conf");

## Initialise database handle.
my $DB = DBI->connect( $config->{database}{dsn}, $config->{database}{username}, $config->{database}{password}, { RaiseError => 1, }) ||
	die("Error establishing database connect; $DBI::errstr\n");

my $strp = DateTime::Format::Strptime->new( pattern => "%Y-%m-%d" );
my $dt   = $strp->parse_datetime( $date );
$date = DateTime::Format::MySQL->format_date( $dt );

my $all_games = $DB->prepare(q{
	SELECT g.game_id, s.home_team, s.away_team, h.name AS home_name, a.name AS away_name
	FROM gameslot g
		LEFT JOIN schedule s ON (s.game_id = g.game_id)
		LEFT JOIN team h ON (h.team_id = s.home_team)
		LEFT JOIN team a ON (a.team_id = s.away_team)
	WHERE NOT ISNULL(g.game_id) AND g.game_date = ?});
$all_games->execute( $date );

my $correct_rating = $DB->prepare(q{
	SELECT IF(g.fid = t.home_field, 1, r.rank)
	FROM team_site_ranking r, field f, gameslot g, team t
	WHERE
		g.game_id = ?
		AND g.fid = f.fid
		AND ( (ISNULL(f.parent_fid) AND f.fid = r.site_id)
			OR f.parent_fid = r.site_id OR g.fid = t.home_field)
		AND t.team_id = r.team_id
		AND r.team_id = ?
});

my $actual_rating = $DB->prepare(q{
	SELECT s.rank
	FROM field_ranking_stats s
	WHERE s.game_id = ? AND s.team_id = ?
});

my $clear_rating = $DB->prepare(q{
	DELETE FROM field_ranking_stats WHERE game_id = ? AND team_id = ?
});

my $replace_rating = $DB->prepare(q{
	REPLACE INTO field_ranking_stats (game_id, team_id, rank) VALUES (?,?,?)
});

while( my $row = $all_games->fetchrow_hashref() ) {
	#print Dumper $row;

	for my $what ( qw(home away) ) {
		my $team_id   = $row->{"${what}_team"};
		my $team_name = $row->{"${what}_name"};

		my ($correct) = $DB->selectrow_array($correct_rating, {}, $row->{game_id}, $team_id);
		my ($actual) = $DB->selectrow_array($actual_rating, {}, $row->{game_id}, $team_id);
		if( ! defined $correct && !defined $actual ) {
			# Good... field not rated, and no rating registered.  Ignore.
		} elsif( ! defined $correct && defined $actual ) {
			print "Team $team_name has actual $actual, but should not have a stats score\n";
			my $rv = $clear_rating->execute( $row->{game_id}, $team_id );
			if( $rv != 1 ) {
				print "Warning: Expected to delete 1 row, instead deleted $rv\n";
			}
		} elsif( !defined $actual || $correct != $actual ) {
			my $expect = 2;
			if( ! defined($actual)) {
				$expect = 1;
				$actual = '(none)';
			}
			print "Team $team_name has actual $actual, should be $correct\n";
			my $rv = $replace_rating->execute( $row->{game_id}, $team_id, $correct );
			if( $rv != $expect ) {
				print "Warning: Expected to affect $expect row(s), instead affected $rv\n";
			}
		}
	}

}


# SELECT g.game_id, r.team_id, IF(g.fid = t.home_field, 1, r.rank) FROM team_site_ranking r, field f, gameslot g, team t WHERE g.game_id = 36246 AND g.fid = f.fid AND ( (ISNULL(f.parent_fid) AND f.fid = r.site_id) OR f.parent_fid = r.site_id OR g.fid = t.home_field)AND t.team_id = r.team_id AND r.team_id = 328;


#my $update = $DB->prepare(q{UPDATE gameslot g, field f SET g.game_end = ? WHERE g.game_end = ? AND g.fid = f.fid AND (f.code = ? OR f.parent_fid = ?)});
