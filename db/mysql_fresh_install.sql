-- Use InnoDB tables
SET storage_engine=INNODB;

--
-- People and accounts
--
DROP TABLE IF EXISTS person;

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

-- For use when assigning member IDs

DROP TABLE IF EXISTS member_id_sequence;

CREATE TABLE member_id_sequence (
        year      year not null,
        gender    ENUM('Male','Female'),
        id       integer not null,
        KEY (year,gender)
);

DROP TABLE IF EXISTS team;

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

DROP TABLE IF EXISTS teamroster;

CREATE TABLE teamroster (
        team_id     integer NOT NULL,
        player_id   integer NOT NULL,
        status      ENUM('coach', 'captain', 'assistant', 'player', 'substitute', 'captain_request', 'player_request'),
        date_joined date,
        PRIMARY KEY (team_id,player_id)
);

DROP TABLE IF EXISTS league;

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
        schedule_type       ENUM('none','roundrobin','ladder','pyramid','ratings_ladder') default 'roundrobin',
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

DROP TABLE IF EXISTS leagueteams;

CREATE TABLE leagueteams (
        league_id   integer NOT NULL,
        team_id     integer NOT NULL,
        rank        integer NOT NULL DEFAULT 0,
        PRIMARY KEY (team_id,league_id),
        INDEX leagueteams_league (league_id)
);

DROP TABLE IF EXISTS leaguemembers;

CREATE TABLE leaguemembers (
        league_id   integer NOT NULL,
        player_id   integer NOT NULL,
        status      varchar(64),
        PRIMARY KEY (league_id, player_id),
        INDEX leaguemembers_league (league_id)
);

-- Tables for scheduling/scorekeeping

DROP TABLE IF EXISTS schedule;

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

-- score_entry table is used to store scores entered by either team
-- before they are approved

DROP TABLE IF EXISTS score_entry;

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

DROP TABLE IF EXISTS score_reminder;

CREATE TABLE score_reminder (
        game_id   integer NOT NULL,
        team_id   integer NOT NULL,
        sent_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY ( game_id, team_id )
);

DROP TABLE IF EXISTS question;

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

DROP TABLE IF EXISTS multiplechoice_answers;

CREATE TABLE multiplechoice_answers (
        akey        varchar(255),
        qkey        varchar(255),
        answer      varchar(255),
        value       varchar(255),
        sorder      integer default 0,
        PRIMARY KEY (akey,qkey)
);

DROP TABLE IF EXISTS team_spirit_answers;

CREATE TABLE team_spirit_answers (
        tid_created integer NOT NULL,
        tid         integer NOT NULL,
        gid         integer NOT NULL,
        qkey        varchar(255) NOT NULL,
        akey        blob,
        PRIMARY KEY (tid_created,gid,qkey)
);

DROP TABLE IF EXISTS field;

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

DROP TABLE IF EXISTS gameslot;

CREATE TABLE gameslot (
        slot_id    integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
        fid        integer NOT NULL,
        game_date  date,
        game_start time,
        game_end   time,
        game_id    integer
);

DROP TABLE IF EXISTS league_gameslot_availability;

CREATE TABLE league_gameslot_availability (
        league_id integer NOT NULL,
        slot_id   integer NOT NULL
);

DROP TABLE IF EXISTS variable;

CREATE TABLE variable (
        name        varchar(50) NOT NULL default '',
        value        longtext    NOT NULL,
        PRIMARY KEY(name)
);

DROP TABLE IF EXISTS registration_events;

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

DROP TABLE IF EXISTS registration_prereq;

CREATE TABLE registration_prereq (
        registration_id int(11) NOT NULL default '0',
        prereq_id int(11) NOT NULL default '0',
        is_prereq tinyint(1) NOT NULL default '0',
        PRIMARY KEY  (registration_id,prereq_id)
);

DROP TABLE IF EXISTS registrations;

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

-- answers to registration questions

DROP TABLE IF EXISTS registration_answers;

CREATE TABLE registration_answers (
        order_id int(10) unsigned NOT NULL default '0',
        qkey varchar(255) NOT NULL default '',
        akey varchar(255) default NULL,
        PRIMARY KEY  (order_id,qkey)
);

-- online registration payment details

DROP TABLE IF EXISTS registration_audit;

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

DROP TABLE IF EXISTS preregistrations;

CREATE TABLE preregistrations (
        user_id int(11) NOT NULL default '0',
        registration_id int(10) unsigned NOT NULL default '0',
        KEY user_id (user_id,registration_id)
);

INSERT INTO person (username,password,firstname,lastname,class,status)
        VALUES ('admin',
                MD5('admin'),
                'System',
                'Administrator',
                'administrator',
                'active'
);

INSERT INTO league (name,season,schedule_type) VALUES ('Inactive Teams', 'none', 'none');

INSERT INTO leaguemembers (league_id, player_id, status) VALUES (1,1,'coordinator');

--
-- Spirit Scoring questions
--

INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'Timeliness',
	'team_spirit',
	'Our opponents had a full line and were ready to play',
	'multiplechoice',
	0);

INSERT INTO multiplechoice_answers VALUES(
	'OnTime',
	'Timeliness',
	'early, or at the official start time',
	'0',
	0);

INSERT INTO multiplechoice_answers VALUES(
	'FiveOrLess',
	'Timeliness',
	'less than five minutes late',
	'-1',
	1);

INSERT INTO multiplechoice_answers VALUES(
	'LessThanTen',
	'Timeliness',
	'less than ten minutes late',
	'-2',
	2);

INSERT INTO multiplechoice_answers VALUES(
	'MoreThanTen',
	'Timeliness',
	'more than ten minutes late',
	'-3',
	3);

INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'RulesKnowledge',
	'team_spirit',
	'Our opponents\' rules knowledge was',
	'multiplechoice',
	1);

INSERT INTO multiplechoice_answers VALUES(
	'ExcellentRules',
	'RulesKnowledge',
	'excellent',
	'0',
	0);

INSERT INTO multiplechoice_answers VALUES(
	'AcceptableRules',
	'RulesKnowledge',
	'acceptable',
	'-1',
	1);

INSERT INTO multiplechoice_answers VALUES(
	'PoorRules',
	'RulesKnowledge',
	'poor',
	'-2',
	2);

INSERT INTO multiplechoice_answers VALUES(
	'NonexistantRules',
	'RulesKnowledge',
	'nonexistant',
	'-3',
	3);

INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'Sportsmanship',
	'team_spirit',
	'Our opponents\' sportsmanship was',
	'multiplechoice',
	2);

INSERT INTO multiplechoice_answers VALUES(
	'ExcellentSportsmanship',
	'Sportsmanship',
	'excellent',
	'0',
	0);

INSERT INTO multiplechoice_answers VALUES(
	'AcceptableSportsmanship',
	'Sportsmanship',
	'acceptable',
	'-1',
	1);

INSERT INTO multiplechoice_answers VALUES(
	'PoorSportsmanship',
	'Sportsmanship',
	'poor',
	'-2',
	2);

INSERT INTO multiplechoice_answers VALUES(
	'NonexistantSportsmanship',
	'Sportsmanship',
	'nonexistant',
	'-3',
	3);

INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'Enjoyment',
	'team_spirit',
	'Ignoring the score and based on the opponents\' spirit of the game, did your team enjoy this game?',
	'multiplechoice',
	3);

INSERT INTO multiplechoice_answers VALUES(
	'AllEnjoyed',
	'Enjoyment',
	'all of my players did',
	'0',
	0);

INSERT INTO multiplechoice_answers VALUES(
	'MostEnjoyed',
	'Enjoyment',
	'most of my players did',
	'-1',
	1);

INSERT INTO multiplechoice_answers VALUES(
	'SomeEnjoyed',
	'Enjoyment',
	'some of my players did',
	'-1',
	2);

INSERT INTO multiplechoice_answers VALUES(
	'NoneEnjoyed',
	'Enjoyment',
	'none of my players did',
	'-1',
	3);


-- Note to coordinator

INSERT INTO question (qkey,genre,question,qtype,required,sorder) VALUES (
	'CommentsToCoordinator',
	'team_spirit',
	'Do you have any comments on this game you would like to bring to the coordinator''s attention?',
	'freetext',
	'N',
	'4');

INSERT INTO variable (name,value) VALUES ('_SchemaVersion', 18);
