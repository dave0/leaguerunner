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
## Set all teams' scores to 1500
$sth = $DB->prepare(q{UPDATE team SET rating = 1500});
$sth->execute();

## Now, retrieve each game
my $query = $DB->prepare(q{SELECT s.*,
	home.name as home_name,
	away.name as away_name
	from schedule s 
	INNER JOIN team home ON (home.team_id = s.home_team)
	INNER JOIN team away ON (away.team_id = s.away_team)
});

my $updateTeam = $DB->prepare(q{UPDATE team SET rating = rating + ? WHERE team_id = ?});
my $updateGame = $DB->prepare(q{UPDATE schedule SET rating_points = ? WHERE game_id = ?});
my $getRating = $DB->prepare(q{SELECT rating FROM team WHERE team_id = ?});

$query->execute();
while(my $game  = $query->fetchrow_hashref()) {

	my $change;
	my $ary;

	unless( defined($game->{home_score}) && defined($game->{away_score}) && ($game->{defaulted} eq "no")) {
	    ## Skip unscored games and defaulted games
	    $updateGame->execute(0,$game->{game_id});
	    next;
	}

	$getRating->execute($game->{home_team});
	$ary = $getRating->fetchrow_arrayref();
	$game->{home_rating} = $ary->[0];
	
	$getRating->execute($game->{away_team});
	$ary = $getRating->fetchrow_arrayref();
	$game->{away_rating} = $ary->[0];

	print "Game $game->{game_id} ($game->{'home_name'} vs $game->{'away_name'}): ";
	if($game->{home_score} > $game->{away_score}) {
		$change = calculateEloChange($game->{home_score}, $game->{away_score}, $game->{'home_rating'}, $game->{'away_rating'});
		$updateTeam->execute($change, $game->{home_team});
		$updateTeam->execute((0 - $change), $game->{away_team});
				
	} else {
		$change = calculateEloChange($game->{away_score}, $game->{home_score}, $game->{'away_rating'}, $game->{'home_rating'});
		$updateTeam->execute($change, $game->{away_team});
		$updateTeam->execute((0 - $change), $game->{home_team});
	}
	print "$change\n";

	$updateGame->execute($change, $game->{game_id})
	
}
