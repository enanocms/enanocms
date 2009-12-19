ALTER TABLE {{TABLE_PREFIX}}users_extra ADD COLUMN date_format varchar(32) NOT NULL DEFAULT 'F d, Y';
ALTER TABLE {{TABLE_PREFIX}}users_extra ADD COLUMN time_format varchar(32) NOT NULL DEFAULT 'G:i';
ALTER TABLE {{TABLE_PREFIX}}lockout ADD COLUMN username varchar(255) NOT NULL DEFAULT '';
ALTER TABLE {{TABLE_PREFIX}}logs ADD COLUMN author_uid mediumint(8) NOT NULL DEFAULT 1;
UPDATE {{TABLE_PREFIX}}logs SET author_uid = 1;
