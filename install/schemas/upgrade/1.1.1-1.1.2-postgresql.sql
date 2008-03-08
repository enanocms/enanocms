-- Enano CMS
-- Upgrade schema - Enano 1.1.1 - 1.1.2

ALTER TABLE {{TABLE_PREFIX}}logs ADD COLUMN log_id SERIAL, ADD PRIMARY KEY ( log_id );
ALTER TABLE {{TABLE_PREFIX}}logs ADD COLUMN is_draft smallint NOT NULL DEFAULT 0;

ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_rank int NOT NULL DEFAULT 1;
@ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_timezone int NOT NULL DEFAULT 0;

ALTER TABLE {{TABLE_PREFIX}}themes
  ADD COLUMN group_list text DEFAULT NULL,
  ADD COLUMN group_policy varchar(5) NOT NULL DEFAULT 'deny',
  ADD CHECK (group_policy IN ('allow', 'deny'));

CREATE TABLE {{TABLE_PREFIX}}ranks(
  rank_id SERIAL,
  rank_title varchar(63) NOT NULL DEFAULT '',
  rank_style varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY ( rank_id )
);

CREATE TABLE {{TABLE_PREFIX}}captcha(
  code_id SERIAL,
  session_id varchar(40) NOT NULL DEFAULT '',
  code varchar(64) NOT NULL DEFAULT '',
  session_data text,
  source_ip varchar(39),
  user_id int(12),
  PRIMARY KEY ( code_id )
);

INSERT INTO {{TABLE_PREFIX}}ranks(rank_id, rank_title, rank_style) VALUES
  (1, 'user_rank_member', ''),
  (2, 'user_rank_mod', 'font-weight: bold; color: #00AA00;'),
  (3, 'user_rank_admin', 'font-weight: bold; color: #AA0000;');

