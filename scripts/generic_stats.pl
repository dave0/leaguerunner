#!/usr/bin/perl -w
#
## Retrieve generic aggregate statistics from system.
## Dave O'Neill <dmo@dmo.ca> Wed, 23 Apr 2003 22:50:31 -0400 
##
## Usage: generic_stats.pl [-m address@host]

use strict;
use DBI;
use POSIX;
use Leaguerunner;
use Getopt::Mixed;
use IO::Handle;
use IO::Pipe;

our(@addresses, $addresses);

Getopt::Mixed::init("m=s mailto>m");
my $optarg;
while( ($_, $optarg) = Getopt::Mixed::nextOption()) {
	/^m$/ && do {
		## TODO: validation?
		push(@addresses, $optarg);
	};
	
}
Getopt::Mixed::cleanup();

my $config = Leaguerunner::parseConfigFile("../src/leaguerunner.conf");

## Initialise database handle.
my $dsn = join("",
	"DBI:mysql:database=", $config->{db_name}, 
	":host=", $config->{db_host});

my $DB = DBI->connect($dsn, $config->{db_user}, $config->{db_password}) || die("Error establishing database connect; $DBI::errstr\n");

$DB->{RaiseError} = 1;

## We must remember to disconnect on exit.  Use the magical END sub.
sub END { $DB->disconnect() if defined($DB); }

## Now, retrieve stats
my $sth;
my $ary;

my $stats = "OCUA Leaguerunner statistics\n";
$stats .= "extracted from database on " . scalar(localtime(time)) . "\n\n";

##
##  Player Stats
##
$stats .= "Player Statistics\n";

$sth = $DB->prepare(q{SELECT COUNT(*) from person});
$sth->execute();
$ary  = $sth->fetchrow_arrayref();
$stats .= "\tNumber of players (total):      " . $ary->[0]."\n";

$stats .= "\tPlayers by current account status \n";
$sth = $DB->prepare(q{SELECT status,COUNT(*) from person group by status});
$sth->execute();
while($ary  = $sth->fetchrow_arrayref()) {
	$stats .= "\t\t" . print_evenly($ary->[0], $ary->[1], 24);
}

$stats .= "\tPlayers by current account class \n";
$sth = $DB->prepare(q{SELECT class,COUNT(*) from person group by class});
$sth->execute();
while($ary  = $sth->fetchrow_arrayref()) {
	$stats .= "\t\t" . print_evenly($ary->[0], $ary->[1], 24);
}

$stats .= "\tPlayers by gender:\n";
$sth = $DB->prepare(q{SELECT gender,COUNT(*) from person group by gender});
$sth->execute();
while($ary  = $sth->fetchrow_arrayref()) {
	$stats .= "\t\t" . print_evenly($ary->[0], $ary->[1], 24);
}

$stats .= "\tPlayers by age:\n";
$sth = $DB->prepare(q{SELECT FLOOR((YEAR(NOW()) - YEAR(birthdate)) / 5) * 5 as age_bucket, COUNT(*) from person group by age_bucket});
$sth->execute();
while($ary  = $sth->fetchrow_arrayref()) {
	my $age_range = $ary->[0] . " to " . ($ary->[0] + 4);
	$stats .= "\t\t" . print_evenly($age_range, $ary->[1], 24);
}

$stats .= "\tPlayers by identified city:\n";
$sth = $DB->prepare(q{SELECT addr_city,COUNT(*) as num from person group by addr_city ORDER BY num desc});
$sth->execute();
while($ary  = $sth->fetchrow_arrayref()) {
	$stats .= "\t\t" . print_evenly($ary->[0], $ary->[1], 24);
}
$stats .= "\tPlayers by skill level:\n";
$sth = $DB->prepare(q{SELECT skill_level,COUNT(*) as num from person group by skill_level ORDER BY skill_level});
$sth->execute();
while($ary  = $sth->fetchrow_arrayref()) {
	$stats .= "\t\t" . print_evenly($ary->[0], $ary->[1], 24);
}
$stats .= "\tPlayers by starting year:\n";
$sth = $DB->prepare(q{SELECT year_started,COUNT(*) as num from person group by year_started ORDER BY year_started});
$sth->execute();
while($ary  = $sth->fetchrow_arrayref()) {
	$stats .= "\t\t" . print_evenly($ary->[0], $ary->[1], 24);
}

$sth = $DB->prepare(q{SELECT COUNT(*) from person where has_dog = 'Y' and !ISNULL(dog_waiver_signed)});
$sth->execute();
$ary  = $sth->fetchrow_arrayref();
$stats .= "\tNumber of dog waivers signed:   " . $ary->[0] . "\n";


$sth = $DB->prepare(q{SELECT COUNT(*) from demographics});
$sth->execute();
$ary  = $sth->fetchrow_arrayref();
$stats .= "\tNumber demographics responses:  " . $ary->[0] . "\n";

##
##  Team Stats
##
$stats .= "\n\nTeam Statistics\n";

$sth = $DB->prepare(q{SELECT COUNT(*) from team});
$sth->execute();
$ary  = $sth->fetchrow_arrayref();
$stats .= "\tNumber of teams (total):        " . $ary->[0] . "\n";


$stats .= "\tTeam assignments by current league/tier:\n";
$sth = $DB->prepare(q{SELECT l.year, l.name, l.tier, count(*) 
			FROM leagueteams t 
			LEFT JOIN league l ON(l.league_id = t.league_id)
			GROUP BY t.league_id 
			ORDER BY l.year,l.season,l.day,l.tier});
$sth->execute();
while($ary  = $sth->fetchrow_arrayref()) {
	my $tier_name = "";
	if(defined($ary->[0])) {
		$tier_name .= $ary->[0] . " ";
	}
	$tier_name .= $ary->[1];
	if(defined($ary->[2])) { 
		$tier_name .= " Tier " . $ary->[2];
	}
	$stats .= "\t\t" . print_evenly($tier_name, $ary->[3], 40);
}

$sth = $DB->prepare(q{
	SELECT t.team_id,t.name, COUNT(r.player_id) as size 
	FROM teamroster r , league l, leagueteams lt
	LEFT JOIN team t ON (t.team_id = r.team_id) 
 	WHERE 
		lt.team_id = r.team_id
		AND l.league_id = lt.league_id 
		AND l.allow_schedule = 'Y' 
		AND (r.status = 'player' OR r.status = 'captain' OR r.status = 'assistant')
	GROUP BY t.team_id 
	HAVING size < 12
	ORDER BY size desc});
$sth->execute();
my $subs = $DB->prepare(q{SELECT COUNT(*) FROM teamroster r WHERE r.team_id = ? AND r.status = 'substitute'});
my $teams_under;
while(($ary = $sth->fetchrow_arrayref())) {
	$subs->execute($ary->[0]);
	my $num_subs = ($subs->fetchrow_arrayref())->[0];
	if(floor($num_subs / 3) + $ary->[2] < 12) {
		unshift(@$teams_under, $ary->[1]);
	}
}
my $num_rows = scalar(@$teams_under);
$stats .= "\tTeams with rosters under the required 12 confirmed players: $num_rows\n";
my $num_team_threshold = 50;
if($num_rows < $num_team_threshold) {
	while(my $row = shift(@$teams_under)) {
		$stats .= "\t\t" . print_evenly($row, "", 30);
	}
} else {
	$stats .= "\t\t[ list suppressed; longer than $num_team_threshold teams ]\n";
}

my $fh;
if(scalar(@addresses)) {
	$fh = new IO::Pipe;
	$fh->writer(qw(/usr/sbin/sendmail -oi -t))
	    || die "Couldn't exec sendmail: $!";
	$addresses = join(",",@addresses);
} else {
	$fh = new IO::Handle;
	$fh->fdopen(fileno(STDOUT),"w");
	$addresses = "Not sent via email";
}

print $fh <<EOF;
From: Leaguerunner Stats Harvester <$config->{admin_email}>
To: $addresses
Subject: Leaguerunner Stats Update

$stats

EOF

$fh->close();

sub print_evenly
{
	my $leftstr = shift;
	my $rightstr = shift;
	my $col = shift;
	return $leftstr . " " x ($col - length($leftstr)) . $rightstr . "\n";
}
