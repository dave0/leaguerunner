#!/usr/bin/perl -w
use strict;

##
## Script to generate SQL for mass-addition of leagues. 
## Add one anon hash section to the $data array for each type
## of tier you wish to create.
##

my $data = [
	{ 
		'season' => 'Summer',
		'day'   => 'Monday',
		'ratio' => '4/3',
		'teams' => 8,
		'tiers' => 10
	},
	{ 
		'season' => 'Summer',
		'day'   => 'Tuesday',
		'ratio' => '4/3',
		'teams' => 8,
		'tiers' => 10
	},
	{ 
		'season' => 'Summer',
		'day'   => 'Wednesday',
		'ratio' => '4/3',
		'teams' => 8,
		'tiers' => 10
	},
	{ 
		'season' => 'Summer',
		'day'   => 'Thursday',
		'ratio' => '4/3',
		'teams' => 8,
		'tiers' => 6 
	},
	{ 
		'name'   => 'Summer Thursday Womens',
		'season' => 'Summer',
		'day'   => 'Thursday',
		'ratio' => 'womens',
		'teams' => 8,
		'tiers' => 3
	},
	{ 
		'name'   => 'Summer Thursday Mens',
		'season' => 'Summer',
		'day'   => 'Thursday',
		'ratio' => 'mens',
		'teams' => 8,
		'tiers' => 1
	},
	{ 
		'season' => 'Summer',
		'day'   => 'Friday',
		'ratio' => '4/3',
		'teams' => 8,
		'tiers' => 4
	}
];

foreach my $l (@$data) {
#INSERT INTO league (name,day,season,tier,ratio,max_teams,coordinator_id) VALUES('Summer Monday','Monday','Summer',1,'4/3',8,1);
	my $sql = "INSERT INTO league (name,day,season,tier,ratio,max_teams,coordinator_id) VALUES(";
	my $name;
	if(exists($l->{name})) {
		$name = $l->{name};
	} else {
		$name = $l->{season} . " " . $l->{day};
	}

	$sql .= "'$name'," . $l->{day} . "','" . $l->{season} . "',%d,'" . $l->{ratio} . "'," . $l->{teams} . ",1);\n";

	for(my $i=1; $i <= $l->{tiers}; $i++) {
		printf($sql, $i);
	}
}
