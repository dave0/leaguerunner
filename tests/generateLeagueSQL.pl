#!/usr/bin/perl

use strict;

use Date::Manip;

my $NUMBEROFTEAMS = undef;
my $GAMESLOTID = undef;
my $NUMBEROFDAYS = undef;
my $STARTDATE = undef;
my $LEAGUEID = undef;
my $TEAMID = undef;
my $RANKINGS = undef;
my $OUTFILE = undef;
my $LEAGUENAME = undef;

getArgs(@ARGV);

open (OUTFILE, ">$OUTFILE");

my $date = UnixDate ($STARTDATE, "%Y-%m-%d");
my $year = UnixDate ($STARTDATE, "%Y");
my $season = "Summer";
my $games_before_repeat = 4;

my $SQL_GAMESLOT = "INSERT INTO gameslot VALUES (GAMESLOTID, FIELDID, 'DATE', '18:30:00', NULL, NULL);";
my $SQL_AVAILABILITY = "INSERT INTO league_gameslot_availability VALUES (LEAGUEID,GAMESLOTID);";
my $SQL_TEAM = "INSERT INTO team VALUES (TEAMID,'TEAMNAME','http://ocua.ca','nocolor',NULL,'---','open',1500);";
my $SQL_LEAGUETEAMS = "INSERT INTO leagueteams VALUES (LEAGUEID,TEAMID,RANK);";
my $SQL_LEAGUE = "INSERT INTO league VALUES ($LEAGUEID,'$LEAGUENAME','Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday','$season',0,'4/3',0,'all',$year,'pyramid',$games_before_repeat,100);";
my $SQL_LEAGUEMEMBER = "INSERT INTO leaguemembers VALUES ($LEAGUEID,1,'coordinator');";

# CREATE THE LEAGUE
print OUTFILE "$SQL_LEAGUE\n";

# SET THE COORDINATOR OF THE LEAGUE
print OUTFILE "$SQL_LEAGUEMEMBER\n";

# CREATE THE GAMESLOTS...
my $field = 1;
while ($NUMBEROFDAYS > 0) {
   while ($field <= $NUMBEROFTEAMS/2) {
      my $g = $SQL_GAMESLOT;
      $g =~ s/GAMESLOTID/$GAMESLOTID/g;
      $g =~ s/FIELDID/$field/g;
      $g =~ s/DATE/$date/g;
      print OUTFILE $g . "\n";
      my $a = $SQL_AVAILABILITY;
      $a =~ s/LEAGUEID/$LEAGUEID/g;
      $a =~ s/GAMESLOTID/$GAMESLOTID/g;
      print OUTFILE $a . "\n";
      $GAMESLOTID++;
      $field++;
   }
   $date = UnixDate ( DateCalc ($date, "+1days"), "%Y-%m-%d" );
   $NUMBEROFDAYS --;
   $field = 1;
}


# CREATE THE TEAMS AND ADD THEM TO THE LEAGUE
my $rank = 1000;
my $mod = 4;
if ($NUMBEROFTEAMS % 8 == 0) {
   $mod = 8;
}


while ($NUMBEROFTEAMS > 0) {
   my $t = $SQL_TEAM;
   $t =~ s/TEAMID/$TEAMID/g;
   $t =~ s/TEAMNAME/Team$TEAMID/g;
   print OUTFILE $t . "\n";
   my $lt = $SQL_LEAGUETEAMS;
   $lt =~ s/LEAGUEID/$LEAGUEID/g;
   $lt =~ s/TEAMID/$TEAMID/g;
   $lt =~ s/RANK/$rank/g;
   print OUTFILE $lt . "\n";
   if ($RANKINGS ne "same" && $NUMBEROFTEAMS % $mod == 1) {
      $rank++;
   }
   $NUMBEROFTEAMS--;
   $TEAMID++;
}


close (OUTFILE);





sub printUsage {
   print "\n";
   print "----------------------------------------------------------------------\n";
   print "--- generateLeagueSQL.pl                                              \n";
   print "                                                                      \n";
   print "--- This script is used to generate an SQL file that can be sucked in \n";
   print "--- to your mysql leaguerunner database.  Use this script to generate \n";
   print "--- a large league to test with.  It will create all the required     \n";
   print "--- artifacts within leaguerunner such as all the teams and gameslots \n";
   print "--- as well as the league.  The script requires several input         \n";
   print "--- parameters as follows:                                            \n";
   print "                                                                      \n";
   print "       -t | -number-of-teams :  number of teams for this league       \n";
   print "       -gid | -gameslot-id   :  starting gameslot ID                  \n";
   print "       -n | -number-of-days  :  number of days of gameslots required  \n";
   print "       -d | -start-date      :  date for first gameslot               \n";
   print "       -lid | -league-id     :  league ID to use for this league      \n";
   print "       -tid | -team-id       :  starting team ID                      \n";
   print "       -r | -rankings        :  teams ranked: 'same', 'different'     \n";
   print "       -o | -out-file        :  file to write the SQL to              \n";
   print "                                                                      \n";
   print "--- The script also requires the last argument to be the league name. \n";
   print "--- Typical usage follows:                                            \n";
   print "                                                                      \n";
   print "  ./generateLeagueSQL -t 80 -gid 1000 -n 30 -d 2006-03-01 -lid 100 \\ \n";
   print "     -tid 1000 -r same -o out.sql Test80TeamLeague                    \n";
   print "                                                                      \n";
   print "  mysql -uDBUSER -p DBNAME < out.sql                                  \n";
   print "                                                                      \n";
   print "--- ASSUMPTIONS:                                                      \n";
   print "---              - pyramid ladder league                              \n";
   print "----------------------------------------------------------------------\n";
   print "\n";
   exit();
}

sub getArgs {
   my @args = @_;
   my $numargs = @args;
   if ($numargs != 17) {
      printUsage();
   }
   for ( my $i = 0; $i < @args; $i++ ) {
      my $arg = $args[$i];
      if ($arg eq "-t" || $arg eq "-number-of-teams") {
         $NUMBEROFTEAMS = $args[++$i];
      } elsif ($arg eq "-gid" || $arg eq "-gameslot-id") {
         $GAMESLOTID = $args[++$i];
      } elsif ($arg eq "-n" || $arg eq "-number-of-days") {
         $NUMBEROFDAYS = $args[++$i];
      } elsif ($arg eq "-d" || $arg eq "-start-date") {
         $STARTDATE = $args[++$i];
      } elsif ($arg eq "-lid" || $arg eq "-league-id") {
         $LEAGUEID = $args[++$i];
      } elsif ($arg eq "-tid" || $arg eq "-team-id") {
         $TEAMID = $args[++$i];
      } elsif ($arg eq "-r" || $arg eq "-rankings") {
         $RANKINGS = $args[++$i];
      } elsif ($arg eq "-o" || $arg eq "-out-file") {
         $OUTFILE = $args[++$i];
      } else {
         $LEAGUENAME = $args[$i];
      }
   }

   if ( not defined $NUMBEROFTEAMS || $NUMBEROFTEAMS eq "" ||
        not defined $GAMESLOTID || $GAMESLOTID eq "" ||
        not defined $NUMBEROFDAYS || $NUMBEROFDAYS eq "" ||
        not defined $STARTDATE || $STARTDATE eq "" ||
        not defined $LEAGUEID || $LEAGUEID eq "" ||
        not defined $TEAMID || $TEAMID eq "" ||
        not defined $RANKINGS || $RANKINGS eq "" ||
        not defined $OUTFILE || $OUTFILE eq "" ||
        not defined $LEAGUENAME || $LEAGUENAME eq "" ) {
      printUsage();
   }

}

