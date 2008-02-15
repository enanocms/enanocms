-- Enano CMS
-- Upgrade schema - Enano 1.1.1 - 1.1.2

ALTER TABLE {{TABLE_PREFIX}}logs ADD COLUMN log_id int(15) NOT NULL auto_increment, ADD PRIMARY KEY ( log_id );
ALTER TABLE {{TABLE_PREFIX}}logs ADD COLUMN is_draft tinyint(1) NOT NULL DEFAULT 0;

ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_rank int(12) UNSIGNED NOT NULL DEFAULT 1,
                                  ADD COLUMN user_timezone int(12) UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE {{TABLE_PREFIX}}tags CHANGE user user_id mediumint(8) NOT NULL DEFAULT 1;

CREATE TABLE {{TABLE_PREFIX}}ranks(
  rank_id int(12) NOT NULL auto_increment,
  rank_title varchar(63) NOT NULL DEFAULT '',
  rank_style varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY ( rank_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}captcha(
  code_id int(12) NOT NULL auto_increment,
  session_id varchar(40) NOT NULL DEFAULT '',
  code varchar(64) NOT NULL DEFAULT '',
  session_data text,
  source_ip varchar(39),
  user_id int(12),
  PRIMARY KEY ( code_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

INSERT INTO {{TABLE_PREFIX}}ranks(rank_id, rank_title, rank_style) VALUES
  (1, 'user_rank_member', ''),
  (2, 'user_rank_mod', 'font-weight: bold; color: #00AA00;'),
  (3, 'user_rank_admin', 'font-weight: bold; color: #AA0000;');

