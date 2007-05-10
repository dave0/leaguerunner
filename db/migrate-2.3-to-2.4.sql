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
