#!perl -T

use Test::More tests => 2;

BEGIN {
    use_ok( 'Leaguerunner' ) || print "Bail out!
";
    use_ok( 'Leaguerunner::DBInit' ) || print "Bail out!
";
}

diag( "Testing Leaguerunner $Leaguerunner::VERSION, Perl $], $^X" );
