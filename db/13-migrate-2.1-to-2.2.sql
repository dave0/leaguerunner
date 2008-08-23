
alter table league modify schedule_type ENUM('none','roundrobin','ladder','pyramid') default 'roundrobin';
alter table league add games_before_repeat integer default 3;
alter table league add schedule_attempts integer default 100;

