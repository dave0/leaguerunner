-- City wards are now removed
ALTER TABLE person DROP ward_id;
DROP INDEX person_ward;

ALTER TABLE field DROP ward_id;
DROP INDEX field_ward;

DROP TABLE ward;
DELETE FROM variable WHERE name = 'wards';
