ALTER TABLE {{TABLE_PREFIX}}session_keys ADD COLUMN key_type tinyint(1) NOT NULL DEFAULT 0;
UPDATE {{TABLE_PREFIX}}session_keys SET key_type = 2 WHERE auth_level > 2;
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_dst varchar(11) NOT NULL DEFAULT '0;0;0;0;60';
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_rank_userset tinyint(1) NOT NULL DEFAULT 0;
