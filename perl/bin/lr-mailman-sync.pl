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

lr-mailman-sync.pl [ --list listname | --all ]

=cut

use lib qw( /opt/websites/www.ocua.ca/leaguerunner/perl/lib );
use Leaguerunner;

my $mm_path = '/usr/lib/mailman/bin';
my $mm_sync_members = join('/',$mm_path, 'sync_members');
my $mm_config_list = join('/',$mm_path, 'config_list');
my $lr_admin = 'dmo@dmo.ca';

sub cb_season_coordinators
{
	my ($dbh, $season_name) = @_;

	my ($season_id) = $dbh->selectrow_array(q{
		SELECT id FROM season WHERE season = ? ORDER BY year DESC limit 1
	}, undef, $season_name);
	if( ! $season_id ) {
		warn "No season_id found for $season_name";
		return;
	}
	return @{ $dbh->selectcol_arrayref(q{
		SELECT email FROM person WHERE username = 'dmo'
		UNION
		SELECT distinct p.email
			FROM person p, leaguemembers m, league l
			WHERE m.player_id = p.user_id AND m.status IN ('coordinator') AND m.league_id = l.league_id AND l.season = ?}, undef, $season_id) || [] }, $lr_admin;
}

sub cb_season_captains_coordinators
{
	my ($dbh, $season_name) = @_;

	my ($season_id) = $dbh->selectrow_array(q{
		SELECT id FROM season WHERE season = ? ORDER BY year DESC limit 1
	}, undef, $season_name);
	if( ! $season_id ) {
		warn "No season_id found for $season_name";
		return;
	}

	return @{ $dbh->selectcol_arrayref(q{
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
	}, undef, $season_id, $season_id) || [] }, $lr_admin;
}

sub cb_active_players
{
	my ($dbh) = @_;
	return @{ $dbh->selectcol_arrayref(q{
		SELECT distinct p.email
		FROM person p
		WHERE p.status = 'active'
			AND p.email IS NOT NULL
			AND p.class IN ('player', 'administrator', 'volunteer')
			AND p.waiver_signed > (NOW() - INTERVAL 13 MONTH)
	}) || [] };
}

sub cb_league_players
{
	my ($dbh, $league_id) = @_;
	return @{ $dbh->selectcol_arrayref(q{
		SELECT distinct p.email
		FROM person p, leagueteams l, teamroster r
		WHERE r.player_id = p.user_id
			AND p.email IS NOT NULL
			AND l.team_id = r.team_id
			AND l.league_id = ?
	}, undef, $league_id) || [] };
}

sub cb_league_captains_coordinators
{
	my ($dbh, $league_id) = @_;
	return @{ $dbh->selectcol_arrayref(q{
		SELECT distinct p.email
		FROM person p, leagueteams l, teamroster r
		WHERE r.player_id = p.user_id
			AND p.email IS NOT NULL
			AND l.team_id = r.team_id
			AND r.status IN ('captain', 'assistant', 'COACH')
			AND l.league_id = ?
		UNION
		SELECT distinct p.email
		FROM person p, leaguemembers m
		WHERE m.player_id = p.user_id
			AND p.email IS NOT NULL
			AND m.status IN ('coordinator')
			AND m.league_id = ?
	}, undef, $league_id, $league_id) || [] }, $lr_admin;
}

sub cb_season_day_ratio_captains_coordinators
{
	my ($dbh, $season_name, $day_name, $ratio) = @_;

	my ($season_id) = $dbh->selectrow_array(q{
		SELECT id FROM season WHERE season = ? ORDER BY year DESC limit 1
	}, undef, $season_name);
	if( ! $season_id ) {
		warn "No season_id found for $season_name";
		return;
	}

	return @{ $dbh->selectcol_arrayref(q{
		SELECT distinct p.email
		FROM person p, leagueteams lt, league l, teamroster r
		WHERE r.player_id = p.user_id
			AND lt.team_id = r.team_id
			AND r.status IN ('captain', 'assistant', 'COACH')
			AND lt.league_id = l.league_id
			AND l.season = ?
			AND l.day = ?
			AND l.ratio = ?
		UNION
		SELECT distinct p.email
		FROM person p, leaguemembers m, league l
		WHERE m.player_id = p.user_id
			AND m.status IN ('coordinator')
			AND m.league_id = l.league_id
			AND l.season = ?
			AND l.day = ?
			AND l.ratio = ?
	}, undef, $season_id, $day_name, $ratio, $season_id, $day_name, $ratio) || [] };
}

sub cb_season_day_ratio_players
{
	my ($dbh, $season_name, $day_name, $ratio) = @_;
	my ($season_id) = $dbh->selectrow_array(q{
		SELECT id FROM season WHERE season = ? ORDER BY year DESC limit 1
	}, undef, $season_name);
	if( ! $season_id ) {
		warn "No season_id found for $season_name";
		return;
	}

	return @{ $dbh->selectcol_arrayref(q{
		SELECT distinct p.email
		FROM person p, leagueteams lt, league l, teamroster r
		WHERE r.player_id = p.user_id
			AND lt.team_id = r.team_id
			AND p.email IS NOT NULL
			AND lt.league_id = l.league_id
			AND l.season = ?
			AND l.day = ?
			AND l.ratio = ?
		UNION
		SELECT distinct p.email
		FROM person p, leaguemembers m, league l
		WHERE m.player_id = p.user_id
			AND m.status IN ('coordinator')
			AND m.league_id = l.league_id
			AND l.season = ?
			AND l.day = ?
			AND l.ratio = ?
	}, undef, $season_id, $day_name, $ratio, $season_id, $day_name, $ratio) || [] };
}

sub cb_league_coordinators
{
	my ($dbh, $league_id) = @_;
	return @{ $dbh->selectcol_arrayref(q{
		SELECT distinct p.email
		FROM person p, leaguemembers m
		WHERE m.player_id = p.user_id
			AND p.email IS NOT NULL
			AND m.status IN ('coordinator')
			AND m.league_id = ?
	}, undef, $league_id) || [] }, $lr_admin;
}

sub cb_season_day_ratio_coordinators
{
	my ($dbh, $season_name, $day_name, $ratio) = @_;

	my ($season_id) = $dbh->selectrow_array(q{
		SELECT id FROM season WHERE season = ? ORDER BY year DESC limit 1
	}, undef, $season_name);
	if( ! $season_id ) {
		warn "No season_id found for $season_name";
		return;
	}

	return @{ $dbh->selectcol_arrayref(q{
		SELECT distinct p.email
		FROM person p, leaguemembers m, league l
		WHERE m.player_id = p.user_id
			AND m.status IN ('coordinator')
			AND m.league_id = l.league_id
			AND l.season = ?
			AND l.day = ?
			AND l.ratio = ?
	}, undef, $season_id, $day_name, $ratio) || [] }, $lr_admin;
}

my %lists = (

	# Ad-hoc lists
	'summer-yp-league-players' => {
		callback   => \&cb_league_players,
		parameters => [ 177 ]
	},
	'summer-masters-league-players' => {
		callback   => \&cb_league_players,
		parameters => [ 176 ]
	},

	# opt-in lists.
	'player-notices' => {
		callback => sub {
			@{ shift->selectcol_arrayref(q{
				SELECT distinct p.email
				FROM person p
				WHERE p.contact_for_feedback = 'Y'
					AND p.email IS NOT NULL
					AND p.status IN ('active')
					AND p.class IN ('player', 'administrator', 'volunteer')
					AND p.waiver_signed > (NOW() - INTERVAL 13 MONTH)
				},
			) || [] };
		},
	},
	'volunteer-survey-l' => {
		callback => sub {
			@{ shift->selectcol_arrayref(q{
				SELECT distinct p.email
				FROM person p
				WHERE p.willing_to_volunteer = 'Y'
					AND p.email IS NOT NULL
					AND p.status IN ('active')
					AND p.class IN ('player', 'administrator', 'volunteer')
					AND p.waiver_signed > (NOW() - INTERVAL 13 MONTH)
				},
			) || [] };
		},
	},
	'players-l' => {
		callback   => \&cb_active_players,
	},

	# Summer league
	'summer-captains' => {
		callback   => \&cb_season_captains_coordinators,
		parameters => [ 'Summer' ],
	},
	'summer-coordinators' => {
		callback   => \&cb_season_coordinators,
		parameters => [ 'Summer' ],
	},

	'summer-monday-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Monday', '4/3' ],
	},
	'summer-monday-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Monday', '4/3' ],
	},
	'summer-tuesday-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Tuesday', '4/3' ],
	},
	'summer-tuesday-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Tuesday', '4/3' ],
	},
	'summer-wednesday-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Wednesday', '4/3' ],
	},
	'summer-wednesday-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Wednesday', '4/3' ],
	},
	'summer-thursday-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Thursday', '4/3' ],
	},
	'summer-thursday-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Thursday', '4/3' ],
	},
	'summer-friday-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Friday', '4/3' ],
	},
	'summer-friday-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Friday', '4/3' ],
	},
	'summer-womens-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Thursday', 'womens' ],
	},
	'summer-womens-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Summer', 'Thursday', 'womens' ],
	},
	'summer-womens-league-players' => {
		callback   => \&cb_season_day_ratio_players,
		parameters => [ 'Summer', 'Thursday', 'womens' ],
	},
	'summer-sunday-coordinators' => {
		callback => \&cb_league_coordinators,
		parameters => [ 213 ],
	},
	'summer-sunday-captains' => {
		callback   => \&cb_league_captains_coordinators,
		parameters => [ 213 ],
		moderator_callback => \&cb_league_coordinators,
	},

	# Fall League
	'fall-captains' => {
		callback   => \&cb_season_captains_coordinators,
		parameters => [ 'Fall' ],
	},
	'fall-coordinators' => {
		callback   => \&cb_season_coordinators,
		parameters => [ 'Fall' ],
	},
	'fall-monday-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Monday', '4/3' ],
	},
	'fall-monday-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Monday', '4/3' ],
	},
	'fall-tuesday-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Tuesday', '4/3' ],
	},
	'fall-tuesday-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Tuesday', '4/3' ],
	},
	'fall-wednesday-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Wednesday', '4/3' ],
	},
	'fall-wednesday-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Wednesday', '4/3' ],
	},

	'fall-thursday-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Thursday', '4/3' ],
	},
	'fall-thursday-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Thursday', '4/3' ],
	},

	'fall-womens-captains' => {
		callback   => \&cb_season_day_ratio_captains_coordinators,
		moderator_callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Thursday', 'womens' ],
	},
	'fall-womens-coordinators' => {
		callback => \&cb_season_day_ratio_coordinators,
		parameters => [ 'Fall', 'Thursday', 'womens' ],
	},

	# TODO:
	'summer-masters-coordinators' => {
		callback => \&cb_league_coordinators,
		parameters => [ 214 ],
	},
	'summer-masters-captains' => {
		callback   => \&cb_league_captains_coordinators,
		parameters => [ 214 ],
		moderator_callback => \&cb_league_coordinators,
	},
	'summer-young-professionals-captains' => {
		callback   => \&cb_league_captains_coordinators,
		parameters => [ 177 ],
		moderator_callback => \&cb_league_coordinators,
	},
	'summer-young-professionals-coordinators' => {
		callback => \&cb_league_coordinators,
		parameters => [ 177 ],
	},

);


my $list_name = undef;
my $all_lists = 0;
GetOptions(
	'list=s' => \$list_name,
	'all'    => \$all_lists,
	'help'   => sub { pod2usage( -exitval => EXIT_SUCCESS, -verbose => 1 ); },
	'man'    => sub { pod2usage( -exitval => EXIT_SUCCESS, -verbose => 2 ); },
);

if( !$all_lists && !($list_name && exists $lists{$list_name}) ) {
	pod2usage( -message => "--list argument is missing, or not a valid list", -exitval => EXIT_FAILURE, -verbose => 0 );
}

my $config = Leaguerunner::parseConfigFile("/opt/websites/www.ocua.ca/leaguerunner/src/leaguerunner.conf");

## Initialise database handle.
my $DB = DBI->connect( $config->{database}{dsn}, $config->{database}{username}, $config->{database}{password}, { RaiseError => 1, }) ||
	die("Error establishing database connect; $DBI::errstr\n");

my @list_names;
if( $all_lists ) {
	@list_names = sort keys %lists;
} else {
	@list_names = $list_name;
}

foreach my $name (@list_names) {
	if( ! exists $lists{ $name } ) {
		warn "No list for $name; skipping";
		next;
	}
	list_sync_members( { name => $name, %{$lists{ $name }} } );
	if( exists $lists{$name}{moderator_callback} ) {
		list_sync_moderators( { name => $name, %{$lists{ $name }} } );
	}
}

sub list_sync_members
{
	my ($listinfo) = @_;

	# Real work starts here
	my @new_members = $listinfo->{callback}->($DB, @{ $listinfo->{parameters} || [] });

	print "Building $listinfo->{name}\n";

	if( ! @new_members ) {
		warn "No members found for $listinfo->{name}, continuing anyway";
	}

#	warn join (",",@new_members) . "\n";

	my $tmpdir = tempdir( CLEANUP => 1 );
	my $tmpfilename = "$tmpdir/temp-address-file";
	my $fh = IO::File->new("> $tmpfilename") or die("Couldn't open tempfile: $!");

	$fh->print(join("\n",@new_members) . "\n");
	$fh->close;

	# run /usr/lib/mailman/bin/sync_members on the list
	print join( ' ',$mm_sync_members, qw( --goodbye-msg=no --welcome-msg=no --digest=no --notifyadmin=no -f ), $tmpfilename, $listinfo->{name}, "\n" ) ;
	system( $mm_sync_members, qw( --goodbye-msg=no --welcome-msg=no --digest=no --notifyadmin=no -f ), $tmpfilename, $listinfo->{name} ) == 0 or die("$mm_sync_members failed");
}

sub list_sync_moderators
{
	my ($listinfo) = @_;

	if (! exists( $listinfo->{moderator_parameters} ) ) {
		$listinfo->{moderator_parameters} = $listinfo->{parameters};
	}

	my $new_moderators = join(',',
		map { qq{'$_'} }
		$listinfo->{moderator_callback}->($DB, @{ $listinfo->{moderator_parameters}} ));

	print "Setting moderators for $listinfo->{name} to $new_moderators\n";
#	warn $new_moderators;

	my $tmpdir = tempdir( CLEANUP => 1 );
	my $tmpfilename = "$tmpdir/temp-config-file";
	my $fh = IO::File->new("> $tmpfilename") or die("Couldn't open tempfile: $!");
	print $fh <<"END";
# Disable emergency moderation
emergency = 0
# Set list moderation for subscribers to prevent posting
default_member_moderation = 1
# Allow coordinators as moderators
moderator = [ $new_moderators ]
# Allow them to post, even if not on the list
accept_these_nonmembers = [ $new_moderators ]
# Reject any nonmember messages
generic_nonmember_action = 1
END
	$fh->close;

	# Import the new config
	system( $mm_config_list, '--inputfile', $tmpfilename , $listinfo->{name}) == 0 or die "$mm_config_list failed";
}
