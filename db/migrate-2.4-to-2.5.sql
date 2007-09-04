-- Allow questions and answers longer than 255 characters
ALTER TABLE question MODIFY COLUMN question BLOB;
ALTER TABLE team_spirit_answers MODIFY COLUMN akey BLOB;

-- Update some keys
ALTER TABLE question DROP PRIMARY KEY;
ALTER TABLE question ADD PRIMARY KEY (qkey, genre);

ALTER TABLE multiplechoice_answers DROP PRIMARY KEY;
ALTER TABLE multiplechoice_answers ADD PRIMARY KEY (akey, qkey);

-- Add new fields to the fields
ALTER TABLE field CHANGE COLUMN site_directions driving_directions TEXT;
ALTER TABLE field ADD parking_details TEXT AFTER driving_directions;
ALTER TABLE field ADD transit_directions TEXT AFTER parking_details;
ALTER TABLE field ADD biking_directions TEXT AFTER transit_directions;
ALTER TABLE field ADD washrooms TEXT AFTER biking_directions;

-- The following are to convert any existing registration data to the new format.
ALTER TABLE registration_answers RENAME temp;

CREATE TABLE registration_answers (
 order_id int UNSIGNED NOT NULL,
 qkey varchar(255) NOT NULL,
 akey varchar(255),
 PRIMARY KEY (order_id, qkey)
);

INSERT INTO registration_answers (
 SELECT
  order_id,
  qkey,
  akey
 FROM
  temp
 LEFT JOIN
  registrations
 ON
  temp.user_id = registrations.user_id
 AND
  temp.registration_id = registrations.registration_id
 ORDER BY
  order_id
);

DROP TABLE temp;

ALTER TABLE refund_answers RENAME temp;

CREATE TABLE refund_answers (
 order_id int UNSIGNED NOT NULL,
 qkey varchar(255) NOT NULL,
 akey varchar(255),
 PRIMARY KEY (order_id, qkey)
);

INSERT INTO refund_answers (
 SELECT
  order_id,
  qkey,
  akey
 FROM
  temp
 LEFT JOIN
  refunds
 ON
  temp.user_id = refunds.user_id
 AND
  temp.registration_id = refunds.registration_id
 ORDER BY
  order_id
);

DROP TABLE temp;

ALTER TABLE registration_events
 ADD COLUMN multiple BOOL DEFAULT FALSE AFTER cap_female;
ALTER TABLE registration_events
 ADD COLUMN anonymous BOOL DEFAULT FALSE AFTER multiple;
