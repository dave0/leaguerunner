#!/usr/bin/perl -w
#
# Sync tier coordinators with mailman

use strict;
use DBI;
use POSIX;
use Leaguerunner;
use Getopt::Mixed;
use IO::Pipe;
use IO::File;

sub list_sync_members($$);

our($season, $opt_create, $opt_want_division, $admin_password);
$opt_create = 0;
$opt_want_division = 1;

our($mm_path, $mm_list_lists, $mm_sync_members, $mm_newlist);
$mm_path = '/usr/lib/mailman/bin';
$mm_list_lists = join('/',$mm_path, 'list_lists');
$mm_sync_members = join('/',$mm_path, 'sync_members');
$mm_newlist = join('/',$mm_path, 'newlist');

our $list_admin = 'dmo@dmo.ca';

my @always_add = qw(lm@ocua.ca);

Getopt::Mixed::init("s=s season>s password=s create nodivision");
my $optarg;
while( ($_, $optarg) = Getopt::Mixed::nextOption()) {
	/^s$/ && do {
		## TODO: validation?
		$season = $optarg;
	};
	/^create$/ && do {
		$opt_create = 1;
	};
	/^nodivision$/ && do {
		$opt_want_division = 0;
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
	if ( /^$season-.*-coordinators$/ ) {
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
my @season_coordinators;

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

	my $coord_emails = $DB->selectcol_arrayref(q{
		SELECT p.email FROM leaguemembers m INNER JOIN person p ON (m.player_id = p.user_id) WHERE
			m.league_id = ? AND m.status = 'coordinator'}, undef, $league_id);

	if( $opt_want_division ) {
		my $list_name = lc $name;
		$list_name =~ s/^\s+//;
		$list_name =~ s/\s+$//;
		$list_name =~ s/\s+/-/g;
		$list_name .= '-coordinators';
		my $day_list_name = join('-', $season, lc($day), 'coordinators');


		print "Looking at $list_name ($day_list_name)\n";
		if( $list_name eq $day_list_name ) {
			# No need to do it twice
			goto NODIVISION;
		}

		if( ! scalar( grep { /^$list_name$/} @mailing_lists ) ) {
			if( $opt_create ) {
				print "Creating $list_name\n";
	#			system( $mm_newlist, qw( -l en -q ), $list_name, $list_admin, $admin_password ) &&  die("$mm_newlist failed");
				# TODO: need to set default list settings
				# TODO: need to allow only coordinators to post
			} else {
				print "List $list_name needs to be created first\n";
				goto NODIVISION;
			}
		}

		list_sync_members($list_name, $coord_emails);
		
		# remove the list from the list of mailing lists to update
		@mailing_lists = grep { $_ ne $list_name } @mailing_lists;
	}
NODIVISION:

	# Add the members to the list for this day
	push @{$day_lists{$day}}, @{ $coord_emails };
	push @season_coordinators, @{ $coord_emails};
	
}

## Create per-day captain lists
foreach my $day (keys %day_lists) {
	my $list_name = join('-',
		$season,
		lc($day),
		'coordinators');

	list_sync_members($list_name, $day_lists{$day});
	
	# remove the list from the list of mailing lists to update
	@mailing_lists = grep { $_ ne $list_name } @mailing_lists;
}

## Create global captain list
list_sync_members("$season-coordinators", \@season_coordinators);

# Go through each remaining list and warn that they weren't updated
foreach my $remaining (@mailing_lists) {
	print "Warning: list $remaining has no Leaguerunner equivalent\n";
}

sub list_sync_members($$)
{
	my ($listname, $new_members) = @_;
	
	print "Building $listname\n";

	# warn join (",",@$new_members) . "\n";

	# dump into a tempfile
	my $tmpfilename = './temp-address-file';
	my $fh = new IO::File "> $tmpfilename" or die("Couldn't open tempfile: $!");
	print $fh join("\n",@$new_members, @always_add) . "\n";
	$fh->close;
	
	# run /usr/lib/mailman/bin/sync_members on the list
	system( $mm_sync_members, qw( --goodbye-msg=no --welcome-msg=no --digest=no --notifyadmin=no -f ), $tmpfilename, $listname ) == 0 or die("$mm_sync_members failed");
	print join( ' ',$mm_sync_members, qw( --goodbye-msg=no --welcome-msg=no --digest=no --notifyadmin=no -f ), $tmpfilename, $listname, "\n" ) ;

	unlink($tmpfilename);
	
}
