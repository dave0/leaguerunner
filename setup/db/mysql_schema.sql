DROP database IF EXISTS leaguerunner;
CREATE database leaguerunner;
use leaguerunner;

grant all on leaguerunner.* to leaguerunner@localhost identified by 'password';

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

CREATE TABLE demographics (
	income enum('000to020K', '020to040K', '040to060K', '060to080K', '080to100K', '100to150K', '150to200K', '200Kplus'),
	num_children enum('0','1','2','3','4','more than 4'),
	education enum(	'none', 'highschool', 'trade', 'college', 'undergrad', 'masters', 'doctorate' ),
	field	varchar(100),
	language enum('en','fr','enfr'),
	other_sports varchar(255)
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
	coordinator_id	integer,	-- coordinator
	alternate_id	integer,	-- alternate coordinator
	max_teams	integer,
-- This information is for handling different rounds in the schedule
-- The current round is used to determine what round a new week will be 
-- and to determine what to display (if stats_display == currentround)
	current_round int DEFAULT 1,
	stats_display ENUM('all','currentround') DEFAULT 'all',
	year        integer,
	start_time	time,
	allow_schedule	ENUM("Y","N") DEFAULT 'Y',  -- Should this league have scheduling info?
	PRIMARY KEY (league_id)
);

CREATE TABLE leagueteams (
	league_id 	integer NOT NULL,
	team_id		integer NOT NULL,
	PRIMARY KEY (team_id,league_id),
	INDEX leagueteams_league (league_id)
);

-- Tables for scheduling/scorekeeping

CREATE TABLE schedule (
    game_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    league_id int NOT NULL,
-- This indicates what round this game is in.
    round int NOT NULL DEFAULT 1,
    date_played datetime, -- date and time of game.
    home_team   integer,
    away_team   integer,
    field_id    integer,
    home_score  tinyint,
    away_score  tinyint,
    home_spirit tinyint,
    away_spirit tinyint,
    rating_points int, -- rating points exchanged for this game
    approved_by int, -- user_id of person who approved the score, or -1 if autoapproved.
    defaulted  enum('no','home','away') DEFAULT 'no',
    INDEX game_date (date_played),
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

CREATE TABLE site ( 
	site_id int NOT NULL PRIMARY KEY AUTO_INCREMENT, 
	name varchar(255) UNIQUE, 
	code char(3) UNIQUE, 
	region   enum('Central','East','South','West'),
	ward_id integer,
	location_url varchar(255), 
	layout_url varchar(255), 
	directions text, 
	instructions text,
	INDEX site_ward (ward_id)
);

CREATE TABLE field (
	field_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
	site_id int NOT NULL,
	num tinyint,
	status   enum('open','closed'),
	notes text,
	availability set('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
	INDEX field_site (site_id),
	INDEX field_status (status)
);

-- field assignments
CREATE TABLE field_assignment (
    league_id int NOT NULL,
    field_id  int NOT NULL,
    day       ENUM('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL
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

-- waiting list
CREATE TABLE waitinglist (
	wlist_id	integer NOT NULL AUTO_INCREMENT,
	name		varchar(100) NOT NULL,
	description	text,
	max_male	integer,
	max_female	integer,
	allow_couples_registration ENUM('Y','N') DEFAULT 'N',
	selection	ENUM('order submitted', 'draft', 'random draw', 'other') DEFAULT 'order submitted',
	PRIMARY KEY (wlist_id)
);

CREATE TABLE waitinglistmembers (
	wlist_id	integer NOT NULL,
	user_id		integer NOT NULL,
	paired_with	integer, -- for couples registration
	preference	smallint,
	date_registered datetime,
	PRIMARY KEY(wlist_id, user_id)
);
