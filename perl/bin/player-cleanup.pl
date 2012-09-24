#!/usr/bin/perl -w
# Clean up bad accounts
# dmo Sun 23 Sep 2012 21:12:01 EDT
use strict;
use warnings;
use DBI;
use POSIX;
use Getopt::Long;

use FindBin '$Bin';

use lib "$Bin/../lib";
use Leaguerunner;

my $team_id = undef;

my $debug = 0;

GetOptions(
	'debug' => \$debug,
);

my $config = Leaguerunner::parseConfigFile("$Bin/../../src/leaguerunner.conf");

## Initialise database handle.
my $dbh = DBI->connect( $config->{database}{dsn}, $config->{database}{username}, $config->{database}{password}, { RaiseError => 1, }) || die("Error establishing database connect; $DBI::errstr\n");

my $variables = Leaguerunner::loadVariables($dbh);

# Delete accounts that:
# 	- are inactive
# 	- more than 6 months old
# 	- have never logged in, or have last logged in 3 years ago or more
# 	- are player accounts that aren't on a team, or are on a roster as non-captain.
my $sth = $dbh->prepare(q{
	SELECT distinct p.user_id,p.username,p.firstname,p.lastname,p.created,p.last_login
	FROM person p
		LEFT JOIN teamroster r ON (p.user_id = r.player_id)
	WHERE
		p.status = 'inactive'
		AND p.created < (NOW() - INTERVAL 6 MONTH)
		AND (
			ISNULL(p.last_login)
			OR
			p.last_login < (NOW() - INTERVAL 36 MONTH)
		)
		AND (p.class  = 'player'
			AND (
				ISNULL(r.team_id)
				OR
				r.status IN ('assistant', 'player', 'substitute', 'captain_request', 'player_request')
			)
		)
	LIMIT 500
});
$sth->execute;

while( my $ref = $sth->fetchrow_hashref ) {
	print join(' ', $ref->{user_id}, $ref->{username}, $ref->{firstname}, $ref->{lastname}, $ref->{created}, $ref->{last_login} ),"\n";
	delete_player($dbh, $ref->{user_id});
}


sub delete_player
{
	my ($dbh, $user_id) = @_;

	if( $user_id == 1 ) {
		# Can't delete admin account
		return;
	}

	# Check 'registrations' table for this user, don't delete if
	# there is one.
	my ($count) = $dbh->selectrow_array(q{SELECT count(*) FROM registrations WHERE user_id = ?}, undef, $user_id);
	if( $count ) {
		warn "User has $count registrations; not deleting";
		return;
	}

	# Check 'leaguemembers' table to see if this user's a
	# coordinator.
	($count) = $dbh->selectrow_array(q{SELECT count(*) FROM leaguemembers WHERE player_id = ?}, undef, $user_id);
	if( $count ) {
		warn "User has $count leagues assigned; not deleting";
		return;
	}

	# Check 'teamroster' table for this user, don't delete if
	# user is a captain.
	($count) = $dbh->selectrow_array(q{SELECT count(*) FROM teamroster WHERE player_id = ? AND status IN ('captain', 'coach')}, undef, $user_id);
	if( $count ) {
		warn "User is captain or coach on $count teams; not deleting";
		return;
	}

	# Check 'person_note' table for this user, don't delete if
	# user has notes
	($count) = $dbh->selectrow_array(q{SELECT count(*) FROM person_note WHERE person_id = ?}, undef, $user_id);
	if( $count ) {
		warn "User has $count notes; not deleting";
		return;
	}

	$dbh->do("DELETE FROM teamroster WHERE player_id = ?", undef, $user_id);
	$dbh->do("DELETE FROM person WHERE user_id = ?", undef, $user_id);
}
