#!/usr/bin/perl -w
use strict;
use warnings;

my $home_chance = 0.5;
my $away_chance = 0.5;

my $game_constant = 40;


for my $h_score (0..20) {
	for my $a_score (0..20) {
		my $h_xfer = calculate_home_transfer( $home_chance, $h_score, $a_score );
		my $a_xfer = -$h_xfer;
		print "$h_score-$a_score: home: $h_xfer, away: $a_xfer\n";
	}
}

use POSIX qw(ceil);
use List::Util qw(max);
sub calculate_home_transfer
{
	my ( $h_chance, $h_score, $a_score) = @_;

	$game_constant = max($h_score,$a_score) * 2 + 10;

	my $h_wager = ceil( $game_constant * $h_chance );
	my $a_wager = $game_constant - $h_wager;
	print "Home wagers $h_wager, away $a_wager\n";

	my $h_gain;
	if( $h_score == $a_score ) {
		$h_gain = ( $game_constant / 2 );
	} elsif( $h_score > $a_score ) {
		$h_gain = $game_constant - $a_score;
	} else {
		$h_gain = $h_score;
	}

	return $h_gain - $h_wager;
}
