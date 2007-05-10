#
# People in the system
#
DROP TABLE IF EXISTS person;
CREATE TABLE person (

	user_id	 integer  NOT NULL PRIMARY KEY AUTO_INCREMENT,

	username        varchar(100) UNIQUE NOT NULL,
	password        varchar(100), -- MD5 hashed

	member_id	integer default 0,

	firstname       varchar(100),
	lastname        varchar(100),

	email	        varchar(100),
	allow_publish_email	ENUM("Y","N") DEFAULT 'N',  -- Publish in directory.
	
	home_phone      varchar(30),
	publish_home_phone	ENUM("Y","N") DEFAULT 'N',

	work_phone      varchar(30),
	publish_work_phone	ENUM("Y","N") DEFAULT 'N',

	mobile_phone    varchar(30),
	publish_mobile_phone	ENUM("Y","N") DEFAULT 'N',

	addr_street     varchar(50),
	addr_city       varchar(50),
	addr_prov       ENUM('Ontario','Quebec','Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland','Northwest Territories','Nunavut','Nova Scotia','Prince Edward Island','Saskatchewan','Yukon'),
	addr_postalcode char(7),

	ward_id 	integer,

	gender 		ENUM("Male","Female"),

	birthdate       date,

	height          smallint,

	skill_level  integer DEFAULT 0,  -- 1-5 scale
	year_started integer DEFAULT 0,  -- years playing

	shirtsize	varchar(50),

	session_cookie varchar(50),
	class   enum('volunteer','administrator', 'player', 'visitor') DEFAULT 'player' NOT NULL,
        status  enum('new','inactive','active','locked') DEFAULT 'new' NOT NULL,

	waiver_signed datetime,

	has_dog		  ENUM("Y","N") DEFAULT 'N',
	dog_waiver_signed datetime,

	survey_completed  ENUM("Y","N") DEFAULT 'N',

	willing_to_volunteer  ENUM("Y","N") DEFAULT 'N',

	contact_for_feedback  ENUM("Y","N") DEFAULT 'Y',

	last_login datetime,
	client_ip      varchar(50),
	INDEX person_ward (ward_id)
);

-- For use when assigning member IDs
DROP TABLE IF EXISTS member_id_sequence;
CREATE TABLE member_id_sequence (
	year	year not null,
	gender 	ENUM("Male","Female"),
	id 	integer not null,
 	KEY (year,gender)
);

DROP TABLE IF EXISTS team;
CREATE TABLE team (
	team_id         integer NOT NULL AUTO_INCREMENT,
	name            varchar(100) UNIQUE NOT NULL,
	website         varchar(100),
	shirt_colour    varchar(50),
	home_field      integer,
	region_preference varchar(50),
	status          ENUM("open","closed"),
	rating		int DEFAULT 1500,
	PRIMARY KEY (team_id)
);

DROP TABLE IF EXISTS teamroster;
CREATE TABLE teamroster (
	team_id		integer NOT NULL,
	player_id	integer NOT NULL,
	status		ENUM("coach", "captain", "assistant", "player", "substitute", "captain_request", "player_request"),
	date_joined	date,
	PRIMARY KEY (team_id,player_id)
);

DROP TABLE IF EXISTS league;
CREATE TABLE league (
	league_id	integer NOT NULL AUTO_INCREMENT,
    name		varchar(100),
	-- can play more than one day a week, so make it a set.	
	day 		SET('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
	season		ENUM('none','Spring','Summer','Fall','Winter'),
	tier		integer,
	ratio		ENUM('4/3','5/2','3/3','4/2','3/2','womens','mens','open'),
-- This information is for handling different rounds in the schedule
-- The current round is used to determine what round a new week will be 
-- and to determine what to display (if stats_display == currentround)
	current_round int DEFAULT 1,
	stats_display ENUM('all','currentround') DEFAULT 'all',
	year        integer,

	-- What type of scheduling should this league have?  
	-- roundrobin is standard
	-- ladder is the 'new' laddering system
	-- pyramid is the pyramid laddering system
	-- ratings_ladder is the ratings ladder system (improved pyramid)
	-- none replaces 'allow_schedule == N'
	schedule_type	ENUM('none','roundrobin','ladder','pyramid','ratings_ladder') default 'roundrobin',

   -- For Pyramid Ladder's:
   -- how many games before allowed to repeat opponents?
	games_before_repeat		integer default 4,
   -- how many attempts at generating the schedule with no repeats?
	schedule_attempts		integer default 100,
   -- Allow players to see SOTG answers assigned to their team?
	see_sotg          ENUM('true','false') default 'true',

	PRIMARY KEY (league_id)
);

DROP TABLE IF EXISTS leagueteams;
CREATE TABLE leagueteams (
	league_id 	integer NOT NULL,
	team_id		integer NOT NULL,
	rank		integer NOT NULL DEFAULT 0,
	PRIMARY KEY (team_id,league_id),
	INDEX leagueteams_league (league_id)
);

DROP TABLE IF EXISTS leaguemembers;
CREATE TABLE leaguemembers (
	league_id	integer NOT NULL,
	player_id	integer NOT NULL,
	status		varchar(64),
	PRIMARY KEY	(league_id, player_id),
	INDEX leaguemembers_league (league_id)
);

-- Tables for scheduling/scorekeeping

DROP TABLE IF EXISTS schedule;
CREATE TABLE schedule (
    game_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    league_id int NOT NULL,
    
-- This indicates what round this game is in.
    round varchar(10) NOT NULL DEFAULT '1',
   
    -- Teams for home/away can be specified either directly, or
    -- by providing a game and result identifier
    home_team   integer,
    home_dependant_game	integer,
    home_dependant_type enum('winner','loser'),
    home_dependant_rank	integer,
    away_team   integer,
    away_dependant_game	integer,
    away_dependant_type enum('winner','loser'),
    away_dependant_rank	integer,
    
    home_score  tinyint,
    away_score  tinyint,
    home_spirit tinyint,
    away_spirit tinyint,
    
    rating_points int, -- rating points exchanged for this game
    approved_by int, -- user_id of person who approved the score, or -1 if autoapproved.

    -- Game status.  Indicates rescheduling, defaults, etc.
    status ENUM('normal','locked','home_default','away_default','rescheduled','cancelled','forfeit') default 'normal' NOT NULL,
    
    INDEX game_league (league_id),
    INDEX game_home_team (home_team),
    INDEX game_away_team (away_team)
);

-- score_entry table is used to store scores entered by either team
-- before they are approved
DROP TABLE IF EXISTS score_entry;
CREATE TABLE score_entry (
    team_id int NOT NULL,
    game_id int NOT NULL,
    entered_by int NOT NULL, -- id of user who entered score
    score_for tinyint NOT NULL, -- score for submitter's team
    score_against tinyint NOT NULL, -- score for opponent
    spirit tinyint NOT NULL,
    defaulted enum('no','us','them') DEFAULT 'no',
    entry_time datetime,
    PRIMARY KEY (team_id,game_id)
);

-- Spirit System
DROP TABLE IF EXISTS question;
CREATE TABLE question (
	qkey	varchar(255) PRIMARY KEY, -- question key
	genre	varchar(255),
	question varchar(255),
	qtype   varchar(255),
	restrictions   varchar(255),  -- used for start/end dates, upper/lower limits, etc.
	required	ENUM('Y','N') DEFAULT 'Y',
	sorder	int default 0
);

DROP TABLE IF EXISTS multiplechoice_answers;
CREATE TABLE multiplechoice_answers (
	akey	varchar(255) PRIMARY KEY,
	qkey	varchar(255),
	answer	varchar(255),
	value	varchar(255),
	sorder	int default 0		-- sort order
);

DROP TABLE IF EXISTS team_spirit_answers;
CREATE TABLE team_spirit_answers (
	tid_created	int NOT NULL, -- ID of team providing this answer
	tid		int NOT NULL, -- id of team receiving this answer
	gid		int NOT NULL, -- ID of game this entry relates to
	qkey		varchar(255) NOT NULL, -- Question asked
	akey		varchar(255), -- Answer provided
	PRIMARY KEY (tid_created,gid,qkey)
);


DROP TABLE IF EXISTS field;
CREATE TABLE field (
	fid	int NOT NULL PRIMARY KEY AUTO_INCREMENT,
	num	tinyint,
	status  enum('open','closed'),

	rating  varchar(16),
	
	notes	text,
	parent_fid	int,
	
	-- If there's a parent field ID provided, the values below are
	-- inherited from parent rather than used from the table
	name	varchar(255),
	code	char(3),

	-- Physical location of field (not location for billing/ownership purposes)
	location_street     varchar(50),
	location_city       varchar(50),
	location_province   varchar(50),
	latitude   double,
	longitude  double,
	
	region	enum('Central','East','South','West'),
	ward_id integer,
	site_directions	text,
	site_instructions text,
	location_url varchar(255),
	layout_url varchar(255),

	INDEX field_ward (ward_id)
);

-- Game slots for scheduling
DROP TABLE IF EXISTS gameslot;
CREATE TABLE gameslot (
	slot_id		integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
	fid		integer NOT NULL,
	game_date	date,
	game_start	time,
	game_end	time,
	game_id		integer
);

-- gameslot availability
DROP TABLE IF EXISTS league_gameslot_availability;
CREATE TABLE league_gameslot_availability (
	league_id 	integer NOT NULL,
	slot_id		integer NOT NULL
);


-- city wards
DROP TABLE IF EXISTS ward;
CREATE TABLE ward (
	ward_id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
	num tinyint,
	name varchar(255) UNIQUE,
	city       varchar(50),
	region   enum('Central','East','South','West'),
	url       varchar(255),
	INDEX ward_city (city)
);

-- configuration variables
DROP TABLE IF EXISTS variable;
CREATE TABLE variable (
	name	varchar(50) NOT NULL default '',
	value	longtext    NOT NULL,
	PRIMARY KEY(name)
);

-- available registration events
DROP TABLE IF EXISTS registration_events;
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
);

-- registration event prerequisites
DROP TABLE IF EXISTS registration_prereq;
CREATE TABLE registration_prereq (
	registration_id int(11) NOT NULL default '0',
	prereq_id int(11) NOT NULL default '0',
	is_prereq tinyint(1) NOT NULL default '0',
	PRIMARY KEY  (registration_id,prereq_id)
);

-- completed registrations
DROP TABLE IF EXISTS registrations;
CREATE TABLE registrations (
	order_id int(10) unsigned NOT NULL auto_increment,
	user_id int(11) NOT NULL default '0',
	registration_id int(10) unsigned NOT NULL default '0',
	`time` timestamp NOT NULL default CURRENT_TIMESTAMP,
	paid tinyint(1) NOT NULL default '0',
	notes blob,
	PRIMARY KEY  (order_id),
	KEY user_id (user_id,registration_id)
);

-- answers to registration questions
DROP TABLE IF EXISTS registration_answers;
CREATE TABLE registration_answers (
	user_id int(11) NOT NULL default '0',
	registration_id int(11) NOT NULL default '0',
	qkey varchar(255) NOT NULL default '',
	akey varchar(255) default NULL,
	PRIMARY KEY  (user_id,registration_id,qkey)
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

-- refunded registrations
DROP TABLE IF EXISTS refunds;
CREATE TABLE refunds (
	order_id int(10) unsigned NOT NULL default '0',
	user_id int(11) NOT NULL default '0',
	registration_id int(10) unsigned NOT NULL default '0',
	`time` timestamp NOT NULL default CURRENT_TIMESTAMP,
	paid tinyint(1) NOT NULL default '0',
	notes blob,
	PRIMARY KEY  (order_id),
	KEY user_id (user_id,registration_id)
);

-- answers to registration questions, for refunded registrations
DROP TABLE IF EXISTS refund_answers;
CREATE TABLE refund_answers (
	user_id int(11) NOT NULL default '0',
	registration_id int(11) NOT NULL default '0',
	qkey varchar(255) NOT NULL default '',
	akey varchar(255) default NULL,
	PRIMARY KEY  (user_id,registration_id,qkey)
);
