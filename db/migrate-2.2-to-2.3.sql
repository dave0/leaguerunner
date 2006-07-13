
alter table person add willing_to_volunteer ENUM('Y','N') DEFAULT 'N' after survey_completed;

alter table teamroster modify status enum('coach','captain','assistant','player','substitute','captain_request','player_request') default NULL;

