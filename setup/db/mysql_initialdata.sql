use leaguerunner;

INSERT INTO person (username,password,firstname,lastname,class,status) VALUES ('admin',MD5('admin'), 'System', 'Administrator','administrator','active');
INSERT INTO league (name,season,allow_schedule) VALUES ('Inactive Teams', 'none', 'N');
INSERT INTO leaguemembers (league_id, player_id, status) VALUES (1,1,'coordinator');

