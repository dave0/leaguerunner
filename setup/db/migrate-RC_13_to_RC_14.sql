alter table person add alias varchar(100) unique after lastname;

-- Changes for proper consideration of rating system
alter table team add rating int default 1500 after status;
alter table schedule add rating_points int after away_spirit;
