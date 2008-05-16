-- This is really honestly a better way to handle plugins.

CREATE TABLE {{TABLE_PREFIX}}plugins (
  plugin_id SERIAL,
  plugin_filename varchar(63),
  plugin_flags int,
  plugin_version varchar(16),
  PRIMARY KEY ( plugin_id )
);

-- User title
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_title varchar(64) DEFAULT NULL;

-- Modifications to user_rank column
-- http://pgsqld.active-venture.com/ddl-alter.html#AEN1984
ALTER TABLE {{TABLE_PREFIX}}users ALTER COLUMN user_rank DROP NOT NULL,
              ALTER COLUMN user_rank DROP DEFAULT;
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_group int NOT NULL DEFAULT 1;
UPDATE {{TABLE_PREFIX}}users SET user_rank = NULL;
              
-- Aggregate function array_accum
-- http://www.postgresql.org/docs/current/static/xaggr.html

CREATE AGGREGATE {{TABLE_PREFIX}}array_accum (anyelement)
(
    sfunc = array_append,
    stype = anyarray,
    initcond = '{}'
);

-- The "guest" rank
-- No frontend to this yet so ranks should not have been created.
DELETE FROM {{TABLE_PREFIX}}ranks WHERE rank_id = 4;
INSERT INTO {{TABLE_PREFIX}}ranks(rank_id, rank_title, rank_style) VALUES
  (4, 'user_rank_guest', '');

-- Other rank-related columns
ALTER TABLE {{TABLE_PREFIX}}groups ADD COLUMN group_rank int DEFAULT NULL;

-- Disable JS effects column
ALTER TABLE {{TABLE_PREFIX}}users_extra ADD COLUMN disable_js_fx smallint NOT NULL DEFAULT 0;

