#!/usr/bin/perl -w
##
## Retrieve generic aggregate statistics from system.
## Dave O'Neill <dmo@dmo.ca> Wed, 23 Apr 2003 22:50:31 -0400 

use strict;
use DBI;

my $database_name = 'leaguerunner';
my $database_host = 'localhost';
my $database_user = 'leaguerunner';
my $database_pass = 'ocuaweb';

## Initialise database handle.
my $dsn = "DBI:mysql:database=${database_name}:host=${database_host}";

my $DB = DBI->connect($dsn, $database_user, $database_pass) || die("Error establishing database connect; $DBI::errstr\n");

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

$stats .= "\tPlayers by current status:\n";
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
$sth = $DB->prepare(q{SELECT addr_city,COUNT(*) as num from person group by addr_city ORDER BY num});
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
	my $tier_name = $ary->[0] . " " . $ary->[1];
	if(defined($ary->[2])) { 
		$tier_name .= " Tier " . $ary->[2];
	}
	$stats .= "\t\t" . print_evenly($tier_name, $ary->[3], 40);
}

$sth = $DB->prepare(q{
	SELECT t.name,COUNT(r.player_id) as size 
	FROM teamroster r 
	LEFT JOIN team t ON (t.team_id = r.team_id) 
	GROUP BY t.team_id 
	HAVING size < 10 
	ORDER BY size desc});
$sth->execute();
$ary = $sth->fetchall_arrayref();
my $num_rows = scalar(@$ary);
$stats .= "\tTeams with rosters under the required 12 players: $num_rows\n";
if($num_rows < 25) {
	while(my $row = shift(@$ary)) {
		$stats .= "\t\t" . print_evenly($row->[0], $row->[1], 30);
	}
} else {
	$stats .= "\t\t[ list suppressed; longer than 25 teams ]\n";
}

print $stats;

sub print_evenly
{
	my $leftstr = shift;
	my $rightstr = shift;
	my $col = shift;
	return $leftstr . " " x ($col - length($leftstr)) . $rightstr . "\n";
}
