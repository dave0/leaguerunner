#!/usr/bin/perl -w
## Update the elo rankings for all teams, from scratch.
## Dave O'Neill <dmo@dmo.ca> Sun, 18 Jan 2004 16:49:32 -0500 

use strict;
use DBI;
use POSIX;
use Leaguerunner;
use Getopt::Mixed;
use IO::Handle;
use IO::Pipe;

sub calculateEloChange($$$$);

our($season, $opt_ladder);

Getopt::Mixed::init("s=s season>s ladder");
my $optarg;
while( ($_, $optarg) = Getopt::Mixed::nextOption()) {
	/^s$/ && do {
		$season = $optarg;
	};
	/^ladder$/ && do {
		$opt_ladder = 1;
	};
}
Getopt::Mixed::cleanup();

if( !defined($season) ) {
	print "Usage: $0 --season=<season> [ --ladder ]\n";
	exit;
}

my $config = Leaguerunner::parseConfigFile("../src/leaguerunner.conf");

## Initialise database handle.
my $dsn = join("",
	"DBI:mysql:database=", $config->{db_name}, 
	":host=", $config->{db_host});

my $DB = DBI->connect($dsn, $config->{db_user}, $config->{db_password}) || die("Error establishing database connect; $DBI::errstr\n");

$DB->{RaiseError} = 1;

## We must remember to disconnect on exit.  Use the magical END sub.
sub END { $DB->disconnect() if defined($DB); }

my $sth;
my $game;

## Set all teams' scores in this season to an appropriate value
$sth = $DB->prepare(q{UPDATE team, leagueteams, league SET team.rating = 1500 WHERE team.team_id = leagueteams.team_id AND leagueteams.league_id = league.league_id AND league.season = ?});

$sth->execute($season);

## Now, retrieve each game
my $query = $DB->prepare(q{SELECT s.*,
	home.name as home_name,
	away.name as away_name,
	l.tier as tier
	from schedule s, league l
	INNER JOIN team home ON (home.team_id = s.home_team)
	INNER JOIN team away ON (away.team_id = s.away_team)
	WHERE s.league_id = l.league_id AND l.season = ?
});

my $updateTeam = $DB->prepare(q{UPDATE team SET rating = ? WHERE team_id = ?});
my $updateGame = $DB->prepare(q{UPDATE schedule SET rating_points = ? WHERE game_id = ?});
my $getRating = $DB->prepare(q{SELECT rating FROM team WHERE team_id = ?});
my $getRank = $DB->prepare(q{SELECT rank FROM leagueteams WHERE team_id = ?});

$query->execute($season);
while(my $game  = $query->fetchrow_hashref()) {

	my $change;
	my $ary;

	unless( defined($game->{home_score}) && defined($game->{away_score}) && ($game->{status} eq "normal")) {
	    ## Skip unscored games and defaulted games
	    $updateGame->execute(0,$game->{game_id});
	    next;
	}

	$getRating->execute($game->{home_team});
	$ary = $getRating->fetchrow_arrayref();
	if($ary->[0] != 0) {
		$game->{home_rating} = $ary->[0];
	} else {
		my $seed_rating;
		# Seed initial rating 
		if( $opt_ladder ) {
			# Ladder seeding is based on team's initial
			# rank
			$getRank->execute($game->{home_team});
			$ary = $getRank->fetchrow_arrayref();
			$seed_rating = 1500 - (10 * ($ary->[0] - 1) );
		} else {
			# Otherwise, based on tier of initial game
			$seed_rating = 1600 - (100 * $game->{'tier'} );
		}
		print "Initializing rating for " . $game->{'home_name'} . " to $seed_rating\n";
		$game->{home_rating} = $seed_rating;
	}
	
	$getRating->execute($game->{away_team});
	$ary = $getRating->fetchrow_arrayref();
	if($ary->[0] != 0) {
		$game->{away_rating} = $ary->[0];
	} else {
		my $seed_rating;
		# Seed initial rating 
		if( $opt_ladder ) {
			# Ladder seeding is based on team's initial
			# rank
			$getRank->execute($game->{home_team});
			$ary = $getRank->fetchrow_arrayref();
			$seed_rating = 1500 - (10 * ($ary->[0] - 1) );
		} else {
			# Otherwise, based on tier of initial game
			$seed_rating = 1600 - (100 * $game->{'tier'} );
		}
		print "Initializing rating for " . $game->{'away_name'} . " to $seed_rating\n";
		$game->{away_rating} = $seed_rating;
	}

	print "Game $game->{game_id} ($game->{'home_name'} vs $game->{'away_name'}): ";
	if($game->{home_score} > $game->{away_score}) {
		$change = calculateEloChange($game->{home_score}, $game->{away_score}, $game->{'home_rating'}, $game->{'away_rating'});
		$updateTeam->execute(($game->{'home_rating'} + $change), $game->{home_team});
		$updateTeam->execute(($game->{'away_rating'} - $change), $game->{away_team});
				
	} else {
		$change = calculateEloChange($game->{away_score}, $game->{home_score}, $game->{'away_rating'}, $game->{'home_rating'});
		$updateTeam->execute(($game->{'away_rating'} + $change), $game->{away_team});
		$updateTeam->execute(($game->{'home_rating'} - $change), $game->{home_team});
	}
	print "$change\n";

	$updateGame->execute($change, $game->{game_id})
	
}

sub calculateEloChange($$$$)
{	
	my ($winningScore, $losingScore, $winnerRating, $loserRating) = @_;
	my $weightConstant = 40;
	my $scoreWeight = 1;

	my $gameValue = 1;
	if($winningScore == $losingScore) {
		$gameValue = 0.5;
	}

	my $scoreDiff = $winningScore - $losingScore;
	if($winningScore && ($scoreDiff / $winningScore > (1/3))) {
		$scoreWeight += $scoreDiff / $winningScore;
	}

	my $power = 10 ** ( (0 - ($winnerRating - $loserRating)) / 400);
	my $expectedWin = (1 / ($power + 1));

	return $weightConstant * $scoreWeight * ($gameValue - $expectedWin);
}
