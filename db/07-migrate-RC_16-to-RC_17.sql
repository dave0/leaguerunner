CREATE TABLE variable (
	name    varchar(50) NOT NULL default '',
	value   longtext    NOT NULL,
	PRIMARY KEY(name)
);

CREATE TABLE leaguemembers (
	league_id	integer NOT NULL,
	player_id	integer NOT NULL,
	status		varchar(64),
	PRIMARY KEY	(player_id, league_id),
	INDEX leaguemembers_league (league_id)
);
INSERT INTO leaguemembers (league_id, player_id, status) 
	SELECT league_id, coordinator_id, 'coordinator' FROM league
	WHERE coordinator_id != 0;
	
INSERT INTO leaguemembers (league_id, player_id, status) 
	SELECT league_id, alternate_id, 'coordinator' FROM league
	WHERE alternate_id != 0;
	
ALTER TABLE league DROP coordinator_id;
ALTER TABLE league DROP alternate_id;
ALTER TABLE league DROP start_time;
