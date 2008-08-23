
-- Add indexes to person table for selecting by ward
create index person_ward on person (ward_id);

-- Update league table to not have separate 'winter' and 'winter indoor' season
alter table league change season season enum('none','Spring','Summer','Fall','Winter');

-- Add index on league id to leagueteams table
create index leagueteams_league on league (league_id);

-- Add indexes on schedule table
create index game_date on schedule (date_played);
create index game_league on schedule (league_id);
create index game_home_team on schedule (home_team);
create index game_away_team on schedule (away_team);

-- Add index to site table for selecting by ward
create index site_ward on site (ward_id);
 
-- Add index to field table for selecting by site and status
create index field_site on field (site_id);
create index field_status on field (status);

-- Add index to ward table for selecting by city
create index ward_city on ward (city);
