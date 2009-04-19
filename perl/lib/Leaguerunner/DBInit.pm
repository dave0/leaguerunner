package Leaguerunner::DBInit;
use strict;
use warnings;

my $LATEST_SCHEMA = 19;

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
			region_preference varchar(50),
			status            ENUM('open','closed'),
			rating            int DEFAULT 1500,
			PRIMARY KEY (team_id),
			INDEX name (name)
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


	'league' => [ q{
		DROP TABLE IF EXISTS league;
	},
	q{
		CREATE TABLE league (
			league_id           integer NOT NULL AUTO_INCREMENT,
			name                varchar(100),
			day                 SET('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
			season              ENUM('none','Winter','Spring','Summer','Fall'),
			tier                integer,
			ratio               ENUM('4/3','5/2','3/3','4/2','3/2','womens','mens','open'),
			current_round       int DEFAULT 1,
			roster_deadline     datetime DEFAULT 0,
			stats_display       ENUM('all','currentround') DEFAULT 'all',
			year                integer,
			status              ENUM('open','closed') NOT NULL default 'open',
			schedule_type       ENUM('none','roundrobin','ladder','pyramid','ratings_ladder', 'ratings_wager_ladder') default 'roundrobin',
			games_before_repeat integer default 4,
			schedule_attempts   integer default 100,
			see_sotg            ENUM('true','false') default 'true',
			excludeTeams        ENUM('true','false') default 'false',
			coord_list          varchar(100),
			capt_list           varchar(100),
			email_after         integer NOT NULL DEFAULT '0',
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
			rank        integer NOT NULL DEFAULT 0,
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
			home_team           integer,
			home_dependant_game integer,
			home_dependant_type enum('winner','loser'),
			home_dependant_rank integer,
			away_team           integer,
			away_dependant_game integer,
			away_dependant_type enum('winner','loser'),
			away_dependant_rank integer,
			home_score          tinyint,
			away_score          tinyint,
			home_spirit         tinyint,
			away_spirit         tinyint,
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
			spirit        tinyint NOT NULL,
			defaulted     enum('no','us','them') DEFAULT 'no',
			entry_time    datetime,
			PRIMARY KEY (team_id,game_id)
		);
	},
	q{
		DROP TABLE IF EXISTS score_reminder;
	},
	q{
		CREATE TABLE score_reminder (
			game_id   integer NOT NULL,
			team_id   integer NOT NULL,
			sent_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY ( game_id, team_id )
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
	q{
		DROP TABLE IF EXISTS team_spirit_answers;
	},
	q{
		CREATE TABLE team_spirit_answers (
			tid_created integer NOT NULL,
			tid         integer NOT NULL,
			gid         integer NOT NULL,
			qkey        varchar(255) NOT NULL,
			akey        blob,
			PRIMARY KEY (tid_created,gid,qkey)
		);
	}],

	'field' => [q{
		DROP TABLE IF EXISTS field;
	},
	q{
		CREATE TABLE field (
			fid               integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
			num               tinyint,
			status            enum('open','closed'),
			rating            varchar(16),
			notes             text,
			parent_fid        integer,
			name              varchar(255),
			code              char(3),
			location_street   varchar(50),
			location_city     varchar(50),
			location_province varchar(50),
			latitude          double,
			longitude         double,
			region            enum('Central','East','South','West'),
			driving_directions text,
			parking_details    text,
			transit_directions text,
			biking_directions  text,
			washrooms          text,
			site_instructions  text,
			sponsor            text,
			location_url       varchar(255),
			layout_url         varchar(255)
		);
	}],

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
			type enum('membership', 'individual_event','team_event','individual_league','team_league') NOT NULL default 'individual_event',
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
		DROP TABLE IF EXISTS registration_prereq;
	},
	q{
		CREATE TABLE registration_prereq (
			registration_id int(11) NOT NULL default '0',
			prereq_id int(11) NOT NULL default '0',
			is_prereq tinyint(1) NOT NULL default '0',
			PRIMARY KEY  (registration_id,prereq_id)
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
			payment enum('Unpaid', 'Pending', 'Paid', 'Refunded') NOT NULL default 'Unpaid',
			notes blob,
			PRIMARY KEY  (order_id),
			KEY user_id (user_id,registration_id)
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
	},
	q{
		DROP TABLE IF EXISTS registration_audit;
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
		);
	},
	q{
		DROP TABLE IF EXISTS preregistrations;
	},
	q{
		CREATE TABLE preregistrations (
			user_id int(11) NOT NULL default '0',
			registration_id int(10) unsigned NOT NULL default '0',
			KEY user_id (user_id,registration_id)
		);
	}],
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

	spirit_questions => [q{
		INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
			'Timeliness',
			'team_spirit',
			'Our opponents had a full line and were ready to play',
			'multiplechoice',
			0);
		},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'OnTime',
			'Timeliness',
			'early, or at the official start time',
			'0',
			0);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'FiveOrLess',
			'Timeliness',
			'less than five minutes late',
			'-1',
			1);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'LessThanTen',
			'Timeliness',
			'less than ten minutes late',
			'-2',
			2);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'MoreThanTen',
			'Timeliness',
			'more than ten minutes late',
			'-3',
			3);
	},
	q{
		INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
			'RulesKnowledge',
			'team_spirit',
			'Our opponents\' rules knowledge was',
			'multiplechoice',
			1);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'ExcellentRules',
			'RulesKnowledge',
			'excellent',
			'0',
			0);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'AcceptableRules',
			'RulesKnowledge',
			'acceptable',
			'-1',
			1);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'PoorRules',
			'RulesKnowledge',
			'poor',
			'-2',
			2);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'NonexistantRules',
			'RulesKnowledge',
			'nonexistant',
			'-3',
			3);
	},
	q{
		INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
			'Sportsmanship',
			'team_spirit',
			'Our opponents\' sportsmanship was',
			'multiplechoice',
			2);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'ExcellentSportsmanship',
			'Sportsmanship',
			'excellent',
			'0',
			0);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'AcceptableSportsmanship',
			'Sportsmanship',
			'acceptable',
			'-1',
			1);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'PoorSportsmanship',
			'Sportsmanship',
			'poor',
			'-2',
			2);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'NonexistantSportsmanship',
			'Sportsmanship',
			'nonexistant',
			'-3',
			3);
	},
	q{
		INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
			'Enjoyment',
			'team_spirit',
			'Ignoring the score and based on the opponents\' spirit of the game, did your team enjoy this game?',
			'multiplechoice',
			3);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'AllEnjoyed',
			'Enjoyment',
			'all of my players did',
			'0',
			0);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'MostEnjoyed',
			'Enjoyment',
			'most of my players did',
			'-1',
			1);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'SomeEnjoyed',
			'Enjoyment',
			'some of my players did',
			'-1',
			2);
	},
	q{
		INSERT INTO multiplechoice_answers VALUES(
			'NoneEnjoyed',
			'Enjoyment',
			'none of my players did',
			'-1',
			3);
	},
	q{
		INSERT INTO question (qkey,genre,question,qtype,required,sorder) VALUES (
			'CommentsToCoordinator',
			'team_spirit',
			'Do you have any comments on this game you would like to bring to the coordinator''s attention?',
			'freetext',
			'N',
			'4');
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

	$sth = $dbh->table_info('%', '%', 'score_reminder', 'TABLE');
	if( $sth->fetch ) {
		return 17;
	}

	$sth = $dbh->column_info(undef, undef, 'field', 'parking_details');
	if( $sth->fetch ) {
		return 16;
	}

	$sth = $dbh->table_info('%', '%', 'registrations', 'TABLE');
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
			DROP INDEX person_ward
		},
		q{
			ALTER TABLE field DROP ward_id
		},
		q{
			DROP INDEX field_ward
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
		add_wager_ladder => [q{
			ALTER TABLE league
				MODIFY schedule_type ENUM('none','roundrobin','ladder','pyramid','ratings_ladder', 'ratings_wager_ladder') DEFAULT 'roundrobin';
		}],

		# Member IDs are now based on the user_id value, rather than
		# silly, unnecessary complexity based on the year and gender.
		change_member_id => [q{
			DROP TABLE member_id_sequence;
		}],
	]);
}


1;
