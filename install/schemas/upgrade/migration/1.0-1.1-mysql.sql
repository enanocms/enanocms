-- Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
-- Version 1.1.1
-- Copyright (C) 2006-2007 Dan Fuhry

-- This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
-- as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

-- This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
-- warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.

-- 1.0-1.1-mysql.sql - Enano 1.0.x to 1.1.x migration queries, MySQL

ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_lang smallint(5) NOT NULL;
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_has_avatar tinyint(1) NOT NULL;
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN avatar_type ENUM('jpg', 'png', 'gif') NOT NULL;
ALTER TABLE {{TABLE_PREFIX}}users ADD COLUMN user_registration_ip varchar(39);
ALTER TABLE {{TABLE_PREFIX}}comments ADD COLUMN ip_address varchar(39);

CREATE TABLE {{TABLE_PREFIX}}lockout(
  id int(12) NOT NULL auto_increment,
  ipaddr varchar(40) NOT NULL,
  action ENUM('credential', 'level') NOT NULL DEFAULT 'credential',
  timestamp int(12) NOT NULL DEFAULT 0,
  PRIMARY KEY ( id )
) CHARACTER SET `utf8`;

CREATE TABLE {{TABLE_PREFIX}}language(
  lang_id smallint(5) NOT NULL auto_increment,
  lang_code varchar(16) NOT NULL,
  lang_name_default varchar(64) NOT NULL,
  lang_name_native varchar(64) NOT NULL,
  last_changed int(12) NOT NULL DEFAULT 0,
  PRIMARY KEY ( lang_id )
) CHARACTER SET `utf8`;

CREATE TABLE {{TABLE_PREFIX}}language_strings(
  string_id bigint(15) NOT NULL auto_increment,
  lang_id smallint(5) NOT NULL,
  string_category varchar(32) NOT NULL,
  string_name varchar(64) NOT NULL,
  string_content longtext NOT NULL,
  PRIMARY KEY ( string_id )
);

UPDATE {{TABLE_PREFIX}}sidebar SET block_name = '{lang:sidebar_title_navigation}', block_type = 1, block_content = '[[Main_Page|{lang:sidebar_btn_home}]]' WHERE item_id = 1;
UPDATE {{TABLE_PREFIX}}sidebar SET block_name = '{lang:sidebar_title_tools}',      block_type = 1, block_content = '[[$NS_SPECIAL$CreatePage|{lang:sidebar_btn_createpage}]]\n[[$NS_SPECIAL$UploadFile|{lang:sidebar_btn_uploadfile}]]\n[[$NS_SPECIAL$SpecialPages|{lang:sidebar_btn_specialpages}]]\n{if auth_admin}\n$ADMIN_LINK$\n[[$NS_SPECIAL$EditSidebar|{lang:sidebar_btn_editsidebar}]]\n{/if}' WHERE item_id = 2;
UPDATE {{TABLE_PREFIX}}sidebar SET block_name = '$USERNAME$',                      block_type = 1, block_content = '[[$NS_USER$$USERNAME$|{lang:sidebar_btn_userpage}]]\n[[$NS_SPECIAL$Contributions/$USERNAME$|{lang:sidebar_btn_mycontribs}]]\n{if user_logged_in}\n[[$NS_SPECIAL$Preferences|{lang:sidebar_btn_preferences}]]\n[[$NS_SPECIAL$PrivateMessages|{lang:sidebar_btn_privatemessages}]]\n[[$NS_SPECIAL$Usergroups|{lang:sidebar_btn_groupcp}]]\n$THEME_LINK$\n{/if}\n{if user_logged_in}\n$LOGOUT_LINK$\n{else}\n[[$NS_SPECIAL$Register|{lang:sidebar_btn_register}]]\n$LOGIN_LINK$\n[[$NS_SPECIAL$Login/$NS_SPECIAL$PrivateMessages|{lang:sidebar_btn_privatemessages}]]\n{/if}' WHERE item_id = 3;
UPDATE {{TABLE_PREFIX}}sidebar SET block_name = '{lang:sidebar_title_search}',     block_type = 1, block_content = '<div class="slideblock2" style="padding: 0px;"><form action="$CONTENTPATH$$NS_SPECIAL$Search" method="get" style="padding: 0; margin: 0;"><p><input type="hidden" name="title" value="$NS_SPECIAL$Search" />$INPUT_AUTH$<input name="q" alt="Search box" type="text" size="10" style="width: 70%" /> <input type="submit" value="{lang:sidebar_btn_search_go}" style="width: 20%" /></p></form></div>' WHERE item_id = 4;
UPDATE {{TABLE_PREFIX}}sidebar SET block_name = '{lang:sidebar_title_links}',      block_type = 4, block_content = 'Links' WHERE item_id = 5;

