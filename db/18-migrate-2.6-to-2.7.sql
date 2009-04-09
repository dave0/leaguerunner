-- City wards are now removed
ALTER TABLE person DROP ward_id;
DROP INDEX person_ward;

ALTER TABLE field DROP ward_id;
DROP INDEX field_ward;

DROP TABLE ward;
DELETE FROM variable WHERE name = 'wards';

ALTER TABLE field ENGINE=INNODB;
ALTER TABLE gameslot ENGINE=INNODB;
ALTER TABLE league ENGINE=INNODB;
ALTER TABLE league_gameslot_availability ENGINE=INNODB;
ALTER TABLE leaguemembers ENGINE=INNODB;
ALTER TABLE leagueteams ENGINE=INNODB;
ALTER TABLE member_id_sequence ENGINE=INNODB;
ALTER TABLE multiplechoice_answers ENGINE=INNODB;
ALTER TABLE person ENGINE=INNODB;
ALTER TABLE player_availability ENGINE=INNODB;
ALTER TABLE preregistrations ENGINE=INNODB;
ALTER TABLE question ENGINE=INNODB;
ALTER TABLE refund_answers ENGINE=INNODB;
ALTER TABLE registration_answers ENGINE=INNODB;
ALTER TABLE registration_audit ENGINE=INNODB;
ALTER TABLE registration_events ENGINE=INNODB;
ALTER TABLE registration_prereq ENGINE=INNODB;
ALTER TABLE registrations ENGINE=INNODB;
ALTER TABLE schedule ENGINE=INNODB;
ALTER TABLE score_entry ENGINE=INNODB;
ALTER TABLE score_reminder ENGINE=INNODB;
ALTER TABLE team ENGINE=INNODB;
ALTER TABLE team_request_player ENGINE=INNODB;
ALTER TABLE team_spirit_answers ENGINE=INNODB;
ALTER TABLE teamroster ENGINE=INNODB;
ALTER TABLE variable ENGINE=INNODB;
ALTER TABLE waitinglist ENGINE=INNODB;
ALTER TABLE waitinglistmembers ENGINE=INNODB;

-- Add country field to person table
ALTER TABLE person ADD COLUMN addr_country varchar(50) AFTER addr_prov;

-- Change score reminder table into something more generic
RENAME TABLE score_reminder TO activity_log;
ALTER TABLE activity_log ADD `type` VARCHAR( 128 ) NOT NULL FIRST;
ALTER TABLE activity_log
	CHANGE game_id primary_id INT( 11 ) NOT NULL DEFAULT '0',
	CHANGE team_id secondary_id INT( 11 ) NOT NULL DEFAULT '0',
	DROP PRIMARY KEY,
	ADD PRIMARY KEY ( `type` , primary_id , secondary_id ),
	ADD INDEX SECONDARY ( `type` , primary_id );
UPDATE activity_log SET `type` = "email_score_reminder" WHERE secondary_id != 0;
UPDATE activity_log SET `type` = "email_score_mismatch" WHERE secondary_id = 0;
