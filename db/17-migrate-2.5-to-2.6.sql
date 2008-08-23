-- Add new fields to the fields
ALTER TABLE field ADD sponsor TEXT AFTER site_instructions;

-- Add new registration event type
ALTER TABLE registration_events
 ADD type ENUM( 'membership', 'individual_event', 'team_event', 'individual_league', 'team_league' ) NOT NULL DEFAULT 'individual_event' AFTER description;

-- Convert the payment field from boolean to an enum
ALTER TABLE registrations
 ADD COLUMN payment ENUM('Unpaid','Pending','Paid','Refunded') NOT NULL DEFAULT 'Unpaid' AFTER paid;
UPDATE registrations SET payment = 'Paid' WHERE paid = 1;
UPDATE registrations SET payment = 'Unpaid' WHERE paid = 0;
ALTER TABLE registrations
 DROP COLUMN paid;

-- Convert the refunds table and merge it into the registrations
ALTER TABLE refunds
 ADD COLUMN payment ENUM('Unpaid','Pending','Paid','Refunded') NOT NULL DEFAULT 'Unpaid' AFTER paid;
UPDATE refunds SET payment = 'Refunded';
ALTER TABLE refunds
 DROP COLUMN paid;
INSERT INTO registrations (SELECT * FROM refunds);
DROP TABLE refunds;
INSERT INTO registration_answers (SELECT * FROM refund_answers);
DROP TABLE refund_answers;

-- Add a "last modified" timestamp field to the registrations
ALTER TABLE registrations
 MODIFY COLUMN time TIMESTAMP NULL DEFAULT 0;
ALTER TABLE registrations
 ADD COLUMN modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER time;
UPDATE registrations SET modified = time;

-- Add a preregistration table
CREATE TABLE preregistrations (
	user_id INTEGER NOT NULL DEFAULT '0',
	registration_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	KEY user_id (user_id,registration_id)
);

-- Add a roster deadline for leagues
ALTER TABLE league
 ADD COLUMN roster_deadline DATETIME DEFAULT 0 AFTER current_round;

-- Allow for leagues to be closed, and no longer appear in normal displays
ALTER TABLE league
 ADD COLUMN status ENUM('open','closed') NOT NULL DEFAULT 'open' AFTER year;

-- Change the sort order of the seasons, for historical team ordering
ALTER TABLE league MODIFY COLUMN season ENUM ('none','Winter','Spring','Summer','Fall') default NULL;

-- Team name uniqueness will be enforced by the code now instead of the database
ALTER TABLE team DROP INDEX name, ADD INDEX name (name);

-- Add fields for configuring how long to wait before emailing delinquent
-- captains and finalizing games, when scores have not been confirmed
ALTER TABLE league
 ADD COLUMN email_after INT NOT NULL DEFAULT '0';
ALTER TABLE league
 ADD COLUMN finalize_after INT NOT NULL DEFAULT '0';

-- Add a table for remembering and tracking missing score reminders
CREATE TABLE score_reminder (
	game_id INTEGER NOT NULL,
	team_id INTEGER NOT NULL,
	sent_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY ( game_id, team_id )
);
