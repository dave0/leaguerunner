-- City wards are now removed
ALTER TABLE person DROP ward_id;
DROP INDEX person_ward;

ALTER TABLE field DROP ward_id;
DROP INDEX field_ward;

DROP TABLE ward;
DELETE FROM variable WHERE name = 'wards';

ALTER TABLE leaguerunner.field ENGINE=INNODB;
ALTER TABLE leaguerunner.gameslot ENGINE=INNODB;
ALTER TABLE leaguerunner.league ENGINE=INNODB;
ALTER TABLE leaguerunner.league_gameslot_availability ENGINE=INNODB;
ALTER TABLE leaguerunner.leaguemembers ENGINE=INNODB;
ALTER TABLE leaguerunner.leagueteams ENGINE=INNODB;
ALTER TABLE leaguerunner.member_id_sequence ENGINE=INNODB;
ALTER TABLE leaguerunner.multiplechoice_answers ENGINE=INNODB;
ALTER TABLE leaguerunner.person ENGINE=INNODB;
ALTER TABLE leaguerunner.player_availability ENGINE=INNODB;
ALTER TABLE leaguerunner.preregistrations ENGINE=INNODB;
ALTER TABLE leaguerunner.question ENGINE=INNODB;
ALTER TABLE leaguerunner.refund_answers ENGINE=INNODB;
ALTER TABLE leaguerunner.registration_answers ENGINE=INNODB;
ALTER TABLE leaguerunner.registration_audit ENGINE=INNODB;
ALTER TABLE leaguerunner.registration_events ENGINE=INNODB;
ALTER TABLE leaguerunner.registration_prereq ENGINE=INNODB;
ALTER TABLE leaguerunner.registrations ENGINE=INNODB;
ALTER TABLE leaguerunner.schedule ENGINE=INNODB;
ALTER TABLE leaguerunner.score_entry ENGINE=INNODB;
ALTER TABLE leaguerunner.score_reminder ENGINE=INNODB;
ALTER TABLE leaguerunner.team ENGINE=INNODB;
ALTER TABLE leaguerunner.team_request_player ENGINE=INNODB;
ALTER TABLE leaguerunner.team_spirit_answers ENGINE=INNODB;
ALTER TABLE leaguerunner.teamroster ENGINE=INNODB;
ALTER TABLE leaguerunner.variable ENGINE=INNODB;
ALTER TABLE leaguerunner.waitinglist ENGINE=INNODB;
ALTER TABLE leaguerunner.waitinglistmembers ENGINE=INNODB;
ALTER TABLE leaguerunner.ward ENGINE=INNODB;
