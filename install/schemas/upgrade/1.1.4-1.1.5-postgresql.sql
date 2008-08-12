ALTER TABLE {{TABLE_PREFIX}}session_keys ADD COLUMN key_type smallint NOT NULL DEFAULT 0;
UPDATE {{TABLE_PREFIX}}session_keys SET key_type = 2 WHERE auth_level > 2;

