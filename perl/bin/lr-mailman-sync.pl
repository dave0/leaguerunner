#!/usr/bin/perl -w
use strict;
use DBI;
use POSIX qw( EXIT_FAILURE EXIT_SUCCESS );
use Getopt::Long;
use Pod::Usage;
use File::Temp qw( tempdir );
use IO::Pipe;
use IO::File;

=head1 NAME

lr-mailman-sync.pl - Sync leaguerunner with mailman

=head1 SYNOPSIS

 # sync data for a single list
 lr-mailman-sync.pl --list listname

 # Or, do it all from cron:
 00 22   *   *   *        root    /home/webteam/bin/lr-mailman-sync.pl --all

=head1 DESCRIPTION

Synchronizes membership lists of a Mailman mailing list with a query run on the
Leaguerunner database.

Note that this script is very hardcoded as OCUA-specific.  You will need to
edit it for your own local use, or perhaps, if you're feeling generous, provide
patches to allow list configuration within Leaguerunner and make editing the
%lists hash unnecessary.

=head1 OPTIONS

=over 4

=item --list=<listname>

Update only the list with the given name.

=item --all

Update all lists.

=item --help

This help

=item --man

Full manpage

=back

=head1 LICENCE AND COPYRIGHT

Copyright (C) 2009 Dave O'Neill.

Released under the terms of the GNU General Public License, version 2.

=cut

use lib qw( lib );
use Leaguerunner;

my $mm_path         = '/usr/lib/mailman/bin';
my $mm_list_lists   = join('/', $mm_path, 'list_lists');
my $mm_sync_members = join('/', $mm_path, 'sync_members');
my $mm_newlist      = join('/', $mm_path, 'newlist');

## This sets up a query dictionary
my %queries = (
	active_players => q{
		SELECT distinct p.email
		FROM person p
		WHERE p.status = 'active'
			AND p.class IN ('player', 'administrator', 'volunteer')
	},
	league_players => q{
		SELECT distinct p.email
		FROM person p, leagueteams l, teamroster r
		WHERE r.player_id = p.user_id
			AND l.team_id = r.team_id
			AND l.league_id = ?;
	},
	league_captains_coordinators => q{
		SELECT distinct p.email
		FROM person p, leagueteams l, teamroster r
		WHERE r.player_id = p.user_id
			AND l.team_id = r.team_id
			AND r.status IN ('captain', 'assistant', 'COACH')
			AND l.league_id = ?
		UNION
		SELECT distinct p.email
		FROM person p, leaguemembers m
		WHERE m.player_id = p.user_id
			AND m.status IN ('coordinator')
			AND m.league_id = ?
	},
	league_coordinators => q{
		SELECT distinct p.email
		FROM person p, leaguemembers m
		WHERE m.player_id = p.user_id
			AND m.status IN ('coordinator')
			AND m.league_id = ?
	},
	season_captains_coordinators => q{
		SELECT distinct p.email
		FROM person p, leagueteams lt, league l, teamroster r
		WHERE r.player_id = p.user_id
			AND lt.team_id = r.team_id
			AND r.status IN ('captain', 'assistant', 'COACH')
			AND lt.league_id = l.league_id
			AND l.season = ?
		UNION
		SELECT distinct p.email
		FROM person p, leaguemembers m, league l
		WHERE m.player_id = p.user_id
			AND m.status IN ('coordinator')
			AND m.league_id = l.league_id
			AND l.season = ?
	},
	season_coordinators => q{
		SELECT distinct p.email
		FROM person p, leaguemembers m, league l
		WHERE m.player_id = p.user_id
			AND m.status IN ('coordinator')
			AND m.league_id = l.league_id
			AND l.season = ?
	},

);

## This sets up the lists to construct, using the queries from the dictionary
## and the given parameters.
my %lists = (

	# Ad-hoc lists
	'summer-yp-league-players' => {
		query      => $queries{league_players},
		parameters => [100]
	},

	# opt-in lists.
	'player-notices' => {
		# Currently, this returns inactive players as well.  Maybe, it
		# shouldn't?
		query => q{
		SELECT distinct p.email
		FROM person p
		WHERE p.contact_for_feedback = 'Y'
			AND p.status IN ('active', 'inactive')
		},
	},
	'players-l' => { query => $queries{active_players}, },

	# Summer league
	'summer-captains' => {
		query      => $queries{season_captains_coordinators},
		parameters => [ 'Summer', 'Summer' ],
	},
	'summer-coordinators' => {
		query      => $queries{season_coordinators},
		parameters => ['Summer'],
	},

	'summer-monday-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 92, 92 ],
	},
	'summer-monday-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [92],
	},
	'summer-tuesday-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 93, 93 ],
	},
	'summer-tuesday-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [93],
	},
	'summer-wednesday-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 94, 94 ],
	},
	'summer-wednesday-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [94],
	},
	'summer-thursday-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 95, 95 ],
	},
	'summer-thursday-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [95],
	},
	'summer-friday-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 97, 97 ],
	},
	'summer-friday-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [97],
	},
	'summer-womens-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 96, 96 ],
	},
	'summer-womens-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [96],
	},
	'summer-masters-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 119, 119 ],
	},
	# TODO: No such list
	#	'summer-masters-coordinators' => {
	#		query	   => $queries{league_coordinators},
	#		parameters => [ 119 ],
	#	},
	'summer-young-professionals-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 100, 100 ],
	},
	'summer-young-professionals-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [100],
	},
	'summer-sunday-east-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 99, 99 ],
	},
	'summer-sunday-east-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [99],
	},
	'summer-sunday-central-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 121, 121 ],
	},
	'summer-sunday-central-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [121],
	},
	'summer-youth-division-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 101, 101 ],
	},
	'summer-youth-division-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [101],
	},

	# Fall League
	'fall-captains' => {
		query      => $queries{season_captains_coordinators},
		parameters => [ 'Fall', 'Fall' ],
	},
	'fall-coordinators' => {
		query      => $queries{season_coordinators},
		parameters => ['Fall'],
	},
	'fall-monday-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 104, 104 ],
	},
	'fall-monday-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [104],
	},
	'fall-tuesday-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 105, 105 ],
	},
	'fall-tuesday-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [105],
	},
	'fall-wednesday-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 106, 106 ],
	},
	'fall-wednesday-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [106],
	},
	'fall-thursday-captains' => {
		query      => $queries{league_captains_coordinators},
		parameters => [ 107, 107 ],
	},
	'fall-thursday-coordinators' => {
		query      => $queries{league_coordinators},
		parameters => [107],
	},
);

my $list_name = undef;
my $all_lists = 0;
GetOptions(
	'list=s' => \$list_name,
	'all'    => \$all_lists,
	'help'   => sub { pod2usage(-exitval => EXIT_SUCCESS, -verbose => 1); },
	'man'    => sub { pod2usage(-exitval => EXIT_SUCCESS, -verbose => 2); },
);

if(!$all_lists && !($list_name && exists $lists{$list_name})) {
	pod2usage(-message => "--list argument is missing, or not a valid list", -exitval => EXIT_FAILURE, -verbose => 0);
}

my $config = Leaguerunner::parseConfigFile("/opt/websites/www.ocua.ca/leaguerunner/src/leaguerunner.conf");

## Initialise database handle.
my $dsn = join("", "DBI:mysql:database=", $config->{db_name}, ":host=", $config->{db_host});

my $DB = DBI->connect($dsn, $config->{db_user}, $config->{db_password}) || die("Error establishing database connect; $DBI::errstr\n");

$DB->{RaiseError} = 1;

# We must remember to disconnect on exit.  Use the magical END sub.
sub END { $DB->disconnect() if defined($DB); }

my @list_names;
if($all_lists) {
	@list_names = sort keys %lists;
} else {
	@list_names = $list_name;
}

foreach my $name (@list_names) {
	if(!exists $lists{$name}) {
		warn "No list for $name; skipping";
		next;
	}
	list_sync_members({ name => $name, %{ $lists{$name} } });
}

sub list_sync_members
{
	my ($listinfo) = @_;

	# Real work starts here
	my $sth = $DB->prepare($listinfo->{query});
	my $new_members = $DB->selectcol_arrayref($sth, undef, @{ $listinfo->{parameters} || [] });

	print "Building $listinfo->{name}\n";

	# warn join (",",@$new_members) . "\n";

	my $tmpdir      = tempdir(CLEANUP => 1);
	my $tmpfilename = "$tmpdir/temp-address-file";
	my $fh          = new IO::File "> $tmpfilename" or die("Couldn't open tempfile: $!");
	print $fh join("\n", @$new_members) . "\n";
	$fh->close;

	# run /usr/lib/mailman/bin/sync_members on the list
	print join(' ', $mm_sync_members, qw( --goodbye-msg=no --welcome-msg=no --digest=no --notifyadmin=no -f ), $tmpfilename, $listinfo->{name}, "\n");
	system($mm_sync_members, qw( --goodbye-msg=no --welcome-msg=no --digest=no --notifyadmin=no -f ), $tmpfilename, $listinfo->{name}) == 0 or die("$mm_sync_members failed");
}
