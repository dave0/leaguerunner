create table gameslot (
	slot_id		integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
	fid		integer NOT NULL,
	game_date	date,
	game_start	time,
	game_end	time,
	game_id		integer
);

create table league_gameslot_availability (
	league_id 	integer NOT NULL,
	slot_id		integer NOT NULL
);

-- dump current field assignments for reassignment in future
select l.name, l.tier, s.name, s.code, f.num, a.day 
	FROM field_assignment a 
		LEFT JOIN league l on (l.league_id = a.league_id) 
		LEFT JOIN field f ON f.field_id = a.field_id 
		LEFT JOIN site s ON f.site_id = s.site_id 
	WHERE l.season = 'Summer' 
	ORDER BY l.season, l.day, l.tier, s.name, f.num;

-- Bring field table up-to-date
alter table field drop index field_site;
alter table field drop index field_status;
alter table field change field_id fid int;
alter table field drop primary key;
alter table field change fid fid int NOT NULL PRIMARY KEY AUTO_INCREMENT;
alter table field add parent_fid int after notes;
alter table field add name varchar(255) after parent_fid;
alter table field add code char(3) after name;
alter table field add region enum('Central','East','South','West') after code;
alter table field add ward_id integer after region;
alter table field add site_directions text after ward_id;
alter table field add site_instructions text after site_directions;
alter table field add location_url varchar(255);
alter table field add layout_url varchar(255);

update field, site SET 
	field.name = site.name, 
	field.code = site.code, 
	field.region = site.region, 
	field.ward_id = site.ward_id, 
	field.site_directions = site.directions, 
	field.site_instructions = site.instructions, 
	field.location_url = site.location_url, 
	field.layout_url = site.layout_url
   WHERE field.site_id = site.site_id;

-- Set parenting info for existing multi-field sites
create table tempfield (
	fid	int,
	num 	tinyint,
	name	varchar(255)
);
insert into tempfield (fid, num, name) 
    SELECT f.fid, f.num, f.name FROM field f where f.num = 1;
update field, tempfield SET
	field.name = NULL,
	field.code = NULL,
	field.region = NULL,
	field.ward_id = NULL,
	field.site_directions = NULL,
	field.site_instructions = NULL,
	field.location_url = NULL,
	field.layout_url = NULL,
	field.parent_fid = tempfield.fid
    WHERE field.name = tempfield.name AND field.num != 1;
drop table tempfield;
	

-- Create gameslots for all played games
insert into gameslot (game_id,fid,game_date, game_start) 
   SELECT s.game_id, s.field_id, DATE_FORMAT(s.date_played,'%Y-%m-%d'),TIME_FORMAT(s.date_played,'%H:%i') as time FROM schedule s;
   
-- Assign gameslots for all played games
insert into league_gameslot_availability (league_id, slot_id) select s.league_id,g.slot_id from gameslot g, schedule s WHERE g.game_id = s.game_id;

-- Clean up field and schedule tables
alter table field drop site_id;
alter table schedule drop date_played;
alter table schedule drop field_id;

-- Remove now-unnecessary site table
drop table site;
	
-- TODO: Drop now-unused information
-- drop table field_assignment;

-- Support for future non-numeric rounds (finals, semis, etc)
alter table schedule change round round varchar(10);

-- Support for rescheduling
alter table schedule add status enum('normal','locked','rescheduled','cancelled','forfeit') DEFAULT 'normal' NOT NULL;
alter table schedule add rescheduled_slot integer;

-- Support for dependant games
alter table schedule add home_dependant_game integer after home_team;
alter table schedule add home_dependant_type enum('winner','loser') after home_dependant_game;
alter table schedule add away_dependant_game integer after away_team;
alter table schedule add away_dependant_type enum('winner','loser') after away_dependant_game;

-- Nuke ancient demographic info
drop table demographics;

-- Drop unused max_teams variable from league table
alter table league drop max_teams;

-- Add scheduling type to league table
alter table league add schedule_type ENUM('none','roundrobin','ladder') default 'roundrobin';
update league set schedule_type = 'none' where allow_schedule = 'N';
alter table league drop allow_schedule;
