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
	PRIMARY KEY(wlist_id, player_id)
);

-- for use in selecting people from waitinglist
ALTER TABLE person ADD height smallint AFTER birthdate;
