-- This is really honestly a better way to handle plugins.

CREATE TABLE {{TABLE_PREFIX}}plugins (
  plugin_id int(12) NOT NULL auto_increment,
  plugin_filename varchar(63),
  plugin_flags int(12),
  plugin_version varchar(16),
  PRIMARY KEY ( plugin_id )
) ENGINE `MyISAM` CHARACTER SET `utf8` COLLATE `utf8_bin`;

ALTER TABLE {{TABLE_PREFIX}}search_index ADD COLUMN word_lcase varchar(64) NOT NULL;

-- User title
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_title varchar(64) DEFAULT NULL;
ALTER TABLE {{TABLE_PREFIX}}users MODIFY COLUMN user_rank int(12) unsigned DEFAULT NULL;
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_group mediumint(5) NOT NULL DEFAULT 1;
UPDATE {{TABLE_PREFIX}}users SET user_rank = NULL;

-- The "guest" rank
-- No frontend to this yet so ranks should not have been created.
DELETE FROM {{TABLE_PREFIX}}ranks WHERE rank_id = 4;
INSERT INTO {{TABLE_PREFIX}}ranks(rank_id, rank_title, rank_style) VALUES
  (4, 'user_rank_guest', '');
  
-- Other rank-related columns
ALTER TABLE {{TABLE_PREFIX}}groups ADD COLUMN group_rank int(12) unsigned DEFAULT NULL;

-- Disable JS effects column
ALTER TABLE {{TABLE_PREFIX}}users_extra ADD COLUMN disable_js_fx tinyint(1) NOT NULL DEFAULT 0;
