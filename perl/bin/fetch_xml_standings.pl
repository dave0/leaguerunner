#!/usr/bin/perl -w
use strict;

use WWW::Mechanize;
use IO::File;
use Getopt::Long;

my $conf = {
	fetch_base    => 'http://www.ocua.ca/leaguerunner/',
	wanted_season => 'Fall 2010',
	username      => 'your username',
	password      => 'your password',
};

GetOptions(
	'season=s' => \($conf->{wanted_season}),
);

my $agent = WWW::Mechanize->new();

$agent->get($conf->{fetch_base} . 'login');
$agent->form_number(1);
$agent->field('edit[username]', $conf->{username});
$agent->field('edit[password]', $conf->{password});
$agent->click('Submit');

$agent->follow_link(text => 'list leagues', n => '1');

if( ! $agent->form_number(1) ) {
	die "No season-selection form found";
}
if( ! $agent->set_visible( [ 'option' => $conf->{wanted_season} ] ) ) {
	die "Couldn't select season name in form";
}
$agent->submit();
my $data = $agent->content();
while( $data =~ m{league/view/(\d+)">([^<]+)</a>}g ) {
	my ($league_id, $league_name) = ($1,$2);
	print "$league_name $league_id\n";

	$league_name =~ s{\s+}{_}g;
	$league_name =~ s{/}{_}g;


	my $filename = "$league_id-\L${league_name}.xml";

	$agent->get($conf->{fetch_base} . 'sportsml/combined/' . $league_id);
	my $fh = IO::File->new( $filename, 'w');
	print $fh $agent->content();
	close $fh;
}

__END__

=head1 NAME 

fetch_xml_standings.pl - Fetch SportsML standings from Leaguerunner

=head1 SYNOPSIS

 fetch_xml_standings.pl

=head1 DESCRIPTION

This program uses WWW::Mechanize to log in to Leaguerunner, fetch the
SportsML output for a given season, and save it to files, one file per
tier.

=head1 CONFIGURATION

To configure it, edit this script and modify the $conf hash.

=head1 AUTHOR

Dave O'Neill <dmo@dmo.ca>
