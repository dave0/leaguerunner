CREATE TABLE registration_events (
	registration_id int(10) unsigned NOT NULL auto_increment,
	name varchar(100) default NULL,
	description blob,
	cost decimal(7,2) default NULL,
	gst decimal(7,2) default NULL,
	pst decimal(7,2) default NULL,
	`open` datetime default NULL,
	`close` datetime default NULL,
	cap_male int(10) NOT NULL default '0',
	cap_female int(10) NOT NULL default '0',
	PRIMARY KEY  (registration_id),
	UNIQUE KEY name (name)
);

CREATE TABLE registration_prereq (
	registration_id int(11) NOT NULL default '0',
	prereq_id int(11) NOT NULL default '0',
	is_prereq tinyint(1) NOT NULL default '0',
	PRIMARY KEY  (registration_id,prereq_id)
);

CREATE TABLE registrations (
	order_id int(10) unsigned NOT NULL auto_increment,
	user_id int(11) NOT NULL default '0',
	registration_id int(10) unsigned NOT NULL default '0',
	`time` timestamp NOT NULL default CURRENT_TIMESTAMP,
	paid tinyint(1) NOT NULL default '0',
	notes blob,
	PRIMARY KEY  (order_id),
	KEY user_id (user_id,registration_id)
);

CREATE TABLE registration_answers (
	user_id int(11) NOT NULL default '0',
	registration_id int(11) NOT NULL default '0',
	qkey varchar(255) NOT NULL default '',
	akey varchar(255) default NULL,
	PRIMARY KEY  (user_id,registration_id,qkey)
);

CREATE TABLE registration_audit (
	order_id int(10) unsigned NOT NULL default '0',
	response_code smallint(5) unsigned NOT NULL default '0',
	iso_code smallint(5) unsigned NOT NULL default '0',
	`date` text NOT NULL,
	`time` text NOT NULL,
	transaction_id bigint(18) NOT NULL default '0',
	approval_code text NOT NULL,
	transaction_name varchar(20) NOT NULL default '',
	charge_total decimal(7,2) NOT NULL default '0.00',
	cardholder varchar(40) NOT NULL default '',
	expiry text NOT NULL,
	f4l4 text NOT NULL,
	card text NOT NULL,
	message varchar(100) NOT NULL default '',
	`issuer` varchar(30) default NULL,
	issuer_invoice varchar(20) default NULL,
	issuer_confirmation varchar(15) default NULL,
	PRIMARY KEY  (order_id)
);

CREATE TABLE refunds (
	order_id int(10) unsigned NOT NULL default '0',
	user_id int(11) NOT NULL default '0',
	registration_id int(10) unsigned NOT NULL default '0',
	`time` timestamp NOT NULL default CURRENT_TIMESTAMP,
	paid tinyint(1) NOT NULL default '0',
	notes blob,
	PRIMARY KEY  (order_id),
	KEY user_id (user_id,registration_id)
);

CREATE TABLE refund_answers (
	user_id int(11) NOT NULL default '0',
	registration_id int(11) NOT NULL default '0',
	qkey varchar(255) NOT NULL default '',
	akey varchar(255) default NULL,
	PRIMARY KEY  (user_id,registration_id,qkey)
);

alter table league modify schedule_type ENUM('none','roundrobin','ladder','pyramid','ratings_ladder') default 'roundrobin';
alter table person add contact_for_feedback ENUM('Y','N') DEFAULT 'Y' after willing_to_volunteer;
alter table league add see_sotg ENUM('true','false') DEFAULT 'true' after schedule_attempts;
alter table league add coord_list varchar(100) after see_sotg;
alter table league add capt_list varchar(100) after coord_list;

alter table schedule add rating_home integer after away_spirit;
alter table schedule add rating_away integer after rating_home;

delete from question;
delete from multiplechoice_answers;
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES ('Timeliness','team_spirit','Our opponents had a full line and were ready to play','multiplechoice',0);
INSERT INTO multiplechoice_answers VALUES('OnTime', 'Timeliness','early, or at the official start time','0',0);
INSERT INTO multiplechoice_answers VALUES('FiveOrLess', 'Timeliness','less than five minutes late','-1',1);
INSERT INTO multiplechoice_answers VALUES('LessThanTen', 'Timeliness','less than ten minutes late','-2',2);
INSERT INTO multiplechoice_answers VALUES('MoreThanTen', 'Timeliness','more than ten minutes late','-3',3);
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES ('RulesKnowledge','team_spirit','Our opponents\' rules knowledge was','multiplechoice',1);
INSERT INTO multiplechoice_answers VALUES('ExcellentRules', 'RulesKnowledge','excellent','0',0);
INSERT INTO multiplechoice_answers VALUES('AcceptableRules', 'RulesKnowledge','acceptable','-1',1);
INSERT INTO multiplechoice_answers VALUES('PoorRules', 'RulesKnowledge','poor','-2',2);
INSERT INTO multiplechoice_answers VALUES('NonexistantRules', 'RulesKnowledge','nonexistant','-3',3);
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES ('Sportsmanship','team_spirit','Our opponents\' sportsmanship was','multiplechoice',2);
INSERT INTO multiplechoice_answers VALUES('ExcellentSportsmanship', 'Sportsmanship','excellent','0',0);
INSERT INTO multiplechoice_answers VALUES('AcceptableSportsmanship', 'Sportsmanship','acceptable','-1',1);
INSERT INTO multiplechoice_answers VALUES('PoorSportsmanship', 'Sportsmanship','poor','-2',2);
INSERT INTO multiplechoice_answers VALUES('NonexistantSportsmanship', 'Sportsmanship','nonexistant','-3',3);
INSERT INTO question (qkey, genre, question, qtype, sorder) VALUES ('Enjoyment','team_spirit','Ignoring the score and based on the opponents\' spirit of the game, did your team enjoy this game?','multiplechoice',3);
INSERT INTO multiplechoice_answers VALUES('AllEnjoyed', 'Enjoyment','all of my players did','0',0);
INSERT INTO multiplechoice_answers VALUES('MostEnjoyed', 'Enjoyment','most of my players did','-1',1);
INSERT INTO multiplechoice_answers VALUES('SomeEnjoyed', 'Enjoyment','some of my players did','-1',2);
INSERT INTO multiplechoice_answers VALUES('NoneEnjoyed', 'Enjoyment','none of my players did','-1',3);
INSERT INTO question (qkey,genre,question,qtype,required,sorder) VALUES ('CommentsToCoordinator','team_spirit','Do you have any comments on this game you would like to bring to the coordinator''s attention?', 'freetext','N','4');

alter table league add excludeTeams ENUM('true','false') DEFAULT 'false' after see_sotg;
