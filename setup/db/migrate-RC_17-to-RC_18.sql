alter table schedule add gameslot integer after field_id;

create table gameslot (
	id		integer AUTO_INCREMENT,
	field 		integer NOT NULL,
	date_played	datetime,
	game_date	date,
	game_start	time,
	game_end	time,
	PRIMARY KEY (id)
);

insert into gameslot (field,date_played,game_date,game_start) select field_id,date_played,DATE_FORMAT(date_played,'%Y-%m-%d'),TIME_FORMAT(date_played,'%H:%i') as time from schedule;

update schedule,gameslot set schedule.gameslot = gameslot.id WHERE gameslot.field = schedule.field_id AND gameslot.date_played = schedule.date_played;

alter table gameslot drop date_played;


alter table schedule drop date_played;
alter table schedule drop field_id;


drop table field_assignment;
create table league_gameslot_assoc (
	league		integer NOT NULL,
	gameslot	integer NOT NULL
);
