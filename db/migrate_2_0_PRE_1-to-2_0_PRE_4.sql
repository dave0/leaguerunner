alter table field add rating varchar(16) after status;
alter table field add location_street varchar(50) after code; 
alter table field add location_city varchar(50) after location_street;
alter table field add location_province varchar(50) after location_city;
alter table field add latitude double after location_province;
alter table field add longitude double after latitude;

alter table person add shirtsize varchar(50) after year_started;

-- Note to coordinator
INSERT INTO question (qkey,genre,question,qtype,required,sorder) VALUES (
	'CommentsToCoordinator',
	'team_spirit',
	'Do you have any comments on this game you would like to bring to the coordinator''s attention?', 
	'freetext',
	'N',
	'5');

alter table team add home_field integer after shirt_colour;
alter table team add region_preference varchar(50);
