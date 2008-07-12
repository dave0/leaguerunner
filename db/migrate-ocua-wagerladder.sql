alter table league modify schedule_type	ENUM('none','roundrobin','ladder','pyramid','ratings_ladder', 'ratings_wager_ladder') default 'roundrobin';
