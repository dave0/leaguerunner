use leaguerunner;

INSERT INTO person (username,password,firstname,lastname,class,status) VALUES ('admin',MD5('admin'), 'System', 'Administrator','administrator','active');
INSERT INTO league (name,season,allow_schedule) VALUES ('Inactive Teams', 'none', 'N');
INSERT INTO leaguemembers (league_id, player_id, status) VALUES (1,1,'coordinator');

-- 
-- Spirit Scoring questions
-- 
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'Timeliness',
	'team_spirit',
	'Our opponents had a full line and were ready to play',
	'multiplechoice',
	0);
INSERT INTO multiplechoice_answers VALUES(
	'OnTime', 
	'Timeliness',
	'at the official start time',
	'0',
	0);
INSERT INTO multiplechoice_answers VALUES(
	'FiveOrLess', 
	'Timeliness',
	'less than five minutes late',
	'-1',
	1);
INSERT INTO multiplechoice_answers VALUES(
	'MoreThanFive', 
	'Timeliness',
	'more than five minutes late',
	'-2',
	2);
	
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'RulesKnowledge',
	'team_spirit',
	'Our opponents\' rules knowledge was',
	'multiplechoice',
	1);
INSERT INTO multiplechoice_answers VALUES(
	'ExcellentRules', 
	'RulesKnowledge',
	'excellent',
	'0',
	0);
INSERT INTO multiplechoice_answers VALUES(
	'AcceptableRules', 
	'RulesKnowledge',
	'acceptable, and they were willing to learn',
	'0',
	1);
INSERT INTO multiplechoice_answers VALUES(
	'PoorRules', 
	'RulesKnowledge',
	'poor, and they weren\'t interested in learning',
	'-1',
	2);
INSERT INTO multiplechoice_answers VALUES(
	'NonexistantRules', 
	'RulesKnowledge',
	'nonexistant',
	'-2',
	3);
	
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'Sportsmanship',
	'team_spirit',
	'Our opponents\' sportsmanship was',
	'multiplechoice',
	2);
INSERT INTO multiplechoice_answers VALUES(
	'ExcellentSportsmanship', 
	'Sportsmanship',
	'excellent',
	'0',
	0);
INSERT INTO multiplechoice_answers VALUES(
	'AcceptableSportsmanship', 
	'Sportsmanship',
	'acceptable',
	'-1',
	1);
INSERT INTO multiplechoice_answers VALUES(
	'PoorSportsmanship', 
	'Sportsmanship',
	'poor',
	'-2',
	2);
	
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'Enjoyment',
	'team_spirit',
	'Did your team enjoy playing against your opponents?',
	'multiplechoice',
	3);
INSERT INTO multiplechoice_answers VALUES(
	'AllEnjoyed', 
	'Enjoyment',
	'all of my players did',
	'0',
	0);
INSERT INTO multiplechoice_answers VALUES(
	'MostEnjoyed', 
	'Enjoyment',
	'most of my players did',
	'-1',
	1);
INSERT INTO multiplechoice_answers VALUES(
	'FewEnjoyed', 
	'Enjoyment',
	'few or none of my players did',
	'-2',
	2);
	
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'GameOverall',
	'team_spirit',
	'Overall, this was',
	'multiplechoice',
	4);
INSERT INTO multiplechoice_answers VALUES(
	'OverallGood', 
	'GameOverall',
	'a team we would be happy to play again',
	'0',
	0);
INSERT INTO multiplechoice_answers VALUES(
	'OverallAverage', 
	'GameOverall',
	'just another average team',
	'-1',
	1);
INSERT INTO multiplechoice_answers VALUES(
	'OverallPoor', 
	'GameOverall',
	'not a team we would like to see again',
	'-2',
	2);
