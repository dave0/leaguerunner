## Changes to database between rc9 and rc10
alter table person add survey_completed ENUM(Y,N) DEFAULT 'N' AFTER dog_waiver_signed
update person set survey_completed = 'Y' where class != 'new' AND class != 'inactive';
