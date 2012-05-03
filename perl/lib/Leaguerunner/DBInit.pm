package Leaguerunner::DBInit;
use strict;
use warnings;

# This is the current schema value.
# It should be increased after a release version (or major deployment from SVN
# by one of the major contributors).
my $LATEST_SCHEMA = 31;

my @TABLES = (
	'person' => [q{
		DROP TABLE IF EXISTS person;
	},
	q{
		CREATE TABLE person (
			user_id              integer  NOT NULL PRIMARY KEY AUTO_INCREMENT,
			username             varchar(100) UNIQUE NOT NULL,
			password             varchar(100),
			member_id            integer default 0,
			firstname            varchar(100),
			lastname             varchar(100),
			email                varchar(100),
			allow_publish_email  ENUM('Y','N') DEFAULT 'N',
			home_phone           varchar(30),
			publish_home_phone   ENUM('Y','N') DEFAULT 'N',
			work_phone           varchar(30),
			publish_work_phone   ENUM('Y','N') DEFAULT 'N',
			mobile_phone         varchar(30),
			publish_mobile_phone ENUM('Y','N') DEFAULT 'N',
			addr_street          varchar(50),
			addr_city            varchar(50),
			addr_prov            ENUM('Ontario','Quebec','Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland','Northwest Territories','Nunavut','Nova Scotia','Prince Edward Island','Saskatchewan','Yukon','Alabama','Alaska','Arizona','Arkansas','California','Colorado','Connecticut','Delaware','Florida','Georgia','Hawaii','Idaho','Illinois','Indiana','Iowa','Kansas','Kentucky','Louisiana','Maine','Maryland','Massachusetts','Michigan','Minnesota','Mississippi','Missouri','Montana','Nebraska','Nevada','New Hampshire','New Jersey','New Mexico','New York','North Carolina','North Dakota','Ohio','Oklahoma','Oregon','Pennsylvania','Rhode Island','South Carolina','South Dakota','Tennessee','Texas','Utah','Vermont','Virginia','Washington','West Virginia','Wisconsin','Wyoming'),
			addr_country         varchar(50),
			addr_postalcode      varchar(7),
			gender               ENUM('Male','Female'),
			birthdate            date,
			height               smallint,
			skill_level          integer DEFAULT 0,
			year_started         integer DEFAULT 0,
			shirtsize            varchar(50),
			session_cookie       varchar(50),
			class                ENUM('volunteer','administrator', 'player', 'visitor') DEFAULT 'player' NOT NULL,
			status               ENUM('new','inactive','active','locked') DEFAULT 'new' NOT NULL,
			waiver_signed        datetime,
			has_dog              ENUM('Y','N') DEFAULT 'N',
			dog_waiver_signed    datetime,
			survey_completed     ENUM('Y','N') DEFAULT 'N',
			willing_to_volunteer ENUM('Y','N') DEFAULT 'N',
			contact_for_feedback ENUM('Y','N') DEFAULT 'Y',
			show_gravatar        BOOL DEFAULT false,
			created              timestamp NOT NULL DEFAULT NOW(),
			last_login           datetime,
			client_ip            varchar(50)
		);
	}],

	'team' => [q{
		DROP TABLE IF EXISTS team;
	},
	q{
		CREATE TABLE team (
			team_id           integer NOT NULL AUTO_INCREMENT,
			name              varchar(100) NOT NULL,
			website           varchar(100),
			shirt_colour      varchar(50),
			home_field        integer,
			status            ENUM('open','closed'),
			rating            int DEFAULT 1500,
			PRIMARY KEY (team_id),
			UNIQUE KEY name (name)
		);
	},
	q{
		DROP TABLE IF EXISTS teamroster;
	},
	q{
		CREATE TABLE teamroster (
			team_id     integer NOT NULL,
			player_id   integer NOT NULL,
			status      ENUM('coach', 'captain', 'assistant', 'player', 'substitute', 'captain_request', 'player_request'),
			date_joined date,
			PRIMARY KEY (team_id,player_id)
		);
	}],

	'season' => [q{
		DROP TABLE IF EXISTS season;
	},
	q{
		CREATE TABLE season (
			id	     integer NOT NULL AUTO_INCREMENT PRIMARY KEY,
			display_name varchar(100) NOT NULL,
			season       ENUM('none', 'Spring', 'Summer', 'Fall', 'Winter') NOT NULL,
			year         integer,
			archived     BOOLEAN default false
		);
	}],


	'league' => [ q{
		DROP TABLE IF EXISTS league;
	},
	q{
		CREATE TABLE league (
			league_id           integer NOT NULL AUTO_INCREMENT,
			name                varchar(100),
			day                 SET('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
			season              integer,
			tier                integer,
			ratio               ENUM('4/3','5/2','3/3','4/2','3/2','womens','mens','open'),
			current_round       int DEFAULT 1,
			roster_deadline     datetime DEFAULT 0,
			min_roster_size		int DEFAULT 12,
			stats_display       ENUM('all','currentround') DEFAULT 'all',
			status              ENUM('open','closed') NOT NULL default 'open',
			schedule_type       ENUM('none','roundrobin','ratings_ladder', 'ratings_wager_ladder') default 'roundrobin',
			games_before_repeat integer default 4,
			schedule_attempts   integer default 100,
			display_sotg        ENUM('coordinator_only', 'symbols_only', 'all') DEFAULT 'all',
			excludeTeams        ENUM('true','false') default 'false',
			coord_list          varchar(100),
			capt_list           varchar(100),
			finalize_after      integer NOT NULL DEFAULT '0',
			PRIMARY KEY (league_id)
		);
	},
	q{
		DROP TABLE IF EXISTS leagueteams;
	},
	q{
		CREATE TABLE leagueteams (
			league_id   integer NOT NULL,
			team_id     integer NOT NULL,
			PRIMARY KEY (team_id,league_id),
			INDEX leagueteams_league (league_id)
		);
	},
	q{
		DROP TABLE IF EXISTS leaguemembers;
	},
	q{
		CREATE TABLE leaguemembers (
			league_id   integer NOT NULL,
			player_id   integer NOT NULL,
			status      varchar(64),
			PRIMARY KEY (league_id, player_id),
			INDEX leaguemembers_league (league_id)
		);
	}],


	'schedule' => [q{
		DROP TABLE IF EXISTS schedule;
	},
	q{
		CREATE TABLE schedule (
			game_id             int NOT NULL PRIMARY KEY AUTO_INCREMENT,
			league_id           int NOT NULL,
			round               varchar(10) NOT NULL DEFAULT '1',
			published           BOOL default true,
			home_team           integer,
			away_team           integer,
			home_score          tinyint,
			away_score          tinyint,
			rating_home         integer,
			rating_away         integer,
			rating_points       integer,
			approved_by         integer,
			status              ENUM('normal','locked','home_default','away_default','rescheduled','cancelled','forfeit') default 'normal' NOT NULL,
			INDEX game_league (league_id),
			INDEX game_home_team (home_team),
			INDEX game_away_team (away_team)
		);
	},
	q{
		DROP TABLE IF EXISTS score_entry;
	},
	q{
		CREATE TABLE score_entry (
			team_id       integer NOT NULL,
			game_id       integer NOT NULL,
			entered_by    integer NOT NULL,
			score_for     tinyint NOT NULL,
			score_against tinyint NOT NULL,
			defaulted     enum('no','us','them') DEFAULT 'no',
			entry_time    datetime,
			PRIMARY KEY (team_id,game_id)
		);
	},
	q{
		DROP TABLE IF EXISTS score_entry;
	},
	q{
		CREATE TABLE spirit_entry (
			tid_created INTEGER NOT NULL,
			tid         INTEGER NOT NULL,
			gid         INTEGER NOT NULL,
			entered_by  INTEGER NOT NULL,

			score_entry_penalty INTEGER NOT NULL DEFAULT 0,
			timeliness       INTEGER NOT NULL DEFAULT 0,
			rules_knowledge  INTEGER NOT NULL DEFAULT 0,
			sportsmanship    INTEGER NOT NULL DEFAULT 0,
			rating_overall   INTEGER NOT NULL DEFAULT 0,

			comments         TEXT,

			PRIMARY KEY (tid_created,gid)
		);
	}],

	'questions' => [
	q{
		DROP TABLE IF EXISTS question;
	},
	q{
		CREATE TABLE question (
			qkey         varchar(255),
			genre        varchar(255),
			question     blob,
			qtype        varchar(255),
			restrictions varchar(255),
			required     ENUM('Y','N') DEFAULT 'Y',
			sorder       integer default 0,
			PRIMARY KEY  (qkey,genre)
		);
	},
	q{
		DROP TABLE IF EXISTS multiplechoice_answers;
	},
	q{
		CREATE TABLE multiplechoice_answers (
			akey        varchar(255),
			qkey        varchar(255),
			answer      varchar(255),
			value       varchar(255),
			sorder      integer default 0,
			PRIMARY KEY (akey,qkey)
		);
	},
	],

	'field' => [q{
		DROP TABLE IF EXISTS field;
	},
	q{
		CREATE TABLE field (
			fid                 integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
			num                 tinyint,
			status              enum('open','closed'),
			rating              varchar(16),
			notes               text,
			parent_fid          integer,
			name                varchar(255),
			code                char(3),
			location_street     varchar(50),
			location_city       varchar(50),
			location_province   varchar(50),
			latitude            double,
			longitude           double,
			is_indoor	    boolean NOT NULL DEFAULT false,
			angle               integer NOT NULL,
			length              integer NOT NULL,
			width               integer NOT NULL,
			zoom                integer NOT NULL,
			parking             text,
			region              enum('Central','East','South','West'),
			driving_directions  text,
			parking_details     text,
			transit_directions  text,
			biking_directions   text,
			washrooms           text,
			public_instructions text,
			site_instructions   text,
			sponsor             text,
			location_url        varchar(255),
			layout_url          varchar(255)
		);
	},
	q{
		DROP TABLE IF EXISTS team_site_ranking;
	},
	q{
		CREATE TABLE team_site_ranking (
			team_id  INTEGER NOT NULL,
			site_id  INTEGER NOT NULL,
			rank     INTEGER NOT NULL,
			PRIMARY KEY(team_id, site_id),
			UNIQUE(team_id,rank)
		);
	},
	q{
		DROP TABLE IF EXISTS field_ranking_stats;
	},
	q{
		CREATE TABLE field_ranking_stats (
			game_id INTEGER NOT NULL,
			team_id INTEGER NOT NULL,
			rank INTEGER NOT NULL,
			PRIMARY KEY (game_id, team_id)
		);
	}
	],

	'gameslot' => [q{
		DROP TABLE IF EXISTS gameslot;
	},
	q{
		CREATE TABLE gameslot (
			slot_id    integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
			fid        integer NOT NULL,
			game_date  date,
			game_start time,
			game_end   time,
			game_id    integer
		);
	},
	q{
		DROP TABLE IF EXISTS league_gameslot_availability;
	},
	q{
		CREATE TABLE league_gameslot_availability (
			league_id integer NOT NULL,
			slot_id   integer NOT NULL
		);
	}],

	'variable' => [
	q{
		DROP TABLE IF EXISTS variable
	},
	q{
		CREATE TABLE variable (
			name        varchar(50) NOT NULL default '',
			value        longtext    NOT NULL,
			PRIMARY KEY(name)
		);
	}],

	'registration' => [
	q{
		DROP TABLE IF EXISTS registration_events;
	},
	q{
		CREATE TABLE registration_events (
			registration_id int(10) unsigned NOT NULL auto_increment,
			name varchar(100) default NULL,
			description blob,
			type enum('membership', 'individual_event','team_event','individual_league','team_league', 'individual_youth') NOT NULL default 'individual_event',
			season_id INTEGER DEFAULT 1,
			cost decimal(7,2) default NULL,
			gst decimal(7,2) default NULL,
			pst decimal(7,2) default NULL,
			`open` datetime default NULL,
			`close` datetime default NULL,
			cap_male int(10) NOT NULL default '0',
			cap_female int(10) NOT NULL default '0',
			multiple tinyint(1) default '0',
			anonymous tinyint(1) default '0',
			PRIMARY KEY  (registration_id),
			UNIQUE KEY name (name)
		);
	},
	q{
		DROP TABLE IF EXISTS registrations;
	},
	q{
		CREATE TABLE registrations (
			order_id int(10) unsigned NOT NULL auto_increment,
			user_id int(11) NOT NULL default '0',
			registration_id int(10) unsigned NOT NULL default '0',
			`time` timestamp NULL default 0,
			modified timestamp default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			payment ENUM('Unpaid', 'Pending', 'Deposit Paid', 'Paid', 'Refunded', 'Wait List') NOT NULL default 'Unpaid',
			total_amount decimal(7,2) default 0.0,
			notes blob,
			PRIMARY KEY  (order_id),
			KEY user_id (user_id,registration_id)
		);
	},
	q{
		DROP TABLE IF EXISTS registration_payments;
	},
	q{
		CREATE TABLE registration_payments (
			order_id       int(10) UNSIGNED NOT NULL,
			payment_type   ENUM('Full', 'Deposit', 'Remaining Balance'),
			payment_amount decimal(7,2) default 0.0,
			paid_by        varchar(255),
			date_paid      date NOT NULL,
			payment_method varchar(255),
			entered_by     int(11) NOT NULL,
			PRIMARY KEY(order_id, payment_type)
		);
	},
	q{
		DROP TABLE IF EXISTS registration_answers;
	},
	q{
		CREATE TABLE registration_answers (
			order_id int(10) unsigned NOT NULL default '0',
			qkey varchar(255) NOT NULL default '',
			akey varchar(255) default NULL,
			PRIMARY KEY  (order_id,qkey)
		);
	}],

	'field_report' => [
	q{
		DROP TABLE IF EXISTS field_report;
	},
	q{
		CREATE TABLE field_report (
			id                integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
			field_id          integer,
			game_id           integer,
			reporting_user_id integer,
			report_text       TEXT
		);
	}],

	'notes' => [
		q{
			CREATE TABLE note (
				id	   INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
				creator_id INTEGER NOT NULL,
				assoc_id   INTEGER,
				assoc_type ENUM('person', 'team'),
				note	   TEXT,
				created    TIMESTAMP NOT NULL DEFAULT NOW(),
				edited     TIMESTAMP
			);
		},
		q{
			CREATE VIEW person_note AS
				SELECT
					n.assoc_id AS person_id,
					n.id AS id,
					n.note AS note,
					n.creator_id AS creator_id,
					n.created AS created,
					n.edited AS edited
				FROM note n
				WHERE
					n.assoc_type = 'person'
			;
		},
		q{
			CREATE VIEW team_note AS
				SELECT
					n.assoc_id AS team_id,
					n.id AS id,
					n.note AS note,
					n.creator_id AS creator_id,
					n.created AS created,
					n.edited AS edited
				FROM note n
				WHERE
					n.assoc_type = 'team'
			;
		},
	],
);

my @INITIAL_DATA = (
	administrator_account => [q{
		INSERT INTO person (username,password,firstname,lastname,class,status)
			VALUES ('admin', MD5('admin'), 'System', 'Administrator', 'administrator', 'active');
	}],

	inactive_teams => [q{
		INSERT INTO league (name,season,schedule_type)
			VALUES ('Inactive Teams', 'none', 'none');
	},
	q{
		INSERT INTO leaguemembers (league_id, player_id, status)
			VALUES (1,1,'coordinator');
	}],
);

sub new
{
	my ($class, $args) = @_;

	return bless ( { %$args }, $class );
}

sub get_dbh
{
	my ($self) = @_;
	return $self->{dbh};
}

sub detect_schema_version
{
	my ($self) = @_;
	my $dbh = $self->get_dbh();

	my $sth = $dbh->table_info('%', '%', 'variable', 'TABLE');
	if( ! $sth->fetch ) {
		# No variable table, no leaguerunner
		return undef;
	}

	my ($version) = $dbh->selectrow_array(q{SELECT value FROM variable WHERE name = '_SchemaVersion'});
	if( $version ) {
		return $version;
	}

	$sth = $dbh->column_info(undef, undef, 'person', 'addr_country');
	if( $sth->fetch ) {
		return 18;
	}

	$sth = $dbh->table_info(undef, undef, 'score_reminder', 'TABLE');
	if( $sth->fetch ) {
		return 17;
	}

	$sth = $dbh->column_info(undef, undef, 'field', 'parking_details');
	if( $sth->fetch ) {
		return 16;
	}

	$sth = $dbh->table_info(undef, undef, 'registrations', 'TABLE');
	if( $sth->fetch ) {
		return 15;
	}

	$sth = $dbh->column_info(undef, undef, 'person', 'willing_to_volunteer');
	if( $sth->fetch ) {
		return 14;
	}

	$sth = $dbh->column_info(undef, undef, 'league', 'games_before_repeat');
	if( $sth->fetch ) {
		return 13;
	}

	die q{TODO: implement schema version guessing for schemas before 13};
}

sub set_schema_version
{
	my ($self, $new_version) = @_;

	my $dbh = $self->get_dbh();
	local $dbh->{RaiseError} = 1;
	my $rows_affected = $dbh->do(q{UPDATE variable set value = ? WHERE name = '_SchemaVersion'}, undef, $new_version);
	if( $rows_affected == 0 ) {
		$rows_affected = $dbh->do(q{INSERT INTO variable (name, value) VALUES('_SchemaVersion', ?)}, undef, $new_version);
	}

	return $rows_affected;
}

sub fresh_install
{
	my ($self) = @_;
	$self->_run_sql([
		force_innodb => [q{
			SET storage_engine=INNODB
		}]
	]);
	$self->_run_sql(\@TABLES);
	$self->_run_sql(\@INITIAL_DATA);
}

sub _run_sql
{
	my ($self, $tables) = @_;
	my $dbh = $self->get_dbh();

	local $dbh->{RaiseError} = 1;
	local $dbh->{PrintError} = 0;

	# Iterate over the tables pairwise.
	for(my $i = 0; $i <= @$tables; $i+=2) {
		my $tablename = $tables->[$i];
		my $tabledata = $tables->[$i+1];

		# Iterate over the sql arrayref
		foreach my $table_sql ( @{$tabledata} ) {
			$table_sql =~ s/^\s+//gs;
			$table_sql =~ s/\s+$//gs;
			eval { $dbh->do( $table_sql ) };
			if( $@ ) {
				my $err = "Failed to run query [[$table_sql]]: $@";
				$self->{ignore_errors} ? warn $err : die $err;
			}
		}
	}

	return 1;
}

sub upgrade_from
{
	my ($self, $current_schema) = @_;

	if( $current_schema == $LATEST_SCHEMA ) {
		print "Already at latest schema ($LATEST_SCHEMA)\n";
		return $LATEST_SCHEMA;
	}

	$self->_run_sql([
		force_innodb => [q{
			SET storage_engine=INNODB
		}]
	]);

	while( $current_schema < $LATEST_SCHEMA ) {
		my $methodname = 'upgrade_' . $current_schema . '_to_' . ($current_schema + 1);
		if( $self->can( $methodname ) ) {
			$self->$methodname();
		} else {
			die "Can't upgrade $current_schema to " . ($current_schema + 1) . ": no method";
		}
		$self->set_schema_version( $current_schema + 1 );
		$current_schema = $self->detect_schema_version();
	}

	return $current_schema;
}

sub upgrade_13_to_14
{
	my ($self) = @_;
	$self->_run_sql([
		volunteering => [q{
			ALTER TABLE person ADD willing_to_volunteer ENUM('Y','N') DEFAULT 'N' AFTER survey_completed;
		}],
	]);
}

sub upgrade_14_to_15
{
	my ($self) = @_;
	$self->_run_sql([
		implement_registrations => [
		q{
			CREATE TABLE registration_events (
				registration_id int(10) unsigned NOT NULL auto_increment,
				name varchar(100) default NULL,
				description blob,
				cost decimal(7,2) default NULL,
				gst decimal(7,2) default NULL,
				pst decimal(7,2) default NULL,
				`open` datetime default NULL,
				`close` datetime default NULL,
				cap_male int(10) NOT NULL default '0',
				cap_female int(10) NOT NULL default '0',
				PRIMARY KEY  (registration_id),
				UNIQUE KEY name (name)
			)
		},
		q{
			CREATE TABLE registration_prereq (
				registration_id int(11) NOT NULL default '0',
				prereq_id int(11) NOT NULL default '0',
				is_prereq tinyint(1) NOT NULL default '0',
				PRIMARY KEY  (registration_id,prereq_id)
			)
		},
		q{
			CREATE TABLE registrations (
				order_id int(10) unsigned NOT NULL auto_increment,
				user_id int(11) NOT NULL default '0',
				registration_id int(10) unsigned NOT NULL default '0',
				`time` timestamp NOT NULL default CURRENT_TIMESTAMP,
				paid tinyint(1) NOT NULL default '0',
				notes blob,
				PRIMARY KEY  (order_id),
				KEY user_id (user_id,registration_id)
			)
		},
		q{
			CREATE TABLE registration_answers (
				user_id int(11) NOT NULL default '0',
				registration_id int(11) NOT NULL default '0',
				qkey varchar(255) NOT NULL default '',
				akey varchar(255) default NULL,
				PRIMARY KEY  (user_id,registration_id,qkey)
			)
		},
		q{
			CREATE TABLE registration_audit (
				order_id int(10) unsigned NOT NULL default '0',
				response_code smallint(5) unsigned NOT NULL default '0',
				iso_code smallint(5) unsigned NOT NULL default '0',
				`date` text NOT NULL,
				`time` text NOT NULL,
				transaction_id bigint(18) NOT NULL default '0',
				approval_code text NOT NULL,
				transaction_name varchar(20) NOT NULL default '',
				charge_total decimal(7,2) NOT NULL default '0.00',
				cardholder varchar(40) NOT NULL default '',
				expiry text NOT NULL,
				f4l4 text NOT NULL,
				card text NOT NULL,
				message varchar(100) NOT NULL default '',
				`issuer` varchar(30) default NULL,
				issuer_invoice varchar(20) default NULL,
				issuer_confirmation varchar(15) default NULL,
				PRIMARY KEY  (order_id)
			)
		},
		q{
			CREATE TABLE refunds (
				order_id int(10) unsigned NOT NULL default '0',
				user_id int(11) NOT NULL default '0',
				registration_id int(10) unsigned NOT NULL default '0',
				`time` timestamp NOT NULL default CURRENT_TIMESTAMP,
				paid tinyint(1) NOT NULL default '0',
				notes blob,
				PRIMARY KEY  (order_id),
				KEY user_id (user_id,registration_id)
			)
		},
		q{
			CREATE TABLE refund_answers (
				user_id int(11) NOT NULL default '0',
				registration_id int(11) NOT NULL default '0',
				qkey varchar(255) NOT NULL default '',
				akey varchar(255) default NULL,
				PRIMARY KEY  (user_id,registration_id,qkey)
			)
		}],

		user_feedback_request => [q{
			ALTER TABLE person ADD contact_for_feedback ENUM('Y','N') DEFAULT 'Y' AFTER willing_to_volunteer
		}],

		league_additions => [q{
			ALTER TABLE league MODIFY schedule_type ENUM('none','roundrobin','ladder','pyramid','ratings_ladder') DEFAULT 'roundrobin'
		},
		q{
			ALTER TABLE league ADD see_sotg ENUM('true','false') DEFAULT 'true' AFTER schedule_attempts
		},
		q{
			ALTER TABLE league ADD coord_list varchar(100) AFTER see_sotg
		},
		q{
			ALTER TABLE league ADD capt_list varchar(100) AFTER coord_list
		}],

		# Preserve ratings
		preserve_ratings => [q{
			ALTER TABLE schedule ADD rating_home integer AFTER away_spirit
		},
		q{
			ALTER TABLE schedule ADD rating_away integer AFTER rating_home
		}],

		# Fix spirit questions
		fix_spirit_questions => [q{
			DELETE FROM multiplechoice_answers WHERE qkey IN (SELECT qkey FROM question WHERE genre = "team_spirit");
		},
		q{
			DELETE FROM question WHERE genre = "team_spirit";
		},
		q{
			INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES ('Timeliness','team_spirit','Our opponents had a full line and were ready to play','multiplechoice',0);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('OnTime', 'Timeliness','early, or at the official start time','0',0);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('FiveOrLess', 'Timeliness','less than five minutes late','-1',1);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('LessThanTen', 'Timeliness','less than ten minutes late','-2',2);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('MoreThanTen', 'Timeliness','more than ten minutes late','-3',3);
		},
		q{
			INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES ('RulesKnowledge','team_spirit','Our opponents\' rules knowledge was','multiplechoice',1);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('ExcellentRules', 'RulesKnowledge','excellent','0',0);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('AcceptableRules', 'RulesKnowledge','acceptable','-1',1);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('PoorRules', 'RulesKnowledge','poor','-2',2);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('NonexistantRules', 'RulesKnowledge','nonexistant','-3',3);
		},
		q{
			INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES ('Sportsmanship','team_spirit','Our opponents\' sportsmanship was','multiplechoice',2);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('ExcellentSportsmanship', 'Sportsmanship','excellent','0',0);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('AcceptableSportsmanship', 'Sportsmanship','acceptable','-1',1);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('PoorSportsmanship', 'Sportsmanship','poor','-2',2);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('NonexistantSportsmanship', 'Sportsmanship','nonexistant','-3',3);
		},
		q{
			INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES ('Enjoyment','team_spirit','Ignoring the score and based on the opponents\' spirit of the game, did your team enjoy this game?','multiplechoice',3);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('AllEnjoyed', 'Enjoyment','all of my players did','0',0);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('MostEnjoyed', 'Enjoyment','most of my players did','-1',1);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('SomeEnjoyed', 'Enjoyment','some of my players did','-1',2);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES('NoneEnjoyed', 'Enjoyment','none of my players did','-1',3);
		},
		q{
			INSERT INTO question (qkey,genre,question,qtype,required,sorder) VALUES ('CommentsToCoordinator','team_spirit','Do you have any comments on this game you would like to bring to the coordinator''s attention?', 'freetext','N','4');
		}],

		excluding_teams => [
		q{
			ALTER TABLE league ADD excludeTeams ENUM('true','false') DEFAULT 'false' AFTER see_sotg;
		}],
	]);
}

sub upgrade_15_to_16
{
	my ($self) = @_;
	$self->_run_sql([
		# Allow questions and answers longer than 255 characters
		longer_questions => [q{
			ALTER TABLE question MODIFY COLUMN question BLOB;
		},
		q{
			ALTER TABLE team_spirit_answers MODIFY COLUMN akey BLOB;
		}],

		# Update some keys
		fix_keys => [q{
			ALTER TABLE question DROP PRIMARY KEY;
		},
		q{
			ALTER TABLE question ADD PRIMARY KEY (qkey, genre);
		},
		q{
			ALTER TABLE multiplechoice_answers DROP PRIMARY KEY;
		},
		q{
			ALTER TABLE multiplechoice_answers ADD PRIMARY KEY (akey, qkey);
		}],

		# Add new fields to the fields
		extra_field_data => [q{
			ALTER TABLE field CHANGE COLUMN site_directions driving_directions TEXT;
		},
		q{
			ALTER TABLE field ADD parking_details TEXT AFTER driving_directions;
		},
		q{
			ALTER TABLE field ADD transit_directions TEXT AFTER parking_details;
		},
		q{
			ALTER TABLE field ADD biking_directions TEXT AFTER transit_directions;
		},
		q{
			ALTER TABLE field ADD washrooms TEXT AFTER biking_directions;
		} ],

		# convert any existing registration data to the new format.
		convert_registrations => [q{
			ALTER TABLE registration_answers RENAME temp;
		},
		q{
			CREATE TABLE registration_answers (
				order_id int UNSIGNED NOT NULL,
				qkey varchar(255) NOT NULL,
				akey varchar(255),
				PRIMARY KEY (order_id, qkey)
			);
		},
		q{
			INSERT INTO registration_answers (
				SELECT order_id, qkey, akey
				FROM temp
					LEFT JOIN registrations ON temp.user_id = registrations.user_id AND temp.registration_id = registrations.registration_id
				ORDER BY order_id
			);
		},
		q{
			DROP TABLE temp
		},
		q{
			ALTER TABLE refund_answers RENAME temp;
		},
		q{
			CREATE TABLE refund_answers (
				order_id int UNSIGNED NOT NULL,
				qkey varchar(255) NOT NULL,
				akey varchar(255),
				PRIMARY KEY (order_id, qkey)
			);
		},
		q{
			INSERT INTO refund_answers (
				SELECT order_id, qkey, akey
				FROM temp
					LEFT JOIN refunds ON temp.user_id = refunds.user_id AND temp.registration_id = refunds.registration_id
				ORDER BY order_id
			)
		},
		q{
			DROP TABLE temp
		},
		q{
			ALTER TABLE registration_events
				ADD COLUMN multiple BOOL DEFAULT FALSE AFTER cap_female;
		},
		q{
			ALTER TABLE registration_events
				ADD COLUMN anonymous BOOL DEFAULT FALSE AFTER multiple;
		}],
	]);
}

sub upgrade_16_to_17
{
	my ($self) = @_;
	$self->_run_sql([
		'add_field_sponsors' => [q{
			ALTER TABLE field ADD sponsor TEXT AFTER site_instructions
		}],
		# Add new registration event type
		'registration_type' => [q{
			ALTER TABLE registration_events
				ADD type ENUM( 'membership', 'individual_event', 'team_event', 'individual_league', 'team_league' ) NOT NULL
				DEFAULT 'individual_event'
				AFTER description
		}],

		# Convert the payment field from boolean to an enum
		'change_payment_type' => [q{
			ALTER TABLE registrations
				ADD COLUMN payment ENUM('Unpaid','Pending','Paid','Refunded') NOT NULL
				DEFAULT 'Unpaid'
				AFTER paid
		},
		q{
			UPDATE registrations SET payment = 'Paid' WHERE paid = 1
		},
		q{
			UPDATE registrations SET payment = 'Unpaid' WHERE paid = 0
		},
		q{
			ALTER TABLE registrations DROP COLUMN paid
		}],

		# Convert the refunds table and merge it into the registrations
		merge_refunds => [
		q{
			ALTER TABLE refunds
				ADD COLUMN payment ENUM('Unpaid','Pending','Paid','Refunded') NOT NULL
				DEFAULT 'Unpaid'
				AFTER paid
		},
		q{
			UPDATE refunds SET payment = 'Refunded'
		},
		q{
			ALTER TABLE refunds
				DROP COLUMN paid
		},
		q{
			INSERT INTO registrations (SELECT * FROM refunds)
		},
		q{
			DROP TABLE refunds
		},
		q{
			INSERT INTO registration_answers (SELECT * FROM refund_answers)
		},
		q{
			DROP TABLE refund_answers
		}],

		# Add a "last modified" timestamp field to the registrations
		last_modified_for_registration => [
		q{
			ALTER TABLE registrations
				MODIFY COLUMN time TIMESTAMP NULL
				DEFAULT 0
		},
		q{
			ALTER TABLE registrations
				ADD COLUMN modified TIMESTAMP
					DEFAULT CURRENT_TIMESTAMP
					ON UPDATE CURRENT_TIMESTAMP
				AFTER time
		},
		q{
			UPDATE registrations SET modified = time
		}],

		# Add a preregistration table
		create_preregistrations => [
		q{
			CREATE TABLE preregistrations (
				user_id INTEGER NOT NULL DEFAULT '0',
				registration_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
				KEY user_id (user_id,registration_id)
			)
		}],

		# Add a roster deadline for leagues
		roster_deadlines => [
		q{
			ALTER TABLE league
 				ADD COLUMN roster_deadline DATETIME
				DEFAULT 0
				AFTER current_round
		}],

		# Allow for leagues to be closed, and no longer appear in normal displays
		league_closing => [
		q{
			ALTER TABLE league
				ADD COLUMN status ENUM('open','closed') NOT NULL
				DEFAULT 'open'
				AFTER year
		}],

		# Change the sort order of the seasons, for historical team ordering
		season_sort => [
		q{
			ALTER TABLE league MODIFY COLUMN season ENUM ('none','Winter','Spring','Summer','Fall')
				DEFAULT NULL
		}],

		# Team name uniqueness will be enforced by the code now instead of the database
		team_name_constraint => [
		q{
			ALTER TABLE team DROP INDEX name, ADD INDEX name (name)
		}],

		# Add fields for configuring how long to wait before emailing delinquent
		# captains and finalizing games, when scores have not been confirmed
		add_finalization_times => [
		q{
			ALTER TABLE league
				ADD COLUMN email_after INT NOT NULL
				DEFAULT '0'
		},
		q{
			ALTER TABLE league
				ADD COLUMN finalize_after INT NOT NULL
				DEFAULT '0'
		}],

		# Add a table for remembering and tracking missing score reminders
		track_score_reminders => [
		q{
			CREATE TABLE score_reminder (
				game_id INTEGER NOT NULL,
				team_id INTEGER NOT NULL,
				sent_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY ( game_id, team_id )
			)
		}],
	]);
}

sub upgrade_17_to_18
{
	my ($self) = @_;

	$self->_run_sql([
		# City wards are now removed
		remove_wards => [q{
			ALTER TABLE person DROP ward_id
		},
		q{
			ALTER TABLE field DROP ward_id
		},
		q{
			DROP TABLE ward
		},
		q{
			DELETE FROM variable WHERE name = 'wards'
		}],

		# Convert to InnoDB
		innodb_conversion => [
		q{
			ALTER TABLE field ENGINE=INNODB
		},
		q{
			ALTER TABLE gameslot ENGINE=INNODB
		},
		q{
			ALTER TABLE league ENGINE=INNODB
		},
		q{
			ALTER TABLE league_gameslot_availability ENGINE=INNODB
		},
		q{
			ALTER TABLE leaguemembers ENGINE=INNODB
		},
		q{
			ALTER TABLE leagueteams ENGINE=INNODB
		},
		q{
			ALTER TABLE member_id_sequence ENGINE=INNODB
		},
		q{
			ALTER TABLE multiplechoice_answers ENGINE=INNODB
		},
		q{
			ALTER TABLE person ENGINE=INNODB
		},
		q{
			ALTER TABLE player_availability ENGINE=INNODB
		},
		q{
			ALTER TABLE preregistrations ENGINE=INNODB
		},
		q{
			ALTER TABLE question ENGINE=INNODB
		},
		q{
			ALTER TABLE registration_answers ENGINE=INNODB
		},
		q{
			ALTER TABLE registration_audit ENGINE=INNODB
		},
		q{
			ALTER TABLE registration_events ENGINE=INNODB
		},
		q{
			ALTER TABLE registration_prereq ENGINE=INNODB
		},
		q{
			ALTER TABLE registrations ENGINE=INNODB
		},
		q{
			ALTER TABLE schedule ENGINE=INNODB
		},
		q{
			ALTER TABLE score_entry ENGINE=INNODB
		},
		q{
			ALTER TABLE score_reminder ENGINE=INNODB
		},
		q{
			ALTER TABLE team ENGINE=INNODB
		},
		q{
			ALTER TABLE team_request_player ENGINE=INNODB
		},
		q{
			ALTER TABLE team_spirit_answers ENGINE=INNODB
		},
		q{
			ALTER TABLE teamroster ENGINE=INNODB
		},
		q{
			ALTER TABLE variable ENGINE=INNODB
		},
		q{
			ALTER TABLE waitinglist ENGINE=INNODB
		},
		q{
			ALTER TABLE waitinglistmembers ENGINE=INNODB
		}],

		# Add country field to person table
		add_country => [
		q{
			ALTER TABLE person ADD COLUMN addr_country varchar(50) AFTER addr_prov
		}],

		# Change score reminder table into something more generic
		activity_log => [q{
			RENAME TABLE score_reminder TO activity_log
		},
		q{
			ALTER TABLE activity_log ADD `type` VARCHAR( 128 ) NOT NULL FIRST
		},
		q{
			ALTER TABLE activity_log
				CHANGE game_id primary_id INT( 11 ) NOT NULL DEFAULT '0',
	        		CHANGE team_id secondary_id INT( 11 ) NOT NULL DEFAULT '0',
		        	DROP PRIMARY KEY,
			        ADD PRIMARY KEY ( `type` , primary_id , secondary_id ),
			        ADD INDEX SECONDARY ( `type` , primary_id )
		},
		q{
			UPDATE activity_log SET `type` = "email_score_reminder" WHERE secondary_id != 0
		},
		q{
			UPDATE activity_log SET `type` = "email_score_mismatch" WHERE secondary_id = 0
		}],
	]);
}

sub upgrade_18_to_19
{
	my ($self) = @_;

	$self->_run_sql([
		# Add ratings_wager_ladder, remove pyramid and ladder(hold/move variety)
		add_wager_ladder => [q{
			ALTER TABLE league
				MODIFY schedule_type ENUM('none','roundrobin','ratings_ladder', 'ratings_wager_ladder') DEFAULT 'roundrobin';
		}],

		# Member IDs are now based on the user_id value, rather than
		# silly, unnecessary complexity based on the year and gender.
		change_member_id => [q{
			DROP TABLE member_id_sequence;
		}],

		# The hold/move ladder is now dead
		remove_holdmove_ladder_columns => [q{
			ALTER TABLE schedule
				DROP COLUMN home_dependant_game,
				DROP COLUMN home_dependant_type,
				DROP COLUMN home_dependant_rank,
				DROP COLUMN away_dependant_game,
				DROP COLUMN away_dependant_type,
				DROP COLUMN away_dependant_rank
		}],

		# Selection of per-game allstars
		allstars => [q{
			ALTER TABLE league ADD allstars  ENUM('never','optional','always') DEFAULT 'never' AFTER see_sotg
		}],

		# Allow unpublished games
		unpublished_games => [q{
			ALTER TABLE schedule
				ADD COLUMN published BOOL default true
		}],

		# see_sotg now allows other values
		fix_sotg => [q{
			ALTER TABLE league
				ADD COLUMN display_sotg ENUM('coordinator_only', 'symbols_only', 'all') DEFAULT 'all' AFTER see_sotg,
				ADD COLUMN enter_sotg ENUM('both', 'numeric_only', 'survey_only') DEFAULT 'both' AFTER display_sotg
		},
		q{
			UPDATE league SET display_sotg = 'coordinator_only' WHERE NOT see_sotg
		},
		q{
			ALTER TABLE league DROP COLUMN see_sotg
		}],

		# Add field layout columns
		field_layout => [q{
			ALTER TABLE `field`
				ADD `angle` INT NOT NULL AFTER `longitude`,
				ADD `length` INT NOT NULL AFTER `angle`,
				ADD `width` INT NOT NULL AFTER `length`,
				ADD `zoom` INT NOT NULL AFTER `width`,
				ADD `parking` TEXT AFTER `zoom`
		}],

		# Add public site instructions column
		public_instructions => [q{
			ALTER TABLE `field`
				ADD `public_instructions` TEXT AFTER `washrooms`
		}],

		# Add allstar nominations table
		allstar_table => [q{
			CREATE TABLE allstars (
				game_id INTEGER NOT NULL default '0',
				player_id INTEGER NOT NULL default '0',
				PRIMARY KEY (game_id, player_id)
			) ENGINE=INNODB;
		}],

		# Add incident reports table
		incident_table => [q{
			CREATE TABLE incidents (
				game_id INTEGER NOT NULL ,
				team_id INTEGER NOT NULL ,
				type VARCHAR( 128 ) NOT NULL ,
				details TEXT NOT NULL ,
				PRIMARY KEY ( game_id , team_id )
			) ENGINE=INNODB;
		}],
	]);
}


sub upgrade_19_to_20
{
	my ($self) = @_;

	$self->_run_sql([

		# Fix allstar column
		allstars => [q{
			ALTER TABLE league MODIFY allstars ENUM('never','optional','always') DEFAULT 'never'
		}],

		# Add allstar nominations table
		allstar_table => [q{
			CREATE TABLE allstars (
				game_id INTEGER NOT NULL default '0',
				player_id INTEGER NOT NULL default '0',
				PRIMARY KEY (game_id, player_id)
			) ENGINE=INNODB;
		}],

		# Add incident reports table
		incident_table => [q{
			CREATE TABLE incidents (
				game_id INTEGER NOT NULL ,
				team_id INTEGER NOT NULL ,
				type VARCHAR( 128 ) NOT NULL ,
				details TEXT NOT NULL ,
				PRIMARY KEY ( game_id , team_id )
			) ENGINE=INNODB;
		}],

		# 'rank' in leagueteams table is useless
		remove_rank => [q{
			ALTER TABLE leagueteams DROP COLUMN rank
		}],

		# OCUA uses new spirit questions now
		ocua_spirit_questions => [q{
			INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
				'OCUATimeliness',
				'ocua_team_spirit',
				'Our opponents\' timeliness',
				'multiplechoice',
				0);
			},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'OnTime',
				'OCUATimeliness',
				'met expetations',
				'0',
				0);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'NotOntime',
				'OCUATimeliness',
				'did not meet expectations',
				'-1',
				1);
		},


		q{
			INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
				'OCUARulesKnowledge',
				'ocua_team_spirit',
				'Our opponents\' rules knowledge was',
				'multiplechoice',
				1);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'ExceptionalRules',
				'OCUARulesKnowledge',
				'exceptional',
				'0',
				0);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'GoodRules',
				'OCUARulesKnowledge',
				'good',
				'-1',
				1);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'BelowAverageRules',
				'OCUARulesKnowledge',
				'below average',
				'-2',
				2);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'BadRules',
				'OCUARulesKnowledge',
				'bad',
				'-3',
				3);
		},

		q{
			INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
				'OCUASportsmanship',
				'ocua_team_spirit',
				'Our opponents\' sportsmanship was',
				'multiplechoice',
				2);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'ExceptionalSportsmanship',
				'OCUASportsmanship',
				'exceptional',
				'0',
				0);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'GoodSportsmanship',
				'OCUASportsmanship',
				'good',
				'-1',
				1);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'BelowAverageSportsmanship',
				'OCUASportsmanship',
				'below average',
				'-2',
				2);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'PoorSportsmanship',
				'OCUASportsmanship',
				'poor',
				'-3',
				3);
		},

		q{
			INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
				'OCUAOverall',
				'ocua_team_spirit',
				'Ignoring the score and based on the opponents\' spirit of the game, what was your overall assessment of the game?',
				'multiplechoice',
				3);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'Exceptional',
				'OCUAOverall',
				'This was an exceptionally great game',
				'0',
				0);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'Good',
				'OCUAOverall',
				'This was an enjoyable game',
				'-1',
				1);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'Mediocre',
				'OCUAOverall',
				'This was a mediocre game',
				'-2',
				2);
		},
		q{
			INSERT INTO multiplechoice_answers VALUES(
				'Bad',
				'OCUAOverall',
				'This was a very bad game',
				'-3',
				3);
		},
		q{
			INSERT INTO question (qkey,genre,question,qtype,required,sorder) VALUES (
				'CommentsToCoordinator',
				'ocua_team_spirit',
				'Do you have any comments on this game you would like to bring to the coordinator''s attention?',
				'freetext',
				'N',
				'4');
		}],
	]);
}

sub upgrade_20_to_21
{
	my ($self) = @_;

	$self->_run_sql([

		# create new spirit table
		spirit_entries => [q{
			CREATE TABLE spirit_entry (
				tid_created INTEGER NOT NULL,
				tid         INTEGER NOT NULL,
				gid         INTEGER NOT NULL,
				entered_by  INTEGER NOT NULL,

				entered_sotg     INTEGER,

				score_entry_penalty INTEGER NOT NULL DEFAULT 0,
				timeliness       INTEGER NOT NULL DEFAULT 0,
				rules_knowledge  INTEGER NOT NULL DEFAULT 0,
				sportsmanship    INTEGER NOT NULL DEFAULT 0,
				rating_overall   INTEGER NOT NULL DEFAULT 0,

				comments         TEXT,

				PRIMARY KEY (tid_created,gid)
			);
		},

		# Convert timeliness scores
		q{
			INSERT INTO spirit_entry ( tid_created, tid, gid, entered_by, timeliness)
				SELECT tid_created, tid, gid, -1,
					CASE akey
						WHEN 'OnTime'      THEN 1
						WHEN 'FiveOrLess'  THEN 1
						WHEN 'LessThanTen' THEN 1
						WHEN 'MoreThanTen' THEN 0
						WHEN 'NotOntime' THEN 0
					END
				FROM team_spirit_answers
				WHERE qkey IN ('Timeliness', 'OCUATimeliness')
		},

		# Convert manually-entered values from 'schedule' table
		q{
			UPDATE spirit_entry, schedule SET
				spirit_entry.entered_sotg = schedule.away_spirit
				WHERE spirit_entry.tid_created = schedule.home_team
				      AND spirit_entry.tid = schedule.away_team
				      AND spirit_entry.gid = schedule.game_id
				      AND schedule.away_spirit is not null
		},
		q{
			UPDATE spirit_entry, schedule SET
				spirit_entry.entered_sotg = schedule.home_spirit
				WHERE spirit_entry.tid_created = schedule.away_team
				      AND spirit_entry.tid = schedule.home_team
				      AND spirit_entry.gid = schedule.game_id
				      AND schedule.home_spirit is not null
		},
		q{
			ALTER TABLE schedule DROP COLUMN home_spirit
		},
		q{
			ALTER TABLE schedule DROP COLUMN away_spirit
		},
		q{
			ALTER TABLE score_entry DROP COLUMN spirit
		},

		# convert RulesKnowledge/OCUARulesKnowledge
		q{
			UPDATE spirit_entry, team_spirit_answers, multiplechoice_answers SET
				spirit_entry.rules_knowledge = (3 + multiplechoice_answers.value)
				WHERE spirit_entry.tid_created = team_spirit_answers.tid_created
				      AND spirit_entry.gid = team_spirit_answers.gid
				      AND team_spirit_answers.qkey = multiplechoice_answers.qkey
				      AND team_spirit_answers.akey = multiplechoice_answers.akey
				      AND team_spirit_answers.qkey IN ('RulesKnowledge', 'OCUARulesKnowledge')
		},
		# convert Sportsmanship/OCUASportsmanship
		q{
			UPDATE spirit_entry, team_spirit_answers, multiplechoice_answers SET
				spirit_entry.sportsmanship = (3 + multiplechoice_answers.value)
				WHERE spirit_entry.tid_created = team_spirit_answers.tid_created
				      AND spirit_entry.gid = team_spirit_answers.gid
				      AND team_spirit_answers.qkey = multiplechoice_answers.qkey
				      AND team_spirit_answers.akey = multiplechoice_answers.akey
				      AND team_spirit_answers.qkey IN ('Sportsmanship', 'OCUASportsmanship')
		},
		# convert Enjoyment/OCUAOverall
		q{
			UPDATE spirit_entry, team_spirit_answers, multiplechoice_answers SET
				spirit_entry.rating_overall = (3 + multiplechoice_answers.value)
				WHERE spirit_entry.tid_created = team_spirit_answers.tid_created
				      AND spirit_entry.gid = team_spirit_answers.gid
				      AND team_spirit_answers.qkey = multiplechoice_answers.qkey
				      AND team_spirit_answers.akey = multiplechoice_answers.akey
				      AND team_spirit_answers.qkey IN ('Enjoyment', 'OCUAOverall')
		},
		# Convert comments
		q{
			UPDATE spirit_entry, team_spirit_answers SET
				spirit_entry.comments = team_spirit_answers.akey
				WHERE spirit_entry.tid_created = team_spirit_answers.tid_created
				      AND spirit_entry.gid = team_spirit_answers.gid
				      AND team_spirit_answers.qkey IN ('CommentsToCoordinator')
				      AND team_spirit_answers.akey != ''
		},
		# delete 'automatic spirit assigned: 10' entries
		q{
			DELETE FROM spirit_entry WHERE comments = 'Automatic spirit assigned: 10'
		},

		# delete numeric spirit entries where unwarranted
		q{
			UPDATE spirit_entry, schedule, league SET
				spirit_entry.entered_sotg = NULL
				WHERE spirit_entry.gid = schedule.game_id
					AND schedule.league_id = league.league_id
					AND league.enter_sotg = 'survey_only'
		},

		# Remove 'both' as an option for sotg_entry (after purging
		# unwanted numeric entries so that the 'both' behaviour for old
		# submissions is preserved.
		q{
			UPDATE league SET enter_sotg = 'survey_only' WHERE enter_sotg = 'both'
		},
		q{
			ALTER TABLE league MODIFY enter_sotg ENUM('numeric_only', 'survey_only')
		},

		# Fill in the spirit penalty for unentered games
		# -3 means we used the away team's submission because no home submission exists.
		q{
			UPDATE spirit_entry, schedule SET
				spirit_entry.score_entry_penalty = -3
			WHERE spirit_entry.gid = schedule.game_id
				AND spirit_entry.tid = schedule.home_team
				AND schedule.approved_by = -3
		},
		# -2 means we used the home team's submission because no away submission exists.
		q{
			UPDATE spirit_entry, schedule SET
				spirit_entry.score_entry_penalty = -3
			WHERE spirit_entry.gid = schedule.game_id
				AND spirit_entry.tid = schedule.away_team
				AND schedule.approved_by = -2
		},

		# Clean up now-unused tables and questionnaires
		q{
			DROP TABLE team_spirit_answers
		},
		q{
			DELETE from multiplechoice_answers
				USING multiplechoice_answers, question
				WHERE multiplechoice_answers.qkey = question.qkey
					AND question.genre IN('team_spirit', 'ocua_team_spirit')
		},
		q{
			DELETE FROM question WHERE genre IN('team_spirit', 'ocua_team_spirit')
		},

		],
	]);
}

sub upgrade_21_to_22
{
	my ($self) = @_;

	$self->_run_sql([

		# Team name uniqueness will once again be enforced by the database
		team_name_constraint => [
		q{
			CREATE TEMPORARY TABLE duplicate_teams AS (
				SELECT team.team_id, team.name AS team_name, league.league_id, league.name AS league_name
				FROM team, leagueteams, league
				WHERE team.team_id = leagueteams.team_id
					AND leagueteams.league_id = league.league_id
					AND team.name IN (
						SELECT name FROM team GROUP BY name HAVING count(*) > 1
					)
			);
		},
		q{
			DELETE FROM teamroster WHERE team_id IN (
				SELECT team_id FROM duplicate_teams WHERE league_id = 1
			);
		},
		q{
			DELETE FROM leagueteams WHERE team_id IN (
				SELECT team_id FROM duplicate_teams WHERE league_id = 1
			);
		},
		q{
			DELETE FROM schedule WHERE home_team IN (
				SELECT team_id FROM duplicate_teams WHERE league_id = 1
			);
		},
		q{
			DELETE FROM schedule WHERE away_team IN (
				SELECT team_id FROM duplicate_teams WHERE league_id = 1
			);
		},
		q{
			DELETE FROM team WHERE team_id IN (
				SELECT team_id FROM duplicate_teams WHERE league_id = 1
			);
		},
		q{
			DELETE FROM duplicate_teams WHERE league_id = 1;
		},
		q{
			ALTER TABLE team DROP INDEX name, ADD UNIQUE (name);
		}],

		# Kill off preregistrations
		preregistration_removal => [q{
			DROP TABLE IF EXISTS preregistrations;
		}],

		# Kill off prerequisites
		prerequisite_removal => [q{
			DROP TABLE IF EXISTS registration_prereq;
		}],

		more_registration_details => [
		q{
			ALTER TABLE registrations
				MODIFY payment ENUM('Unpaid', 'Pending', 'Deposit Paid', 'Paid', 'Refunded') NOT NULL DEFAULT 'Unpaid';
		},
		q{
			ALTER TABLE registrations
				ADD total_amount DECIMAL(7,2) DEFAULT 0.0 AFTER payment;
		},
		q{
			ALTER TABLE registrations
				ADD paid_amount DECIMAL(7,2) DEFAULT 0.0 AFTER total_amount;
		},
		q{
			ALTER TABLE registrations
				ADD paid_by VARCHAR(255) AFTER paid_amount;
		},
		q{
			ALTER TABLE registrations
				ADD date_paid timestamp AFTER paid_by;
		},
		q{
			ALTER TABLE registrations
				ADD payment_method VARCHAR(255) AFTER date_paid;
		},
		],

		youth_category => [
		q{
			ALTER TABLE registration_events MODIFY type ENUM('membership', 'individual_event','team_event','individual_league','team_league', 'individual_youth') NOT NULL DEFAULT 'individual_event';
		}
		],
	]);
}

sub upgrade_22_to_23
{
	my ($self) = @_;

	$self->_run_sql([

		# Kill off allstars
		allstar_removal => [q{
			DROP TABLE IF EXISTS allstars
		},
		q{
			ALTER TABLE league DROP COLUMN allstars
		}
		],

		incident_removal => [q{
			DROP TABLE IF EXISTS incidents
		}],

		field_reporting => [q{
			CREATE TABLE field_report (
				id                integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
				field_id          integer,
				game_id           integer,
				reporting_user_id integer,
				created           timestamp NOT NULL DEFAULT NOW(),
				report_text       TEXT
			);
		}],
	]);
}

sub upgrade_23_to_24
{
	my ($self) = @_;

	$self->_run_sql([
		payment_tracking => [
		q{
			CREATE TABLE registration_payments (
				order_id       int(10) UNSIGNED NOT NULL,
				payment_type   ENUM('Full', 'Deposit', 'Remaining Balance'),
				payment_amount decimal(7,2) default 0.0,
				paid_by        varchar(255),
				date_paid      date NOT NULL,
				payment_method varchar(255),
				entered_by     int(11) NOT NULL,
				PRIMARY KEY(order_id, payment_type)
			);
		},
		q{
			INSERT INTO registration_payments
				(order_id, payment_type, payment_amount, paid_by, date_paid, payment_method, entered_by)
				SELECT order_id, 'Deposit', paid_amount, paid_by, date_paid, payment_method, 1
				FROM registrations WHERE payment = 'Deposit Paid';
		},
		q{
			INSERT INTO registration_payments
				(order_id, payment_type, payment_amount, paid_by, date_paid, payment_method, entered_by)
				SELECT order_id, 'Full', paid_amount, paid_by, date_paid, payment_method, 1
				FROM registrations WHERE payment = 'Paid';
		},
		q{
			ALTER TABLE registrations
				DROP COLUMN paid_amount,
				DROP COLUMN paid_by,
				DROP COLUMN date_paid,
				DROP COLUMN payment_method
		},
		]
	]);
}

sub upgrade_24_to_25
{
	my ($self) = @_;

	$self->_run_sql([
		online_payment_removal => [
		q{
			DROP TABLE registration_audit;
		},
		],
	]);
}

sub upgrade_25_to_26
{
	my ($self) = @_;

	$self->_run_sql([
		remove_email_notices => [
		q{
			DROP TABLE activity_log
		},
		q{
			ALTER TABLE league
				DROP COLUMN email_after
		},
		q{
			DELETE FROM variable WHERE name IN (
				'approval_notice_subject',
				'approval_notice_body',
				'score_reminder_subject',
				'score_reminder_body'
			)
		}],
	]);
}

sub upgrade_26_to_27
{
	my ($self) = @_;

	my %data = (
	);

	$self->_run_sql([
		notes_feature => [
		q{
			CREATE TABLE note (
				id	   INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
				creator_id INTEGER NOT NULL,
				assoc_id   INTEGER,
				assoc_type ENUM('person', 'team'),
				note	   TEXT,
				created    TIMESTAMP NOT NULL DEFAULT NOW(),
				edited     TIMESTAMP
			);
		},
		q{
			CREATE VIEW person_note AS
				SELECT
					n.assoc_id AS person_id,
					n.id AS id,
					n.note AS note,
					n.creator_id AS creator_id,
					n.created AS created,
					n.edited AS edited
				FROM note n
				WHERE
					n.assoc_type = 'person'
			;
		},
		q{
			CREATE VIEW team_note AS
				SELECT
					n.assoc_id AS team_id,
					n.id AS id,
					n.note AS note,
					n.creator_id AS creator_id,
					n.created AS created,
					n.edited AS edited
				FROM note n
				WHERE
					n.assoc_type = 'team'
			;
		},
		],

		person_table_cleanups => [

		# Allow gravatar to be enabled/disabled
		q{
			ALTER TABLE person
				ADD COLUMN show_gravatar BOOL DEFAULT false
				AFTER contact_for_feedback
		},

		# Add a creation-date column
		q{
			ALTER TABLE person
				ADD COLUMN created timestamp NOT NULL DEFAULT NOW()
				AFTER show_gravatar
		},
		],


		waitlist_payment_status => [
		q{
			ALTER TABLE registrations
				MODIFY  payment ENUM('Unpaid', 'Pending', 'Deposit Paid', 'Paid', 'Refunded', 'Wait List') NOT NULL default 'Unpaid'
		},
		]
	]);
}

sub upgrade_27_to_28
{
	my ($self) = @_;

	$self->_run_sql([

		# Remove support for numeric-entry-only SOTG.
		cleanup_sotg   => [
		q{
			ALTER TABLE league DROP COLUMN enter_sotg;
		},
		q{
			ALTER TABLE spirit_entry DROP COLUMN entered_sotg;
		}
		],


		season_support => [

		# Create season table
		q{
			CREATE TABLE season (
				id	     integer NOT NULL AUTO_INCREMENT PRIMARY KEY,
				display_name varchar(100) NOT NULL,
				season       ENUM('none', 'Spring', 'Summer', 'Fall', 'Winter') NOT NULL,
				year         integer,
				archived     BOOLEAN default false
			);
		},

		# Add a season for leagues with no fixed season
		q{
			INSERT INTO season (display_name, season)
				VALUES('Ongoing Leagues', 'none')
		},

		# Add a new season column to the leagues table
		q{
			ALTER TABLE league
				CHANGE COLUMN season old_season varchar(100),
				ADD COLUMN season integer AFTER old_season;
		},

		# Move any of the "no season" leagues to Ongoing Leagues
		q{
			UPDATE league
				SET season = 1, old_season = null
				WHERE old_season = 'none';
		},

		# Create appropriate seasons
		q{
			INSERT INTO season (display_name, season, year)
				SELECT DISTINCT
					CONCAT_WS(' ', 'Old', old_season, IF(year, year, 2010)),
					old_season,
					IF(year, year, 2010)
				FROM league
				WHERE old_season IN('Spring', 'Summer', 'Fall', 'Winter');
		},

		# Use those seasons
		q{
			UPDATE league SET
				season = (SELECT season.id
					FROM season
					WHERE season.season = league.old_season AND season.year = league.year)
				WHERE ISNULL(league.season);
		},

		# Clean up stragglers
		q{
			UPDATE league SET
				season = 1
				WHERE ISNULL(league.season);
		},

		# Clean up league table
		q{
			ALTER TABLE league
				DROP COLUMN old_season,
				DROP COLUMN year
		},

		# Add season column to registration events
		q{
			ALTER TABLE registration_events
				ADD COLUMN season_id INTEGER DEFAULT 1 AFTER type;
		},
		],
	]);
}

sub upgrade_28_to_29
{
	my ($self) = @_;

	$self->_run_sql([

		flag_indoor_fields => [
		q{
			ALTER TABLE field
				ADD COLUMN is_indoor BOOLEAN NOT NULL DEFAULT false;
		},
		],

		field_ranking => [
		q{
			CREATE TABLE team_site_ranking (
				team_id  INTEGER NOT NULL,
				site_id  INTEGER NOT NULL,
				rank     INTEGER NOT NULL,
				PRIMARY KEY(team_id, site_id),
				UNIQUE(team_id,rank)
			);
		},

		# Store the rank at game-creation time for future use in
		# calculating stats.
		q{
			CREATE TABLE field_ranking_stats (
				game_id INTEGER NOT NULL,
				team_id INTEGER NOT NULL,
				rank INTEGER NOT NULL,
				PRIMARY KEY (game_id, team_id)
			);
		},
		# TODO: possibly auto-populate team's preference from the available sites?
		q{
			ALTER TABLE team
				DROP COLUMN region_preference;
		},
		]
	]);
}

sub upgrade_29_to_30
{
	my ($self) = @_;
	$self->_run_sql([

		league_min_roster => [
		q{
			ALTER TABLE league
				ADD COLUMN min_roster_size INTEGER DEFAULT 12;
		},
		]
	]);
}

sub upgrade_30_to_31
{
	my ($self) = @_;
	$self->_run_sql([
		reg_prereq => [
		q{
			CREATE TABLE registration_prerequisites (
				registration_id INTEGER NOT NULL,
				league_id INTEGER NOT NULL,
  				PRIMARY KEY (registration_id,league_id),
  				KEY league_id (league_id)
			);
		},
		]
	]);
}
