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
print_division_schedule($writer, $season, $night);
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
		"Schedule: $league_name $season $day");
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

sub print_division_schedule 
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
		print_league_schedule($writer, $ary);
	}
}

sub print_footer
{
	my $writer = shift;
	$writer->endTag("sports-content");
}

sub print_league_schedule
{
	my $x = shift;
	my $league = shift;
	
	my $full_name = $league->{name};
	if($league->{tier} > 0) {
		$full_name .= " Tier " . $league->{tier};
	}
	$x->startTag("schedule",
		'content-label' => $full_name);

	$x->emptyTag("schedule-metadata",
		'team-coverage' => 'multi-team',
		'date-coverage-type' => 'season-regular',
		'date-coverage-value' => $league->{year});
	
	## Fetch our schedules	
	my $sth = $DB->prepare(
		q{SELECT DISTINCT
			s.game_id, 
			s.league_id,
			DATE_FORMAT(s.date_played, "%Y-%m-%dT%H:%M") as game_date,
			DATE_FORMAT(s.date_played, "%Y-%m-%dT%H:%M") as game_time,
			s.home_team, 
			s.away_team, 
			s.field_id,
			s.home_score,
			s.away_score,
			h.name,
			a.name,
			CONCAT(t.name,' ',f.num,' (',t.code,f.num,')') AS field_name,
			f.site_id,
			h.shirt_colour,
			a.shirt_colour
		  FROM
		  	schedule s, field f, site t
			LEFT JOIN team h ON (h.team_id = s.home_team)
			LEFT JOIN team a ON (a.team_id = s.away_team)
		  WHERE 
               f.field_id = s.field_id
               AND t.site_id = f.site_id
		  	AND s.league_id = ? ORDER BY s.date_played});
	$sth->execute($league->{league_id});
	my $row;
	while ($row = $sth->fetchrow_arrayref) {

		## Skip weeks without teams	
		next if(!defined($row->[4]) || !defined($row->[5]));

		my $home_score = $row->[7];
		my $away_score = $row->[8];
		if(defined($home_score) && defined($away_score)) {
			if($home_score > $away_score) {
				$home_score .= " (win)";
				$away_score .= " (loss)";
			} elsif ($home_score < $away_score) {
				$home_score .= " (loss)";
				$away_score .= " (win)";
			} else {
				$home_score .= " (tie)";
				$away_score .= " (tie)";
			}
		}
		my $home_name = $row->[9];
		$home_name .= " ($row->[13])" if($row->[13]);

		my $away_name = $row->[10];
		$away_name .= " ($row->[14])" if($row->[14]);

		my $game = {
			game_date => $row->[2],
			home_name => $home_name,
			away_name => $away_name,
			field_name => $row->[11],
			field_url =>  $row->[12],
			home_score => $home_score,
			away_score => $away_score,
		};
		print_one_game($x, $game);
	}
	$sth->finish;
	$x->endTag("schedule");
}

sub print_one_game
{
	my $x = shift;
	my $game = shift;

	my $event_status = "pre-event";

	# TODO: If date is already past, change to "post-event", rather
	# than checking scores.  This requires a real date, not the evilly 
	# formatted one
	if($game->{home_score} || $game->{away_score}) {
		$event_status = "post-event";
	}
	
	
	$x->startTag("sports-event");
	  $x->startTag("event-metadata",
	  	'site-name' => $game->{field_name},
	  	'start-date-time' => $game->{game_date},
		'event-status' => $event_status
	  );
	  $x->endTag("event-metadata");
	  $x->startTag("team");
	    $x->startTag("team-metadata", 'alignment' => 'home');
	      $x->emptyTag("name", 'full' => $game->{'home_name'});
	    $x->endTag("team-metadata");
	    $x->emptyTag("team-stats", 'score' => $game->{home_score});
	  $x->endTag("team");
	  $x->startTag("team");
	    $x->startTag("team-metadata", 'alignment' => 'away');
	      $x->emptyTag("name", 'full' => $game->{'away_name'});
	    $x->endTag("team-metadata");
	    $x->emptyTag("team-stats", 'score' => $game->{away_score});
	  $x->endTag("team");
	  
	$x->endTag("sports-event");
}
