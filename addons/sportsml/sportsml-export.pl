#!/usr/bin/perl -w
#
# Export Leaguerunner ultimate standings in SportsML format
# (http://www.sportsml.com)
#
# Dave O'Neill May 23, 2003
use strict;
use Carp;
use DBI;
use POSIX;
use Leaguerunner;
use Pod::Usage;
use Getopt::Mixed;
use XML::Writer;
use IO;

use constant TIER_PAGE => 1;
use constant DIVISION_PAGE => 2;

my $league_name = "Ottawa-Carleton Ultimate Association";

my $grouping = TIER_PAGE;
#my $grouping = DIVISION_PAGE;

our($location,$season);

Getopt::Mixed::init("l=s s=s location>l season>s");
my $optarg;
while( ($_, $optarg) = Getopt::Mixed::nextOption()) {
	/^l$/ && do {
		$location = $optarg;
	};
	/^s$/ && do {
		$season = $optarg;
	};
}
Getopt::Mixed::cleanup();

if (! -d $location ) {
	pod2usage("Must specify a valid location directory");
}

if ( length($season) == 0 ) {
	pod2usage("Must specify a season");
}

my $config = Leaguerunner::parseConfigFile("../..//src/leaguerunner.conf");

## Initialise database handle.
my $dsn = join("",
	"DBI:mysql:database=", $config->{db_name}, 
	":host=", $config->{db_host});

my $DB = DBI->connect($dsn, $config->{db_user}, $config->{db_password}) || die("Error establishing database connect; $DBI::errstr\n");

$DB->{RaiseError} = 1;

## We must remember to disconnect on exit.  Use the magical END sub.
sub END { $DB->disconnect() if defined($DB); }
my $leagues = get_leagues_for_season($season);

export_to_files($location,$leagues, 
	\&export_tier_schedule,
	\&export_tier_standing,
);

sub export_to_files
{
	my $filebase = shift;
	my $leagues  = shift;
	my @coderefs = @_;
	
	my $prev_league = undef;
	my $xml;
	my $file;
	foreach my $league (@$leagues) {
		if( need_new_page($grouping, $league, $prev_league)) {
			if(defined($prev_league)) {
				end_export_page($file, $xml);
			}
			($file, $xml) = start_export_page($filebase, $league);
		}
		foreach my $coderef (@coderefs) {
			print $league->{name}, "\n";
			$coderef->($xml, $league);
		}
		$prev_league = $league;
	}
	end_export_page($file, $xml);
}

sub export_tier_schedule
{
	my ($x, $league) = @_;
	
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
			UNIX_TIMESTAMP(s.date_played) as game_date,
			s.home_team, 
			s.away_team, 
			s.home_score,
			s.away_score,
			h.name AS home_name,
			a.name AS away_name,
			t.site_id,
			CONCAT(t.name,' ',f.num) AS field_name
		  FROM
		  	schedule s, field f, site t
			LEFT JOIN team h ON (h.team_id = s.home_team)
			LEFT JOIN team a ON (a.team_id = s.away_team)
		  WHERE 
               f.field_id = s.field_id
               AND t.site_id = f.site_id
		  	AND s.league_id = ? ORDER BY s.date_played});
	$sth->execute($league->{league_id}) or croak();
	my $row;
	my $currentTime = time();
	while ($row = $sth->fetchrow_hashref) {

		## Skip weeks without teams	
		next if(!defined($row->{'home_team'}) || !defined($row->{'away_team'}));

		my $event_status = "pre-event";

		# If date is already past, change to "post-event"
		if($currentTime > $row->{'game_date'}) {
			$event_status = "post-event";
		}
		
		$x->startTag("sports-event");
		  $x->startTag("event-metadata",
			'site-name' => $row->{'field_name'},
			'site-id' => $row->{'site_id'},
			'start-date-time' => strftime("%Y-%m-%dT%H:%M",localtime($row->{'game_date'})),
			'event-status' => $event_status
		  );
		  $x->endTag("event-metadata");
		  $x->startTag("team");
		    $x->startTag("team-metadata", 'alignment' => 'home');
		      $x->emptyTag("name", 'full' => $row->{'home_name'});
		    $x->endTag("team-metadata");
		   	$x->emptyTag("team-stats", 'score' => (defined($row->{'home_score'}) ? $row->{'home_score'} : ""));
		  $x->endTag("team");
		  $x->startTag("team");
		    $x->startTag("team-metadata", 'alignment' => 'away');
		      $x->emptyTag("name", 'full' => $row->{'away_name'});
		    $x->endTag("team-metadata");
		   	$x->emptyTag("team-stats", 'score' => (defined($row->{'away_score'}) ? $row->{'away_score'} : ""));
		  $x->endTag("team");
		$x->endTag("sports-event");
	}
	$sth->finish;
	
	$x->endTag("schedule");
}

my $sort_data;
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
		$rc = (calculate_sotg($sort_data->{$b},1) <=> calculate_sotg($sort_data->{$a},1));
		if($rc != 0)  { return $rc; }
	}

	## If still tied, check losses.  This is to ensure that teams without
	## a score on their sheet appear above teams who have lost a game.
	$rc = ($sort_data->{$a}->{loss} <=> $sort_data->{$b}->{loss});
	if($rc != 0) { return $rc; }

} 

sub export_tier_standing
{
	my ($x, $league) = @_;

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
			worst_spirit => 9999,
			best_spirit => 0,
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
				worst_spirit => 9999,
				best_spirit => 0,
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
			$spirit = sprintf("%.2f", calculate_sotg($season->{$id},1));
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
		
		$x->startTag("team");
		  $x->startTag("team-metadata");
		    $x->emptyTag("name", 'full' => $srow->{team_name});
		  $x->endTag("team-metadata");
		  $x->startTag("team-stats",
			'standing-points' => (2 * $srow->{wins}) + $srow->{ties});
		    $x->emptyTag("outcome-totals",
			'wins' => $srow->{wins},
			'losses' => $srow->{losses},
			'ties' => $srow->{ties},
			'points-scored-for' => $srow->{points_for},
			'points-scored-against' => $srow->{points_against},
		    );
		    $x->startTag("team-stats-ultimate");
		      $x->emptyTag("stats-ultimate-spirit",
			'value' => $srow->{sotg});
		      $x->emptyTag("stats-ultimate-miscellaneous",
			'defaults' => $srow->{defaults_against},
			'plusminus' => $srow->{plusminus}
		      );
		    $x->endTag("team-stats-ultimate");
		    $x->emptyTag("rank", 
			'competition-scope' => 'tier',
			'value' => $rank);
		  $x->endTag("team-stats");
		  
		$x->endTag("team");
		
		$rank++;
	}
	$x->endTag("standing");
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
			if($sref->{$home_id}->{best_spirit} < $home_sotg) {
				$sref->{$home_id}->{best_spirit} = $home_sotg;
			}
			if($sref->{$home_id}->{worst_spirit} > $home_sotg) {
				$sref->{$home_id}->{worst_spirit} = $home_sotg;
			}
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
			if($sref->{$away_id}->{worst_spirit} > $away_sotg) {
				$sref->{$away_id}->{worst_spirit} = $away_sotg
			}
			if($sref->{$away_id}->{best_spirit} < $away_sotg) {
				$sref->{$away_id}->{best_spirit} = $away_sotg
			}
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

sub start_export_page
{
	my ($filebase, $league) = @_;
	
	my $filename = league_make_filename($filebase, $league);
	print "Starting new file $filename for " , $league->{'name'}, "\n";

	my $fh = new IO::File $filename, O_RDWR|O_CREAT|O_TRUNC or croak("Couldn't create $filename: $!");
	my $x = new XML::Writer(
		DATA_MODE => 1,
		DATA_INDENT => 4,
		OUTPUT => $fh) or croak("Couldn't create XML::Writer");
		
	$x->xmlDecl("ISO-8859-1");
	$x->startTag("sports-content");
	  $x->startTag("sports-metadata");
	    $x->dataElement("sports-title", league_make_title($league));
	  $x->endTag("sports-metadata");

	return ($fh, $x);
}

# finalize XML::Writer, close file, delete both objects
sub end_export_page
{
	my ($fh, $x) = @_;
	$x->endTag("sports-content");
	$x->end();
	$fh->close();
	undef $x;
	undef $fh;
}

sub need_new_page
{
	my $grouping = shift || croak("No grouping given");	
	my $league   = shift || croak("No league given");
	
	my $prev_league = shift || return 1;

	if($grouping == DIVISION_PAGE) {
		return 1 if($league->{'day'} ne $prev_league->{'day'});
		return 1 if($league->{'ratio'} ne $prev_league->{'ratio'});
		return 0;
	} elsif ($grouping == TIER_PAGE) {
		return 1;
	}

	return 0;
}

sub get_leagues_for_season
{
	my $season = shift;

	print "Fetching leagues for season $season\n";
	
	my $league_sth = $DB->prepare(q{
		SELECT * FROM league 
		WHERE season = ?
		ORDER BY day, ratio, tier});
	$league_sth->execute($season);
	return $league_sth->fetchall_arrayref({});
}

sub league_make_filename
{
	my ($filebase, $league) = @_;
	my $suffix;
	my $ratio = $league->{'ratio'};
	$ratio =~ s/\///g;
	if($grouping == TIER_PAGE) {
		$suffix = lc("/" .  join("_",$league->{'season'}, $league->{'name'}, $ratio, $league->{'tier'})) . ".xml";
	} else {
		$suffix = lc("/" .  join("_",$league->{'season'}, $league->{'name'}, $ratio)) . ".xml";
	}

	$suffix =~ s/ /_/g;
	return $filebase . $suffix;
}

sub league_make_title
{
	my $league = shift;
	my $league_title = $league->{'name'};
	if($grouping == TIER_PAGE && $league->{'tier'} > 0) {
		$league_title .= " Tier " . $league->{'tier'};
	}
	return $league_title
}

sub calculate_sotg
{
	my $stats = shift;
	my $drop_best_worst = shift || 0;

	my $raw = $stats->{'spirit'};
	my $games = $stats->{'games'} - ($stats->{defaults_for} + $stats->{defaults_against});

	if($games > 0) {
		if($games >= 3 && $drop_best_worst) {
			$raw = $raw - ($stats->{best_spirit} + $stats->{worst_spirit});
			$games = $games - 2;
		}
		return $raw / $games;
	} else {
		return 0;
	}
}


__END__

=head1 NAME

sportsml-export - Export Leaguerunner stats as SportsML

=head1 SYNOPSIS

sportsml-export --season=Summer --location=/tmp/export

=head1 OPTIONS

=over 4

=item B<--season>

Specify which season to export

=item B<--location>

The directory to export these files to.  Must exist.

=back

=head1 DESCRIPTION

This program exports scores and schedules from Leaguerunner in the
SportsML export format.  See http://www.sportsml.com for details on the
format.

=cut
