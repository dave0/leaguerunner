use leaguerunner;

INSERT INTO person (username,password,firstname,lastname,class) VALUES ('admin',MD5('admin'), 'System', 'Administrator','administrator');
INSERT INTO league (name,coordinator_id,allow_schedule) VALUES ('Inactive Teams', 1, 'N');

