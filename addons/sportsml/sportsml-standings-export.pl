#!/usr/bin/perl -w

## Export standings in SportsML format (http://www.sportsml.com>
## Dave O'Neill <dmo@dmo.ca>  Fri 16 May 2003 00:07:12 EDT 

use strict;
use DBI;
use POSIX;
use Data::Dumper;
use XML::Writer;

my $database_name = 'leaguerunner';
my $database_host = 'localhost';
my $database_user = 'leaguerunner';
my $database_pass = 'ocuaweb';
my $from_addr = 'dmo@acm.org';
my $to_addr = 'dmo@acm.org';

## Initialise database handle.
my $dsn = "DBI:mysql:database=${database_name}:host=${database_host}";

my $DB = DBI->connect($dsn, $database_user, $database_pass) || die("Error establishing database connect; $DBI::errstr\n");

$DB->{RaiseError} = 1;

## We must remember to disconnect on exit.  Use the magical END sub.
sub END { $DB->disconnect() if defined($DB); }

my $season = "Summer";
my $night = "Monday";
my $league_name = "Ottawa-Carleton Ultimate Association";


my $writer = new XML::Writer(DATA_MODE => 1, DATA_INDENT => 4);

$writer->xmlDecl();
$writer->comment("DOCTYPE sports-content SYSTEM \"../dtds/sportsml-core.dtd\"");
print_header($writer, $season, $night);
print_division_standings($writer, $season, $night);
print_footer($writer);
$writer->end();

sub print_header 
{
	my $x = shift;
	my $season = shift;
	my $day = shift;

	my $date_time = strftime("%Y-%m-%dT%H:%M", localtime(time()));

	$x->startTag("sports-content");
	$x->startTag("sports-metadata",
		'language' => 'en-US',
		'fixture-name' => 'Standings');
	$x->dataElement("sports-title", 
		"$league_name $season $day");
	$x->startTag("sports-content-codes");
		$x->emptyTag("sports-content-code",
			'doc-id' => 'u1',
			'date-time' => $date_time,
			'code-type' => "league",
			'code-key' => "l.ocua.ca",
			'code-name' => $league_name);
	$x->endTag("sports-content-codes");
		
	$x->endTag("sports-metadata");
}

sub print_division_standings 
{
	my $writer = shift || die;
	my $season = shift || die;
	my $night = shift || die;

	my $league_sth = $DB->prepare(
		q{SELECT * 
		FROM league 
		WHERE season = ? AND day = ?
		ORDER BY league_id});
	$league_sth->execute($season,$night);
	my $league_hash = {};
	my $ary;
	while($ary = $league_sth->fetchrow_hashref()) {
		print_league_standings($writer, $ary);
	}
}

sub print_footer
{
	my $writer = shift;
	$writer->endTag("sports-content");
}

my $sort_data; ## evil, but it lets this work under 'strict'

sub sort_standings
{ 
	##print $sort_data->{$b}->{name}, " vs ", $sort_data->{$a}->{name}, "\n";
	## Figure out who's in the lead based on raw win/loss/tie.
	my $b_points = 0 + (2 * $sort_data->{$b}->{win}) + $sort_data->{$b}->{tie};
	my $a_points = 0 + (2 * $sort_data->{$a}->{win}) + $sort_data->{$a}->{tie};
	my $rc = ($b_points <=> $a_points);
	if($rc != 0)  { return $rc; }

	## If still tied, use head-to-head
	if(defined($sort_data->{$b}->{vs}->{$a}) && defined($sort_data->{$a}->{vs}->{$b})) {
		$rc = ($sort_data->{$b}->{vs}->{$a} <=> $sort_data->{$a}->{vs}->{$b});
   		if($rc != 0)  { return $rc; }
	}

	## If still tied, use +/-
	$rc = (($sort_data->{$b}->{points_for} - $sort_data->{$b}->{points_against}) <=> ($sort_data->{$a}->{points_for} - $sort_data->{$a}->{points_against}));
	if($rc != 0)  { return $rc; }

	## Ties after +/- are to be broken by SOTG.
	if($sort_data->{$b}->{games} > 0 && $sort_data->{$a}->{games} > 0) {
		$rc = (($sort_data->{$b}->{spirit} / $sort_data->{$b}->{games}) <=> ($sort_data->{$a}->{spirit} / $sort_data->{$a}->{games}));
		if($rc != 0)  { return $rc; }
	}

	## If still tied, check losses.  This is to ensure that teams without
	## a score on their sheet appear above teams who have lost a game.
	$rc = ($sort_data->{$a}->{loss} <=> $sort_data->{$b}->{loss});
	if($rc != 0) { return $rc; }

} 

sub print_league_standings
{
	my $x = shift;
	my $league = shift;
	my $want_round = undef;

	my $full_name = $league->{name};
	if($league->{tier} > 0) {
		$full_name .= " Tier " . $league->{tier};
	}
	$x->startTag("standing",
		'content-label' => $full_name);

	$x->emptyTag("standing-metadata",
		'date-coverage-type' => 'season-regular',
		'date-coverage-value' => $league->{year});
	
	my $league_id = $league->{league_id};

	## fetch our teams.
	my $sth = $DB->prepare(
		q{SELECT 
			lt.team_id,
			t.name
		  FROM
			leagueteams lt, team t
		  WHERE 
			lt.team_id = t.team_id
			AND league_id = ?});
	$sth->execute($league_id);

	## Initialise 
	my $season = {};
	my $round = {};
	my $row;
	while ($row = $sth->fetchrow_arrayref) {
		my ($team_id, $team_name) = @$row;
		$season->{$team_id} = {
			name => $team_name,
			points_for => 0,
			points_against => 0,
			spirit => 0,
			win => 0,
			loss => 0,
			tie => 0,
			defaults_for => 0,
			defaults_against => 0,
			games => 0,
			vs => {},
		};
		if(defined($want_round)) {
			$round->{$team_id} = {
				name => $team_name,
				points_for => 0,
				points_against => 0,
				spirit => 0,
				win => 0,
				loss => 0,
				tie => 0,
				defaults_for => 0,
				defaults_against => 0,
				games => 0,
				vs => {},
			};
		}
	}
	
		
	## Now, fetch the schedule info.
	## We want all games played by anyone currently in this league.
	$sth = $DB->prepare(
		q{SELECT DISTINCT
			s.game_id, 
			s.home_team, 
			s.away_team, 
			s.home_score, 
			s.away_score,
			s.home_spirit, 
			s.away_spirit,
			s.round,
			s.defaulted
		 FROM
		  	schedule s, leagueteams t
		 WHERE 
			t.league_id = ?
			AND (s.home_team = t.team_id OR s.away_team = t.team_id)
		 ORDER BY s.game_id});
	$sth->execute($league_id);

	while ($row = $sth->fetchrow_arrayref) {
		my ($game_id,$home_id,$away_id,$home_score,$away_score,$home_sotg,$away_sotg,$this_round,$defaulted) = @$row;

		## Skip unscored games.
		next if(!defined($home_score) || !defined($away_score));

		## Now, one of the two teams may not be in this league any longer.
		## If it's not, it won't have an entry in $season for its team ID.  
		record_game($season,$home_id,$away_id,$home_score,$away_score,$home_sotg,$away_sotg, $defaulted);
		if($want_round && $this_round == $want_round) {
			record_game($round,$home_id,$away_id,$home_score,$away_score,$home_sotg,$away_sotg, $defaulted);
		}
	}
	$sth->finish;

	## Now, get all this shiznit in sorted order and display.
	my @sorted_ids;
	if($want_round) {
		$sort_data = $round;
		@sorted_ids = sort sort_standings (keys %$round);
	} else {
		$sort_data = $season;
		@sorted_ids = sort sort_standings (keys %$season);
	}

	my @standings;
	my $rank = 1;
	foreach my $id (@sorted_ids) {
		my $spirit = '--';  ## Default to not displaying

		## SOTG gets displayed after the third game
		## We show entire season's spirit, not just the round.
		if($season->{$id}->{games} >= 3) {
			$spirit = sprintf("%.2f",$season->{$id}->{spirit} / ($season->{$id}->{games} - ($season->{$id}->{defaults_for} + $season->{$id}->{defaults_against})));
		}
		my $srow = {
			team_name => $season->{$id}->{name},
			wins => $season->{$id}->{win} || 0,
			losses => $season->{$id}->{loss} || 0,
			ties => $season->{$id}->{tie} || 0,
			defaults_against => $season->{$id}->{defaults_against} || 0,
			sotg => $spirit,
			points_for => $season->{$id}->{points_for} || 0,
			points_against => $season->{$id}->{points_against} || 0,
			plusminus => ($season->{$id}->{points_for} - $season->{$id}->{points_against}) || 0,
		};
		if(defined($want_round)) {
			$srow->{round_wins} = $round->{$id}->{win};
			$srow->{round_losses} = $round->{$id}->{loss};
			$srow->{round_ties} = $round->{$id}->{tie};
			$srow->{round_defaults_against} = $round->{$id}->{defaults_against},
			$srow->{round_points_for} = $round->{$id}->{points_for};
			$srow->{round_points_against} = $round->{$id}->{points_against};
			$srow->{round_plusminus} = $round->{$id}->{points_for} - $round->{$id}->{points_against};
		}
#		push @standings, $srow;
		print_one_team($x, $srow, $rank++);
	}
	$x->endTag("standing");
}

sub print_one_team
{
	my $x = shift;
	my $team = shift;
	my $rank = shift;
	
	$x->startTag("team");
	  $x->startTag("team-metadata");
	    $x->emptyTag("name", 'full' => $team->{team_name});
	  $x->endTag("team-metadata");
	  $x->startTag("team-stats",
	  	'standing-points' => (2 * $team->{wins}) + $team->{ties});
	    $x->emptyTag("outcome-totals",
	    	'wins' => $team->{wins},
	    	'losses' => $team->{losses},
	    	'ties' => $team->{ties},
	    	'points-scored-for' => $team->{points_for},
	    	'points-scored-against' => $team->{points_against},
	    );
	    $x->startTag("team-stats-ultimate");
	      $x->emptyTag("stats-ultimate-spirit",
	      	'value' => $team->{sotg});
	      $x->emptyTag("stats-ultimate-miscellaneous",
	      	'defaults' => $team->{defaults_against},
	      	'plusminus' => $team->{plusminus}
	      );
	    $x->endTag("team-stats-ultimate");
	    $x->emptyTag("rank", 
	    	'competition-scope' => 'tier',
		'value' => $rank);
	  $x->endTag("team-stats");
	  
	$x->endTag("team");
}


## Record one game into the standings hashtable.
sub record_game 
{
	my $sref = shift;
	my $home_id = shift;
	my $away_id = shift;
	my $home_score = shift;
	my $away_score = shift;
	my $home_sotg = shift;
	my $away_sotg = shift;
	my $defaulted = shift;

	if(defined($sref->{$home_id})) {
		$sref->{$home_id}->{games}++;
		$sref->{$home_id}->{points_for} += $home_score;
		$sref->{$home_id}->{points_against} += $away_score;
		if($defaulted eq 'home') {
			$sref->{$home_id}->{defaults_against}++;
		} elsif($defaulted eq 'away') {
			$sref->{$home_id}->{defaults_for}++;
		} else {
			$sref->{$home_id}->{spirit} += $home_sotg;
		}

		if($home_score == $away_score) {
			$sref->{$home_id}->{tie}++;
			$sref->{$home_id}->{vs}->{$away_id}++;
		} elsif ($home_score > $away_score) {
			$sref->{$home_id}->{win}++;
			$sref->{$home_id}->{vs}->{$away_id} += 2;
		} else {
			$sref->{$home_id}->{vs}->{$away_id} += 0; ## to prevent undef;
			$sref->{$home_id}->{loss}++;
		}
	}
	if(defined($sref->{$away_id})) {
		$sref->{$away_id}->{games}++;
		$sref->{$away_id}->{points_for} += $away_score;
		$sref->{$away_id}->{points_against} += $home_score;

		if($defaulted eq 'away') {
			$sref->{$away_id}->{defaults_against}++;
		} elsif($defaulted eq 'home') {
			$sref->{$away_id}->{defaults_for}++;
		} else {
			$sref->{$away_id}->{spirit} += $away_sotg;
		}

		if($home_score == $away_score) {
			$sref->{$away_id}->{tie}++;
			$sref->{$away_id}->{vs}->{$home_id}++;
		} elsif ($home_score > $away_score) {
			$sref->{$away_id}->{loss}++;
			$sref->{$away_id}->{vs}->{$home_id} += 0; ## to prevent undef;
		} else {
			$sref->{$away_id}->{win}++;
			$sref->{$away_id}->{vs}->{$home_id} +=2;
		}
	}
}
