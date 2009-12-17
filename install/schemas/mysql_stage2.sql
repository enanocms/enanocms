-- Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
-- Version 1.1.1
-- Copyright (C) 2006-2007 Dan Fuhry

-- This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
-- as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

-- This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
-- warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.

-- mysql_stage2.sql - MySQL installation schema, main payload

CREATE TABLE {{TABLE_PREFIX}}categories(
  page_id varchar(512),
  namespace varchar(16),
  category_id varchar(64)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}comments(
  comment_id int(12) NOT NULL auto_increment,
  page_id varchar(512),
  namespace varchar(16),
  subject text,
  comment_data text,
  name text,
  approved tinyint(1) default 1,
  user_id mediumint(8) NOT NULL DEFAULT -1,
  time int(12) NOT NULL DEFAULT 0,
  ip_address varchar(39),
  PRIMARY KEY ( comment_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}logs(
  log_id int(15) NOT NULL auto_increment,
  log_type varchar(16),
  action varchar(16),
  time_id int(12) NOT NULL DEFAULT '0',
  date_string varchar(63),
  page_id varchar(512),
  namespace varchar(16),
  page_text text,
  char_tag varchar(40),
  author varchar(63),
  edit_summary text,
  minor_edit tinyint(1),
  page_format varchar(16) NOT NULL DEFAULT 'wikitext',
  is_draft tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY ( log_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}page_text(
  page_id varchar(512),
  namespace varchar(16) NOT NULL DEFAULT 'Article',
  page_text text,
  char_tag varchar(63),
  FULLTEXT KEY {{TABLE_PREFIX}}page_search_idx (page_id, namespace, page_text)
) ENGINE = MYISAM CHARACTER SET `utf8`;

CREATE TABLE {{TABLE_PREFIX}}pages(
  page_order int(8),
  name varchar(255),
  urlname varchar(512),
  namespace varchar(16) NOT NULL DEFAULT 'Article',
  special tinyint(1) default '0',
  visible tinyint(1) default '1',
  comments_on tinyint(1) default '1',
  page_format varchar(16) NOT NULL DEFAULT 'wikitext',
  protected tinyint(1) NOT NULL DEFAULT 0,
  wiki_mode tinyint(1) NOT NULL DEFAULT 2,
  delvotes int(10) NOT NULL DEFAULT 0,
  password varchar(40) NOT NULL DEFAULT '',
  delvote_ips text DEFAULT NULL
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}session_keys(
  session_key varchar(32),
  salt varchar(32),
  user_id mediumint(8),
  auth_level tinyint(1) NOT NULL DEFAULT '0',
  source_ip varchar(39) NOT NULL DEFAULT '127.0.0.1',
  time bigint(15) default '0',
  key_type tinyint(3) NOT NULL DEFAULT 0
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}themes(
  theme_id varchar(63),
  theme_name text,
  theme_order smallint(5) NOT NULL DEFAULT '1',
  default_style varchar(63) NOT NULL DEFAULT '',
  enabled tinyint(1) NOT NULL DEFAULT '1',
  group_list text DEFAULT NULL,
  group_policy ENUM('allow_all', 'whitelist', 'blacklist') NOT NULL DEFAULT 'allow_all'
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}users(
  user_id mediumint(8) NOT NULL auto_increment,
  username text,
  password varchar(40),
  password_salt varchar(40) NOT NULL DEFAULT '',
  email text,
  real_name text,
  user_level tinyint(1) NOT NULL DEFAULT 2,
  theme varchar(64) NOT NULL DEFAULT 'bleu.css',
  style varchar(64) NOT NULL DEFAULT 'default',
  signature text,
  reg_time int(11) NOT NULL DEFAULT 0,
  account_active tinyint(1) NOT NULL DEFAULT 0,
  activation_key varchar(40) NOT NULL DEFAULT 0,
  old_encryption tinyint(1) NOT NULL DEFAULT 0,
  temp_password text,
  temp_password_time int(12) NOT NULL DEFAULT 0,
  user_coppa tinyint(1) NOT NULL DEFAULT 0,
  user_lang smallint(5) NOT NULL DEFAULT 1,
  user_has_avatar tinyint(1) NOT NULL DEFAULT 0,
  avatar_type ENUM('jpg', 'png', 'gif', 'grv') NOT NULL DEFAULT 'png',
  user_registration_ip varchar(39),
  user_rank int(12) UNSIGNED DEFAULT NULL,
  user_rank_userset tinyint(1) NOT NULL DEFAULT 0,
  user_timezone int(12) UNSIGNED NOT NULL DEFAULT 1440,
  user_title varchar(64) DEFAULT NULL,
  user_group mediumint(5) NOT NULL DEFAULT 1,
  user_dst varchar(11) NOT NULL DEFAULT '0;0;0;0;60',
  PRIMARY KEY  (user_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}users_extra(
  user_id mediumint(8) NOT NULL,
  user_aim varchar(63),
  user_yahoo varchar(63),
  user_msn varchar(255),
  user_xmpp varchar(255),
  user_homepage text,
  user_location text,
  user_job text,
  user_hobbies text,
  email_public tinyint(1) NOT NULL DEFAULT 0,
  disable_js_fx tinyint(1) NOT NULL DEFAULT 0,
  date_format varchar(32) NOT NULL DEFAULT 'F d, Y',
  time_format varchar(32) NOT NULL DEFAULT 'G:i',
  PRIMARY KEY ( user_id ) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}banlist(
  ban_id mediumint(8) NOT NULL auto_increment,
  ban_type tinyint(1),
  ban_value varchar(64),
  is_regex tinyint(1) DEFAULT 0,
  reason text,
  PRIMARY KEY ( ban_id ) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}files(
  file_id int(12) NOT NULL auto_increment,
  time_id int(12) NOT NULL,
  page_id varchar(512) NOT NULL,
  filename varchar(127) default NULL,
  size bigint(15) NOT NULL,
  mimetype varchar(63) default NULL,
  file_extension varchar(8) default NULL,
  file_key varchar(32) NOT NULL,
  PRIMARY KEY (file_id) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}buddies(
  buddy_id int(15) NOT NULL auto_increment,
  user_id mediumint(8),
  buddy_user_id mediumint(8),
  is_friend tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY  (buddy_id) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}privmsgs(
  message_id int(15) NOT NULL auto_increment,
  message_from varchar(63),
  message_to varchar(255),
  date int(12),
  subject varchar(63),
  message_text text,
  folder_name varchar(63),
  message_read tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (message_id) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}sidebar(
  item_id smallint(3) NOT NULL auto_increment,
  item_order smallint(3) NOT NULL DEFAULT 0,
  item_enabled tinyint(1) NOT NULL DEFAULT 1,
  sidebar_id smallint(3) NOT NULL DEFAULT 1,
  block_name varchar(63) NOT NULL,
  block_type tinyint(1) NOT NULL DEFAULT 0,
  block_content text,
  PRIMARY KEY ( item_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}hits(
  hit_id bigint(20) NOT NULL auto_increment,
  username varchar(63) NOT NULL,
  time int(12) NOT NULL DEFAULT 0,
  page_id varchar(512),
  namespace varchar(16),
  PRIMARY KEY ( hit_id ) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}search_index(
  word varchar(64) NOT NULL,
  word_lcase varchar(64) NOT NULL,
  page_names text,
  PRIMARY KEY ( word ) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}groups(
  group_id mediumint(5) UNSIGNED NOT NULL auto_increment,
  group_name varchar(64),
  group_type tinyint(1) NOT NULL DEFAULT 1,
  system_group tinyint(1) NOT NULL DEFAULT 0,
  group_rank int(12) unsigned DEFAULT NULL,
  PRIMARY KEY ( group_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}group_members(
  member_id int(12) UNSIGNED NOT NULL auto_increment,
  group_id mediumint(5) UNSIGNED NOT NULL,
  user_id int(12) NOT NULL,
  is_mod tinyint(1) NOT NULL DEFAULT 0,
  pending tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY ( member_id ) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}acl(
  rule_id int(12) UNSIGNED NOT NULL auto_increment,
  target_type tinyint(1) UNSIGNED NOT NULL,
  target_id int(12) UNSIGNED NOT NULL,
  page_id varchar(512),
  namespace varchar(16),
  rules text,
  PRIMARY KEY ( rule_id ) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.0.1

CREATE TABLE {{TABLE_PREFIX}}page_groups(
  pg_id mediumint(8) NOT NULL auto_increment,
  pg_type tinyint(2) NOT NULL DEFAULT 1,
  pg_name varchar(255) NOT NULL DEFAULT '',
  pg_target varchar(255) DEFAULT NULL,
  PRIMARY KEY ( pg_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.0.1

CREATE TABLE {{TABLE_PREFIX}}page_group_members(
  pg_member_id int(12) NOT NULL auto_increment,
  pg_id mediumint(8) NOT NULL,
  page_id varchar(512) NOT NULL,
  namespace varchar(16) NOT NULL DEFAULT 'Article',
  PRIMARY KEY ( pg_member_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.0.1

CREATE TABLE {{TABLE_PREFIX}}tags(
  tag_id int(12) NOT NULL auto_increment,
  tag_name varchar(63) NOT NULL DEFAULT 'bla',
  page_id varchar(512) NOT NULL,
  namespace varchar(16) NOT NULL,
  user_id mediumint(8) NOT NULL DEFAULT 1,
  PRIMARY KEY ( tag_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.1.1

CREATE TABLE {{TABLE_PREFIX}}lockout(
  id int(12) NOT NULL auto_increment,
  ipaddr varchar(40) NOT NULL,
  action ENUM('credential', 'level') NOT NULL DEFAULT 'credential',
  timestamp int(12) NOT NULL DEFAULT 0,
  username varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY ( id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.1.1

CREATE TABLE {{TABLE_PREFIX}}language(
  lang_id smallint(5) NOT NULL auto_increment,
  lang_code varchar(16) NOT NULL,
  lang_name_default varchar(64) NOT NULL,
  lang_name_native varchar(64) NOT NULL,
  last_changed int(12) NOT NULL DEFAULT 0,
  PRIMARY KEY ( lang_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.1.1

CREATE TABLE {{TABLE_PREFIX}}language_strings(
  string_id bigint(15) NOT NULL auto_increment,
  lang_id smallint(5) NOT NULL,
  string_category varchar(32) NOT NULL,
  string_name varchar(64) NOT NULL,
  string_content longtext NOT NULL,
  PRIMARY KEY ( string_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.1.1

CREATE TABLE {{TABLE_PREFIX}}ranks(
  rank_id int(12) NOT NULL auto_increment,
  rank_title varchar(63) NOT NULL DEFAULT '',
  rank_style varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY ( rank_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.1.1

CREATE TABLE {{TABLE_PREFIX}}captcha(
  code_id int(12) NOT NULL auto_increment,
  session_id varchar(40) NOT NULL DEFAULT '',
  code varchar(64) NOT NULL DEFAULT '',
  session_data text,
  source_ip varchar(39),
  user_id int(12),
  PRIMARY KEY ( code_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.1.3
-- Storing obscenely huge integers as strings since that's how php processes them.

CREATE TABLE {{TABLE_PREFIX}}diffiehellman (
  key_id int(12) NOT NULL auto_increment,
  private_key text,
  public_key text,
  PRIMARY KEY ( key_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.1.4
-- This is really honestly a better way to handle plugins.

CREATE TABLE {{TABLE_PREFIX}}plugins (
  plugin_id int(12) NOT NULL auto_increment,
  plugin_filename varchar(63),
  plugin_flags int(12),
  plugin_version varchar(16),
  PRIMARY KEY ( plugin_id )
) ENGINE `MyISAM` CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.1.6: Indices for several tables
-- The size of 317 is a trial-and-error-produced value based on MySQL's index column size limit
-- of 1000 bytes. It's low like that because of the UTF-8 character set being used.
ALTER TABLE {{TABLE_PREFIX}}logs ADD INDEX {{TABLE_PREFIX}}logs_page_idx (page_id(317), namespace);
ALTER TABLE {{TABLE_PREFIX}}logs ADD INDEX {{TABLE_PREFIX}}logs_time_idx (time_id);
ALTER TABLE {{TABLE_PREFIX}}logs ADD INDEX {{TABLE_PREFIX}}logs_action_idx (log_type, action);
ALTER TABLE {{TABLE_PREFIX}}comments ADD INDEX {{TABLE_PREFIX}}comments_page_idx (page_id(317), namespace);
ALTER TABLE {{TABLE_PREFIX}}hits ADD INDEX {{TABLE_PREFIX}}hits_time_idx ( time );
ALTER TABLE {{TABLE_PREFIX}}hits ADD INDEX {{TABLE_PREFIX}}hits_page_idx (page_id(317), namespace);
ALTER TABLE {{TABLE_PREFIX}}pages ADD INDEX {{TABLE_PREFIX}}pages_page_idx (urlname(317), namespace);
ALTER TABLE {{TABLE_PREFIX}}page_text ADD INDEX {{TABLE_PREFIX}}page_text_page_idx (page_id(317), namespace);

-- The default config. Kind of important.
-- P.S. the allowed_mime_types value is a compressed bitfield. Source for the (rather simple) algo is in functions.php.

INSERT INTO {{TABLE_PREFIX}}config(config_name, config_value) VALUES
  ('site_name', '{{SITE_NAME}}'),
  ('site_desc', '{{SITE_DESC}}'),
  ('wiki_mode', '{{WIKI_MODE}}'),
  ('copyright_notice', '{{COPYRIGHT}}'),
  ('cache_thumbs', '{{ENABLE_CACHE}}'),
  ('contact_email', '{{ADMIN_EMAIL}}'),
  ('allowed_mime_types', 'cbf2:7414a6b80184038102810b810781098106830a810282018101820683018102840182038104821a850682028104810a82018116'),
  ('theme_default', 'enanium'),
  ('enano_version', '{{VERSION}}');

INSERT INTO {{TABLE_PREFIX}}themes(theme_id, theme_name, theme_order, default_style, enabled) VALUES
  ('enanium', 'Enanium', 1, 'babygrand.css', 1),
  ('oxygen', 'Oxygen', 2, 'bleu.css', 1),
  ('stpatty', 'St. Patty', 3, 'shamrock.css', 1);

INSERT INTO {{TABLE_PREFIX}}users(user_id, username, password, password_salt, email, real_name, user_level, theme, style, signature, reg_time, account_active, user_registration_ip) VALUES
  (1, 'Anonymous', 'invalid-pass-hash', '', 'anonspam@enanocms.org', 'None', 1, 'enanium', 'babygrand', '', 0, 0, '{{IP_ADDRESS}}'),
  (2, '{{ADMIN_USER}}', '{{ADMIN_PASS}}', '{{ADMIN_PASS_SALT}}', '{{ADMIN_EMAIL}}', '{{REAL_NAME}}', 9, 'enanium', 'babygrand', '', UNIX_TIMESTAMP(), 1, '{{IP_ADDRESS}}');
  
INSERT INTO {{TABLE_PREFIX}}users_extra(user_id) VALUES
  (2);
  
INSERT INTO {{TABLE_PREFIX}}ranks(rank_id, rank_title, rank_style) VALUES
  (1, 'user_rank_member', ''),
  (2, 'user_rank_mod', 'font-weight: bold; color: #00AA00;'),
  (3, 'user_rank_admin', 'font-weight: bold; color: #AA0000;'),
  (4, 'user_rank_guest', '');

INSERT INTO {{TABLE_PREFIX}}groups(group_id,group_name,group_type,system_group) VALUES
  (1, 'Everyone', 3, 1),
  (2, 'Administrators', 3, 1),
  (3, 'Moderators', 3, 1);

INSERT INTO {{TABLE_PREFIX}}group_members(group_id,user_id,is_mod) VALUES(2, 2, 1);

INSERT INTO {{TABLE_PREFIX}}acl(target_type,target_id,page_id,namespace,rules) VALUES
  (1,2,NULL,NULL,'read=4;post_comments=4;edit_comments=4;edit_page=4;edit_wysiwyg=4;view_source=4;mod_comments=4;history_view=4;history_rollback=4;history_rollback_extra=4;protect=4;rename=4;clear_logs=4;vote_delete=4;vote_reset=4;delete_page=4;tag_create=4;tag_delete_own=4;tag_delete_other=4;set_wiki_mode=4;password_set=4;password_reset=4;mod_misc=4;edit_cat=4;even_when_protected=4;upload_files=4;upload_new_version=4;create_page=4;html_in_pages=4;php_in_pages={{ADMIN_EMBED_PHP}};edit_acl=4;'),
  (1,3,NULL,NULL,'read=4;post_comments=4;edit_comments=4;edit_page=4;edit_wysiwyg=4;view_source=4;mod_comments=4;history_view=4;history_rollback=4;history_rollback_extra=4;protect=4;rename=3;clear_logs=2;vote_delete=4;vote_reset=4;delete_page=4;set_wiki_mode=2;password_set=2;password_reset=2;mod_misc=2;edit_cat=4;even_when_protected=4;upload_files=2;upload_new_version=3;create_page=3;php_in_pages=2;edit_acl=2;');

INSERT INTO {{TABLE_PREFIX}}sidebar(item_id, item_order, sidebar_id, block_name, block_type, block_content) VALUES
  (1, 1, 1, '{lang:sidebar_title_navigation}', 1, '[[Main_Page|{lang:sidebar_btn_home}]]'),
  (2, 2, 1, '{lang:sidebar_title_tools}', 1, '[[$NS_SPECIAL$CreatePage|{lang:sidebar_btn_createpage}]]\n[[$NS_SPECIAL$UploadFile|{lang:sidebar_btn_uploadfile}]]\n[[$NS_SPECIAL$SpecialPages|{lang:sidebar_btn_specialpages}]]\n{if auth_admin}\n$ADMIN_LINK$\n[[$NS_SPECIAL$EditSidebar|{lang:sidebar_btn_editsidebar}]]\n{/if}'),
  (3, 3, 1, '$USERNAME$', 1, '[[$NS_USER$$USERNAME$|{lang:sidebar_btn_userpage}]]\n[[$NS_SPECIAL$Contributions/$USERNAME$|{lang:sidebar_btn_mycontribs}]]\n{if user_logged_in}\n[[$NS_SPECIAL$Preferences|{lang:sidebar_btn_preferences}]]\n[[$NS_SPECIAL$PrivateMessages|{lang:sidebar_btn_privatemessages}]]\n[[$NS_SPECIAL$Usergroups|{lang:sidebar_btn_groupcp}]]\n$THEME_LINK$\n{/if}\n{if user_logged_in}\n$LOGOUT_LINK$\n{else}\n[[$NS_SPECIAL$Register|{lang:sidebar_btn_register}]]\n$LOGIN_LINK$\n[[$NS_SPECIAL$Login/$NS_SPECIAL$PrivateMessages|{lang:sidebar_btn_privatemessages}]]\n{/if}'),
  (4, 4, 1, '{lang:sidebar_title_search}', 1, '<div class="slideblock2" style="padding: 0px;"><form action="$CONTENTPATH$$NS_SPECIAL$Search" method="get" style="padding: 0; margin: 0;"><p><input type="hidden" name="title" value="$NS_SPECIAL$Search" />$INPUT_AUTH$<input name="q" alt="Search box" type="text" size="10" style="width: 70%" /> <input type="submit" value="{lang:sidebar_btn_search_go}" style="width: 20%" /></p></form></div>'),
  (5, 2, 2, '{lang:sidebar_title_links}', 4, 'Links');

