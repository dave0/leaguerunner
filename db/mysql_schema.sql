#
# People in the system
#
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

	session_cookie varchar(50),
	class   enum('volunteer','administrator', 'player', 'visitor') DEFAULT 'player' NOT NULL,
        status  enum('new','inactive','active','locked') DEFAULT 'new' NOT NULL,

	waiver_signed datetime,

	has_dog		  ENUM("Y","N") DEFAULT 'N',
	dog_waiver_signed datetime,

	survey_completed  ENUM("Y","N") DEFAULT 'N',

	last_login datetime,
	client_ip      varchar(50),
	INDEX person_ward (ward_id)
);

-- For use when assigning member IDs
CREATE TABLE member_id_sequence (
	year	year not null,
	gender 	ENUM("Male","Female"),
	id 	integer not null,
 	KEY (year,gender)
);

CREATE TABLE team (
	team_id         integer NOT NULL AUTO_INCREMENT,
	name            varchar(100) UNIQUE NOT NULL,
	website         varchar(100),
	shirt_colour    varchar(30),
	status          ENUM("open","closed"),
	rating		int DEFAULT 1500,
	PRIMARY KEY (team_id)
);

CREATE TABLE teamroster (
	team_id		integer NOT NULL,
	player_id	integer NOT NULL,
	status		ENUM("captain", "assistant", "player", "substitute", "captain_request", "player_request"),
	date_joined	date,
	PRIMARY KEY (team_id,player_id)
);

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
	-- none replaces 'allow_schedule == N'
	schedule_type	ENUM('none','roundrobin','ladder') default 'roundrobin',

	PRIMARY KEY (league_id)
);

CREATE TABLE leagueteams (
	league_id 	integer NOT NULL,
	team_id		integer NOT NULL,
	rank		integer NOT NULL DEFAULT 0,
	PRIMARY KEY (team_id,league_id),
	INDEX leagueteams_league (league_id)
);

CREATE TABLE leaguemembers (
	league_id	integer NOT NULL,
	player_id	integer NOT NULL,
	status		varchar(64),
	PRIMARY KEY	(league_id, player_id),
	INDEX leaguemembers_league (league_id)
);

-- Tables for scheduling/scorekeeping

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
CREATE TABLE score_entry (
    team_id int NOT NULL,
    game_id int NOT NULL,
    entered_by int NOT NULL, -- id of user who entered score
    score_for tinyint NOT NULL, -- score for submitter's team
    score_against tinyint NOT NULL, -- score for opponent
    spirit tinyint NOT NULL,
    defaulted enum('no','us','them') DEFAULT 'no',
    PRIMARY KEY (team_id,game_id)
);

-- Spirit System
CREATE TABLE question (
	qkey	varchar(255) PRIMARY KEY, -- question key
	genre	varchar(255),
	question varchar(255),
	qtype   varchar(255),
	restrictions   varchar(255),  -- used for start/end dates, upper/lower limits, etc.
	required	ENUM('Y','N') DEFAULT 'Y',
	sorder	int default 0
);

CREATE TABLE multiplechoice_answers (
	akey	varchar(255) PRIMARY KEY,
	qkey	varchar(255),
	answer	varchar(255),
	value	varchar(255),
	sorder	int default 0		-- sort order
);

CREATE TABLE team_spirit_answers (
	tid_created	int NOT NULL, -- ID of team providing this answer
	tid		int NOT NULL, -- id of team receiving this answer
	gid		int NOT NULL, -- ID of game this entry relates to
	qkey		varchar(255), -- Question asked
	akey		varchar(255), -- Answer provided
	PRIMARY KEY (tid_created,gid,qkey)
);


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
	permit_url varchar(255),

	INDEX field_ward (ward_id)
);

-- Game slots for scheduling
create table gameslot (
	slot_id		integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
	fid		integer NOT NULL,
	game_date	date,
	game_start	time,
	game_end	time,
	game_id		integer
);

-- gameslot availability
create table league_gameslot_availability (
	league_id 	integer NOT NULL,
	slot_id		integer NOT NULL
);


-- city wards
CREATE TABLE ward (
	ward_id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
	num tinyint,
	name varchar(255) UNIQUE,
	city       varchar(50),
	region   enum('Central','East','South','West'),
	url       varchar(255),
	INDEX ward_city (city)
);

CREATE TABLE variable (
	name	varchar(50) NOT NULL default '',
	value	longtext    NOT NULL,
	PRIMARY KEY(name)
);
