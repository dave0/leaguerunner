#!/usr/bin/perl

use warnings;
use strict;
use DBI;
use Leaguerunner;
use Spreadsheet::WriteExcel;

my $config = Leaguerunner::parseConfigFile("../src/leaguerunner.conf");

## Initialise database handle.
my $DB = DBI->connect( $config->{database}{dsn}, $config->{database}{username}, $config->{database}{password}, { RaiseError => 1, }) || die("Error establishing database connect; $DBI::errstr\n");

sub END { $DB->disconnect() if defined($DB); }

my $sth = $DB->prepare(
        qq{SELECT u.member_id, u.addr_street, u.addr_city, u.addr_postalcode FROM person u where u.status = 'active'});
$sth->execute;
my $ary;
## now, create Excel spreadsheet.

my $workbook  = Spreadsheet::WriteExcel->new("postalcode_listing.xls");
my $worksheet = $workbook->addworksheet('Postal Code Listing');
my $format = $workbook->addformat(); # Add a format
$format->set_bold();
                   

## Write headings to sheet
my $col = 0;  #needed for column position
foreach my $head ("ID","Street", "City", "Postalcode") {
        $worksheet->write(0, $col++,  $head, $format);
}

my $row = 1;
while(defined($ary = $sth->fetchrow_arrayref)) {
        my $col = 0;
        foreach my $this_col (@$ary) {
                $worksheet->write($row,$col++,$this_col || " ");
        }
        $row++;
}
$sth->finish;
