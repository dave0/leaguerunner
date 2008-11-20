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
