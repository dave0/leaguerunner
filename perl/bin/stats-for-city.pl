#!/usr/bin/perl -w
use strict;
use warnings;
use lib qw( /opt/websites/www.ocua.ca/leaguerunner/perl/lib );
use DBI;
use Leaguerunner;

my $config = Leaguerunner::parseConfigFile("/opt/websites/www.ocua.ca/leaguerunner/src/leaguerunner.conf");

## Initialise database handle.
my $DB = DBI->connect( $config->{database}{dsn}, $config->{database}{username}, $config->{database}{password}, { RaiseError => 1, }) ||
	die("Error establishing database connect; $DBI::errstr\n");

my $adult_sth = $DB->prepare(q{SELECT p.gender, count(*) AS num FROM league l, leagueteams lt, teamroster r, person p WHERE p.birthdate < date_sub(now(), interval 18 year) AND l.league_id = lt.league_id AND lt.team_id = r.team_id AND r.player_id = p.user_id AND l.season = ? GROUP BY p.gender});
my $adult_residency = $DB->prepare(q{SELECT p.addr_city, count(*) AS num FROM league l, leagueteams lt, teamroster r, person p WHERE p.birthdate < date_sub(now(), interval 18 year) AND l.league_id = lt.league_id AND lt.team_id = r.team_id AND r.player_id = p.user_id AND l.season = ? GROUP BY p.addr_city order by num});
my $youth_sth = $DB->prepare(q{SELECT p.gender, count(*) AS num FROM league l, leagueteams lt, teamroster r, person p WHERE p.birthdate > date_sub(now(), interval 18 year) AND l.league_id = lt.league_id AND lt.team_id = r.team_id AND r.player_id = p.user_id AND l.season = ? GROUP BY p.gender});
my $youth_residency = $DB->prepare(q{SELECT p.addr_city, count(*) AS num FROM league l, leagueteams lt, teamroster r, person p WHERE p.birthdate > date_sub(now(), interval 18 year) AND l.league_id = lt.league_id AND lt.team_id = r.team_id AND r.player_id = p.user_id AND l.season = ? GROUP BY p.addr_city order by num});

my $teams_sth = $DB->prepare(q{SELECT l.name, count(*) from league l, leagueteams lt WHERE l.season = ? AND l.league_id = lt.league_id GROUP BY l.name});

my %gatineau_names = map { lc $_ => 1} qw(
	hull
	gatineau
	aylmer
	chelsea
);

my %ottawa_names = map { lc $_ => 1 } qw(
	ashton
	barrhaven
	carp
	cumberland
	dunrobin
	gloucester
	Glouscester
	goulbourn
	greely
	huntley
	kanata
	kars
	kinburn
	kenmore
	manotick
	metcalfe
	metcalf
	munster
	navan
	nepean
	orleans
	osgoode
	ottawa
	richmond
	rockcliffe
	stittsville
	vanier
	vars
	woodlawn
	Orléans

), 'manotick station', 'north gower', 'carlsbad springs';

my %unknown_names;

foreach my $season_name (qw( Summer Fall Winter ) ) {
	my ($season_id, $year) = $DB->selectrow_array(q{
		SELECT id, year FROM season WHERE season = ? AND year IS NOT NULL ORDER BY year DESC limit 1
	}, undef, $season_name);
	if( ! $season_id ) {
		warn "No season_id found for $season_name";
		return;
	}

	print "$season_name $year Season\n";

	$adult_sth->execute( $season_id );
	while( my ($gender, $count) = $adult_sth->fetchrow_array() ) {
		print "\tAdult $gender: $count\n";
	}

	my $adults_in  = 0;
	my $adults_out = 0;
	$adult_residency->execute( $season_id );
	while( my ($city, $count) = $adult_residency->fetchrow_array() ) {
		if ( $ottawa_names{lc $city} ) {
			$adults_in += $count;
		} else {
			$adults_out += $count;
			$unknown_names{$city} += $count unless exists $gatineau_names{lc $city};
		}
	}
	print "\tAdults in Ottawa: $adults_in\n";
	print "\tAdults out of Ottawa: $adults_out\n";

	$youth_sth->execute( $season_id );
	while( my ($gender, $count) = $youth_sth->fetchrow_array() ) {
		print "\tYouth $gender: $count\n";
	}

	my $youth_in  = 0;
	my $youth_out = 0;
	$youth_residency->execute( $season_id );
	while( my ($city, $count) = $youth_residency->fetchrow_array() ) {
		if ( $ottawa_names{lc $city} ) {
			$youth_in += $count;
		} else {
			$youth_out += $count;
			$unknown_names{$city} += $count unless exists $gatineau_names{lc $city};
		}
	}
	print "\tYouth in Ottawa: $youth_in\n";
	print "\tYouth out of Ottawa: $youth_out\n";

	$teams_sth->execute( $season_id );
	my $adult_cnt = 0;
	my $youth_cnt = 0;
	while( my($lname, $count) = $teams_sth->fetchrow_array() ) {
#		print "$season_id $lname $count\n";
		if( $lname =~ /(?:youth|junior)/i ) {
			$youth_cnt += $count;
		} else {
			$adult_cnt += $count;
		}
	}
	print "\tAdult Teams: $adult_cnt\n";
	print "\tYouth Teams: $youth_cnt\n";
}

for my $name (sort { $unknown_names{$a} <=> $unknown_names{$b} } keys %unknown_names) {
	print "$name: $unknown_names{$name}\n";
}
