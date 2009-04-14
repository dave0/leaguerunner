#!/usr/bin/perl -w
#
## Sync tier captains with mailman
## Dave O'Neill <dmo@dmo.ca> Wed, 19 May 2004 23:23:25 -0400 

use strict;
use DBI;
use POSIX;
use Leaguerunner;
use Getopt::Mixed;
use IO::Pipe;
use IO::File;

sub list_sync_members($$);

our($season, $opt_create, $opt_want_tier, $admin_password);
$opt_create = 0;
$opt_want_tier = 1;

our($mm_path, $mm_list_lists, $mm_sync_members, $mm_newlist);
$mm_path = '/usr/lib/mailman/bin';
$mm_list_lists = join('/',$mm_path, 'list_lists');
$mm_sync_members = join('/',$mm_path, 'sync_members');
$mm_newlist = join('/',$mm_path, 'newlist');

our $list_admin = 'dmo@dmo.ca';


Getopt::Mixed::init("s=s season>s password=s create notier");
my $optarg;
while( ($_, $optarg) = Getopt::Mixed::nextOption()) {
	/^s$/ && do {
		## TODO: validation?
		$season = $optarg;
	};
	/^create$/ && do {
		$opt_create = 1;
	};
	/^notier$/ && do {
		$opt_want_tier = 0;
	};
	/^password$/ && do {
		## TODO: validation?
		$admin_password = $optarg;
	};
	
}
Getopt::Mixed::cleanup();

if( $opt_create && !$admin_password ) {
	print "Usage: $0 --season [season] --password [adminpassword]\n";
	exit 1;
}

if( ! $season ) {
	print "Usage: $0 --season [season] --password [adminpassword]\n";
	exit 1;
}
# Normalize for matching.
$season = lc($season);

my $config = Leaguerunner::parseConfigFile("../src/leaguerunner.conf");

## Initialise database handle.
my $dsn = join("",
	"DBI:mysql:database=", $config->{db_name}, 
	":host=", $config->{db_host});

my $DB = DBI->connect($dsn, $config->{db_user}, $config->{db_password}) || die("Error establishing database connect; $DBI::errstr\n");

$DB->{RaiseError} = 1;

# We must remember to disconnect on exit.  Use the magical END sub.
sub END { $DB->disconnect() if defined($DB); }

my $variables = Leaguerunner::loadVariables($DB);

# Figure out what existing lists exist, loading them into a hash
my @mailing_lists;
my $pipe = new IO::Pipe;
$pipe->reader( ($mm_list_lists, '-b') ) || die "Couldn't run $mm_list_lists: $!";
while(<$pipe>) {
	# Ignore lists that don't match the pattern for this season.
	chomp();
	if ( /^$season-.*-captains$/ ) {
		push(@mailing_lists, $_);
	}
}
$pipe->close;

if( scalar(@mailing_lists) < 1 ) {
	print "Warning: No existing mailing lists found!\n";
}

# Select all leagues for a given season
my $league_sth = $DB->prepare(q{SELECT league_id,name,day,ratio,tier FROM league WHERE season = ?});
$league_sth->execute($season);

# Per-day lists
my %day_lists;
my @season_captains;

# Go through each league
while( my($league_id, $name, $day, $ratio, $tier) = $league_sth->fetchrow_array()) {

	$day = lc($day);
	if( $day =~ /,/ ) {
		$day =~ s/,?(saturday|sunday),?//g;
	}

	# sanity check
	if( $name !~ /^$season/i ) {
		print "Warning: League [$name] has a nonstandard name\n";
		next;
	}

	my @user_emails;
	my $sth = $DB->prepare(q{SELECT p.email FROM leagueteams l, teamroster r INNER JOIN person p ON (r.player_id = p.user_id) WHERE l.league_id = ? AND l.team_id = r.team_id AND (r.status = 'captain' OR r.status = 'assistant' OR r.status = 'COACH')});
	$sth->execute($league_id);
	while( my($email_addr) = $sth->fetchrow_array() ) {
		print "Adding $email_addr to $day\n";
		push @user_emails, $email_addr;
	}
	
	# fetch all captains and coordinators in this league
	$sth = $DB->prepare(q{SELECT p.email FROM leaguemembers m INNER JOIN person p ON (m.player_id = p.user_id) WHERE m.league_id = ? AND m.status = 'coordinator'});
	$sth->execute($league_id);
	while( my($email_addr) = $sth->fetchrow_array() ) {
		push @user_emails, $email_addr;
	}

	if( $opt_want_tier ) {
		my $list_name;
		if ($ratio eq 'womens') {
			 $list_name = join('-',
				$season,
				lc($day),
				'womens',
				'tier',
				$tier,
				'captains');
		} else {
			 $list_name = join('-',
				$season,
				lc($day),
				'tier',
				$tier,
				'captains');
		}

		if( ! scalar( grep { /^$list_name$/} @mailing_lists ) ) {
			if( $opt_create ) {
	#			system( $mm_newlist, qw( -l en -q ), $list_name, $list_admin, $admin_password ) == 0 or die("$mm_newlist failed");
				# TODO: need to set default list settings
				# TODO: need to allow only coordinators to post
			} else {
				print "List $list_name needs to be created first\n";
				next;
			}
		}


		list_sync_members($list_name, \@user_emails);
		
		# remove the list from the list of mailing lists to update
		@mailing_lists = grep { $_ ne $list_name } @mailing_lists;
	}

	# Add the members to the list for this day
	push @{$day_lists{$day}}, @user_emails;
	push @season_captains, @user_emails;
	
}

## Create per-day captain lists
foreach my $day (keys %day_lists) {
	my $list_name = join('-',
		$season,
		lc($day),
		'captains');

	list_sync_members($list_name, $day_lists{$day});
	
	# remove the list from the list of mailing lists to update
	@mailing_lists = grep { $_ ne $list_name } @mailing_lists;
}

## Create global captain list
list_sync_members("$season-captains", \@season_captains);

# Go through each remaining list and warn that they weren't updated
foreach my $remaining (@mailing_lists) {
	print "Warning: list $remaining has no Leaguerunner equivalent\n";
}

sub list_sync_members($$)
{
	my ($listname, $new_members) = @_;
	
	print "Building $listname\n";

	print join (",",@$new_members) . "\n";

	# dump into a tempfile
	my $tmpfilename = './temp-address-file';
	my $fh = new IO::File "> $tmpfilename" or die("Couldn't open tempfile: $!");
	print $fh join("\n",@$new_members) . "\n";
	$fh->close;
	
	# run /usr/lib/mailman/bin/sync_members on the list
	system( $mm_sync_members, qw( --goodbye-msg=no --welcome-msg=no --digest=no --notifyadmin=no -f ), $tmpfilename, $listname ) == 0 or die("$mm_sync_members failed");
	print join( ' ',$mm_sync_members, qw( --goodbye-msg=no --welcome-msg=no --digest=no --notifyadmin=no -f ), $tmpfilename, $listname, "\n" ) ;

	unlink($tmpfilename);
	
}
