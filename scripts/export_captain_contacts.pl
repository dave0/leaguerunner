#!/usr/bin/perl

use warnings;
use strict;

use DBI;
use Leaguerunner;
use Getopt::Mixed;
use Spreadsheet::WriteExcel;
use Spreadsheet::WriteExcel::Utility;

our( $season, $year );

Getopt::Mixed::init("s=s season>s y=s year>y");
my $optarg;
while( ($_, $optarg) = Getopt::Mixed::nextOption()) {
	/^s$/ && do {
		## TODO: validation?
		$season = $optarg;
	};
	/^y$/ && do {
		$year = $optarg;
	};
}
Getopt::Mixed::cleanup();

if( ! $season ) {
	print "Usage: $0 --season [season] --year [year]\n";
	exit 1;
}

if( ! $year ) {
	print "Usage: $0 --season [season] --year [year]\n";
	exit 1;
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

# Define column headings
my @columnHeadings = (
	'Night','Tier','Team','Lastname', 'Firstname', 'Member Number', 'Captain or Assistant?', 'Email');

# Set up queries.
my $listQuery = $DB->prepare(
	qq{SELECT l.day, l.tier, t.name, p.lastname, p.firstname, p.member_id, r.status, p.email FROM league l, leagueteams lt, team t, teamroster r, person p WHERE (l.season = ? AND lt.league_id=l.league_id AND lt.team_id = t.team_id AND t.team_id = r.team_id AND r.player_id = p.user_id AND (r.status = 'captain' OR r.status = 'assistant')) ORDER BY l.day, l.tier, t.team_id});
	
# Create Excel spreadsheet.
my $workbook  = Spreadsheet::WriteExcel->new("captain_export_${season}_${year}.xls");
my $boldFormat = $workbook->addformat(); # Add a format
$boldFormat->set_bold();
		   
$listQuery->execute($season);
my $sheet = $workbook->addworksheet("$season $year Teams");
my $column = 0;
my $row = 0;
foreach my $heading (@columnHeadings)  {
	$sheet->write($row,$column++, $heading, $boldFormat);
}
$row++;
my $person;
while(defined($person = $listQuery->fetchrow_hashref())) {
	$column = 0;
	$sheet->write($row,$column++, $person->{'day'});
	$sheet->write($row,$column++, $person->{'tier'});
	$sheet->write($row,$column++, $person->{'name'});
	$sheet->write($row,$column++, $person->{'lastname'});
	$sheet->write($row,$column++, $person->{'firstname'});
	$sheet->write($row,$column++, $person->{'member_id'});
	$sheet->write($row,$column++, $person->{'status'});
	$sheet->write($row,$column++, $person->{'email'});
	$row++;	
}
$sheet->freeze_panes(1, 0);
$workbook->close();
