INSERT INTO person (username,password,firstname,lastname,class,status) VALUES ('admin',MD5('admin'), 'System', 'Administrator','administrator','active');
INSERT INTO league (name,season,schedule_type) VALUES ('Inactive Teams', 'none', 'none');
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
	'early, or at the official start time',
	'0',
	0);
INSERT INTO multiplechoice_answers VALUES(
	'FiveOrLess', 
	'Timeliness',
	'less than five minutes late',
	'-1',
	1);
INSERT INTO multiplechoice_answers VALUES(
	'LessThanTen', 
	'Timeliness',
	'less than ten minutes late',
	'-2',
	2);
INSERT INTO multiplechoice_answers VALUES(
	'MoreThanTen', 
	'Timeliness',
	'more than ten minutes late',
	'-3',
	3);
	
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
	'acceptable',
	'-1',
	1);
INSERT INTO multiplechoice_answers VALUES(
	'PoorRules', 
	'RulesKnowledge',
	'poor',
	'-2',
	2);
INSERT INTO multiplechoice_answers VALUES(
	'NonexistantRules', 
	'RulesKnowledge',
	'nonexistant',
	'-3',
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

INSERT INTO multiplechoice_answers VALUES(
	'NonexistantSportsmanship', 
	'Sportsmanship',
	'nonexistant',
	'-3',
	3);
	
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES (
	'Enjoyment',
	'team_spirit',
	'Ignoring the score and based on the opponents\' spirit of the game, did your team enjoy this game?',
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
	'SomeEnjoyed', 
	'Enjoyment',
	'some of my players did',
	'-1',
	2);
INSERT INTO multiplechoice_answers VALUES(
	'NoneEnjoyed', 
	'Enjoyment',
	'none of my players did',
	'-1',
	3);
	

-- Note to coordinator
INSERT INTO question (qkey,genre,question,qtype,required,sorder) VALUES (
	'CommentsToCoordinator',
	'team_spirit',
	'Do you have any comments on this game you would like to bring to the coordinator''s attention?', 
	'freetext',
	'N',
	'4');
