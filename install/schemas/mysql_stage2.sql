-- Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
-- Version 1.0.2 (Coblynau)
-- Copyright (C) 2006-2007 Dan Fuhry

-- This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
-- as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

-- This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
-- warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.

-- mysql_stage2.sql - MySQL installation schema, main payload

CREATE TABLE {{TABLE_PREFIX}}categories(
  page_id varchar(64),
  namespace varchar(64),
  category_id varchar(64)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}comments(
  comment_id int(12) NOT NULL auto_increment,
  page_id text,
  namespace text,
  subject text,
  comment_data text,
  name text,
  approved tinyint(1) default 1,
  user_id mediumint(8) NOT NULL DEFAULT -1,
  time int(12) NOT NULL DEFAULT 0,
  PRIMARY KEY ( comment_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}logs(
  log_type varchar(16),
  action varchar(16),
  time_id int(12) NOT NULL default '0',
  date_string varchar(63),
  page_id text,
  namespace text,
  page_text text,
  char_tag varchar(40),
  author varchar(63),
  edit_summary text,
  minor_edit tinyint(1)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}page_text(
  page_id varchar(255),
  namespace varchar(16) NOT NULL default 'Article',
  page_text text,
  char_tag varchar(63),
  FULLTEXT KEY {{TABLE_PREFIX}}page_search_idx (page_id, namespace, page_text)
) ENGINE = MYISAM CHARACTER SET `utf8`;

CREATE TABLE {{TABLE_PREFIX}}pages(
  page_order int(8),
  name varchar(255),
  urlname varchar(255),
  namespace varchar(16) NOT NULL default 'Article',
  special tinyint(1) default '0',
  visible tinyint(1) default '1',
  comments_on tinyint(1) default '1',
  protected tinyint(1) NOT NULL DEFAULT 0,
  wiki_mode tinyint(1) NOT NULL DEFAULT 2,
  delvotes int(10) NOT NULL default 0,
  password varchar(40) NOT NULL DEFAULT '',
  delvote_ips text DEFAULT NULL
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}session_keys(
  session_key varchar(32),
  salt varchar(32),
  user_id mediumint(8),
  auth_level tinyint(1) NOT NULL default '0',
  source_ip varchar(10) default '0x7f000001',
  time bigint(15) default '0'
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}themes(
  theme_id varchar(63),
  theme_name text,
  theme_order smallint(5) NOT NULL default '1',
  default_style varchar(63) NOT NULL DEFAULT '',
  enabled tinyint(1) NOT NULL default '1'
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}users(
  user_id mediumint(8) NOT NULL auto_increment,
  username text,
  password varchar(255),
  email text,
  real_name text,
  user_level tinyint(1) NOT NULL default 2,
  theme varchar(64) NOT NULL default 'bleu.css',
  style varchar(64) NOT NULL default 'default',
  signature text,
  reg_time int(11) NOT NULL DEFAULT 0,
  account_active tinyint(1) NOT NULL DEFAULT 0,
  activation_key varchar(40) NOT NULL DEFAULT 0,
  old_encryption tinyint(1) NOT NULL DEFAULT 0,
  temp_password text,
  temp_password_time int(12) NOT NULL DEFAULT 0,
  user_coppa tinyint(1) NOT NULL DEFAULT 0,
  user_lang smallint(5) NOT NULL,
  user_has_avatar tinyint(1) NOT NULL,
  avatar_type ENUM('jpg', 'png', 'gif') NOT NULL,
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
  page_id varchar(63) NOT NULL,
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
  is_friend tinyint(1) NOT NULL default '1',
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
  page_id varchar(63),
  namespace varchar(63),
  PRIMARY KEY ( hit_id ) 
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

CREATE TABLE {{TABLE_PREFIX}}groups(
  group_id mediumint(5) UNSIGNED NOT NULL auto_increment,
  group_name varchar(64),
  group_type tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY ( group_id ),
  system_group tinyint(1) NOT NULL DEFAULT 0 
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
  page_id varchar(255),
  namespace varchar(24),
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
  page_id varchar(63) NOT NULL,
  namespace varchar(63) NOT NULL DEFAULT 'Article',
  PRIMARY KEY ( pg_member_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.0.1

CREATE TABLE {{TABLE_PREFIX}}tags(
  tag_id int(12) NOT NULL auto_increment,
  tag_name varchar(63) NOT NULL DEFAULT 'bla',
  page_id varchar(255) NOT NULL,
  namespace varchar(255) NOT NULL,
  user mediumint(8) NOT NULL DEFAULT 1,
  PRIMARY KEY ( tag_id )
) CHARACTER SET `utf8` COLLATE `utf8_bin`;

-- Added in 1.1.1

CREATE TABLE {{TABLE_PREFIX}}lockout(
  id int(12) NOT NULL auto_increment,
  ipaddr varchar(40) NOT NULL,
  action ENUM('credential', 'level') NOT NULL DEFAULT 'credential',
  timestamp int(12) NOT NULL DEFAULT 0,
  PRIMARY KEY ( id )
) CHARACTER SET `utf8`;

-- Added in 1.1.1

CREATE TABLE {{TABLE_PREFIX}}language(
  lang_id smallint(5) NOT NULL auto_increment,
  lang_code varchar(16) NOT NULL,
  lang_name_default varchar(64) NOT NULL,
  lang_name_native varchar(64) NOT NULL,
  last_changed int(12) NOT NULL DEFAULT 0,
  PRIMARY KEY ( lang_id )
) CHARACTER SET `utf8`;

-- Added in 1.1.1

CREATE TABLE {{TABLE_PREFIX}}language_strings(
  string_id bigint(15) NOT NULL auto_increment,
  lang_id smallint(5) NOT NULL,
  string_category varchar(32) NOT NULL,
  string_name varchar(64) NOT NULL,
  string_content longtext NOT NULL,
  PRIMARY KEY ( string_id )
);

INSERT INTO {{TABLE_PREFIX}}config(config_name, config_value) VALUES
  ('site_name', '{{SITE_NAME}}'),
  ('main_page', 'Main_Page'),
  ('site_desc', '{{SITE_DESC}}'),
  ('wiki_mode', '{{WIKI_MODE}}'),
  ('wiki_edit_notice', '0'),
  ('sflogo_enabled', '0'),
  ('sflogo_groupid', ''),
  ('sflogo_type', '1'),
  ('w3c_vh32', '0'),
  ('w3c_vh40', '0'),
  ('w3c_vh401', '0'),
  ('w3c_vxhtml10', '0'),
  ('w3c_vxhtml11', '0'),
  ('w3c_vcss', '0'),
  ('approve_comments', '0'),
  ('enable_comments', '1'),
  ('plugin_SpecialAdmin.php', '1'),
  ('plugin_SpecialPageFuncs.php', '1'),
  ('plugin_SpecialUserFuncs.php', '1'),
  ('plugin_SpecialCSS.php', '1'),
  ('copyright_notice', '{{COPYRIGHT}}'),
  ('wiki_edit_notice_text', '== Why can I edit this page? ==\n\nEveryone can edit almost any page in this website. This concept is called a wiki. It gives everyone the opportunity to make a change for the best. While some spam and vandalism may occur, it is believed that most contributions will be legitimate and helpful.\n\nFor security purposes, a history of all page edits is kept, and administrators are able to restore vandalized or spammed pages with just a few clicks.'),
  ('cache_thumbs', '{{ENABLE_CACHE}}'),
  ('max_file_size', '256000'),('enano_version', '{{VERSION}}'),( 'allowed_mime_types', 'cbf:len=185;crc=55fb6f14;data=0[1],1[4],0[3],1[1],0[22],1[1],0[16],1[3],0[16],1[1],0[1],1[2],0[6],1[1],0[1],1[1],0[4],1[2],0[3],1[1],0[48],1[2],0[2],1[1],0[4],1[1],0[37]|end' ),
  ('contact_email', '{{ADMIN_EMAIL}}'),
  ('powered_btn', '1');

INSERT INTO {{TABLE_PREFIX}}page_text(page_id, namespace, page_text, char_tag) VALUES
  ('Main_Page', 'Article', '=== Enano has been successfully installed and is working. ===\n\nIf you can see this message, it means that you\'ve finished the Enano setup process and are ready to start building your website. Congratulations!\n\nTo edit this front page, click the Log In button to the left, enter the credentials you provided during the installation, and click the Edit This Page button that appears on the blue toolbar just above this text. You can also [http://docs.enanocms.org/Help:2.4 learn more] about editing pages.\n\nTo create more pages, use the Create a Page button to the left. If you enabled wiki mode, you don\'t have to log in first, however your IP address will be shown in the page history.\n\nVisit the [http://docs.enanocms.org/Help:Contents Enano documentation project website] to learn more about administering your site effectively and keeping things secure.\n\n\'\'\'NOTE:\'\'\' You have just installed an unstable version of Enano. This release is completely unsupported and may contain security issues or serious usability bugs. You should not use this release on a production website. The Enano team will not provide any type of support at all for this experimental release.', '');

INSERT INTO {{TABLE_PREFIX}}pages(page_order, name, urlname, namespace, special, visible, comments_on, protected, delvotes, delvote_ips) VALUES
  (NULL, 'Main Page', 'Main_Page', 'Article', 0, 1, 1, 1, 0, '');

INSERT INTO {{TABLE_PREFIX}}themes(theme_id, theme_name, theme_order, default_style, enabled) VALUES
  ('oxygen', 'Oxygen', 1, 'bleu.css', 1),
  ('stpatty', 'St. Patty', 2, 'shamrock.css', 1);

INSERT INTO {{TABLE_PREFIX}}users(user_id, username, password, email, real_name, user_level, theme, style, signature, reg_time, account_active) VALUES
  (1, 'Anonymous', 'invalid-pass-hash', 'anonspam@enanocms.org', 'None', 1, 'oxygen', 'bleu', '', 0, 0),
  (2, '{{ADMIN_USER}}', '{{ADMIN_PASS}}', '{{ADMIN_EMAIL}}', '{{REAL_NAME}}', 9, 'oxygen', 'bleu', '', UNIX_TIMESTAMP(), 1);
  
INSERT INTO {{TABLE_PREFIX}}users_extra(user_id) VALUES
  (2);

INSERT INTO {{TABLE_PREFIX}}groups(group_id,group_name,group_type,system_group) VALUES(1, 'Everyone', 3, 1),
  (2,'Administrators',3,1),
  (3,'Moderators',3,1);

INSERT INTO {{TABLE_PREFIX}}group_members(group_id,user_id,is_mod) VALUES(2, 2, 1);

INSERT INTO {{TABLE_PREFIX}}acl(target_type,target_id,page_id,namespace,rules) VALUES
  (1,2,NULL,NULL,'read=4;post_comments=4;edit_comments=4;edit_page=4;view_source=4;mod_comments=4;history_view=4;history_rollback=4;history_rollback_extra=4;protect=4;rename=4;clear_logs=4;vote_delete=4;vote_reset=4;delete_page=4;tag_create=4;tag_delete_own=4;tag_delete_other=4;set_wiki_mode=4;password_set=4;password_reset=4;mod_misc=4;edit_cat=4;even_when_protected=4;upload_files=4;upload_new_version=4;create_page=4;php_in_pages={{ADMIN_EMBED_PHP}};edit_acl=4;'),
  (1,3,NULL,NULL,'read=4;post_comments=4;edit_comments=4;edit_page=4;view_source=4;mod_comments=4;history_view=4;history_rollback=4;history_rollback_extra=4;protect=4;rename=3;clear_logs=2;vote_delete=4;vote_reset=4;delete_page=4;set_wiki_mode=2;password_set=2;password_reset=2;mod_misc=2;edit_cat=4;even_when_protected=4;upload_files=2;upload_new_version=3;create_page=3;php_in_pages=2;edit_acl=2;');

INSERT INTO {{TABLE_PREFIX}}sidebar(item_id, item_order, sidebar_id, block_name, block_type, block_content) VALUES
  (1, 1, 1, '{lang:sidebar_title_navigation}', 1, '[[Main_Page|{lang:sidebar_btn_home}]]'),
  (2, 2, 1, '{lang:sidebar_title_tools}', 1, '[[$NS_SPECIAL$CreatePage|{lang:sidebar_btn_createpage}]]\n[[$NS_SPECIAL$UploadFile|{lang:sidebar_btn_uploadfile}]]\n[[$NS_SPECIAL$SpecialPages|{lang:sidebar_btn_specialpages}]]\n{if auth_admin}\n$ADMIN_LINK$\n[[$NS_SPECIAL$EditSidebar|{lang:sidebar_btn_editsidebar}]]\n{/if}'),
  (3, 3, 1, '$USERNAME$', 1, '[[$NS_USER$$USERNAME$|{lang:sidebar_btn_userpage}]]\n[[$NS_SPECIAL$Contributions/$USERNAME$|{lang:sidebar_btn_mycontribs}]]\n{if user_logged_in}\n[[$NS_SPECIAL$Preferences|{lang:sidebar_btn_preferences}]]\n[[$NS_SPECIAL$PrivateMessages|{lang:sidebar_btn_privatemessages}]]\n[[$NS_SPECIAL$Usergroups|{lang:sidebar_btn_groupcp}]]\n$THEME_LINK$\n{/if}\n{if user_logged_in}\n$LOGOUT_LINK$\n{else}\n[[$NS_SPECIAL$Register|{lang:sidebar_btn_register}]]\n$LOGIN_LINK$\n[[$NS_SPECIAL$Login/$NS_SPECIAL$PrivateMessages|{lang:sidebar_btn_privatemessages}]]\n{/if}'),
  (4, 4, 1, '{lang:sidebar_title_search}', 1, '<div class="slideblock2" style="padding: 0px;"><form action="$CONTENTPATH$$NS_SPECIAL$Search" method="get" style="padding: 0; margin: 0;"><p><input type="hidden" name="title" value="$NS_SPECIAL$Search" />$INPUT_AUTH$<input name="q" alt="Search box" type="text" size="10" style="width: 70%" /> <input type="submit" value="{lang:sidebar_btn_search_go}" style="width: 20%" /></p></form></div>'),
  (5, 2, 2, '{lang:sidebar_title_links}', 4, 'Links');

